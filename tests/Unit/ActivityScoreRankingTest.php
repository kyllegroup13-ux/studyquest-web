<?php

use App\Support\ActivityScoreRanking;

test('students are ranked by highest score', function () {
    $students = [
        [
            'name' => 'Student A',
            'highestScore' => 80,
            'completedAttempts' => 1,
        ],
        [
            'name' => 'Student B',
            'highestScore' => 100,
            'completedAttempts' => 1,
        ],
        [
            'name' => 'Student C',
            'highestScore' => 90,
            'completedAttempts' => 1,
        ],
    ];

    $result = ActivityScoreRanking::build($students);

    expect($result[0]['name'])->toBe('Student B')
        ->and($result[0]['rank'])->toBe(1)
        ->and($result[1]['name'])->toBe('Student C')
        ->and($result[1]['rank'])->toBe(2)
        ->and($result[2]['name'])->toBe('Student A')
        ->and($result[2]['rank'])->toBe(3);
});

test('students with equal scores share the same rank', function () {
    $students = [
        [
            'name' => 'Student A',
            'highestScore' => 100,
            'completedAttempts' => 1,
        ],
        [
            'name' => 'Student B',
            'highestScore' => 90,
            'completedAttempts' => 1,
        ],
        [
            'name' => 'Student C',
            'highestScore' => 90,
            'completedAttempts' => 2,
        ],
        [
            'name' => 'Student D',
            'highestScore' => 80,
            'completedAttempts' => 1,
        ],
    ];

    $result = ActivityScoreRanking::build($students);

    expect(array_column($result, 'rank'))
        ->toBe([1, 2, 2, 4]);
});

test('students without completed scores are excluded', function () {
    $students = [
        [
            'name' => 'Student A',
            'highestScore' => 100,
            'completedAttempts' => 1,
        ],
        [
            'name' => 'Student B',
            'highestScore' => null,
            'completedAttempts' => 0,
        ],
        [
            'name' => 'Student C',
            'highestScore' => null,
            'completedAttempts' => 0,
        ],
    ];

    $result = ActivityScoreRanking::build($students);

    expect(count($result))->toBe(1)
        ->and($result[0]['name'])->toBe('Student A');
});

test('a valid zero score is still eligible', function () {
    $students = [
        [
            'name' => 'Student A',
            'highestScore' => 0,
            'completedAttempts' => 1,
        ],
    ];

    $result = ActivityScoreRanking::build($students);

    expect(count($result))->toBe(1)
        ->and($result[0]['rank'])->toBe(1);
});
