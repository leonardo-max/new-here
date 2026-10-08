<?php

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Date;

beforeEach(fn () => Date::setTestNow('2026-10-08'));

it('adds the marker to an action', function (): void {
    $attributes = Action::make('export')
        ->label('Export')
        ->isNew('2026-10-01', 'Export as a spreadsheet.')
        ->getExtraAttributes();

    expect($attributes)
        ->toHaveKey('data-new-here', 'action.export')
        ->toHaveKey('data-new-here-since', '2026-10-01')
        ->toHaveKey('data-new-here-title', 'Export')
        ->toHaveKey('data-new-here-hint', 'Export as a spreadsheet.');
});

it('keeps attributes set before and after the marker', function (): void {
    $attributes = Action::make('export')
        ->extraAttributes(['data-before' => '1'])
        ->isNew('2026-10-01')
        ->extraAttributes(['data-after' => '1'], merge: true)
        ->getExtraAttributes();

    expect($attributes)->toHaveKeys(['data-before', 'data-new-here', 'data-after']);
});

it('renders nothing outside the announcement window', function (string $since): void {
    expect(Action::make('export')->isNew($since)->getExtraAttributes())->toBe([]);
})->with([
    'scheduled' => ['2026-10-09'],
    'expired' => ['2026-08-01'],
]);

it('escapes the texts, since Filament does not escape extra attributes', function (): void {
    $attributes = Action::make('x')->isNew('2026-10-01', '"><script>alert(1)</script>')->getExtraAttributes();

    expect($attributes['data-new-here-hint'])->not->toContain('<script>');
});

it('accepts a custom key, title and until', function (): void {
    $expired = Action::make('export')
        ->isNew('2026-10-01', key: 'export.v2', title: 'Export, now faster', until: '2026-10-07')
        ->getExtraAttributes();

    expect($expired)->toBe([]);

    $attributes = Action::make('export')
        ->isNew('2026-10-01', key: 'export.v2', title: 'Export, now faster')
        ->getExtraAttributes();

    expect($attributes)
        ->toHaveKey('data-new-here', 'export.v2')
        ->toHaveKey('data-new-here-title', 'Export, now faster');
});

it('marks an action group, which has no name', function (): void {
    $attributes = ActionGroup::make([])->label('More')->isNew('2026-10-01')->getExtraAttributes();

    expect($attributes)->toHaveKey('data-new-here', 'action-group.more');
});

it('marks the header of a column', function (): void {
    $column = TextColumn::make('tracking_code')->isNew('2026-10-01');

    expect($column->getExtraHeaderAttributes())->toHaveKey('data-new-here', 'column.tracking_code')
        ->and($column->getExtraAttributes())->not->toHaveKey('data-new-here');
});

it('marks the wrapper of a form field', function (): void {
    $field = TextInput::make('nickname')->isNew('2026-10-01');

    expect($field->getExtraFieldWrapperAttributes())->toHaveKey('data-new-here', 'field.nickname');
});

it('marks layout components', function (): void {
    $section = Section::make('Billing')->key('billing')->isNew('2026-10-01');

    expect($section->getExtraAttributes())->toHaveKey('data-new-here', 'section.billing');
});
