<?php

use Filament\Facades\Filament;
use LeonardoMax\NewHere\Livewire\Tracker;
use LeonardoMax\NewHere\NewHere;
use LeonardoMax\NewHere\NewHerePlugin;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(user());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('follows the app locale, resolving close variants', function (string $appLocale, string $expected): void {
    app()->setLocale($appLocale);

    expect(app(NewHere::class)->locale())->toBe($expected);
})->with([
    'english' => ['en', 'en'],
    'brazilian portuguese' => ['pt_BR', 'pt_BR'],
    'with a hyphen' => ['pt-BR', 'pt_BR'],
    'lower case region' => ['pt_br', 'pt_BR'],
    'portuguese only' => ['pt', 'pt_BR'],
    'spanish' => ['es', 'es'],
    'regional spanish' => ['es_AR', 'es'],
    'unknown' => ['ja', 'en'],
]);

it('translates the buttons', function (string $locale, string $gotIt, string $next): void {
    app()->setLocale($locale);

    $config = livewire(Tracker::class)->viewData('config');

    expect($config['locale'])->toBe($locale)
        ->and($config['labels']['gotIt'])->toBe($gotIt)
        ->and($config['labels']['next'])->toBe($next);
})->with([
    ['en', 'Got it', 'Next'],
    ['pt_BR', 'Entendi', 'Próxima'],
    ['es', 'Entendido', 'Siguiente'],
]);

it('lets the config force a language', function (): void {
    app()->setLocale('en');
    config()->set('new-here.locale', 'es');

    expect(livewire(Tracker::class)->viewData('config')['labels']['gotIt'])->toBe('Entendido');
});

it('lets the plugin force a language, also per user', function (): void {
    app()->setLocale('en');
    NewHerePlugin::get()->locale(fn (): string => 'pt-BR');

    expect(livewire(Tracker::class)->viewData('config')['labels']['gotIt'])->toBe('Entendi');

    NewHerePlugin::get()->locale(null);
});

it('blocks the page by default and can be turned off', function (): void {
    expect(livewire(Tracker::class)->viewData('config')['backdrop'])->toBeTrue();

    config()->set('new-here.backdrop', false);

    expect(livewire(Tracker::class)->viewData('config')['backdrop'])->toBeFalse();

    NewHerePlugin::get()->backdrop();

    expect(livewire(Tracker::class)->viewData('config')['backdrop'])->toBeTrue();
});
