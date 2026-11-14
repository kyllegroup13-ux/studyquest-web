<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Services\GameActivityAiService;
use App\Services\ActivitySourceFileService;
use Illuminate\Validation\ValidationException;
use App\Services\GameActivityQuestionValidator;
use App\Support\ActivityDeadline;
use Carbon\Carbon;
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
            'itemCount' => [
                'required',
                'integer',
                'in:5,10,15,20,25,30',
            ],
            'roundCount' => [
                'nullable',
                'integer',
                'between:1,6',
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
            'deadlineAt' => [
                'nullable',
                'date_format:Y-m-d\TH:i',
            ],
        ]);

        $gameType = $validated['gameType'];
        $itemCount = (int) $validated['itemCount'];
        $roundCount = $validated['roundCount'] ?? null;

        if ($gameType === 'link_up') {
            if (
                $roundCount === null ||
                $itemCount !== ((int) $roundCount * 5)
            ) {
                throw ValidationException::withMessages([
                    'roundCount' => [
                        'Link Up requires 1–6 rounds with 5 items per round.',
                    ],
                ]);
            }

            $roundCount = (int) $roundCount;
        } else {
            if ($roundCount !== null) {
                throw ValidationException::withMessages([
                    'roundCount' => [
                        'Rounds are only supported for Link Up.',
                    ],
                ]);
            }
        }

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


        $deadlineAt = null;

        if (!empty($validated['deadlineAt'])) {
            $deadlineAt = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                $validated['deadlineAt'],
                'Asia/Manila'
            )->utc();

            if ($deadlineAt->lessThanOrEqualTo(now('UTC'))) {
                throw ValidationException::withMessages([
                    'deadlineAt' => [
                        'The activity deadline must be in the future.',
                    ],
                ]);
            }
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
            'itemCount' => [
                'integerValue' => (string) $itemCount,
            ],
            'roundCount' => [
                'integerValue' => (string) ($roundCount ?? 0),
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

        if ($deadlineAt !== null) {
            $fields['deadlineAt'] = [
                'timestampValue' => $deadlineAt->toIso8601String(),
            ];
        }

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
        GameActivityAiService $aiService,
        ActivitySourceFileService $sourceService
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


            $generationMethod =
                $activity['generationMethod']['stringValue'] ?? '';

            $gameType = $activity['gameType']['stringValue'] ?? '';

            $itemCount = (int) (
                $activity['itemCount']['integerValue'] ?? 10
            );

            if (! in_array($itemCount, [5, 10, 15, 20, 25, 30], true)) {
                return response()->json([
                    'message' => 'Invalid activity item count.',
                ], 422);
            }

            $topic = '';
            $sourceFile = null;

            if ($generationMethod === 'topic') {
                $topic = trim(
                    $activity['topic']['stringValue'] ?? ''
                );

                if ($topic === '') {
                    return response()->json([
                        'message' => 'Activity topic is missing.',
                    ], 422);
                }
            } elseif ($generationMethod === 'file') {
                $sourceFields =
                    $activity['sourceFile']['mapValue']['fields'] ?? null;

                if (! is_array($sourceFields)) {
                    return response()->json([
                        'message' => 'Upload a source document before generating questions.',
                    ], 422);
                }

                $sourceFile = [
                    'publicId' =>
                    $sourceFields['publicId']['stringValue'] ?? '',
                    'resourceType' =>
                    $sourceFields['resourceType']['stringValue'] ?? '',
                    'deliveryType' =>
                    $sourceFields['deliveryType']['stringValue'] ?? '',
                    'fileName' =>
                    $sourceFields['fileName']['stringValue'] ?? '',
                    'mimeType' =>
                    $sourceFields['mimeType']['stringValue'] ?? '',
                    'bytes' => (int) (
                        $sourceFields['bytes']['integerValue'] ?? 0
                    ),
                ];
            } else {
                return response()->json([
                    'message' => 'Unsupported generation method.',
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

            if ($generationMethod === 'file') {
                $fileContents = $sourceService->download($sourceFile);

                $questions = $aiService->generateFromFile(
                    $gameType,
                    $fileContents,
                    $sourceFile['mimeType'],
                    $sourceFile['fileName'],
                    $itemCount
                );
            } else {
                $questions = $aiService->generate(
                    $gameType,
                    $topic,
                    $itemCount
                );
            }

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


            $generationMethod =
                $activity['generationMethod']['stringValue'] ?? '';

            if (! in_array($generationMethod, ['topic', 'file'], true)) {
                return response()->json([
                    'message' => 'Unsupported generation method.',
                ], 422);
            }

            if (
                $generationMethod === 'file' &&
                ! isset($activity['sourceFile']['mapValue']['fields'])
            ) {
                return response()->json([
                    'message' => 'This activity has no uploaded source document.',
                ], 422);
            }

            if (isset($activity['questions'])) {
                return response()->json([
                    'message' => 'Questions are already saved for this activity.',
                ], 409);
            }

            $gameType = $activity['gameType']['stringValue'] ?? '';

            $itemCount = (int) (
                $activity['itemCount']['integerValue'] ?? 10
            );

            if (! in_array($itemCount, [5, 10, 15, 20, 25, 30], true)) {
                return response()->json([
                    'message' => 'Invalid activity item count.',
                ], 422);
            }

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
                $validated['questions'],
                $itemCount
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
                'integerValue' => (string) count($questions['items']),
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
            'questionCount' => count($questions['items']),
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


            $deadlineAt =
                $activity['deadlineAt']['timestampValue'] ?? null;

            if (ActivityDeadline::hasPassed($deadlineAt)) {
                return response()->json([
                    'code' => 'ACTIVITY_DEADLINE_PASSED',
                    'message' =>
                    'This activity deadline has already passed. ' .
                        'Create a new draft with a future deadline.',
                ], 422);
            }


            // 3. Require a complete saved question bank.
            $expectedCount = (int) (
                $activity['itemCount']['integerValue'] ?? 10
            );

            if (! in_array($expectedCount, [5, 10, 15, 20, 25, 30], true)) {
                return response()->json([
                    'message' => 'Invalid activity item count.',
                ], 422);
            }

            $gameType = $activity['gameType']['stringValue'] ?? '';
            $roundCount = (int) (
                $activity['roundCount']['integerValue'] ?? 0
            );

            if (
                $gameType === 'link_up' &&
                ($roundCount < 1 ||
                    $roundCount > 6 ||
                    $expectedCount !== $roundCount * 5)
            ) {
                return response()->json([
                    'message' => 'Invalid Link Up round configuration.',
                ], 422);
            }

            $questionCount = (int) (
                $activity['questionCount']['integerValue'] ?? 0
            );

            $savedItems = $activity['questions']['mapValue']['fields']['items']['arrayValue']['values'] ?? null;

            if (
                $questionCount !== $expectedCount ||
                ! is_array($savedItems) ||
                count($savedItems) !== $expectedCount
            ) {
                return response()->json([
                    'message' => "Save all {$expectedCount} questions before publishing.",
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
            'itemCount' => [
                'integerValue' => (string) $expectedCount,
            ],
            'roundCount' => [
                'integerValue' => (string) $roundCount,
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

        if (isset($activity['deadlineAt']['timestampValue'])) {
            $publishedFields['deadlineAt'] = [
                'timestampValue' =>
                $activity['deadlineAt']['timestampValue'],
            ];
        }

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


        // 6. Notify enrolled students after successful publication.
        $notificationSent = false;

        try {
            $activityTitle = $activity['title']['stringValue'] ?? 'New Activity';

            $gameType = $activity['gameType']['stringValue'] ?? '';

            $gameLabels = [
                'mind_rush' => 'Mind Rush',
                'link_up' => 'Link Up',
                'word_forge' => 'Word Forge',
                'letter_quest' => 'Letter Quest',
            ];

            $gameLabel = $gameLabels[$gameType] ?? 'Game';

            $message = CloudMessage::withTarget(
                'topic',
                'class_' . $classId
            )
                ->withNotification(
                    Notification::create(
                        'New Class Activity',
                        "{$activityTitle} · {$gameLabel}"
                    )
                )
                ->withData([
                    'type' => 'activity',
                    'classId' => $classId,
                    'activityId' => $activityId,
                    'gameType' => $gameType,
                ]);

            Firebase::messaging()->send($message);

            $notificationSent = true;
        } catch (Throwable $e) {
            Log::error('FCM activity notification failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'activityId' => $activityId,
            'status' => 'published',
            'notificationSent' => $notificationSent,
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


    public function destroy(Request $request, string $activityId): JsonResponse
    {
        $validated = $request->validate([
            'classId' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_-]{1,128}$/',
            ],
        ]);

        if (! Str::isUuid($activityId)) {
            return response()->json([
                'message' => 'Invalid activity ID.',
            ], 422);
        }

        $idToken = $request->bearerToken();

        if (! $idToken) {
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

        if (! is_string($projectId) || $projectId === '') {
            return response()->json([
                'message' => 'Firebase is not configured.',
            ], 503);
        }

        $classId = $validated['classId'];
        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $activityPath = "{$classPath}/activities/{$activityId}";
        $publishedPath = "{$classPath}/publishedActivities/{$activityId}";
        $url = 'https://firestore.googleapis.com/v1/';

        try {
            // Verify class ownership using the teacher's Firebase token.
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get($url . $classPath);

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
                    'message' => 'Only the class teacher can delete drafts.',
                ], 403);
            }

            // Authenticate server-side Firestore operations.
            $credentialsPath = config(
                'firebase.projects.app.credentials'
            );

            if (! is_string($credentialsPath) || $credentialsPath === '') {
                throw new \RuntimeException(
                    'Firebase credentials are missing.'
                );
            }

            $resolvedPath = str_starts_with($credentialsPath, '/')
                || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $accessToken = $credentials->fetchAuthToken()['access_token'] ?? null;

            if (! $accessToken) {
                throw new \RuntimeException(
                    'Google OAuth token is missing.'
                );
            }

            // Read the authoritative activity record.
            $activityResponse = Http::withToken($accessToken)
                ->timeout(15)
                ->get($url . $activityPath);

            if ($activityResponse->status() === 404) {
                return response()->json([
                    'message' => 'Activity not found.',
                ], 404);
            }

            if (! $activityResponse->successful()) {
                throw new \RuntimeException(
                    'Could not verify activity.'
                );
            }

            $fields = $activityResponse->json('fields', []);

            if (($fields['teacherId']['stringValue'] ?? null) !== $uid) {
                return response()->json([
                    'message' => 'You do not own this activity.',
                ], 403);
            }

            if (($fields['status']['stringValue'] ?? null) !== 'draft') {
                return response()->json([
                    'message' => 'Published activities cannot be deleted.',
                ], 403);
            }

            // Defense-in-depth: reject if published metadata exists.
            $publishedResponse = Http::withToken($accessToken)
                ->timeout(15)
                ->get($url . $publishedPath);

            if ($publishedResponse->successful()) {
                return response()->json([
                    'message' => 'Published activities cannot be deleted.',
                ], 403);
            }

            if ($publishedResponse->status() !== 404) {
                throw new \RuntimeException(
                    'Could not verify publication state.'
                );
            }

            $updateTime = $activityResponse->json('updateTime');

            if (! is_string($updateTime) || $updateTime === '') {
                throw new \RuntimeException(
                    'Activity version is unavailable.'
                );
            }

            // Delete only if the draft has not changed since verification.
            $commit = Http::withToken($accessToken)
                ->timeout(30)
                ->post($url . $base . ':commit', [
                    'writes' => [
                        [
                            'delete' => $activityPath,
                            'currentDocument' => [
                                'updateTime' => $updateTime,
                            ],
                        ],
                    ],
                ]);

            if ($commit->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Draft activity deleted successfully.',
                ]);
            }

            if (in_array(
                $commit->json('error.status'),
                ['FAILED_PRECONDITION', 'ABORTED'],
                true
            )) {
                return response()->json([
                    'message' => 'The activity changed. Refresh and try again.',
                ], 409);
            }

            Log::error('Draft activity deletion rejected', [
                'classId' => $classId,
                'activityId' => $activityId,
                'status' => $commit->status(),
                'body' => $commit->body(),
            ]);

            throw new \RuntimeException(
                'Firestore draft deletion failed.'
            );
        } catch (Throwable $e) {
            Log::error('Draft activity deletion failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not delete draft. Please retry.',
            ], 503);
        }
    }


    public function uploadSource(Request $request): JsonResponse
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
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,docx,txt',
            ],
        ]);

        $idToken = $request->bearerToken();

        if (! $idToken) {
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

        try {
            // Verify ownership of the class.
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$classPath}"
                );

            if (! $classResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify the class.',
                ], $classResponse->status() === 404 ? 404 : 503);
            }

            if (
                $classResponse->json('fields.teacherId.stringValue')
                !== $uid
            ) {
                return response()->json([
                    'message' => 'Only the class teacher can upload files.',
                ], 403);
            }

            // Verify ownership and status of the activity.
            $activityResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$activityPath}"
                );

            if (! $activityResponse->successful()) {
                return response()->json([
                    'message' => 'Could not retrieve the activity.',
                ], $activityResponse->status() === 404 ? 404 : 503);
            }

            $fields = $activityResponse->json('fields', []);

            if (
                ($fields['teacherId']['stringValue'] ?? null)
                !== $uid
            ) {
                return response()->json([
                    'message' => 'You do not own this activity.',
                ], 403);
            }

            if (
                ($fields['status']['stringValue'] ?? null)
                !== 'draft'
            ) {
                return response()->json([
                    'message' => 'Published activities cannot be modified.',
                ], 403);
            }

            if (
                ($fields['generationMethod']['stringValue'] ?? null)
                !== 'file'
            ) {
                return response()->json([
                    'message' => 'This activity is not file-based.',
                ], 422);
            }

            if (isset($fields['sourceFile'])) {
                return response()->json([
                    'message' => 'A source file is already attached.',
                ], 409);
            }

            if (isset($fields['questions'])) {
                return response()->json([
                    'message' => 'Questions have already been saved.',
                ], 409);
            }


            $updateTime = $activityResponse->json('updateTime');

            if (! is_string($updateTime) || $updateTime === '') {
                return response()->json([
                    'message' => 'Activity version could not be verified.',
                ], 503);
            }

            $file = $request->file('file');

            $extension = strtolower(
                $file->getClientOriginalExtension()
            );

            $mimeTypes = [
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'txt' => 'text/plain',
            ];

            if (! isset($mimeTypes[$extension])) {
                return response()->json([
                    'message' => 'Unsupported source file type.',
                ], 422);
            }

            $publicId = "duolarp/classes/{$classId}/activity-sources/{$activityId}/"
                . (string) Str::uuid();
            try {
                $upload = Cloudinary::uploadApi()->upload(
                    $file->getRealPath(),
                    [
                        'public_id' => $publicId,
                        'resource_type' => 'raw',
                        'type' => 'authenticated',
                        'overwrite' => false,
                    ]
                );
            } catch (Throwable $e) {
                Log::error('Activity source Cloudinary upload failed', [
                    'classId' => $classId,
                    'activityId' => $activityId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Could not upload source document.',
                ], 502);
            }

            $storedPublicId = $upload['public_id'] ?? null;

            if (! is_string($storedPublicId) || $storedPublicId === '') {
                return response()->json([
                    'message' => 'Cloudinary did not return an asset ID.',
                ], 502);
            }

            $sourceFile = [
                'publicId' => $storedPublicId,
                'resourceType' => 'raw',
                'deliveryType' => 'authenticated',
                'fileName' => basename($file->getClientOriginalName()),
                'mimeType' => $mimeTypes[$extension],
                'bytes' => (int) ($upload['bytes'] ?? $file->getSize()),
            ];

            $accessToken = null;

            try {
                $credentialsPath = config(
                    'firebase.projects.app.credentials'
                );

                if (! is_string($credentialsPath) || $credentialsPath === '') {
                    throw new \RuntimeException(
                        'Firebase credentials are missing.'
                    );
                }

                $resolvedPath = str_starts_with($credentialsPath, '/')
                    || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                    ? $credentialsPath
                    : base_path($credentialsPath);

                $credentials = new ServiceAccountCredentials(
                    ['https://www.googleapis.com/auth/datastore'],
                    $resolvedPath
                );

                $accessToken = $credentials
                    ->fetchAuthToken()['access_token'] ?? null;

                if (! $accessToken) {
                    throw new \RuntimeException(
                        'Google OAuth token is missing.'
                    );
                }

                $response = Http::withToken($accessToken)
                    ->timeout(30)
                    ->post(
                        "https://firestore.googleapis.com/v1/{$base}:commit",
                        [
                            'writes' => [
                                [
                                    'update' => [
                                        'name' => $activityPath,
                                        'fields' => [
                                            'sourceFile' =>
                                            $this->toFirestoreValue($sourceFile),
                                        ],
                                    ],
                                    'updateMask' => [
                                        'fieldPaths' => ['sourceFile'],
                                    ],
                                    'currentDocument' => [
                                        'updateTime' => $updateTime,
                                    ],
                                ],
                            ],
                        ]
                    );

                if (! $response->successful()) {
                    throw new \RuntimeException(
                        'Firestore source metadata update failed: ' .
                            $response->status()
                    );
                }

                return response()->json([
                    'success' => true,
                    'activityId' => $activityId,
                    'fileName' => $sourceFile['fileName'],
                    'message' => 'Activity source uploaded successfully.',
                ], 201);
            } catch (Throwable $e) {
                Log::error('Activity source metadata save failed', [
                    'classId' => $classId,
                    'activityId' => $activityId,
                    'publicId' => $storedPublicId,
                    'error' => $e->getMessage(),
                ]);

                // A timeout does not prove that Firestore rejected the write.
                // Re-read the activity before deciding whether cleanup is safe.

                try {
                    if (! $accessToken) {
                        throw new \RuntimeException(
                            'Firestore authentication unavailable for reconciliation.'
                        );
                    }

                    $checkResponse = Http::withToken($accessToken)

                        ->timeout(15)
                        ->get(
                            "https://firestore.googleapis.com/v1/{$activityPath}"
                        );

                    if ($checkResponse->successful()) {
                        $savedPublicId = $checkResponse->json(
                            'fields.sourceFile.mapValue.fields.publicId.stringValue'
                        );

                        if ($savedPublicId === $storedPublicId) {
                            return response()->json([
                                'success' => true,
                                'activityId' => $activityId,
                                'fileName' => $sourceFile['fileName'],
                                'message' => 'Activity source uploaded successfully.',
                            ], 201);
                        }

                        // Another asset is referenced by Firestore.
                        // The current upload is unreferenced and may be removed.
                        if (
                            $savedPublicId === null ||
                            $savedPublicId !== $storedPublicId
                        ) {
                            try {
                                Cloudinary::uploadApi()->destroy(
                                    $storedPublicId,
                                    [
                                        'resource_type' => 'raw',
                                        'type' => 'authenticated',
                                    ]
                                );
                            } catch (Throwable $cleanupError) {
                                Log::error('Activity source cleanup failed', [
                                    'activityId' => $activityId,
                                    'publicId' => $storedPublicId,
                                    'error' => $cleanupError->getMessage(),
                                ]);
                            }
                        }
                    }
                } catch (Throwable $checkError) {
                    // Leave the asset in Cloudinary when Firestore cannot be checked.
                    // This avoids deleting a potentially referenced document.
                    Log::warning('Activity source state is uncertain', [
                        'activityId' => $activityId,
                        'publicId' => $storedPublicId,
                        'error' => $checkError->getMessage(),
                    ]);
                }

                return response()->json([
                    'message' =>
                    'Could not confirm the source upload. Refresh the activity before retrying.',
                ], 503);
            }
        } catch (Throwable $e) {
            Log::error('Activity source upload verification failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not verify upload permissions.',
            ], 503);
        }
    }
}
