<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LeonardoMax\NewHere\Livewire\Tracker;
use LeonardoMax\NewHere\Models\SeenFeature;
use LeonardoMax\NewHere\NewHere;
use LeonardoMax\NewHere\NewHerePlugin;
use LeonardoMax\NewHere\Tests\Fixtures\Pages\ProductsPage;
use LeonardoMax\NewHere\Tests\Fixtures\Pages\ReportsPage;

use function Pest\Livewire\livewire;

beforeEach(fn () => Date::setTestNow('2026-10-08'));

it('registers itself on every panel', function (): void {
    expect(Filament::getPanel('admin')->hasPlugin(NewHerePlugin::ID))->toBeTrue();
});

it('can be limited to some panels or switched off', function (mixed $panels, string $id, bool $expected): void {
    config()->set('new-here.panels', $panels);

    expect(Panel::make()->id($id)->hasPlugin(NewHerePlugin::ID))->toBe($expected);
})->with([
    'switched off' => [false, 'one', false],
    'not listed' => [['admin'], 'two', false],
    'listed' => [['three'], 'three', true],
    'everywhere' => ['*', 'four', true],
]);

it('lets a hand-made instance replace the automatic one', function (): void {
    $plugin = NewHerePlugin::make()->maxPerPage(1);

    $panel = Panel::make()->id('custom')->plugin($plugin);

    expect($panel->getPlugin(NewHerePlugin::ID))->toBe($plugin);
});

it('renders the markers and the tracker on a panel page', function (): void {
    $this->actingAs(user());

    $this->get(ProductsPage::getUrl())
        ->assertSuccessful()
        ->assertSee('data-new-here="' . e(ProductsPage::class . '::action.export') . '"', escape: false)
        ->assertSee('data-new-here-filters=', escape: false)
        ->assertSee('newHere(', escape: false);
});

it('marks the header cell of the column', function (): void {
    $this->actingAs(user());

    livewire(ProductsPage::class)
        ->assertSeeHtml('data-new-here="' . e(ProductsPage::class . '::column.name') . '"');
});

it('describes marked filters on the table', function (): void {
    $this->actingAs(user());

    $html = livewire(ProductsPage::class)->html();

    preg_match('/data-new-here-filters="([^"]+)"/', $html, $matches);

    $filters = json_decode(html_entity_decode($matches[1] ?? ''), true);

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toMatchArray([
            'key' => ProductsPage::class . '::filter.role',
            'name' => 'role',
            'title' => 'Role',
            'hint' => 'Filter by role.',
            'since' => '2026-10-01',
        ]);
});

it('hands the tracker what the user saw, the new pages and the labels', function (): void {
    $user = user();
    $this->actingAs($user);
    app(NewHere::class)->markSeen($user, ['already-seen']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $config = livewire(Tracker::class)->viewData('config');

    expect($config['seen'])->toBe(['already-seen'])
        ->and($config['notBefore'])->toBe('2026-01-01')
        ->and($config['maxPerPage'])->toBe(3)
        ->and($config['labels']['gotIt'])->toBe('Got it')
        ->and(collect($config['pages'])->pluck('key')->all())->toBe(['page:' . ReportsPage::class])
        ->and($config['pages'][0])->toMatchArray([
            'title' => 'Reports',
            'hint' => 'All reports in one place.',
            'url' => ReportsPage::getUrl(),
        ]);
});

it('stores what the user dismissed', function (): void {
    $user = user();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(Tracker::class)->call('markSeen', ['a', 'b', 'a', '', str_repeat('x', 300)]);

    expect(app(NewHere::class)->seenKeys($user))->toEqualCanonicalizing(['a', 'b']);
});

it('stays silent for users who opted out', function (): void {
    $this->actingAs(user());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(Tracker::class)->call('optOut');

    expect(livewire(Tracker::class)->viewData('config'))->toBe([]);
});

it('does not give newcomers the past', function (): void {
    $this->actingAs(user(['created_at' => '2026-10-05 10:00:00']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(livewire(Tracker::class)->viewData('config')['notBefore'])->toBe('2026-10-05');

    config()->set('new-here.ignore_features_older_than_user', false);

    expect(livewire(Tracker::class)->viewData('config')['notBefore'])->toBeNull();
});

it('refuses keys beyond the per-user ceiling', function (): void {
    config()->set('new-here.max_seen_per_user', 3);
    $user = user();

    app(NewHere::class)->markSeen($user, ['a', 'b']);
    app(NewHere::class)->markSeen($user, ['c', 'd']);

    expect(app(NewHere::class)->seenKeys($user))->toEqualCanonicalizing(['a', 'b']);
});

it('prunes rows older than the retention window, but not opt-outs', function (): void {
    $user = user();
    $newHere = app(NewHere::class);

    Date::setTestNow('2025-01-01');
    $newHere->markSeen($user, ['old']);
    $newHere->optOut($user);

    Date::setTestNow('2026-10-08');
    $newHere->markSeen($user, ['recent']);

    $this->artisan('new-here:prune')->assertSuccessful();

    expect(SeenFeature::query()->pluck('feature_key')->all())
        ->toEqualCanonicalizing([NewHere::OPT_OUT_KEY, 'recent']);
});

it('only lets resources claim the URLs below them', function (): void {
    $this->actingAs(user());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(livewire(Tracker::class)->viewData('config')['pages'][0]['prefix'])->toBeFalse();
});

it('keeps the panel working when the migration has not run yet', function (): void {
    Schema::drop(config('new-here.table'));
    Log::spy();

    $this->actingAs(user());

    $this->get(ProductsPage::getUrl())
        ->assertSuccessful()
        ->assertDontSee('newHere(', escape: false);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    livewire(Tracker::class)->call('markSeen', ['a'])->assertOk();

    Log::shouldHaveReceived('warning')->once();
});

it('keeps seen rows per user', function (): void {
    $ada = user();
    $bob = user(['email' => 'bob@example.com']);

    app(NewHere::class)->markSeen($ada, ['x']);

    expect(app(NewHere::class)->seenKeys($bob))->toBe([]);

    app(NewHere::class)->forget($ada);

    expect(app(NewHere::class)->seenKeys($ada))->toBe([]);
});
