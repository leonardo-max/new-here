<?php

namespace LeonardoMax\NewHere\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
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

        $seen = $newHere->seenKeys($user);

        if (in_array(NewHere::OPT_OUT_KEY, $seen, true)) {
            return [];
        }

        $plugin = NewHerePlugin::get();

        return [
            'seen' => $seen,
            'notBefore' => $newHere->notBeforeFor($user),
            'pages' => $newHere->pagesFor($panel),
            'maxPerPage' => $plugin?->getMaxPerPage() ?? (int) config('new-here.max_per_page', 3),
            'autoOpen' => $plugin?->opensFirstHintAutomatically() ?? true,
            'labels' => [
                'badge' => __('new-here::new-here.badge'),
                'gotIt' => __('new-here::new-here.got_it'),
                'next' => __('new-here::new-here.next'),
                'dismissAll' => __('new-here::new-here.dismiss_all'),
                'optOut' => __('new-here::new-here.opt_out'),
                'beacon' => __('new-here::new-here.beacon'),
                'counter' => __('new-here::new-here.counter'),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $keys
     */
    #[Renderless]
    public function markSeen(array $keys, NewHere $newHere): void
    {
        $user = Filament::auth()->user();

        if ($user !== null) {
            $newHere->markSeen($user, $keys);
        }
    }

    #[Renderless]
    public function optOut(NewHere $newHere): void
    {
        $user = Filament::auth()->user();

        if ($user !== null) {
            $newHere->optOut($user);
        }
    }

    public function render(): View
    {
        return view('new-here::livewire.tracker', [
            'config' => $this->config(app(NewHere::class)),
        ]);
    }
}
