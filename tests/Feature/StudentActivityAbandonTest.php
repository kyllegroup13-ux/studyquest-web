<?php

test('abandon requires the attempt identifiers', function () {
    /** @var \Tests\TestCase $this */
    $this->postJson('/api/class/activities/abandon', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['classId', 'activityId', 'attemptId']);
});

test('abandon requires an authenticated student', function () {
    /** @var \Tests\TestCase $this */
    $this->postJson('/api/class/activities/abandon', [
        'classId' => 'class-1',
        'activityId' => '00000000-0000-4000-8000-000000000001',
        'attemptId' => '00000000-0000-4000-8000-000000000002',
    ])->assertUnauthorized();
});
