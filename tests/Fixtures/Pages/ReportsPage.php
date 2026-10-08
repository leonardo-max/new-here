<?php

namespace LeonardoMax\NewHere\Tests\Fixtures\Pages;

use Filament\Pages\Page;
use LeonardoMax\NewHere\Attributes\IsNew;

#[IsNew('2026-10-01', 'All reports in one place.')]
class ReportsPage extends Page
{
    protected static ?string $slug = 'reports';

    protected static ?string $navigationLabel = 'Reports';

    protected string $view = 'new-here-tests::empty';
}
