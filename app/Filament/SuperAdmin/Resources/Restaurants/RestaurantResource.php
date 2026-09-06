<?php

namespace App\Filament\SuperAdmin\Resources\Restaurants;

use App\Filament\SuperAdmin\Resources\Restaurants\Pages\CreateRestaurant;
use App\Filament\SuperAdmin\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\SuperAdmin\Resources\Restaurants\Pages\ListRestaurants;
use App\Filament\SuperAdmin\Resources\Restaurants\Schemas\RestaurantForm;
use App\Filament\SuperAdmin\Resources\Restaurants\Tables\RestaurantsTable;
use App\Models\Restaurant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RestaurantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RestaurantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestaurants::route('/'),
            'create' => CreateRestaurant::route('/create'),
            'edit' => EditRestaurant::route('/{record}/edit'),
        ];
    }
}
