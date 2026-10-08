<?php

use LeonardoMax\NewHere\Tests\Fixtures\User;
use LeonardoMax\NewHere\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function user(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'created_at' => '2026-01-01 00:00:00',
        ...$attributes,
    ]);
}
