<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class ClassPostController extends Controller
{
    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'fileName' => ['nullable', 'string', 'max:255'],
            'fileUrl' => ['nullable', 'url', 'max:2048'],
            'filePublicId' => ['nullable', 'string', 'max:512'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*.fileName' => ['required', 'string', 'max:255'],
            'attachments.*.fileUrl' => ['required', 'url', 'max:2048'],
            'attachments.*.filePublicId' => ['required', 'string', 'max:512'],
        ]);

        // Authenticate the teacher.
        $idToken = $request->bearerToken();

        if (! $idToken) {
            return response()->json([
                'message' => 'Please sign in again.',
            ], 401);
        }

        try {
            $verifiedToken = Firebase::auth()->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            Log::error('Firebase ID token verification failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Your session is invalid or expired.',
            ], 401);
        }

        $classId = $validated['classId'];
        $projectId = config('services.firebase.project_id');

        // Check class ownership with the authenticated user's token.
        $classUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/classes/{$classId}";

        try {
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get($classUrl);
        } catch (Throwable $e) {
            Log::error('Class lookup failed', [
                'classId' => $classId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not verify class ownership.',
            ], 503);
        }

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

        if ($classResponse->json('fields.teacherId.stringValue') !== $uid) {
            return response()->json([
                'message' => 'Only the class teacher can publish posts.',
            ], 403);
        }

        // Obtain a service-account OAuth token for Firestore REST.
        try {
            $credentialsPath = config('firebase.projects.app.credentials');

            $resolvedPath = str_starts_with($credentialsPath, '/')
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (! $accessToken) {
                throw new \RuntimeException('No Google access token returned.');
            }
        } catch (Throwable $e) {
            Log::error('Firestore authentication failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not connect to Firestore.',
            ], 503);
        }

        // Create the Firestore document.
        $fields = [];

        $attachments = $validated['attachments'] ?? [];
        $primaryAttachment = $attachments[0] ?? [];

        foreach (
            [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'authorId' => $uid,
                'authorName' => $classResponse->json('fields.teacherName.stringValue')
                    ?: 'Teacher',
                'fileName' => $primaryAttachment['fileName'] ?? $validated['fileName'] ?? '',
                'filePublicId' => $primaryAttachment['filePublicId'] ?? $validated['filePublicId'] ?? '',
                'fileUrl' => $primaryAttachment['fileUrl'] ?? $validated['fileUrl'] ?? '',
            ] as $key => $value
        ) {
            $fields[$key] = ['stringValue' => $value];
        }

        if ($attachments !== []) {
            $fields['attachments'] = [
                'arrayValue' => [
                    'values' => array_map(
                        static fn(array $attachment): array => [
                            'mapValue' => [
                                'fields' => [
                                    'fileName' => ['stringValue' => $attachment['fileName']],
                                    'fileUrl' => ['stringValue' => $attachment['fileUrl']],
                                    'filePublicId' => ['stringValue' => $attachment['filePublicId']],
                                ],
                            ],
                        ],
                        $attachments
                    ),
                ],
            ];
        }

        $fields['createdAt'] = [
            'timestampValue' => now('UTC')->toIso8601String(),
        ];

        // Generate a unique post ID.
        $postId = (string) \Illuminate\Support\Str::uuid();

        $databasePath = "projects/{$projectId}/databases/(default)/documents";
        $postPath = "{$databasePath}/classes/{$classId}/posts/{$postId}";
        $classPath = "{$databasePath}/classes/{$classId}";

        $commitUrl = "https://firestore.googleapis.com/v1/{$databasePath}:commit";

        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->post($commitUrl, [
                    'writes' => [
                        [
                            'update' => [
                                'name' => $postPath,
                                'fields' => $fields,
                            ],
                            'currentDocument' => [
                                'exists' => false,
                            ],
                        ],
                        [
                            'transform' => [
                                'document' => $classPath,
                                'fieldTransforms' => [
                                    [
                                        'fieldPath' => 'postCount',
                                        'increment' => [
                                            'integerValue' => '1',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Firestore post creation rejected', [
                    'classId' => $classId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Could not publish the announcement.',
                ], 503);
            }
        } catch (Throwable $e) {
            Log::error('Firestore post creation failed', [
                'classId' => $classId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not confirm announcement creation. Check the class posts before retrying.',
            ], 503);
        }

        // Notify the class after the post has been saved.
        $notificationSent = false;

        try {
            $message = CloudMessage::withTarget(
                'topic',
                'class_' . $classId
            )
                ->withNotification(
                    Notification::create(
                        'New Class Announcement',
                        $validated['title']
                    )
                )
                ->withData([
                    'type' => 'announcement',
                    'classId' => $classId,
                    'postId' => $postId,
                ]);

            Firebase::messaging()->send($message);
            $notificationSent = true;
        } catch (Throwable $e) {
            Log::error('FCM announcement notification failed', [
                'classId' => $classId,
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'postId' => $postId,
            'notificationSent' => $notificationSent,
            'message' => 'Announcement published successfully.',
        ], 201);
    }

    private function postContext(Request $request, string $postId): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $postId)) {
            abort(422, 'Invalid post ID.');
        }

        $classId = $request->input('classId');

        if (
            !is_string($classId) ||
            !preg_match('/^[A-Za-z0-9_-]{1,128}$/', $classId)
        ) {
            abort(422, 'Invalid class ID.');
        }

        $idToken = $request->bearerToken();

        if (!$idToken) {
            abort(401, 'Please sign in again.');
        }

        try {
            $verifiedToken = Firebase::auth()->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            abort(401, 'Your session is invalid or expired.');
        }

        $projectId = config('services.firebase.project_id');
        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $postPath = "{$classPath}/posts/{$postId}";

        $classResponse = Http::withToken($idToken)
            ->timeout(15)
            ->get("https://firestore.googleapis.com/v1/{$classPath}");

        if ($classResponse->status() === 404) {
            abort(404, 'Class not found.');
        }

        if (!$classResponse->successful()) {
            abort(503, 'Could not verify class ownership.');
        }

        if ($classResponse->json('fields.teacherId.stringValue') !== $uid) {
            abort(403, 'Only the class teacher can manage posts.');
        }

        try {
            $credentialsPath = config('firebase.projects.app.credentials');

            $resolvedPath = str_starts_with($credentialsPath, '/')
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (!$accessToken) {
                throw new \RuntimeException('Missing access token.');
            }
        } catch (Throwable $e) {
            abort(503, 'Could not connect to Firestore.');
        }

        $postResponse = Http::withToken($accessToken)
            ->timeout(15)
            ->get("https://firestore.googleapis.com/v1/{$postPath}");

        if ($postResponse->status() === 404) {
            abort(404, 'Post not found.');
        }

        if (!$postResponse->successful()) {
            abort(503, 'Could not retrieve the post.');
        }

        if ($postResponse->json('fields.authorId.stringValue') !== $uid) {
            abort(403, 'You can only modify your own posts.');
        }

        return [
            'base' => $base,
            'classPath' => $classPath,
            'postPath' => $postPath,
            'accessToken' => $accessToken,
            'updateTime' => $postResponse->json('updateTime'),
        ];
    }

    public function update(Request $request, string $postId): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:200'],
            'attachments' => ['required', 'array'],
            'attachments.*.fileName' => ['required', 'string', 'max:255'],
            'attachments.*.fileUrl' => ['required', 'url', 'max:2048'],
            'attachments.*.filePublicId' => ['nullable', 'string', 'max:512'],
        ]);

        try {
            $context = $this->postContext($request, $postId);

            $attachments = $validated['attachments'];

            $primaryAttachment = $attachments[0] ?? [];

            $attachmentValues = array_map(
                static fn(array $attachment): array => [
                    'mapValue' => [
                        'fields' => [
                            'fileName' => [
                                'stringValue' => $attachment['fileName'],
                            ],
                            'fileUrl' => [
                                'stringValue' => $attachment['fileUrl'],
                            ],
                            'filePublicId' => [
                                'stringValue' => $attachment['filePublicId'] ?? '',
                            ],
                        ],
                    ],
                ],
                $attachments
            );

            $url = "https://firestore.googleapis.com/v1/"
                . $context['postPath'];

            $updateUrl = $url
                . '?updateMask.fieldPaths=title'
                . '&updateMask.fieldPaths=description'
                . '&updateMask.fieldPaths=attachments'
                . '&updateMask.fieldPaths=fileName'
                . '&updateMask.fieldPaths=fileUrl'
                . '&updateMask.fieldPaths=filePublicId'
                . '&currentDocument.updateTime='
                . urlencode($context['updateTime']);

            $response = Http::withToken($context['accessToken'])
                ->timeout(20)
                ->patch($updateUrl, [
                    'fields' => [
                        'title' => [
                            'stringValue' => $validated['title'],
                        ],
                        'description' => [
                            'stringValue' => $validated['description'] ?? '',
                        ],
                        'attachments' => [
                            'arrayValue' => [
                                'values' => $attachmentValues,
                            ],
                        ],
                        'fileName' => [
                            'stringValue' => $primaryAttachment['fileName'] ?? '',
                        ],
                        'fileUrl' => [
                            'stringValue' => $primaryAttachment['fileUrl'] ?? '',
                        ],
                        'filePublicId' => [
                            'stringValue' => $primaryAttachment['filePublicId'] ?? '',
                        ],
                    ],
                ]);

            // The update mask and version precondition are supplied
            // as query parameters to preserve other post fields.
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Post update failed', [
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not update the post.',
            ], 503);
        }

        if (!$response->successful()) {
            Log::error('Firestore post update rejected', [
                'postId' => $postId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'Could not update the post.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully.',
        ]);
    }

    public function destroy(Request $request, string $postId): JsonResponse
    {
        $request->validate([
            'classId' => ['required', 'string'],
        ]);

        try {
            $context = $this->postContext($request, $postId);

            $commitUrl = "https://firestore.googleapis.com/v1/"
                . $context['base'] . ':commit';

            $response = Http::withToken($context['accessToken'])
                ->timeout(20)
                ->post($commitUrl, [
                    'writes' => [
                        [
                            'delete' => $context['postPath'],
                            'currentDocument' => [
                                'updateTime' => $context['updateTime'],
                            ],
                        ],
                        [
                            'transform' => [
                                'document' => $context['classPath'],
                                'fieldTransforms' => [
                                    [
                                        'fieldPath' => 'postCount',
                                        'increment' => [
                                            'integerValue' => '-1',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('Post deletion rejected', [
                    'postId' => $postId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Could not delete the post. Refresh and retry.',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully.',
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Post deletion failed', [
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not confirm deletion. Refresh before retrying.',
            ], 503);
        }
    }
}
