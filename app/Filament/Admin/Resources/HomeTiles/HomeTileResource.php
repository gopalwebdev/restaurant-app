<?php

namespace App\Filament\Admin\Resources\HomeTiles;

use App\Filament\Admin\Resources\HomeTiles\Pages\ListHomeTiles;
use App\Filament\Admin\Resources\HomeTiles\Schemas\HomeTileForm;
use App\Filament\Admin\Resources\HomeTiles\Tables\HomeTilesTable;
use App\Models\HomeTile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The home screen a guest lands on after scanning a table's QR code.
 *
 * Each tile is a picture and a destination, and the order they are dragged into
 * here is the order they appear on the phone. It is its own navigation group
 * rather than sitting under Menu, because it is the shop window rather than the
 * menu itself — a tile may open a PDF that has nothing to do with food.
 *
 * Scoping is Filament's: the panel has a tenant, so every query here is limited
 * to the restaurant in the subdomain. Who may use the page is HomeTilePolicy's
 * business, through storefront.view and storefront.manage.
 */
class HomeTileResource extends Resource
{
    protected static ?string $model = HomeTile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $tenantOwnershipRelationshipName = 'restaurant';

    /**
     * Labels are methods rather than static properties because a property is
     * evaluated when the class loads, before the request has chosen a language.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('panel.navigation.storefront');
    }

    public static function getModelLabel(): string
    {
        return __('panel.tiles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.tiles.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return HomeTileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeTilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeTiles::route('/'),
        ];
    }
}
