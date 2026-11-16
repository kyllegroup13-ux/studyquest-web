<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class StudentActivityRankingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classId' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_-]{1,128}$/',
            ],
            'activityId' => ['required', 'uuid'],
        ]);

        $idToken = $request->bearerToken();

        if (!$idToken) {
            return response()->json([
                'message' => 'Please sign in again.',
            ], 401);
        }

        try {
            $uid = Firebase::auth()
                ->verifyIdToken($idToken)
                ->claims()
                ->get('sub');
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired session.',
            ], 401);
        }

        $projectId = config('services.firebase.project_id');

        if (!$projectId) {
            return response()->json([
                'message' => 'Firebase is not configured.',
            ], 503);
        }

        $classId = $validated['classId'];
        $activityId = $validated['activityId'];

        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $url = 'https://firestore.googleapis.com/v1/';

        try {
            // Verify that the student is enrolled.
            $enrollment = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    $url . "{$classPath}/students/{$uid}"
                );

            if ($enrollment->status() === 404) {
                return response()->json([
                    'message' => 'You are not enrolled in this class.',
                ], 403);
            }

            if (!$enrollment->successful()) {
                return response()->json([
                    'message' => 'Could not verify enrollment.',
                ], 503);
            }

            // Verify published activity.
            $published = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    $url . "{$classPath}/publishedActivities/{$activityId}"
                );

            if ($published->status() === 404) {
                return response()->json([
                    'message' => 'Activity not found.',
                ], 404);
            }

            if (!$published->successful()) {
                return response()->json([
                    'message' => 'Could not verify activity.',
                ], 503);
            }

            $publishedFields = $published->json('fields', []);

            if (
                ($publishedFields['status']['stringValue'] ?? null)
                !== 'published'
            ) {
                return response()->json([
                    'message' => 'Activity is not available.',
                ], 403);
            }

            // Obtain service-account access for score summaries.
            $credentialsPath =
                config('firebase.projects.app.credentials');

            if (
                !is_string($credentialsPath) ||
                $credentialsPath === ''
            ) {
                throw new \RuntimeException(
                    'Firebase credentials are missing.'
                );
            }

            $resolvedPath =
                str_starts_with($credentialsPath, '/') ||
                preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $accessToken =
                $credentials->fetchAuthToken()['access_token'] ?? null;

            if (!$accessToken) {
                throw new \RuntimeException(
                    'Google access token is missing.'
                );
            }

            $summaryPath =
                "{$classPath}/activityAttempts/{$activityId}/students";

            $summaries = $this->listDocuments(
                $url,
                $summaryPath,
                $accessToken
            );

            $rankings = [];

            foreach ($summaries as $document) {
                $studentUid = basename($document['name']);

                $summary = $this->decodeFields(
                    $document['fields'] ?? []
                );

                $highestScore = $summary['highestScore'] ?? null;

                if (
                    !is_numeric($highestScore) ||
                    !empty($summary['activeAttemptId']) &&
                    !is_string($summary['activeAttemptId'])
                ) {
                    continue;
                }

                // Verify completed attempt history.
                $attemptsPath =
                    "{$summaryPath}/{$studentUid}/attempts";

                $attempts = $this->listDocuments(
                    $url,
                    $attemptsPath,
                    $accessToken
                );

                $completedCount = 0;

                foreach ($attempts as $attemptDocument) {
                    $attempt = $this->decodeFields(
                        $attemptDocument['fields'] ?? []
                    );

                    if (($attempt['status'] ?? '') === 'completed') {
                        $completedCount++;
                    }
                }

                if ($completedCount === 0) {
                    continue;
                }

                // Retrieve student profile.
                $profileResponse = Http::withToken($accessToken)
                    ->timeout(15)
                    ->get(
                        $url . "{$base}/users/" .
                            rawurlencode($studentUid)
                    );

                $profile = $profileResponse->successful()
                    ? $this->decodeFields(
                        $profileResponse->json('fields', [])
                    )
                    : [];

                $name = $profile['fullName']
                    ?? $profile['name']
                    ?? $profile['username']
                    ?? null;

                if (!is_string($name) || trim($name) === '') {
                    $name = 'Unnamed student';
                }

                $rankings[] = [
                    'uid' => $studentUid,
                    'name' => $name,
                    'highestScore' => (int) $highestScore,
                ];
            }

            // Sort descending by highest score.
            usort($rankings, function ($a, $b) {
                return $b['highestScore'] <=>
                    $a['highestScore'];
            });

            // Competition ranking: 1, 1, 3.
            $previousScore = null;
            $rank = 0;

            foreach ($rankings as $index => &$student) {
                if (
                    $previousScore === null ||
                    $student['highestScore'] !== $previousScore
                ) {
                    $rank = $index + 1;
                }

                $student['rank'] = $rank;
                $previousScore = $student['highestScore'];
            }

            unset($student);

            $myRanking = null;

            foreach ($rankings as $student) {
                if ($student['uid'] === $uid) {
                    $myRanking = $student;
                    break;
                }
            }

            // Do not expose student UIDs in the public leaderboard.
            $publicRankings = array_map(
                fn($student) => [
                    'rank' => $student['rank'],
                    'name' => $student['name'],
                    'highestScore' => $student['highestScore'],
                    'isCurrentUser' => $student['uid'] === $uid,
                ],
                $rankings
            );

            return response()->json([
                'success' => true,
                'activityId' => $activityId,
                'maxScore' => (int) (
                    $publishedFields['maxScore']['integerValue'] ?? 100
                ),
                'totalRankedStudents' => count($rankings),
                'myRanking' => $myRanking ? [
                    'rank' => $myRanking['rank'],
                    'highestScore' => $myRanking['highestScore'],
                ] : null,
                'rankings' => $publicRankings,
            ]);
        } catch (Throwable $e) {
            Log::error('Student activity ranking failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not load activity ranking.',
            ], 503);
        }
    }

    private function listDocuments(
        string $rootUrl,
        string $path,
        string $accessToken
    ): array {
        $documents = [];
        $pageToken = null;

        do {
            $query = ['pageSize' => 100];

            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->get($rootUrl . $path, $query);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'Firestore list failed: HTTP ' .
                        $response->status()
                );
            }

            array_push(
                $documents,
                ...$response->json('documents', [])
            );

            $pageToken = $response->json('nextPageToken');
        } while (
            is_string($pageToken) &&
            $pageToken !== ''
        );

        return $documents;
    }

    private function decodeFields(array $fields): array
    {
        $decoded = [];

        foreach ($fields as $key => $value) {
            foreach (
                [
                    'stringValue',
                    'integerValue',
                    'doubleValue',
                    'booleanValue',
                    'timestampValue',
                ] as $type
            ) {
                if (array_key_exists($type, $value)) {
                    $decoded[$key] = $value[$type];
                    break;
                }
            }
        }

        return $decoded;
    }
}
