<?php

namespace LeonardoMax\NewHere\Tests\Fixtures\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use LeonardoMax\NewHere\Tests\Fixtures\User;

class ProductsPage extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static ?string $slug = 'products';

    protected static ?string $title = 'Products';

    protected string $view = 'new-here-tests::products';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->isNew('2026-10-01', 'Export the list as a spreadsheet.'),
            Action::make('import'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query())
            ->columns([
                TextColumn::make('name')->isNew('2026-10-01', 'Customer name, right in the list.'),
                TextColumn::make('email'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(['admin' => 'Admin', 'staff' => 'Staff'])
                    ->isNew('2026-10-01', 'Filter by role.'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('duplicate')->isNew('2026-10-01', 'Duplicate in one click.'),
                ]),
            ]);
    }
}
