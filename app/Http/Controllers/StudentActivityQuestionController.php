<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class StudentActivityQuestionController extends Controller
{
    public function questions(Request $request): JsonResponse
    {
        $input = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'activityId' => ['required', 'uuid'],
            'attemptId' => ['required', 'uuid'],
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
            // User-token reads enforce Firestore class membership rules.
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
                    return response()->json(['message' => "Could not verify {$label}."], 503);
                }
                if (
                    $label === 'Published activity' &&
                    $response->json('fields.status.stringValue') !== 'published'
                ) {
                    return response()->json(['message' => 'Activity is unavailable.'], 403);
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

            $summaryResponse = Http::withToken($accessToken)->timeout(15)->get($url . $summaryPath);
            $attemptResponse = Http::withToken($accessToken)->timeout(15)->get($url . $attemptPath);
            if ($summaryResponse->status() === 404 || $attemptResponse->status() === 404) {
                return response()->json(['message' => 'Attempt not found.'], 404);
            }
            if (! $summaryResponse->successful() || ! $attemptResponse->successful()) {
                throw new \RuntimeException('Could not read attempt state.');
            }

            $summary = $this->decodeFields($summaryResponse->json('fields', []));
            $attempt = $this->decodeFields($attemptResponse->json('fields', []));
            $used = $summary['attemptsUsed'] ?? null;
            if (($attempt['status'] ?? '') !== 'in_progress' ||
                ($summary['activeAttemptId'] ?? '') !== $attemptId ||
                ! is_int($used) || $used < 1 ||
                ($attempt['attemptNumber'] ?? null) !== $used
            ) {
                return response()->json(['message' => 'Attempt is not active.'], 409);
            }

            $activityResponse = Http::withToken($accessToken)->timeout(15)
                ->get($url . "{$classPath}/activities/{$activityId}");
            if (! $activityResponse->successful()) {
                throw new \RuntimeException('Could not read activity.');
            }
            $activity = $this->decodeFields($activityResponse->json('fields', []));
            $type = $activity['gameType'] ?? '';
            $items = $activity['questions']['items'] ?? null;
            $maxAttempts = $activity['maxAttempts'] ?? null;
            if (($activity['status'] ?? '') !== 'published' ||
                ! is_int($maxAttempts) || $used > $maxAttempts ||
                ! is_array($items) || ! array_is_list($items) || count($items) !== 10
            ) {
                return response()->json(['message' => 'Activity is unavailable or invalid.'], 409);
            }

            $safe = [];
            foreach ($items as $i => $item) {
                if (! is_array($item)) {
                    throw new \RuntimeException('Invalid question item.');
                }
                switch ($type) {
                    case 'mind_rush':
                        if (
                            ! is_string($item['question'] ?? null) ||
                            ! is_array($item['choices'] ?? null) || count($item['choices']) !== 4
                        ) {
                            throw new \RuntimeException('Invalid Mind Rush question.');
                        }
                        $safe[] = ['question' => $item['question'], 'choices' => $item['choices']];
                        break;
                    case 'link_up':
                        if (! is_string($item['term'] ?? null) || ! is_string($item['definition'] ?? null)) {
                            throw new \RuntimeException('Invalid Link Up question.');
                        }
                        $safe[] = ['term' => $item['term']];
                        break;
                    case 'word_forge':
                        if (
                            ! is_string($item['statement'] ?? null) ||
                            ! is_array($item['choices'] ?? null)
                        ) {
                            throw new \RuntimeException('Invalid Word Forge question.');
                        }
                        $safe[] = ['statement' => $item['statement'], 'choices' => $item['choices']];
                        break;
                    case 'letter_quest':
                        if (
                            ! is_string($item['question'] ?? null) ||
                            ! is_string($item['answer'] ?? null)
                        ) {
                            throw new \RuntimeException('Invalid Letter Quest question.');
                        }
                        // The letter multiset is part of the game, but the
                        // ordered answer must never be returned to students.
                        $answer = mb_strtoupper(preg_replace('/\s+/u', '', $item['answer']));
                        $letters = preg_split('//u', $answer, -1, PREG_SPLIT_NO_EMPTY);
                        if (! is_array($letters) || count($letters) < 1 || count($letters) > 100) {
                            throw new \RuntimeException('Invalid Letter Quest letter bank.');
                        }
                        // Deterministic Fisher-Yates shuffle scoped to this attempt
                        // and question; repeated requests yield the same bank.
                        for ($j = count($letters) - 1; $j > 0; $j--) {
                            $digest = hash('sha256', $attemptId . ':letter:' . $i . ':' . $j);
                            $swap = hexdec(substr($digest, 0, 8)) % ($j + 1);
                            [$letters[$j], $letters[$swap]] = [$letters[$swap], $letters[$j]];
                        }
                        $safe[] = ['question' => $item['question'], 'letters' => $letters];
                        break;
                    default:
                        throw new \RuntimeException('Unsupported game type.');
                }
            }

            $payload = [
                'success' => true,
                'activityId' => $activityId,
                'attemptId' => $attemptId,
                'gameType' => $type,
                'questionCount' => 10,
                'questions' => $safe,
            ];
            if ($type === 'link_up') {
                $definitions = array_column($items, 'definition');
                // Stable permutation for this attempt. Never expose source indices.
                $indices = range(0, 9);
                usort($indices, fn(int $a, int $b) => strcmp(
                    hash('sha256', $attemptId . ':definition:' . $a),
                    hash('sha256', $attemptId . ':definition:' . $b)
                ));
                $payload['definitions'] = array_map(fn(int $i) => $definitions[$i], $indices);
            }
            return response()->json($payload);
        } catch (Throwable $e) {
            Log::error('Student activity question delivery failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not load activity questions.'], 503);
        }
    }

    private function decodeFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            $result[$key] = $this->decodeValue($value);
        }
        return $result;
    }

    private function decodeValue(array $value): mixed
    {
        if (array_key_exists('stringValue', $value)) return $value['stringValue'];
        if (array_key_exists('integerValue', $value)) return (int) $value['integerValue'];
        if (array_key_exists('doubleValue', $value)) return (float) $value['doubleValue'];
        if (array_key_exists('booleanValue', $value)) return $value['booleanValue'];
        if (array_key_exists('nullValue', $value)) return null;
        if (isset($value['mapValue'])) return $this->decodeFields($value['mapValue']['fields'] ?? []);
        if (isset($value['arrayValue'])) return array_map(
            fn($v) => $this->decodeValue($v),
            $value['arrayValue']['values'] ?? []
        );
        if (array_key_exists('timestampValue', $value)) return $value['timestampValue'];
        throw new \RuntimeException('Unsupported Firestore field value.');
    }
}
