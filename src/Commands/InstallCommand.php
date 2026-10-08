<?php

namespace LeonardoMax\NewHere\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;

/**
 * One command after `composer require`: creates the table and teaches the
 * project's AI agents to announce what they ship.
 */
class InstallCommand extends Command
{
    public const BLOCK_START = '<!-- new-here:start -->';

    public const BLOCK_END = '<!-- new-here:end -->';

    protected $signature = 'new-here:install
        {--no-migrate : Do not run the migration}
        {--no-ai : Do not touch AI agent guidelines or skills}';

    protected $description = 'Install New Here: create its table and teach your AI agents to announce new features';

    public function handle(): int
    {
        $this->components->info('Installing New Here.');

        if (! $this->option('no-migrate')) {
            $this->migrate();
        }

        // Filament publishes assets on `composer update` only when the app kept
        // the `filament:upgrade` script. Without them, nothing shows.
        $this->components->task('Publishing assets', fn (): bool => $this->callSilently('filament:assets') === self::SUCCESS);

        if (! $this->option('no-ai')) {
            $this->installAiGuidance();
        }

        $this->newLine();
        $this->components->bulletList([
            'The plugin is already active on every Filament panel.',
            'Mark what you ship: <fg=yellow>->isNew(\'' . now()->toDateString() . '\', \'What it does for the user.\')</>',
            'See every marker: <fg=yellow>php artisan new-here:list</>',
        ]);

        return self::SUCCESS;
    }

    protected function migrate(): void
    {
        if (! $this->confirmed('Create the new_here_seen table now (php artisan migrate)?')) {
            return;
        }

        $this->call('migrate', ['--force' => true]);
    }

    protected function installAiGuidance(): void
    {
        if ($this->hasBoost()) {
            $this->components->info('Laravel Boost found: New Here ships a Boost guideline and skill.');

            if ($this->confirmed('Run `php artisan boost:update` to load them now?')) {
                $this->call('boost:update', ['--discover' => true]);
            } else {
                $this->components->twoColumnDetail('Later', '<fg=yellow>php artisan boost:update</>');
            }

            return;
        }

        $guideline = $this->guideline();
        $written = [];

        foreach ($this->agentFiles() as $file) {
            $this->writeBlock(base_path($file), $guideline);
            $written[] = $file;
        }

        foreach ($this->skillFolders() as $folder) {
            File::ensureDirectoryExists(base_path("{$folder}/new-here"));
            File::copy($this->skillSource(), base_path("{$folder}/new-here/SKILL.md"));
            $written[] = "{$folder}/new-here/SKILL.md";
        }

        foreach ($written as $file) {
            $this->components->twoColumnDetail($file, '<fg=green>updated</>');
        }
    }

    /**
     * Agent instruction files that already exist. When none does, AGENTS.md
     * is created: it is read by Codex, Cursor, Copilot, Gemini and others.
     *
     * @return array<int, string>
     */
    protected function agentFiles(): array
    {
        $candidates = ['AGENTS.md', 'CLAUDE.md', 'GEMINI.md', '.github/copilot-instructions.md', '.junie/guidelines.md'];

        $existing = array_values(array_filter($candidates, fn (string $file): bool => File::exists(base_path($file))));

        return $existing === [] ? ['AGENTS.md'] : $existing;
    }

    /**
     * @return array<int, string>
     */
    protected function skillFolders(): array
    {
        $folders = [];

        if (File::isDirectory(base_path('.claude')) || File::exists(base_path('CLAUDE.md'))) {
            $folders[] = '.claude/skills';
        }

        if (File::isDirectory(base_path('.agents')) || File::exists(base_path('AGENTS.md'))) {
            $folders[] = '.agents/skills';
        }

        return $folders;
    }

    /**
     * Replaces the New Here block in place, so running the command again
     * updates the guideline instead of duplicating it.
     */
    protected function writeBlock(string $path, string $guideline): void
    {
        $block = self::BLOCK_START . "\n" . trim($guideline) . "\n" . self::BLOCK_END;
        $contents = File::exists($path) ? File::get($path) : '';

        $pattern = '/' . preg_quote(self::BLOCK_START, '/') . '.*?' . preg_quote(self::BLOCK_END, '/') . '/s';

        $contents = preg_match($pattern, $contents)
            ? (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $block), $contents)
            : rtrim($contents) . ($contents === '' ? '' : "\n\n") . $block . "\n";

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    /**
     * The Boost guideline, turned into plain Markdown.
     */
    public function guideline(): string
    {
        $guideline = File::get(__DIR__ . '/../../resources/boost/guidelines/core.blade.php');

        return str_replace(
            ['@verbatim', '@endverbatim', '<code-snippet name="Marking components and pages as new" lang="php">', '</code-snippet>'],
            ['', '', '```php', '```'],
            $guideline,
        );
    }

    protected function skillSource(): string
    {
        return __DIR__ . '/../../resources/boost/skills/new-here/SKILL.md';
    }

    protected function hasBoost(): bool
    {
        return array_key_exists('boost:update', Artisan::all());
    }

    protected function confirmed(string $question): bool
    {
        if (! $this->input->isInteractive()) {
            return true;
        }

        return confirm($question, default: true);
    }
}
