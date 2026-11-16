<?php

namespace App\Support;

class ActivityScoreRanking
{
    public static function build(array $students): array
    {
        // Only students with a completed attempt and a valid score.
        $rankings = array_values(array_filter(
            $students,
            fn($student) => ($student['completedAttempts'] ?? 0) > 0
                && is_numeric($student['highestScore'] ?? null)
        ));

        // Highest score first. Alphabetical order breaks display ties.
        usort($rankings, function ($a, $b) {
            $scoreComparison =
                (int) $b['highestScore'] <=>
                (int) $a['highestScore'];

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        // Competition ranking: 1, 1, 3.
        $previousScore = null;
        $rank = 0;

        foreach ($rankings as $index => &$student) {
            $score = (int) $student['highestScore'];

            if ($previousScore === null || $score !== $previousScore) {
                $rank = $index + 1;
            }

            $student['rank'] = $rank;
            $previousScore = $score;
        }

        unset($student);

        return $rankings;
    }
}
