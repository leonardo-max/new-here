<?php

namespace LeonardoMax\NewHere;

use DateTimeInterface;
use Filament\Panel;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Components\Component;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use LeonardoMax\NewHere\Commands\InstallCommand;
use LeonardoMax\NewHere\Commands\ListCommand;
use LeonardoMax\NewHere\Commands\PruneCommand;
use LeonardoMax\NewHere\Livewire\Tracker;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NewHereServiceProvider extends PackageServiceProvider
{
    public static string $name = 'new-here';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_new_here_seen_table')
            ->runsMigrations()
            ->hasCommands([
                InstallCommand::class,
                ListCommand::class,
                PruneCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(NewHere::class);

        $this->registerOnPanels();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            AlpineComponent::make('new-here', __DIR__ . '/../resources/dist/new-here.js'),
            Css::make('new-here', __DIR__ . '/../resources/dist/new-here.css'),
        ], package: 'leonardo-max/new-here');

        Livewire::component('new-here-tracker', Tracker::class);

        $this->registerMacro();

        FilamentView::registerRenderHook(
            TablesRenderHook::HEADER_BEFORE,
            fn (): string => app(NewHere::class)->renderTableFilters(),
        );
    }

    /**
     * Installing the package is enough: every panel (or the ones listed in
     * `new-here.panels`) gets the plugin. Adding `NewHerePlugin::make()` by
     * hand still works and wins, so it can be customised.
     */
    protected function registerOnPanels(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panels = config('new-here.panels', '*');

            if ($panels === false || $panels === null || $panel->hasPlugin(NewHerePlugin::ID)) {
                return;
            }

            if ($panels !== '*' && ! in_array($panel->getId(), (array) $panels, true)) {
                return;
            }

            $panel->plugin(NewHerePlugin::make());
        });
    }

    protected function registerMacro(): void
    {
        /**
         * Marks any Filament component (action, column, field, entry, filter,
         * section, tab...) as new from `$since`.
         */
        Component::macro('isNew', function (
            string | DateTimeInterface $since,
            ?string $hint = null,
            ?string $title = null,
            ?string $key = null,
            string | DateTimeInterface | null $until = null,
        ): Component {
            // `$this` is the component: Filament binds macros to the instance.
            app(NewHere::class)->mark($this, $since, $hint, $title, $key, $until);

            return $this;
        });
    }
}
