<?php

namespace LeonardoMax\NewHere\Tests\Fixtures\Pages;

use Filament\Pages\Page;
use LeonardoMax\NewHere\Attributes\IsNew;

#[IsNew('2026-10-01', 'Nobody may open this one.')]
class SecretPage extends Page
{
    protected static ?string $slug = 'secret';

    protected string $view = 'new-here-tests::empty';

    public static function canAccess(): bool
    {
        return false;
    }
}
