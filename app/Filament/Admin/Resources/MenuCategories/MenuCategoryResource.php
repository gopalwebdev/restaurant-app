<?php

namespace App\Filament\Admin\Resources\MenuCategories;

use App\Filament\Admin\Resources\MenuCategories\Pages\ListMenuCategories;
use App\Filament\Admin\Resources\MenuCategories\Schemas\MenuCategoryForm;
use App\Filament\Admin\Resources\MenuCategories\Tables\MenuCategoriesTable;
use App\Models\MenuCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The sections of this restaurant's menu.
 *
 * Scoping is Filament's: the panel has a tenant, and naming the relationship
 * back to it limits every query here to the restaurant in the subdomain and
 * stamps new rows with it. Who may use the page is MenuCategoryPolicy's
 * business, through menu.view and menu.manage.
 *
 * Categories are few and edited together, so they are managed in modals on the
 * list page rather than on pages of their own.
 */
class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Menu';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'restaurant';

    public static function form(Schema $schema): Schema
    {
        return MenuCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenuCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuCategories::route('/'),
        ];
    }
}
