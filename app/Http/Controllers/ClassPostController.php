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

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                base_path($credentialsPath)
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

        $postsUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/classes/{$classId}/posts";

        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->post($postsUrl, ['fields' => $fields]);

            if (! $response->successful()) {
                Log::error('Firestore post creation rejected', [
                    'classId' => $classId,
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'message' => 'Could not publish the announcement.',
                ], 503);
            }

            $postName = $response->json('name');
            $postId = basename($postName);

            if (! $postName || ! $postId) {
                throw new \RuntimeException('Firestore returned no post ID.');
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
}
