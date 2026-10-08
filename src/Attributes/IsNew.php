<?php

namespace LeonardoMax\NewHere\Attributes;

use Attribute;

/**
 * Marks a whole Filament page, resource or cluster as new.
 *
 * Its navigation item gets a beacon and, on the first visit, a hint is shown
 * next to the page heading.
 *
 *     #[IsNew('2026-10-08', 'Upload a PDF once and publish it to many classes.')]
 *     class DocumentResource extends Resource {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class IsNew
{
    public function __construct(
        public string $since,
        public ?string $hint = null,
        public ?string $title = null,
        public ?string $until = null,
        public ?string $key = null,
    ) {}
}
