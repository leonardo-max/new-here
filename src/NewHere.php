<?php

namespace LeonardoMax\NewHere;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Support\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LeonardoMax\NewHere\Attributes\IsNew;
use LeonardoMax\NewHere\Models\SeenFeature;
use Livewire\Livewire;
use ReflectionClass;
use Throwable;
use WeakMap;

/**
 * Turns `->isNew()` calls and `#[IsNew]` attributes into what the browser
 * needs, and remembers what each user has already seen.
 */
class NewHere
{
    /** Marker stored per user when they opt out of every hint. */
    public const OPT_OUT_KEY = '__new-here:opt-out';

    /** @var WeakMap<BaseFilter, Feature> */
    protected WeakMap $filters;

    /** @var array<class-string, ?IsNew> */
    protected array $classAttributes = [];

    public function __construct()
    {
        $this->filters = new WeakMap;
    }

    /**
     * Applies the `->isNew()` marker to any Filament component.
     */
    public function mark(
        Component $component,
        string | DateTimeInterface $since,
        ?string $hint = null,
        ?string $title = null,
        ?string $key = null,
        string | DateTimeInterface | null $until = null,
    ): void {
        if ($component instanceof BaseFilter) {
            $this->filters[$component] = Feature::make($key ?? '', $since, $title, $hint, $until);

            return;
        }

        $attributes = fn (): array => $this->attributesFor($component, $since, $hint, $title, $key, $until);

        match (true) {
            $component instanceof Column => $component->extraHeaderAttributes($attributes, merge: true),
            $component instanceof Field => $component->extraFieldWrapperAttributes($attributes, merge: true),
            $component instanceof Entry => $component->extraEntryWrapperAttributes($attributes, merge: true),
            method_exists($component, 'extraAttributes') => $component->extraAttributes($attributes, merge: true),
            default => throw new \InvalidArgumentException(sprintf(
                '[%s] cannot be marked as new: it does not render extra attributes.',
                $component::class,
            )),
        };
    }

