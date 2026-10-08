<?php

namespace LeonardoMax\NewHere\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LeonardoMax\NewHere\Feature;
use LeonardoMax\NewHere\NewHere;
use Symfony\Component\Finder\SplFileInfo;

class ListCommand extends Command
{
    protected $signature = 'new-here:list
        {--path=* : Folders to scan (defaults to app/)}
        {--expired : Only list markers that no longer show and can be removed}
        {--json : Print the result as JSON}';

    protected $description = 'List every ->isNew() and #[IsNew] marker in the code, with its status';

    /**
     * `->isNew('2026-10-08'` / `->isNew(since: '2026-10-08'` and `#[IsNew('2026-10-08'`.
     */
    protected const PATTERN = '/(?:->isNew|#\[\s*(?:\\\\?[\w\\\\]*\\\\)?IsNew)\(\s*(?:since:\s*)?[\'"](\d{4}-\d{2}-\d{2})[\'"]/';

    public function handle(NewHere $newHere): int
    {
        $paths = $this->option('path') ?: [app_path()];
        $days = $newHere->expiresAfterDays();
        $markers = [];

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                $this->components->warn("Skipping [{$path}]: not a folder.");

                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() === 'php') {
                    array_push($markers, ...$this->markersIn($file, $days));
                }
            }
        }

        if ($this->option('expired')) {
            $markers = array_values(array_filter($markers, fn (array $marker): bool => $marker['status'] === 'expired'));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($markers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($markers === []) {
            $this->components->info($this->option('expired') ? 'No expired markers. Nothing to clean up.' : 'No ->isNew() markers found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Status', 'Since', 'Expires', 'Location', 'Code'],
            array_map(fn (array $marker): array => [
                $marker['status'],
                $marker['since'],
                $marker['expires'],
                $marker['file'] . ':' . $marker['line'],
                $marker['code'],
            ], $markers),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{status: string, since: string, expires: string, file: string, line: int, code: string}>
     */
    protected function markersIn(SplFileInfo $file, int $days): array
    {
        $contents = $file->getContents();

        if (! preg_match_all(self::PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $markers = [];
        $today = CarbonImmutable::today();
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($matches[0] as $index => [$match, $offset]) {
            $since = $matches[1][$index][0];
            $feature = Feature::make('', $since, until: $this->untilOf($contents, $offset));
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;

            $markers[] = [
                'status' => match (true) {
                    $today->lt($feature->since) => 'scheduled',
                    $feature->isActive($days) => 'active',
                    default => 'expired',
                },
                'since' => $since,
                'expires' => $feature->expiresAt($days)->toDateString(),
                'file' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                'line' => $line,
                'code' => mb_strimwidth(trim($lines[$line - 1] ?? $match), 0, 90, '…'),
            ];
        }

        return $markers;
    }

    /**
     * `until:` in the same call, looked up until the next chained call or
     * statement end. Good enough for literal dates, which is what the
     * guidelines ask for.
     */
    protected function untilOf(string $contents, int $offset): ?string
    {
        $call = substr($contents, $offset + 2, 600);
        $call = preg_split('/->|#\[|;/', $call, 2)[0] ?? $call;

        return preg_match('/until:\s*[\'"](\d{4}-\d{2}-\d{2})[\'"]/', $call, $match) === 1 ? $match[1] : null;
    }
}
