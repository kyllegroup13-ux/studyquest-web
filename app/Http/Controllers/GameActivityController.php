<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Services\GameActivityAiService;
use Illuminate\Validation\ValidationException;
use App\Services\GameActivityQuestionValidator;
use Throwable;

class GameActivityController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classId' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_-]{1,128}$/',
            ],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'gameType' => [
                'required',
                'in:mind_rush,link_up,word_forge,letter_quest',
            ],
            'generationMethod' => [
                'required',
                'in:topic,file',
            ],
            'topic' => [
                'required_if:generationMethod,topic',
                'nullable',
                'string',
                'max:1000',
            ],
            'maxAttempts' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],
        ]);

        // 1. Verify Firebase authentication.
        $idToken = $request->bearerToken();

        if (! $idToken) {
            return response()->json([
                'message' => 'Please sign in again.',
            ], 401);
        }

        try {
            $verifiedToken = Firebase::auth()
                ->verifyIdToken($idToken);

            $uid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired session.',
            ], 401);
        }

        // 2. Verify class ownership.
        $classId = $validated['classId'];
        $projectId = config('services.firebase.project_id');

        if (! is_string($projectId) || $projectId === '') {
            return response()->json([
                'message' => 'Firebase is not configured.',
            ], 503);
        }

        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";

        try {
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$classPath}"
                );

            if ($classResponse->status() === 404) {
                return response()->json([
                    'message' => 'Class not found.',
                ], 404);
            }

            if (! $classResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify class ownership.',
                ], 503);
            }

            $teacherId = $classResponse->json(
                'fields.teacherId.stringValue'
            );

            if ($teacherId !== $uid) {
                return response()->json([
                    'message' => 'Only the class teacher can create activities.',
                ], 403);
            }
        } catch (Throwable $e) {
            Log::error('Activity class verification failed', [
                'classId' => $classId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not verify class ownership.',
            ], 503);
        }

        // 3. Obtain Google OAuth token for Firestore writes.
        try {
            $credentialsPath = config(
                'firebase.projects.app.credentials'
            );

            if (
                ! is_string($credentialsPath) ||
                $credentialsPath === ''
            ) {
                throw new \RuntimeException(
                    'Firebase credentials are missing.'
                );
            }

            $resolvedPath = str_starts_with(
                $credentialsPath,
                '/'
            ) || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (! $accessToken) {
                throw new \RuntimeException(
                    'Google OAuth token is missing.'
                );
            }
        } catch (Throwable $e) {
            Log::error('Activity Firestore authentication failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not connect to Firestore.',
            ], 503);
        }

        // 4. Prepare the draft activity document.
        $activityId = (string) Str::uuid();

        $activityPath = "{$classPath}/activities/{$activityId}";

        $fields = [
            'title' => [
                'stringValue' => $validated['title'],
            ],
            'description' => [
                'stringValue' => $validated['description'] ?? '',
            ],
            'teacherId' => [
                'stringValue' => $uid,
            ],
            'gameType' => [
                'stringValue' => $validated['gameType'],
            ],
            'generationMethod' => [
                'stringValue' => $validated['generationMethod'],
            ],
            'topic' => [
                'stringValue' => $validated['topic'] ?? '',
            ],
            'status' => [
                'stringValue' => 'draft',
            ],
            'maxAttempts' => [
                'integerValue' => (string) $validated['maxAttempts'],
            ],
            'scoringPolicy' => [
                'stringValue' => 'highest',
            ],
            'maxScore' => [
                'integerValue' => '100',
            ],
            'createdAt' => [
                'timestampValue' => now('UTC')->toIso8601String(),
            ],
        ];

        // 5. Save the draft using a create-only precondition.
        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->post(
                    "https://firestore.googleapis.com/v1/{$base}:commit",
                    [
                        'writes' => [
                            [
                                'update' => [
                                    'name' => $activityPath,
                                    'fields' => $fields,
                                ],
                                'currentDocument' => [
                                    'exists' => false,
                                ],
                            ],
                        ],
                    ]
                );

            if (! $response->successful()) {
                Log::error('Activity draft creation rejected', [
                    'classId' => $classId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Could not create activity draft.',
                ], 503);
            }
        } catch (Throwable $e) {
            Log::error('Activity draft creation failed', [
                'classId' => $classId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not confirm draft creation. Check Firestore before retrying.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'activityId' => $activityId,
            'status' => 'draft',
            'message' => 'Activity draft created successfully.',
        ], 201);
    }


    public function generate(
        Request $request,
        GameActivityAiService $aiService
    ): JsonResponse {
        $validated = $request->validate([
            'classId' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_-]{1,128}$/',
            ],
            'activityId' => [
                'required',
                'string',
                'uuid',
            ],
        ]);

        // 1. Verify the teacher's Firebase session.
        $idToken = $request->bearerToken();

        if (! $idToken) {
            return response()->json([
                'message' => 'Please sign in again.',
            ], 401);
        }

        try {
            $verifiedToken = Firebase::auth()
                ->verifyIdToken($idToken);

            $uid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired session.',
            ], 401);
        }

        // 2. Verify class ownership using the user's token.
        $classId = $validated['classId'];
        $activityId = $validated['activityId'];

        $projectId = config('services.firebase.project_id');

        if (! is_string($projectId) || $projectId === '') {
            return response()->json([
                'message' => 'Firebase is not configured.',
            ], 503);
        }

        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $activityPath = "{$classPath}/activities/{$activityId}";

        $publishedActivityPath =
            "{$classPath}/publishedActivities/{$activityId}";

        try {
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$classPath}"
                );

            if ($classResponse->status() === 404) {
                return response()->json([
                    'message' => 'Class not found.',
                ], 404);
            }

            if (! $classResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify class ownership.',
                ], 503);
            }

            $teacherId = $classResponse->json(
                'fields.teacherId.stringValue'
            );

            if ($teacherId !== $uid) {
                return response()->json([
                    'message' => 'Only the class teacher can generate questions.',
                ], 403);
            }

            // 3. Retrieve the existing activity.
            $activityResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$activityPath}"
                );

            if ($activityResponse->status() === 404) {
                return response()->json([
                    'message' => 'Activity not found.',
                ], 404);
            }

            if (! $activityResponse->successful()) {
                return response()->json([
                    'message' => 'Could not retrieve activity.',
                ], 503);
            }

            $activity = $activityResponse->json('fields');

            if (
                ($activity['teacherId']['stringValue'] ?? null) !== $uid
            ) {
                return response()->json([
                    'message' => 'You are not authorized to generate this activity.',
                ], 403);
            }

            if (
                ($activity['status']['stringValue'] ?? null) !== 'draft'
            ) {
                return response()->json([
                    'message' => 'Only draft activities can generate questions.',
                ], 422);
            }

            if (
                ($activity['generationMethod']['stringValue'] ?? null)
                !== 'topic'
            ) {
                return response()->json([
                    'message' => 'File-based generation is not available yet.',
                ], 422);
            }

            $gameType = $activity['gameType']['stringValue'] ?? '';
            $topic = trim(
                $activity['topic']['stringValue'] ?? ''
            );

            if ($topic === '') {
                return response()->json([
                    'message' => 'Activity topic is missing.',
                ], 422);
            }
        } catch (Throwable $e) {
            Log::error('Activity generation authorization failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not verify activity permissions.',
            ], 503);
        }

        // 4. Generate and validate the questions.
        try {
            $questions = $aiService->generate(
                $gameType,
                $topic
            );

            return response()->json([
                'success' => true,
                'activityId' => $activityId,
                'gameType' => $gameType,
                'questions' => $questions,
                'message' => 'Questions generated successfully.',
            ]);
        } catch (ValidationException $e) {
            Log::warning('Gemini returned invalid activity questions', [
                'classId' => $classId,
                'activityId' => $activityId,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'message' => 'AI generated an invalid question structure. Please try again.',
            ], 422);
        } catch (Throwable $e) {
            Log::error('Activity AI generation failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not generate questions. Please try again later.',
            ], 503);
        }
    }


    public function saveQuestions(
        Request $request,
        GameActivityQuestionValidator $validator
    ): JsonResponse {
        $validated = $request->validate([
            'classId' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_-]{1,128}$/',
            ],
            'activityId' => [
                'required',
                'string',
                'uuid',
            ],
            'questions' => [
                'required',
                'array',
            ],
        ]);

        // 1. Authenticate the teacher.
        $idToken = $request->bearerToken();

        if (! $idToken) {
            return response()->json([
                'message' => 'Please sign in again.',
            ], 401);
        }

        try {
            $verifiedToken = Firebase::auth()
                ->verifyIdToken($idToken);

            $uid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired session.',
            ], 401);
        }

        $classId = $validated['classId'];
        $activityId = $validated['activityId'];
        $projectId = config('services.firebase.project_id');

        if (! is_string($projectId) || $projectId === '') {
            return response()->json([
                'message' => 'Firebase is not configured.',
            ], 503);
        }

        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $activityPath = "{$classPath}/activities/{$activityId}";

        // 2. Verify ownership and retrieve the activity.
        try {
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$classPath}"
                );

            if ($classResponse->status() === 404) {
                return response()->json([
                    'message' => 'Class not found.',
                ], 404);
            }

            if (! $classResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify class ownership.',
                ], 503);
            }

            if (
                $classResponse->json('fields.teacherId.stringValue')
                !== $uid
            ) {
                return response()->json([
                    'message' => 'Only the class teacher can save questions.',
                ], 403);
            }

            $activityResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$activityPath}"
                );

            if ($activityResponse->status() === 404) {
                return response()->json([
                    'message' => 'Activity not found.',
                ], 404);
            }

            if (! $activityResponse->successful()) {
                return response()->json([
                    'message' => 'Could not retrieve activity.',
                ], 503);
            }

            $activity = $activityResponse->json('fields');

            if (
                ($activity['teacherId']['stringValue'] ?? null)
                !== $uid
            ) {
                return response()->json([
                    'message' => 'You do not own this activity.',
                ], 403);
            }

            if (
                ($activity['status']['stringValue'] ?? null)
                !== 'draft'
            ) {
                return response()->json([
                    'message' => 'Only draft activities can be modified.',
                ], 422);
            }

            if (
                ($activity['generationMethod']['stringValue'] ?? null)
                !== 'topic'
            ) {
                return response()->json([
                    'message' => 'Only topic-based generation is supported.',
                ], 422);
            }

            if (isset($activity['questions'])) {
                return response()->json([
                    'message' => 'Questions are already saved for this activity.',
                ], 409);
            }

            $gameType = $activity['gameType']['stringValue'] ?? '';

            // Used to prevent concurrent changes from being overwritten.
            $updateTime = $activityResponse->json('updateTime');

            if (! is_string($updateTime) || $updateTime === '') {
                return response()->json([
                    'message' => 'Activity version could not be verified.',
                ], 503);
            }
        } catch (Throwable $e) {
            Log::error('Activity save authorization failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not verify activity permissions.',
            ], 503);
        }

        // 3. Validate the submitted questions.
        try {
            $questions = $validator->validate(
                $gameType,
                $validated['questions']
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The questions contain invalid data.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Activity question validation failed', [
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not validate the questions.',
            ], 422);
        }

        // 4. Obtain service-account credentials.
        try {
            $credentialsPath = config(
                'firebase.projects.app.credentials'
            );

            if (
                ! is_string($credentialsPath) ||
                $credentialsPath === ''
            ) {
                throw new \RuntimeException(
                    'Firebase credentials are missing.'
                );
            }

            $resolvedPath = str_starts_with(
                $credentialsPath,
                '/'
            ) || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (! $accessToken) {
                throw new \RuntimeException(
                    'Google OAuth token is missing.'
                );
            }
        } catch (Throwable $e) {
            Log::error('Activity save Firestore authentication failed', [
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not connect to Firestore.',
            ], 503);
        }

        // 5. Prepare only the fields we want to update.
        $fields = [
            'questions' => $this->toFirestoreValue($questions),
            'questionCount' => [
                'integerValue' => '10',
            ],
            'questionsGeneratedAt' => [
                'timestampValue' => now('UTC')->toIso8601String(),
            ],
        ];

        // 6. Commit an atomic, conditional update.
        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post(
                    "https://firestore.googleapis.com/v1/{$base}:commit",
                    [
                        'writes' => [
                            [
                                'update' => [
                                    'name' => $activityPath,
                                    'fields' => $fields,
                                ],
                                'updateMask' => [
                                    'fieldPaths' => [
                                        'questions',
                                        'questionCount',
                                        'questionsGeneratedAt',
                                    ],
                                ],
                                'currentDocument' => [
                                    'updateTime' => $updateTime,
                                ],
                            ],
                        ],
                    ]
                );

            if (! $response->successful()) {
                if (
                    in_array(
                        $response->json('error.status'),
                        ['FAILED_PRECONDITION', 'ABORTED'],
                        true
                    )
                ) {
                    return response()->json([
                        'message' => 'The activity changed. Refresh before saving again.',
                    ], 409);
                }

                Log::error('Activity question save rejected', [
                    'activityId' => $activityId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Could not save questions.',
                ], 503);
            }
        } catch (Throwable $e) {
            Log::error('Activity question save failed', [
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Save result is uncertain. Check Firestore before retrying.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'activityId' => $activityId,
            'questionCount' => 10,
            'status' => 'draft',
            'message' => 'Questions saved successfully.',
        ]);
    }


    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classId' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_-]{1,128}$/',
            ],
            'activityId' => [
                'required',
                'string',
                'uuid',
            ],
        ]);

        // 1. Authenticate the teacher.
        $idToken = $request->bearerToken();

        if (! $idToken) {
            return response()->json([
                'message' => 'Please sign in again.',
            ], 401);
        }

        try {
            $verifiedToken = Firebase::auth()
                ->verifyIdToken($idToken);

            $uid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired session.',
            ], 401);
        }

        $classId = $validated['classId'];
        $activityId = $validated['activityId'];
        $projectId = config('services.firebase.project_id');

        if (! is_string($projectId) || $projectId === '') {
            return response()->json([
                'message' => 'Firebase is not configured.',
            ], 503);
        }

        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $activityPath = "{$classPath}/activities/{$activityId}";
        $publishedActivityPath =
            "{$classPath}/publishedActivities/{$activityId}";

        // 2. Verify class and activity ownership.
        try {
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$classPath}"
                );

            if ($classResponse->status() === 404) {
                return response()->json([
                    'message' => 'Class not found.',
                ], 404);
            }

            if (! $classResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify class ownership.',
                ], 503);
            }

            if (
                $classResponse->json('fields.teacherId.stringValue')
                !== $uid
            ) {
                return response()->json([
                    'message' => 'Only the class teacher can publish activities.',
                ], 403);
            }

            $activityResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$activityPath}"
                );

            if ($activityResponse->status() === 404) {
                return response()->json([
                    'message' => 'Activity not found.',
                ], 404);
            }

            if (! $activityResponse->successful()) {
                return response()->json([
                    'message' => 'Could not retrieve activity.',
                ], 503);
            }

            $activity = $activityResponse->json('fields');

            if (
                ($activity['teacherId']['stringValue'] ?? null)
                !== $uid
            ) {
                return response()->json([
                    'message' => 'You do not own this activity.',
                ], 403);
            }

            if (
                ($activity['status']['stringValue'] ?? null)
                !== 'draft'
            ) {
                return response()->json([
                    'message' => 'Only draft activities can be published.',
                ], 422);
            }

            // 3. Require a complete saved question bank.
            $questionCount = (int) (
                $activity['questionCount']['integerValue'] ?? 0
            );

            $savedItems = $activity['questions']['mapValue']['fields']['items']['arrayValue']['values'] ?? null;

            if (
                $questionCount !== 10 ||
                ! is_array($savedItems) ||
                count($savedItems) !== 10
            ) {
                return response()->json([
                    'message' => 'Save all 10 questions before publishing.',
                ], 422);
            }

            // Ensure each saved question has a Firestore map.
            foreach ($savedItems as $item) {
                if (
                    ! is_array($item) ||
                    ! isset($item['mapValue']['fields'])
                ) {
                    return response()->json([
                        'message' => 'The saved questions are incomplete.',
                    ], 422);
                }
            }

            $updateTime = $activityResponse->json('updateTime');

            if (! is_string($updateTime) || $updateTime === '') {
                return response()->json([
                    'message' => 'Activity version could not be verified.',
                ], 503);
            }
        } catch (Throwable $e) {
            Log::error('Activity publishing authorization failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not verify activity permissions.',
            ], 503);
        }

        // 4. Obtain service-account credentials.
        try {
            $credentialsPath = config(
                'firebase.projects.app.credentials'
            );

            if (
                ! is_string($credentialsPath) ||
                $credentialsPath === ''
            ) {
                throw new \RuntimeException(
                    'Firebase credentials are missing.'
                );
            }

            $resolvedPath = str_starts_with(
                $credentialsPath,
                '/'
            ) || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (! $accessToken) {
                throw new \RuntimeException(
                    'Google OAuth token is missing.'
                );
            }
        } catch (Throwable $e) {
            Log::error('Activity publishing authentication failed', [
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not connect to Firestore.',
            ], 503);
        }

        // 5. Prepare teacher and student publication fields.
        $publishedAt = now('UTC')->toIso8601String();

        $fields = [
            'status' => [
                'stringValue' => 'published',
            ],
            'publishedAt' => [
                'timestampValue' => $publishedAt,
            ],
        ];

        // Student-visible metadata only.
        // Never include the questions or answer keys.
        $publishedFields = [
            'title' => [
                'stringValue' =>
                $activity['title']['stringValue'] ?? '',
            ],
            'description' => [
                'stringValue' =>
                $activity['description']['stringValue'] ?? '',
            ],
            'gameType' => [
                'stringValue' =>
                $activity['gameType']['stringValue'] ?? '',
            ],
            'status' => [
                'stringValue' => 'published',
            ],
            'questionCount' => [
                'integerValue' => (string) $questionCount,
            ],
            'maxAttempts' => [
                'integerValue' => (string) (
                    $activity['maxAttempts']['integerValue'] ?? 1
                ),
            ],
            'maxScore' => [
                'integerValue' => (string) (
                    $activity['maxScore']['integerValue'] ?? 100
                ),
            ],
            'scoringPolicy' => [
                'stringValue' =>
                $activity['scoringPolicy']['stringValue'] ?? 'highest',
            ],
            'publishedAt' => [
                'timestampValue' => $publishedAt,
            ],
        ];

        try {

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post(
                    "https://firestore.googleapis.com/v1/{$base}:commit",
                    [
                        'writes' => [
                            // Write 1: Publish the teacher-owned activity.
                            [
                                'update' => [
                                    'name' => $activityPath,
                                    'fields' => $fields,
                                ],
                                'updateMask' => [
                                    'fieldPaths' => [
                                        'status',
                                        'publishedAt',
                                    ],
                                ],
                                'currentDocument' => [
                                    'updateTime' => $updateTime,
                                ],
                            ],

                            // Write 2: Create student-visible metadata.
                            [
                                'update' => [
                                    'name' => $publishedActivityPath,
                                    'fields' => $publishedFields,
                                ],
                                'currentDocument' => [
                                    'exists' => false,
                                ],
                            ],
                        ],
                    ]
                );


            if (! $response->successful()) {
                if (
                    in_array(
                        $response->json('error.status'),
                        ['FAILED_PRECONDITION', 'ABORTED'],
                        true
                    )
                ) {
                    return response()->json([
                        'message' => 'The activity changed or published metadata already exists. Refresh and check Firestore.',
                    ], 409);
                }

                Log::error('Activity publishing rejected', [
                    'activityId' => $activityId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Could not publish activity.',
                ], 503);
            }
        } catch (Throwable $e) {
            Log::error('Activity publishing failed', [
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Publish result is uncertain. Check Firestore before retrying.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'activityId' => $activityId,
            'status' => 'published',
            'message' => 'Activity published successfully.',
        ]);
    }


    private function toFirestoreValue(mixed $value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }

        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if ($value === null) {
            return ['nullValue' => null];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'arrayValue' => [
                        'values' => array_map(
                            fn($item) => $this->toFirestoreValue($item),
                            $value
                        ),
                    ],
                ];
            }

            $fields = [];

            foreach ($value as $key => $item) {
                $fields[$key] = $this->toFirestoreValue($item);
            }

            return [
                'mapValue' => [
                    'fields' => $fields,
                ],
            ];
        }

        throw new \InvalidArgumentException(
            'Unsupported Firestore value type.'
        );
    }
}
