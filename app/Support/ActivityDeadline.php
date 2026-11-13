<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class ActivityDeadline
{
    public static function hasPassed(mixed $deadline): bool
    {
        if ($deadline === null || $deadline === '') {
            return false;
        }

        if (! is_string($deadline)) {
            throw new \UnexpectedValueException(
                'Invalid activity deadline.'
            );
        }

        return CarbonImmutable::parse($deadline, 'UTC')
            ->lessThanOrEqualTo(CarbonImmutable::now('UTC'));
    }

    public static function rejection(): JsonResponse
    {
        return response()->json([
            'code' => 'ACTIVITY_DEADLINE_PASSED',
            'message' =>
                'The activity deadline has passed. ' .
                'Responses are no longer accepted.',
        ], 403);
    }
}
