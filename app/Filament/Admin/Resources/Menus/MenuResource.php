<?php

namespace App\Filament\Admin\Resources\Menus;

use App\Filament\Admin\Resources\Menus\Pages\EditMenu;
use App\Filament\Admin\Resources\Menus\Pages\ListMenus;
use App\Filament\Admin\Resources\Menus\RelationManagers\CategoriesRelationManager;
use App\Filament\Admin\Resources\Menus\RelationManagers\CombosRelationManager;
use App\Filament\Admin\Resources\Menus\RelationManagers\FeaturedItemsRelationManager;
use App\Filament\Admin\Resources\Menus\RelationManagers\SubCategoriesRelationManager;
use App\Filament\Admin\Resources\Menus\Schemas\MenuForm;
use App\Filament\Admin\Resources\Menus\Tables\MenusTable;
use App\Models\Menu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The menus this restaurant serves: Lunch, Dinner, Drinks.
 *
 * The top of the hierarchy the panel edits, and the one page a menu's whole
 * structure is arranged on: a menu holds sections, a section may be subdivided,
 * and both are dragged into order here, alongside the featured dishes and the
 * combos the menu opens with. Dishes themselves are their own page — there are
 * far more of them, and they are the thing edited daily.
 *
 * A restaurant that serves one card all day simply keeps one menu.
 *
 * Scoping is Filament's: the panel has a tenant, so every query here is limited
 * to the restaurant in the subdomain and new rows are stamped with it. Who may
 * use the page is MenuPolicy's business, through menu.view and menu.manage.
 */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * First in the group, because it is the level everything else hangs off.
     */
    protected static ?int $navigationSort = 5;

    protected static ?string $tenantOwnershipRelationshipName = 'restaurant';

    /**
     * Labels are methods rather than static properties because a property is
     * evaluated when the class loads, before the request has chosen a language.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('panel.navigation.menu');
    }

    public static function getModelLabel(): string
    {
        return __('panel.menus.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.menus.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return MenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenusTable::configure($table);
    }

    /**
     * A menu's whole shape hangs under its own page.
     *
     * In reading order rather than in any technical one: the sections a guest
     * scrolls, the subdivisions inside them, then the two rows the menu opens
     * with. Categories and sub-categories were a separate navigation item
     * before this and are not any more — a category only means something
     * inside a menu.
     */
    public static function getRelations(): array
    {
        return [
            CategoriesRelationManager::class,
            SubCategoriesRelationManager::class,
            FeaturedItemsRelationManager::class,
            CombosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
