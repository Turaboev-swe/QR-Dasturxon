<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Every table belongs to exactly one restaurant (hasMany, not a pivot) —
 * so this only ever creates/edits/deletes rows scoped to the parent
 * restaurant, no associate/dissociate (there's nothing to associate:
 * a table has no independent existence outside its restaurant).
 */
class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'tables';

    protected static ?string $title = 'Stollar';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kod')
                    ->helperText("QR start_param'dagi t{code} qismi, masalan \"5\".")
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Nomi')
                    ->helperText('Masalan "Stol 5" — bo\'sh bo\'lsa kod ko\'rsatiladi.')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Faol')
                    ->default(true)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Kod')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nomi')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
                TextColumn::make('assigned_waiter_name')
                    ->label('Hozir biriktirilgan ofitsiant')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
