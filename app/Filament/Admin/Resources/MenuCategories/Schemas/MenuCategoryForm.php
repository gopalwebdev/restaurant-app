<?php

namespace App\Filament\Admin\Resources\MenuCategories\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MenuCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(64)
                    ->prefixIcon(Heroicon::OutlinedRectangleStack)
                    // Scoped to the tenant: two restaurants may both have
                    // Starters, one restaurant may not have it twice.
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where(
                        'restaurant_id',
                        Filament::getTenant()?->getKey(),
                    ))
                    ->helperText('What guests see as a heading on the menu.'),

                TextInput::make('position')
                    ->label('Order on the menu')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(9999)
                    ->default(0)
                    ->required()
                    ->prefixIcon(Heroicon::OutlinedBars3BottomLeft)
                    ->helperText('Lower numbers come first. Ties fall back to the name.'),

                Toggle::make('is_active')
                    ->label('Showing on the menu')
                    ->default(true)
                    ->helperText('Turn this off to hide the whole section, and everything in it, without deleting anything.'),
            ]);
    }
}
