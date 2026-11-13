<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class GameActivityScoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'activityId' => ['required', 'uuid'],
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
        $rootUrl = 'https://firestore.googleapis.com/v1/';

        try {
            // Check ownership with the teacher's Firebase token, not the service account.
            $classResponse = Http::withToken($idToken)->timeout(15)
                ->get($rootUrl . $classPath);
            if ($classResponse->status() === 404) {
                return response()->json(['message' => 'Class not found.'], 404);
            }
            if (! $classResponse->successful()) {
                return response()->json(['message' => 'Could not verify class ownership.'], 503);
            }
            if ($classResponse->json('fields.teacherId.stringValue') !== $uid) {
                return response()->json(['message' => 'Only the class teacher can view scores.'], 403);
            }

            $activityResponse = Http::withToken($idToken)->timeout(15)
                ->get($rootUrl . "{$classPath}/activities/{$activityId}");
            if ($activityResponse->status() === 404) {
                return response()->json(['message' => 'Activity not found.'], 404);
            }
            if (! $activityResponse->successful()) {
                return response()->json(['message' => 'Could not retrieve activity.'], 503);
            }
            $activityFields = $activityResponse->json('fields', []);
            if (($activityFields['teacherId']['stringValue'] ?? null) !== $uid) {
                return response()->json(['message' => 'You do not own this activity.'], 403);
            }
            if (($activityFields['status']['stringValue'] ?? null) !== 'published') {
                return response()->json(['message' => 'Score monitoring is available for published activities only.'], 422);
            }

            $credentialsPath = config('firebase.projects.app.credentials');
            if (! is_string($credentialsPath) || $credentialsPath === '') {
                throw new \RuntimeException('Firebase credentials are missing.');
            }
            $resolvedPath = str_starts_with($credentialsPath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath : base_path($credentialsPath);
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );
            $accessToken = $credentials->fetchAuthToken()['access_token'] ?? null;
            if (! $accessToken) {
                throw new \RuntimeException('Google OAuth token is missing.');
            }

            // Listing a collection also returns documents in its nested paths via the parent endpoint.
            $enrollments = $this->listDocuments($rootUrl, "{$classPath}/students", $accessToken);
            $summaries = $this->listDocuments(
                $rootUrl,
                "{$classPath}/activityAttempts/{$activityId}/students",
                $accessToken
            );
            $summaryByUid = [];
            foreach ($summaries as $doc) {
                $studentUid = basename($doc['name']);
                $summaryByUid[$studentUid] = $this->decodeFields($doc['fields'] ?? []);
            }

            $maxAttempts = (int) ($activityFields['maxAttempts']['integerValue'] ?? 1);
            $maxScore = (int) ($activityFields['maxScore']['integerValue'] ?? 100);
            $students = [];
            $completedCount = 0;
            $totalBest = 0;

            foreach ($enrollments as $doc) {
                $studentUid = basename($doc['name']);
                $enrollment = $this->decodeFields($doc['fields'] ?? []);
                $summary = $summaryByUid[$studentUid] ?? [];

                // The profile document supplies the display name if enrollment has none.
                // Retrieve the student's full name from their user profile.
                $name = null;

                $profileResponse = Http::withToken($accessToken)
                    ->timeout(15)
                    ->get($rootUrl . $base . '/users/' . rawurlencode($studentUid));

                if ($profileResponse->successful()) {
                    $profile = $this->decodeFields(
                        $profileResponse->json('fields', [])
                    );

                    // Prioritize fullName over name and username.
                    $name = $profile['fullName']
                        ?? $profile['name']
                        ?? $profile['username']
                        ?? null;
                }

                // Fallback to enrollment information if the profile name is missing.
                if (! is_string($name) || trim($name) === '') {
                    $name = $enrollment['fullName']
                        ?? $enrollment['name']
                        ?? $enrollment['studentName']
                        ?? 'Unnamed student';
                }

                $history = [];
                if (isset($summaryByUid[$studentUid])) {
                    $attemptDocs = $this->listDocuments(
                        $rootUrl,
                        "{$classPath}/activityAttempts/{$activityId}/students/{$studentUid}/attempts",
                        $accessToken
                    );
                    foreach ($attemptDocs as $attemptDoc) {
                        $attempt = $this->decodeFields($attemptDoc['fields'] ?? []);
                        $history[] = [
                            'attemptId' => basename($attemptDoc['name']),
                            'attemptNumber' => (int) ($attempt['attemptNumber'] ?? 0),
                            'score' => isset($attempt['score']) ? (int) $attempt['score'] : null,
                            'correctAnswers' => isset($attempt['correctAnswers']) ? (int) $attempt['correctAnswers'] : null,
                            'status' => $attempt['status'] ?? 'unknown',
                            'finishedAt' => $attempt['finishedAt'] ?? null,
                        ];
                    }
                    usort($history, fn($a, $b) => $a['attemptNumber'] <=> $b['attemptNumber']);
                }

                $completed = count(array_filter(
                    $history,
                    fn($attempt) => $attempt['status'] === 'completed'
                ));
                $attemptsUsed = (int) ($summary['attemptsUsed'] ?? 0);
                $bestScore = $completed > 0 ? (int) ($summary['highestScore'] ?? 0) : null;
                if ($completed > 0) {
                    $completedCount++;
                    $totalBest += $bestScore;
                }
                $students[] = [
                    'uid' => $studentUid,
                    'name' => is_string($name) && trim($name) !== '' ? $name : 'Unnamed student',
                    'highestScore' => $bestScore,
                    'attemptsUsed' => $attemptsUsed,
                    'maxAttempts' => $maxAttempts,
                    'completedAttempts' => $completed,
                    'status' => $completed > 0 ? 'completed' : (
                        ! empty($summary['activeAttemptId']) ? 'in_progress' : 'not_started'
                    ),
                    'history' => $history,
                ];
            }

            usort($students, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            return response()->json([
                'success' => true,
                'activityId' => $activityId,
                'maxScore' => $maxScore,
                'maxAttempts' => $maxAttempts,
                'totalStudents' => count($students),
                'completedStudents' => $completedCount,
                'averageHighestScore' => $completedCount > 0 ? round($totalBest / $completedCount, 2) : null,
                'students' => $students,
            ]);
        } catch (Throwable $e) {
            Log::error('Activity score monitoring failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not load activity scores.'], 503);
        }
    }

    private function listDocuments(string $rootUrl, string $path, string $accessToken): array
    {
        $documents = [];
        $pageToken = null;
        do {
            $query = ['pageSize' => 100];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $response = Http::withToken($accessToken)->timeout(20)
                ->get($rootUrl . $path, $query);
            if (! $response->successful()) {
                throw new \RuntimeException('Firestore list failed: HTTP ' . $response->status());
            }
            array_push($documents, ...$response->json('documents', []));
            $pageToken = $response->json('nextPageToken');
        } while (is_string($pageToken) && $pageToken !== '');

        return $documents;
    }

    private function decodeFields(array $fields): array
    {
        $decoded = [];
        foreach ($fields as $key => $value) {
            foreach (['stringValue', 'integerValue', 'doubleValue', 'booleanValue', 'timestampValue'] as $type) {
                if (array_key_exists($type, $value)) {
                    $decoded[$key] = $value[$type];
                    break;
                }
            }
        }
        return $decoded;
    }
}
