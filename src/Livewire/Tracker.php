<?php

namespace LeonardoMax\NewHere\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LeonardoMax\NewHere\NewHere;
use LeonardoMax\NewHere\NewHerePlugin;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Rendered once per page at the end of the panel body. Hands the browser
 * script what the user already saw and stores what they dismiss.
 */
class Tracker extends Component
{
    /**
     * Built once per page and handed to the view, not kept as public state:
     * it would travel in every Livewire request otherwise.
     *
     * @return array<string, mixed>
     */
    public function config(NewHere $newHere): array
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();

        if ($user === null || $panel === null) {
            return [];
        }

        try {
            $seen = $newHere->seenKeys($user);
        } catch (QueryException $exception) {
            // Usually the migration has not run yet (fresh deploy, test
            // schema). The panel must keep working: no hints until it does.
            $this->warnOnce($exception);

            return [];
        }

        if (in_array(NewHere::OPT_OUT_KEY, $seen, true)) {
            return [];
        }

        $plugin = NewHerePlugin::get();
        $locale = $newHere->locale();

        return [
            'seen' => $seen,
            'notBefore' => $newHere->notBeforeFor($user),
            'pages' => $newHere->pagesFor($panel),
            'maxPerPage' => $plugin?->getMaxPerPage() ?? (int) config('new-here.max_per_page', 3),
            'autoOpen' => $plugin?->opensFirstHintAutomatically() ?? (bool) config('new-here.open_first_hint_automatically', true),
            'backdrop' => $plugin?->hasBackdrop() ?? (bool) config('new-here.backdrop', true),
            'locale' => $locale,
            'labels' => collect([
                'badge' => 'badge',
                'gotIt' => 'got_it',
                'next' => 'next',
                'dismissAll' => 'dismiss_all',
                'optOut' => 'opt_out',
                'beacon' => 'beacon',
                'counter' => 'counter',
            ])->map(fn (string $key): string => __("new-here::new-here.{$key}", [], $locale))->all(),
        ];
    }

    /**
     * @param  array<int, mixed>  $keys
     */
    #[Renderless]
    public function markSeen(array $keys, NewHere $newHere): void
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            return;
        }

        try {
            $newHere->markSeen($user, $keys);
        } catch (QueryException $exception) {
            $this->warnOnce($exception);
        }
    }

    #[Renderless]
    public function optOut(NewHere $newHere): void
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            return;
        }

        try {
            $newHere->optOut($user);
        } catch (QueryException $exception) {
            $this->warnOnce($exception);
        }
    }

    /**
     * One log line per hour is enough to notice the missing migration
     * without flooding the log on every page view.
     */
    protected function warnOnce(QueryException $exception): void
    {
        try {
            if (! Cache::add('new-here:query-failed', true, now()->addHour())) {
                return;
            }
        } catch (\Throwable) {
            // No cache available: log anyway.
        }

        Log::warning('[new-here] Hints are disabled: ' . $exception->getMessage() . ' Did you run `php artisan migrate`?');
    }

    public function render(): View
    {
        return view('new-here::livewire.tracker', [
            'config' => $this->config(app(NewHere::class)),
        ]);
    }
}
