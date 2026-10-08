<?php

namespace LeonardoMax\NewHere;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Something that shipped and deserves to be pointed at.
 */
final readonly class Feature
{
    public function __construct(
        public string $key,
        public CarbonImmutable $since,
        public ?string $title = null,
        public ?string $hint = null,
        public ?CarbonImmutable $until = null,
    ) {}

    public static function make(
        string $key,
        string | DateTimeInterface $since,
        ?string $title = null,
        ?string $hint = null,
        string | DateTimeInterface | null $until = null,
    ): self {
        return new self(
            key: $key,
            since: CarbonImmutable::parse($since)->startOfDay(),
            title: filled($title) ? $title : null,
            hint: filled($hint) ? $hint : null,
            until: $until === null ? null : CarbonImmutable::parse($until)->endOfDay(),
        );
    }

    /**
     * A feature is announced from its release day until it expires. Before the
     * release day nothing is shown, so `->isNew()` can be merged ahead of time.
     */
    public function isActive(int $expiresAfterDays, ?DateTimeInterface $now = null): bool
    {
        $now = CarbonImmutable::instance($now ?? now());

        if ($now->lt($this->since)) {
            return false;
        }

        return $now->lte($this->expiresAt($expiresAfterDays));
    }

    public function expiresAt(int $expiresAfterDays): CarbonImmutable
    {
        return $this->until ?? $this->since->addDays($expiresAfterDays)->endOfDay();
    }

    /**
     * @return array{key: string, since: string, title: ?string, hint: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'since' => $this->since->toDateString(),
            'title' => $this->title,
            'hint' => $this->hint,
        ];
    }
}
