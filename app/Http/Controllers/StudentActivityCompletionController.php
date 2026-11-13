<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class StudentActivityCompletionController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $input = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'activityId' => ['required', 'uuid'],
            'attemptId' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'size:10'],
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

        $classId = $input['classId'];
        $activityId = $input['activityId'];
        $attemptId = $input['attemptId'];
        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $summaryPath = "{$classPath}/activityAttempts/{$activityId}/students/{$uid}";
        $attemptPath = "{$summaryPath}/attempts/{$attemptId}";
        $url = 'https://firestore.googleapis.com/v1/';

        try {
            // Student-scoped reads enforce the existing Firestore enrollment rules.
            foreach (
                [
                    $classPath => 'Class',
                    "{$classPath}/students/{$uid}" => 'Enrollment',
                    "{$classPath}/publishedActivities/{$activityId}" => 'Published activity',
                ] as $path => $label
            ) {
                $response = Http::withToken($idToken)->timeout(15)->get($url . $path);
                if ($response->status() === 404) {
                    return response()->json(['message' => "{$label} not found."], 403);
                }

                if (! $response->successful()) {
                    return response()->json([
                        'message' => "Could not verify {$label}.",
                    ], 503);
                }

                if (
                    $label === 'Published activity' &&
                    $response->json('fields.status.stringValue') !== 'published'
                ) {
                    return response()->json([
                        'message' => 'Activity is unavailable.',
                    ], 403);
                }


                if ($label === 'Published activity') {
                    $deadlineAt = $response->json(
                        'fields.deadlineAt.timestampValue'
                    );
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

            // Answer keys are read ONLY with the server service account.
            $activityResponse = Http::withToken($accessToken)->timeout(15)
                ->get($url . "{$classPath}/activities/{$activityId}");
            if (! $activityResponse->successful()) {
                throw new \RuntimeException('Could not read authoritative activity.');
            }
            $activity = $this->decodeDocument($activityResponse->json('fields', []));

            if (($activity['status'] ?? '') !== 'published') {
                return response()->json(['message' => 'Activity is unavailable.'], 403);
            }
            $gameType = $activity['gameType'] ?? '';
            $items = $activity['questions']['items'] ?? null;
            if (! is_array($items) || count($items) !== 10 || ! array_is_list($items)) {
                throw new \RuntimeException('Activity question bank is invalid.');
            }
            $maxScore = (int) ($activity['maxScore'] ?? 100);
            $maxAttempts = (int) ($activity['maxAttempts'] ?? 0);
            if ($maxScore < 1 || $maxScore > 10000 || $maxAttempts < 1 || $maxAttempts > 10) {
                throw new \RuntimeException('Activity scoring settings are invalid.');
            }

            $answers = array_values($input['answers']);
            $correct = $this->grade($gameType, $items, $answers);
            if ($correct === null) {
                return response()->json(['message' => 'Invalid answer format.'], 422);
            }
            $score = (int) round($correct * $maxScore / 10);

            for ($retry = 0; $retry < 5; $retry++) {
                $summaryResponse = Http::withToken($accessToken)->timeout(15)->get($url . $summaryPath);
                $attemptResponse = Http::withToken($accessToken)->timeout(15)->get($url . $attemptPath);
                if (! $summaryResponse->successful() || ! $attemptResponse->successful()) {
                    if ($summaryResponse->status() === 404 || $attemptResponse->status() === 404) {
                        return response()->json(['message' => 'Attempt not found.'], 404);
                    }
                    throw new \RuntimeException('Could not verify attempt records.');
                }
                $summary = $this->decodeDocument($summaryResponse->json('fields', []));
                $attempt = $this->decodeDocument($attemptResponse->json('fields', []));
                $used = (int) ($summary['attemptsUsed'] ?? 0);
                $attemptNumber = (int) ($attempt['attemptNumber'] ?? 0);
                $status = $attempt['status'] ?? '';

                if ($status === 'completed') {
                    return response()->json([
                        'success' => true,
                        'alreadyCompleted' => true,
                        'status' => 'completed',
                        'score' => (int) ($attempt['score'] ?? 0),
                        'maxScore' => $maxScore,
                        'correctAnswers' => (int) ($attempt['correctAnswers'] ?? 0),
                        'totalQuestions' => 10,
                        'highestScore' => (int) ($summary['highestScore'] ?? 0),
                        'attemptsUsed' => $used,
                        'remainingAttempts' => max(0, $maxAttempts - $used),
                    ]);
                }
                if (
                    $status !== 'in_progress' ||
                    ($summary['activeAttemptId'] ?? '') !== $attemptId ||
                    $attemptNumber < 1 || $attemptNumber !== $used || $used > $maxAttempts
                ) {
                    return response()->json(['message' => 'Attempt is not active or is inconsistent.'], 409);
                }

                $highestScore = max((int) ($summary['highestScore'] ?? 0), $score);
                $now = now('UTC')->toIso8601String();
                $writes = [
                    [
                        'update' => ['name' => $attemptPath, 'fields' => [
                            'status' => ['stringValue' => 'completed'],
                            'score' => ['integerValue' => (string) $score],
                            'correctAnswers' => ['integerValue' => (string) $correct],
                            'finishedAt' => ['timestampValue' => $now],
                        ]],
                        'updateMask' => ['fieldPaths' => ['status', 'score', 'correctAnswers', 'finishedAt']],
                        'currentDocument' => ['updateTime' => $attemptResponse->json('updateTime')],
                    ],
                    [
                        'update' => ['name' => $summaryPath, 'fields' => [
                            'highestScore' => ['integerValue' => (string) $highestScore],
                            'activeAttemptId' => ['stringValue' => ''],
                            'updatedAt' => ['timestampValue' => $now],
                        ]],
                        'updateMask' => ['fieldPaths' => ['highestScore', 'activeAttemptId', 'updatedAt']],
                        'currentDocument' => ['updateTime' => $summaryResponse->json('updateTime')],
                    ],
                ];
                $commit = Http::withToken($accessToken)->timeout(30)
                    ->post($url . $base . ':commit', ['writes' => $writes]);
                if ($commit->successful()) {
                    return response()->json([
                        'success' => true,
                        'alreadyCompleted' => false,
                        'status' => 'completed',
                        'score' => $score,
                        'maxScore' => $maxScore,
                        'correctAnswers' => $correct,
                        'totalQuestions' => 10,
                        'highestScore' => $highestScore,
                        'attemptsUsed' => $used,
                        'remainingAttempts' => max(0, $maxAttempts - $used),
                    ]);
                }
                if (in_array($commit->json('error.status'), ['FAILED_PRECONDITION', 'ABORTED'], true)) {
                    continue;
                }
                Log::error('Activity completion commit rejected', [
                    'classId' => $classId,
                    'activityId' => $activityId,
                    'status' => $commit->status(),
                    'body' => $commit->body(),
                ]);
                throw new \RuntimeException('Firestore completion commit failed.');
            }
            return response()->json(['message' => 'Attempt is busy. Please retry.'], 409);
        } catch (Throwable $e) {
            Log::error('Student activity completion failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not confirm completion. Retry the same attempt.'], 503);
        }
    }

    private function grade(string $gameType, array $items, array $answers): ?int
    {
        $correct = 0;
        foreach ($items as $i => $item) {
            $answer = $answers[$i];
            if (! is_array($item)) {
                return null;
            }
            switch ($gameType) {
                case 'mind_rush':
                    if (
                        ! is_int($answer) || $answer < 0 || $answer > 3 ||
                        ! isset($item['correctIndex'])
                    ) {
                        return null;
                    }
                    $isCorrect = $answer === (int) $item['correctIndex'];
                    break;
                case 'link_up':
                    // Submit the selected definition TEXT, never the original index.
                    // The future question-delivery API must shuffle definitions.
                    if (
                        ! is_string($answer) || mb_strlen($answer) > 500 ||
                        ! isset($item['definition'])
                    ) {
                        return null;
                    }
                    $isCorrect = mb_strtolower(trim($answer)) ===
                        mb_strtolower(trim($item['definition']));
                    break;
                case 'word_forge':
                    if (
                        ! is_array($answer) || ! array_is_list($answer) ||
                        ! isset($item['answers']) || ! is_array($item['answers']) ||
                        count($answer) > 20
                    ) {
                        return null;
                    }
                    foreach ($answer as $value) {
                        if (! is_string($value) || mb_strlen($value) > 100) {
                            return null;
                        }
                    }
                    $expected = array_map(fn($s) => mb_strtolower(trim($s)), $item['answers']);
                    $actual = array_map(fn($s) => mb_strtolower(trim($s)), $answer);
                    if (count($actual) !== count(array_unique($actual))) {
                        return null;
                    }
                    sort($expected);
                    sort($actual);
                    $isCorrect = $actual === $expected;
                    break;
                case 'letter_quest':
                    if (
                        ! is_string($answer) || mb_strlen($answer) > 100 ||
                        ! isset($item['answer'])
                    ) {
                        return null;
                    }
                    // Letter tiles omit spaces, matching reviewer-mode behavior.
                    $normalize = fn(string $value): string => mb_strtolower(
                        preg_replace('/\s+/u', '', trim($value))
                    );
                    $isCorrect = $normalize($answer) === $normalize($item['answer']);
                    break;
                default:
                    return null;
            }
            if ($isCorrect) {
                $correct++;
            }
        }
        return $correct;
    }

    private function decodeDocument(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            $result[$key] = $this->decodeValue($value);
        }
        return $result;
    }

    private function decodeValue(array $value): mixed
    {
        if (array_key_exists('stringValue', $value)) {
            return $value['stringValue'];
        }
        if (array_key_exists('integerValue', $value)) {
            return (int) $value['integerValue'];
        }
        if (array_key_exists('doubleValue', $value)) {
            return (float) $value['doubleValue'];
        }
        if (array_key_exists('booleanValue', $value)) {
            return $value['booleanValue'];
        }
        if (array_key_exists('nullValue', $value)) {
            return null;
        }
        if (isset($value['mapValue'])) {
            return $this->decodeDocument($value['mapValue']['fields'] ?? []);
        }
        if (isset($value['arrayValue'])) {
            return array_map(
                fn($v) => $this->decodeValue($v),
                $value['arrayValue']['values'] ?? []
            );
        }
        if (array_key_exists('timestampValue', $value)) {
            return $value['timestampValue'];
        }
        throw new \RuntimeException('Unsupported Firestore field value.');
    }
}
