<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use LeonardoMax\NewHere\Commands\InstallCommand;

it('lists markers with their status', function (): void {
    Date::setTestNow('2026-10-08');

    Artisan::call('new-here:list', ['--path' => [__DIR__ . '/../Fixtures/markers'], '--json' => true]);

    $markers = json_decode(Artisan::output(), true);

    expect(array_column($markers, 'status'))->toBe(['active', 'expired', 'scheduled', 'active', 'active'])
        ->and(array_column($markers, 'line'))->toBe([5, 6, 8, 13, 16])
        ->and($markers[2]['since'])->toBe('2026-12-24');
});

it('lists only expired markers', function (): void {
    Date::setTestNow('2026-10-08');

    $this->artisan('new-here:list', ['--path' => [__DIR__ . '/../Fixtures/markers'], '--expired' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('2026-06-01')
        ->doesntExpectOutputToContain('2026-12-24');
});

describe('install', function (): void {
    $cleanUp = function (): void {
        foreach (['AGENTS.md', 'CLAUDE.md'] as $file) {
            File::delete(base_path($file));
        }

        File::deleteDirectory(base_path('.claude'));
        File::deleteDirectory(base_path('.agents'));
    };

    beforeEach($cleanUp);
    afterEach($cleanUp);

    it('writes the guideline once, even when run twice', function (): void {
        File::put(base_path('CLAUDE.md'), "# My project\n");

        $this->artisan('new-here:install', ['--no-migrate' => true])->assertSuccessful();
        $this->artisan('new-here:install', ['--no-migrate' => true])->assertSuccessful();

        $contents = File::get(base_path('CLAUDE.md'));

        expect($contents)->toStartWith('# My project')
            ->and(substr_count($contents, InstallCommand::BLOCK_START))->toBe(1)
            ->and($contents)->toContain('->isNew(')
            ->and($contents)->not->toContain('@verbatim')
            ->and(File::exists(base_path('.claude/skills/new-here/SKILL.md')))->toBeTrue()
            ->and(File::exists(base_path('AGENTS.md')))->toBeFalse();
    });

    it('creates AGENTS.md when the project has no agent file', function (): void {
        $this->artisan('new-here:install', ['--no-migrate' => true])->assertSuccessful();

        expect(File::get(base_path('AGENTS.md')))->toContain(InstallCommand::BLOCK_START)
            ->and(File::exists(base_path('.agents/skills/new-here/SKILL.md')))->toBeTrue();
    });

    it('can skip the AI part', function (): void {
        $this->artisan('new-here:install', ['--no-migrate' => true, '--no-ai' => true])->assertSuccessful();

        expect(File::exists(base_path('AGENTS.md')))->toBeFalse();
    });
});
