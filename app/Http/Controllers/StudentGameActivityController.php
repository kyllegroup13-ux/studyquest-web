<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class StudentGameActivityController extends Controller
{
    public function authorizeActivity(Request $request): JsonResponse
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

        // 1. Authenticate the student.
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

        // 2. Prepare Firestore document paths.
        $projectId = config('services.firebase.project_id');

        if (! is_string($projectId) || $projectId === '') {
            return response()->json([
                'message' => 'Firebase is not configured.',
            ], 503);
        }

        $classId = $validated['classId'];
        $activityId = $validated['activityId'];

        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $studentPath = "{$classPath}/students/{$uid}";
        $publishedPath =
            "{$classPath}/publishedActivities/{$activityId}";

        try {
            // 3. Verify class existence.
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
                    'message' => 'Could not verify class.',
                ], 503);
            }

            // 4. Verify student enrollment.
            $studentResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$studentPath}"
                );

            if ($studentResponse->status() === 404) {
                return response()->json([
                    'message' => 'You are not enrolled in this class.',
                ], 403);
            }

            if (! $studentResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify enrollment.',
                ], 503);
            }

            // 5. Retrieve student-visible published metadata.
            $activityResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get(
                    "https://firestore.googleapis.com/v1/{$publishedPath}"
                );

            if ($activityResponse->status() === 404) {
                return response()->json([
                    'message' => 'Published activity not found.',
                ], 404);
            }

            if (! $activityResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify activity.',
                ], 503);
            }

            $activity = $activityResponse->json('fields', []);

            if (
                ($activity['status']['stringValue'] ?? null)
                !== 'published'
            ) {
                return response()->json([
                    'message' => 'This activity is not available.',
                ], 403);
            }

            // 6. Return only approved metadata.
            return response()->json([
                'success' => true,
                'authorized' => true,
                'activityId' => $activityId,
                'classId' => $classId,
                'title' =>
                    $activity['title']['stringValue'] ?? '',
                'gameType' =>
                    $activity['gameType']['stringValue'] ?? '',
                'questionCount' => (int) (
                    $activity['questionCount']['integerValue'] ?? 0
                ),
                'maxAttempts' => (int) (
                    $activity['maxAttempts']['integerValue'] ?? 1
                ),
                'message' => 'Activity access authorized.',
            ]);
        } catch (Throwable $e) {
            Log::error('Student activity authorization failed', [
                'classId' => $classId,
                'activityId' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not verify activity access.',
            ], 503);
        }
    }
}

