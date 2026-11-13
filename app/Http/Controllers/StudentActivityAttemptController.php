<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class StudentActivityAttemptController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'activityId' => ['required', 'string', 'uuid'],
        ]);

        $idToken = $request->bearerToken();
        if (! $idToken) {
            return response()->json(['message' => 'Please sign in again.'], 401);
        }

        try {
            $uid = Firebase::auth()->verifyIdToken($idToken)->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json(['message' => 'Invalid or expired session.'], 401);
        }

        $projectId = config('services.firebase.project_id');
        if (! is_string($projectId) || $projectId === '') {
            return response()->json(['message' => 'Firebase is not configured.'], 503);
        }

        $classId = $validated['classId'];
        $activityId = $validated['activityId'];
        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $studentPath = "{$classPath}/students/{$uid}";
        $publishedPath = "{$classPath}/publishedActivities/{$activityId}";
        $summaryPath = "{$classPath}/activityAttempts/{$activityId}/students/{$uid}";
        $url = 'https://firestore.googleapis.com/v1/';

        try {
            // Read using the student's token: Firestore rules enforce enrollment.
            $class = Http::withToken($idToken)->timeout(15)->get($url . $classPath);
            if ($class->status() === 404) {
                return response()->json(['message' => 'Class not found.'], 404);
            }
            if (! $class->successful()) {
                return response()->json(['message' => 'Could not verify class.'], 503);
            }

            $student = Http::withToken($idToken)->timeout(15)->get($url . $studentPath);
            if ($student->status() === 404) {
                return response()->json(['message' => 'You are not enrolled in this class.'], 403);
            }
            if (! $student->successful()) {
                return response()->json(['message' => 'Could not verify enrollment.'], 503);
            }

            $published = Http::withToken($idToken)->timeout(15)->get($url . $publishedPath);
            if ($published->status() === 404) {
                return response()->json(['message' => 'Published activity not found.'], 404);
            }
            if (! $published->successful()) {
                return response()->json(['message' => 'Could not verify activity.'], 503);
            }
            $fields = $published->json('fields', []);
            if (($fields['status']['stringValue'] ?? null) !== 'published') {
                return response()->json(['message' => 'Activity is not available.'], 403);
            }
            $maxAttempts = (int) ($fields['maxAttempts']['integerValue'] ?? 0);
            if ($maxAttempts < 1 || $maxAttempts > 10) {
                return response()->json(['message' => 'Invalid activity attempt limit.'], 503);
            }

            $credentialsPath = config('firebase.projects.app.credentials');
            if (! is_string($credentialsPath) || $credentialsPath === '') {
                throw new \RuntimeException('Firebase service-account credentials missing.');
            }
            $resolvedPath = str_starts_with($credentialsPath, '/') ||
                preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath : base_path($credentialsPath);
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );
            $accessToken = $credentials->fetchAuthToken()['access_token'] ?? null;
            if (! $accessToken) {
                throw new \RuntimeException('Google OAuth token missing.');
            }

            for ($retry = 0; $retry < 5; $retry++) {
                $summary = Http::withToken($accessToken)
                    ->timeout(15)->get($url . $summaryPath);
                if (! $summary->successful() && $summary->status() !== 404) {
                    throw new \RuntimeException('Could not read attempt summary.');
                }

                $exists = $summary->successful();
                $previous = $exists ? $summary->json('fields', []) : [];
                $used = (int) ($previous['attemptsUsed']['integerValue'] ?? 0);
                $activeId = $previous['activeAttemptId']['stringValue'] ?? '';
                if ($used < 0 || $used > $maxAttempts) {
                    return response()->json([
                        'message' => 'Activity attempt count is inconsistent.',
                    ], 409);
                }

                // Repeated requests resume the existing in-progress attempt.
                // Verify the active attempt before allowing recovery.
                if ($activeId !== '') {
                    if (! Str::isUuid($activeId)) {
                        Log::warning('Invalid active activity attempt ID', [
                            'classId' => $classId,
                            'activityId' => $activityId,
                        ]);

                        return response()->json([
                            'message' => 'Activity attempt record is invalid.',
                        ], 409);
                    }

                    $activeAttemptPath =
                        "{$summaryPath}/attempts/{$activeId}";

                    $activeAttempt = Http::withToken($accessToken)
                        ->timeout(15)
                        ->get($url . $activeAttemptPath);

                    if ($activeAttempt->status() === 404) {
                        return response()->json([
                            'message' => 'Active attempt record is missing.',
                        ], 409);
                    }

                    if (! $activeAttempt->successful()) {
                        throw new \RuntimeException(
                            'Could not verify active attempt.'
                        );
                    }

                    $attemptFields = $activeAttempt->json('fields', []);

                    if (
                        ($attemptFields['status']['stringValue'] ?? null)
                        !== 'in_progress'
                    ) {
                        return response()->json([
                            'message' => 'This attempt is no longer active.',
                        ], 409);
                    }

                    $attemptNumber = (int) (
                        $attemptFields['attemptNumber']['integerValue'] ?? 0
                    );

                    if ($attemptNumber < 1 || $attemptNumber !== $used) {
                        return response()->json([
                            'message' => 'Attempt record is inconsistent.',
                        ], 409);
                    }

                    return response()->json([
                        'success' => true,
                        'resumed' => true,
                        'attemptId' => $activeId,
                        'attemptNumber' => $attemptNumber,
                        'attemptsUsed' => $used,
                        'remainingAttempts' => max(
                            0,
                            $maxAttempts - $used
                        ),
                    ]);
                }
                if ($used >= $maxAttempts) {
                    return response()->json([
                        'message' => 'Maximum activity attempts reached.',
                        'attemptsUsed' => $used,
                        'remainingAttempts' => 0,
                    ], 409);
                }

                $attemptId = (string) Str::uuid();
                $attemptPath = "{$summaryPath}/attempts/{$attemptId}";
                $now = now('UTC')->toIso8601String();
                $precondition = $exists
                    ? ['updateTime' => $summary->json('updateTime')]
                    : ['exists' => false];

                $writes = [
                    [
                        'update' => [
                            'name' => $summaryPath,
                            'fields' => [
                                'attemptsUsed' => [
                                    'integerValue' => (string) ($used + 1),
                                ],
                                'highestScore' => [
                                    'integerValue' => (string) (
                                        $previous['highestScore']['integerValue'] ?? 0
                                    ),
                                ],
                                'activeAttemptId' => [
                                    'stringValue' => $attemptId,
                                ],
                                'updatedAt' => [
                                    'timestampValue' => $now,
                                ],
                            ],
                        ],
                        'updateMask' => [
                            'fieldPaths' => [
                                'attemptsUsed',
                                'highestScore',
                                'activeAttemptId',
                                'updatedAt',
                            ],
                        ],
                        'currentDocument' => $precondition,
                    ],
                    [
                        'update' => [
                            'name' => $attemptPath,
                            'fields' => [
                                'attemptNumber' => ['integerValue' => (string) ($used + 1)],
                                'status' => ['stringValue' => 'in_progress'],
                                'startedAt' => ['timestampValue' => $now],
                                'finishedAt' => ['nullValue' => null],
                            ],
                        ],
                        'currentDocument' => ['exists' => false],
                    ],
                ];

                $commit = Http::withToken($accessToken)->timeout(30)
                    ->post($url . $base . ':commit', ['writes' => $writes]);
                if ($commit->successful()) {
                    return response()->json([
                        'success' => true,
                        'resumed' => false,
                        'attemptId' => $attemptId,
                        'attemptNumber' => $used + 1,
                        'attemptsUsed' => $used + 1,
                        'remainingAttempts' => $maxAttempts - $used - 1,
                    ], 201);
                }

                // A competing request updated the summary. Re-read and retry.
                if (in_array(
                    $commit->json('error.status'),
                    ['FAILED_PRECONDITION', 'ABORTED'],
                    true
                )) {
                    continue;
                }
                Log::error('Activity attempt commit rejected', [
                    'classId' => $classId,
                    'activityId' => $activityId,
                    'status' => $commit->status(),
                    'body' => $commit->body(),
                ]);
                throw new \RuntimeException('Firestore attempt commit failed.');
            }

            return response()->json([
                'message' => 'Activity is busy. Please try again.',
            ], 409);
        } catch (Throwable $e) {
            Log::error('Student activity attempt start failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Could not start activity. Please try again.',
            ], 503);
        }
    }

    public function abandon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'activityId' => ['required', 'string', 'uuid'],
            'attemptId' => ['required', 'string', 'uuid'],
        ]);

        $idToken = $request->bearerToken();
        if (! $idToken) {
            return response()->json(['message' => 'Please sign in again.'], 401);
        }

        try {
            $uid = Firebase::auth()->verifyIdToken($idToken)->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json(['message' => 'Invalid or expired session.'], 401);
        }

        $projectId = config('services.firebase.project_id');
        if (! is_string($projectId) || $projectId === '') {
            return response()->json(['message' => 'Firebase is not configured.'], 503);
        }

        $classId = $validated['classId'];
        $activityId = $validated['activityId'];
        $attemptId = $validated['attemptId'];
        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $studentPath = "{$classPath}/students/{$uid}";
        $summaryPath = "{$classPath}/activityAttempts/{$activityId}/students/{$uid}";
        $attemptPath = "{$summaryPath}/attempts/{$attemptId}";
        $url = 'https://firestore.googleapis.com/v1/';

        try {
            foreach (
                [
                    $classPath => 'Class',
                    $studentPath => 'Enrollment',
                ] as $path => $label
            ) {
                $response = Http::withToken($idToken)->timeout(15)->get($url . $path);
                if ($response->status() === 404) {
                    return response()->json(['message' => "{$label} not found."], 403);
                }
                if (! $response->successful()) {
                    return response()->json(['message' => "Could not verify {$label}."], 503);
                }
            }

            $credentialsPath = config('firebase.projects.app.credentials');
            if (! is_string($credentialsPath) || $credentialsPath === '') {
                throw new \RuntimeException('Service account credentials missing.');
            }
            $resolvedPath = str_starts_with($credentialsPath, '/') ||
                preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath : base_path($credentialsPath);
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );
            $accessToken = $credentials->fetchAuthToken()['access_token'] ?? null;
            if (! $accessToken) {
                throw new \RuntimeException('Google OAuth token missing.');
            }

            for ($retry = 0; $retry < 5; $retry++) {
                $summary = Http::withToken($accessToken)
                    ->timeout(15)->get($url . $summaryPath);
                $attempt = Http::withToken($accessToken)
                    ->timeout(15)->get($url . $attemptPath);

                if ($summary->status() === 404 || $attempt->status() === 404) {
                    return response()->json(['message' => 'Attempt not found.'], 404);
                }
                if (! $summary->successful() || ! $attempt->successful()) {
                    throw new \RuntimeException('Could not verify attempt records.');
                }

                $summaryFields = $summary->json('fields', []);
                $attemptFields = $attempt->json('fields', []);
                $used = (int) ($summaryFields['attemptsUsed']['integerValue'] ?? 0);
                $attemptNumber = (int) ($attemptFields['attemptNumber']['integerValue'] ?? 0);
                $status = $attemptFields['status']['stringValue'] ?? '';

                if ($status === 'abandoned' || $status === 'completed') {
                    return response()->json([
                        'success' => true,
                        'alreadyClosed' => true,
                        'status' => $status,
                        'attemptsUsed' => $used,
                    ]);
                }

                if (
                    $status !== 'in_progress' ||
                    ($summaryFields['activeAttemptId']['stringValue'] ?? '') !== $attemptId ||
                    $used < 1 ||
                    $attemptNumber !== $used
                ) {
                    return response()->json([
                        'message' => 'Attempt is not active or is inconsistent.',
                    ], 409);
                }

                $now = now('UTC')->toIso8601String();
                $writes = [
                    [
                        'update' => [
                            'name' => $attemptPath,
                            'fields' => [
                                'status' => ['stringValue' => 'abandoned'],
                                'finishedAt' => ['timestampValue' => $now],
                            ],
                        ],
                        'updateMask' => [
                            'fieldPaths' => ['status', 'finishedAt'],
                        ],
                        'currentDocument' => [
                            'updateTime' => $attempt->json('updateTime'),
                        ],
                    ],
                    [
                        'update' => [
                            'name' => $summaryPath,
                            'fields' => [
                                'activeAttemptId' => ['stringValue' => ''],
                                'updatedAt' => ['timestampValue' => $now],
                            ],
                        ],
                        'updateMask' => [
                            'fieldPaths' => ['activeAttemptId', 'updatedAt'],
                        ],
                        'currentDocument' => [
                            'updateTime' => $summary->json('updateTime'),
                        ],
                    ],
                ];

                $commit = Http::withToken($accessToken)->timeout(30)
                    ->post($url . $base . ':commit', ['writes' => $writes]);
                if ($commit->successful()) {
                    return response()->json([
                        'success' => true,
                        'alreadyClosed' => false,
                        'status' => 'abandoned',
                        'attemptsUsed' => $used,
                    ]);
                }
                if (in_array(
                    $commit->json('error.status'),
                    ['FAILED_PRECONDITION', 'ABORTED'],
                    true
                )) {
                    continue;
                }

                Log::error('Activity attempt abandonment commit rejected', [
                    'classId' => $classId,
                    'activityId' => $activityId,
                    'status' => $commit->status(),
                    'body' => $commit->body(),
                ]);
                throw new \RuntimeException('Firestore abandonment commit failed.');
            }

            return response()->json([
                'message' => 'Activity is busy. Please try again.',
            ], 409);
        } catch (Throwable $e) {
            Log::error('Student activity attempt abandonment failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Could not abandon activity. Please retry.',
            ], 503);
        }
    }
}
