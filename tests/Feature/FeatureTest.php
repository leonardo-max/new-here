<?php

use Illuminate\Support\Facades\Date;
use LeonardoMax\NewHere\Feature;

it('is active from its release day until it expires', function (string $today, bool $expected): void {
    Date::setTestNow($today);

    expect(Feature::make('k', '2026-10-01')->isActive(30))->toBe($expected);
})->with([
    'the day before' => ['2026-09-30 23:59:59', false],
    'release day' => ['2026-10-01 00:00:00', true],
    'last day' => ['2026-10-31 23:59:59', true],
    'the day after expiry' => ['2026-11-01 00:00:00', false],
]);

it('lets `until` replace the default expiry', function (): void {
    $feature = Feature::make('k', '2026-10-01', until: '2026-10-05');

    Date::setTestNow('2026-10-05 12:00');
    expect($feature->isActive(30))->toBeTrue();

    Date::setTestNow('2026-10-06');
    expect($feature->isActive(30))->toBeFalse();
});

it('treats blank texts as missing', function (): void {
    $feature = Feature::make('k', '2026-10-01', title: '', hint: '');

    expect($feature->title)->toBeNull()
        ->and($feature->hint)->toBeNull();
});
