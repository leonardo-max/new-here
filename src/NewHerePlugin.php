<?php

namespace LeonardoMax\NewHere;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class NewHerePlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'new-here';

    protected bool | Closure $isEnabled = true;

    protected ?int $expiresAfterDays = null;

    protected ?int $maxPerPage = null;

    protected ?bool $ignoreFeaturesOlderThanUser = null;

    protected ?bool $opensFirstHintAutomatically = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): ?static
    {
        $panel = filament()->getCurrentPanel();

        if (! $panel?->hasPlugin(static::ID)) {
            return null;
        }

        /** @var static */
        return $panel->getPlugin(static::ID);
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        // The plugin may be added twice: once automatically (see the config
        // `panels` key) and once by hand to customise it. The render hook
        // reads the plugin at render time, so registering it once is enough.
        if ($panel->hasPlugin(static::ID)) {
            return;
        }

        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => static::get()?->isEnabled() ? Blade::render('@livewire(\'new-here-tracker\')') : '',
        );
    }

    public function boot(Panel $panel): void {}

    /**
     * Turn the highlights off, for everyone or for some users:
     * `->enabled(fn() => ! auth()->user()->isImpersonated())`.
     */
    public function enabled(bool | Closure $condition = true): static
    {
        $this->isEnabled = $condition;

        return $this;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->evaluate($this->isEnabled);
    }

    public function expiresAfterDays(int $days): static
    {
        $this->expiresAfterDays = $days;

        return $this;
    }

    public function getExpiresAfterDays(): int
    {
        return $this->expiresAfterDays ?? (int) config('new-here.expires_after_days', 30);
    }

    public function maxPerPage(int $max): static
    {
        $this->maxPerPage = $max;

        return $this;
    }

    public function getMaxPerPage(): int
    {
        return max(1, $this->maxPerPage ?? (int) config('new-here.max_per_page', 3));
    }

    public function ignoreFeaturesOlderThanUser(bool $condition = true): static
    {
        $this->ignoreFeaturesOlderThanUser = $condition;

        return $this;
    }

    public function shouldIgnoreFeaturesOlderThanUser(): bool
    {
        return $this->ignoreFeaturesOlderThanUser ?? (bool) config('new-here.ignore_features_older_than_user', true);
    }

    /**
     * Open the first hint on page load. When off, only the beacons pulse and
     * the hint opens on click.
     */
    public function openFirstHintAutomatically(bool $condition = true): static
    {
        $this->opensFirstHintAutomatically = $condition;

        return $this;
    }

    public function opensFirstHintAutomatically(): bool
    {
        return $this->opensFirstHintAutomatically ?? (bool) config('new-here.open_first_hint_automatically', true);
    }
}