    /**
     * The HTML attributes that tell the browser script what to highlight.
     * Empty outside the announcement window, so an old `->isNew()` costs nothing.
     *
     * @return array<string, string>
     */
    public function attributesFor(
        Component $component,
        string | DateTimeInterface $since,
        ?string $hint = null,
        ?string $title = null,
        ?string $key = null,
        string | DateTimeInterface | null $until = null,
    ): array {
        $feature = Feature::make(
            key: $key ?? $this->keyFor($component),
            since: $since,
            title: $title ?? $this->labelOf($component),
            hint: $hint,
            until: $until,
        );

        if (! $feature->isActive($this->expiresAfterDays())) {
            return [];
        }

        // Filament does not escape extra attributes, so we do.
        return array_filter([
            'data-new-here' => e($feature->key),
            'data-new-here-since' => $feature->since->toDateString(),
            'data-new-here-title' => $feature->title === null ? null : e($feature->title),
            'data-new-here-hint' => $feature->hint === null ? null : e($feature->hint),
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * Filters render no wrapper of their own, so the marked ones travel in a
     * hidden element inside the table and the browser script finds their
     * fields by name. Rendered from a table render hook rather than through
     * `$table->extraAttributes()`, which the app may overwrite.
     */
    public function renderTableFilters(): string
    {
        $livewire = Livewire::current();

        if (! $livewire instanceof HasTable) {
            return '';
        }

        try {
            $attributes = $this->tableAttributesFor($livewire->getTable());
        } catch (Throwable) {
            return '';
        }

        if ($attributes === []) {
            return '';
        }

        return '<div hidden data-new-here-filters="' . $attributes['data-new-here-filters'] . '"></div>';
    }

    /**
     * @return array<string, string>
     */
    public function tableAttributesFor(Table $table): array
    {
        $features = [];

        foreach ($table->getFilters() as $name => $filter) {
            $feature = $this->filters[$filter] ?? null;

            if ($feature === null || ! $feature->isActive($this->expiresAfterDays())) {
                continue;
            }

            $features[] = [
                ...$feature->toArray(),
                'key' => $feature->key !== '' ? $feature->key : $this->keyFor($filter, livewire: $table->getLivewire()),
                'title' => $feature->title ?? $this->labelOf($filter),
                'name' => (string) $name,
            ];
        }

        if ($features === []) {
            return [];
        }

        return ['data-new-here-filters' => e((string) json_encode($features))];
    }

    /**
     * A stable key: the Livewire component the element lives in, its type and
     * its name. Changing the name of a button makes it "new" again, which is
     * usually what you want; pass `key:` to opt out.
     */
    public function keyFor(Component $component, ?object $livewire = null): string
    {
        $livewire ??= $this->livewireOf($component);

        [$type, $name] = match (true) {
            $component instanceof Action => ['action', $component->getName()],
            // Groups have no name: their label (or icon) is all there is.
            $component instanceof ActionGroup => ['action-group', Str::slug((string) ($this->labelOf($component) ?? 'actions'))],
            $component instanceof Column => ['column', $component->getName()],
            $component instanceof BaseFilter => ['filter', $component->getName()],
            $component instanceof Field => ['field', $component->getName()],
            $component instanceof Entry => ['entry', $component->getName()],
            $component instanceof SchemaComponent => [
                Str::kebab(class_basename($component)),
                $component->getKey(isAbsolute: false) ?? Str::slug((string) $this->labelOf($component)),
            ],
            default => [Str::kebab(class_basename($component)), Str::slug((string) $this->labelOf($component))],
        };

        return ($livewire === null ? '' : $livewire::class . '::') . $type . '.' . $name;
    }

    /**
     * Pages, resources and clusters of the panel marked with `#[IsNew]`
     * that the current user can open.
     *
     * @return array<int, array{key: string, since: string, title: ?string, hint: ?string, url: string, prefix: bool}>
     */
    public function pagesFor(Panel $panel): array
    {
        $pages = [];

        $classes = [
            ...$panel->getResources(),
            ...$panel->getPages(),
            ...$panel->getClusters(),
        ];

        foreach (array_unique($classes) as $class) {
            $attribute = $this->classAttribute($class);

            if ($attribute === null) {
                continue;
            }

            $feature = Feature::make(
                key: $attribute->key ?? 'page:' . $class,
                since: $attribute->since,
                title: $attribute->title ?? $this->navigationLabelOf($class),
                hint: $attribute->hint,
                until: $attribute->until,
            );

            if (! $feature->isActive($this->expiresAfterDays())) {
                continue;
            }

            try {
                if (method_exists($class, 'canAccess') && ! $class::canAccess()) {
                    continue;
                }

                $url = method_exists($class, 'getUrl') ? $class::getUrl(panel: $panel->getId()) : null;
            } catch (Throwable) {
                continue;
            }

            if (blank($url)) {
                continue;
            }

            // A resource owns every URL below its index (create, edit...);
            // a plain page only its own.
            $pages[] = [...$feature->toArray(), 'url' => $url, 'prefix' => ! is_subclass_of($class, Page::class)];
        }

        return $pages;
    }

    /**
     * Keys this user already dismissed. Only rows recent enough to matter are
     * read: anything older belongs to a feature that has already expired.
     *
     * @return array<int, string>
     */
    public function seenKeys(Authenticatable $user): array
    {
        [$type, $id] = $this->identify($user);

        return SeenFeature::query()
            ->where('user_type', $type)
            ->where('user_id', $id)
            ->where(fn ($query) => $query
                ->where('seen_at', '>=', now()->subDays($this->maxAnnouncementDays()))
                ->orWhere('feature_key', self::OPT_OUT_KEY))
            ->pluck('feature_key')
            ->all();
    }

    /**
     * @param  array<int, mixed>  $keys  Straight from the browser: anything goes.
     */
    public function markSeen(Authenticatable $user, array $keys): void
    {
        [$type, $id] = $this->identify($user);
        $now = now();

        $rows = collect($keys)
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '' && strlen($key) <= 255)
            ->unique()
            ->take(100)
            ->map(fn (string $key): array => [
                'user_type' => $type,
                'user_id' => $id,
                'feature_key' => $key,
                'seen_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        // Keys come from the browser. A ceiling keeps a scripted client from
        // filling the table; real users dismiss a few hints a week.
        $stored = SeenFeature::query()->where('user_type', $type)->where('user_id', $id)->count();

        if ($stored + count($rows) > (int) config('new-here.max_seen_per_user', 5000)) {
            return;
        }

        SeenFeature::query()->upsert($rows, ['user_type', 'user_id', 'feature_key'], ['seen_at']);
    }

    /**
     * Deletes rows older than the retention window. They belong to features
     * that expired long ago. Scheduled by `new-here:prune`.
     */
    public function prune(): int
    {
        return SeenFeature::query()
            ->where('feature_key', '!=', self::OPT_OUT_KEY)
            ->where('seen_at', '<', now()->subDays($this->maxAnnouncementDays()))
            ->delete();
    }

    public function optOut(Authenticatable $user): void
    {
        $this->markSeen($user, [self::OPT_OUT_KEY]);
    }

    /**
     * Forget everything a user dismissed, e.g. to replay the hints.
     */
    public function forget(Authenticatable $user): void
    {
        [$type, $id] = $this->identify($user);

        SeenFeature::query()->where('user_type', $type)->where('user_id', $id)->delete();
    }

    public function hasOptedOut(Authenticatable $user): bool
    {
        return in_array(self::OPT_OUT_KEY, $this->seenKeys($user), true);
    }

    /**
     * Features released before this date are not shown to the user.
     */
    public function notBeforeFor(Authenticatable $user): ?string
    {
        $shouldIgnore = $this->plugin()?->shouldIgnoreFeaturesOlderThanUser()
            ?? (bool) config('new-here.ignore_features_older_than_user', true);

        if (! $shouldIgnore) {
            return null;
        }

        $createdAt = $user instanceof Model ? $user->getAttribute(config('new-here.user_created_at_attribute', 'created_at')) : null;

        if (blank($createdAt)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($createdAt)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    public function expiresAfterDays(): int
    {
        return $this->plugin()?->getExpiresAfterDays() ?? (int) config('new-here.expires_after_days', 30);
    }

    public function plugin(): ?NewHerePlugin
    {
        return NewHerePlugin::get();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function identify(Authenticatable $user): array
    {
        $type = $user instanceof Model ? $user->getMorphClass() : $user::class;

        return [$type, (string) $user->getAuthIdentifier()];
    }

    /**
     * Seen rows have to outlive the longest announcement. `until:` may push a
     * feature beyond the default window, so keep a generous margin.
     */
    protected function maxAnnouncementDays(): int
    {
        return max($this->expiresAfterDays(), (int) config('new-here.seen_retention_days', 400));
    }

    protected function livewireOf(Component $component): ?object
    {
        if (! method_exists($component, 'getLivewire')) {
            return null;
        }

        try {
            return $component->getLivewire();
        } catch (Throwable) {
            return null;
        }
    }

    protected function labelOf(Component $component): ?string
    {
        if (! method_exists($component, 'getLabel')) {
            return null;
        }

        try {
            $label = $component->getLabel();
        } catch (Throwable) {
            return null;
        }

        if ($label instanceof Htmlable) {
            $label = strip_tags($label->toHtml());
        }

        return filled($label) ? (string) $label : null;
    }

    /**
     * @param  class-string  $class
     */
    protected function navigationLabelOf(string $class): ?string
    {
        foreach (['getNavigationLabel', 'getTitle', 'getPluralModelLabel'] as $method) {
            if (! method_exists($class, $method)) {
                continue;
            }

            try {
                $label = $class::$method();
            } catch (Throwable) {
                continue;
            }

            if ($label instanceof Htmlable) {
                $label = strip_tags($label->toHtml());
            }

            if (filled($label)) {
                return (string) $label;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $class
     */
    protected function classAttribute(string $class): ?IsNew
    {
        if (array_key_exists($class, $this->classAttributes)) {
            return $this->classAttributes[$class];
        }

        if (! class_exists($class)) {
            return $this->classAttributes[$class] = null;
        }

        $attributes = (new ReflectionClass($class))->getAttributes(IsNew::class);

        return $this->classAttributes[$class] = ($attributes === [] ? null : $attributes[0]->newInstance());
    }
}
