<?php

namespace App\Filament\Admin\Resources\MenuCategories\Schemas;

use App\Filament\Schemas\TranslatedFields;
use App\Models\Menu;
use App\Models\MenuCategory;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MenuCategoryForm
{
    /**
     * The columns this form edits in more than one language.
     *
     * @var list<string>
     */
    public const array TRANSLATED = ['name'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.categories.section'))
                    ->description(__('panel.shared.both_languages'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->schema([
                        // Only this restaurant's menus are offered, and the
                        // composite foreign key refuses anything else even if
                        // the submitted id is tampered with.
                        Select::make('menu_id')
                            ->label(__('panel.categories.menu'))
                            ->options(fn (): array => self::menuOptions())
                            ->default(fn (): ?int => self::onlyMenuKey())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->prefixIcon(Heroicon::OutlinedBookOpen)
                            ->columnSpanFull()
                            ->helperText(__('panel.categories.menu_help')),

                        ...TranslatedFields::text(
                            'name',
                            __('panel.shared.name'),
                            maxLength: 64,
                            // Unique within the menu rather than the restaurant:
                            // now that a restaurant can serve a lunch card and a
                            // dinner card, both are allowed a "Starters".
                            uniqueWithin: fn (Get $get): Builder => MenuCategory::query()
                                ->where('menu_id', $get('menu_id')),
                            uniqueMessage: __('panel.categories.unique'),
                        ),
                    ])
                    ->columns(2),

                Section::make(__('panel.categories.on_the_menu'))
                    ->icon(Heroicon::OutlinedEye)
                    ->schema([
                        TextInput::make('position')
                            ->label(__('panel.categories.position'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->required()
                            ->prefixIcon(Heroicon::OutlinedBars3BottomLeft)
                            ->helperText(__('panel.shared.order_help')),

                        Toggle::make('is_active')
                            ->label(__('panel.categories.is_active'))
                            ->default(true)
                            ->helperText(__('panel.categories.is_active_help')),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Put every language back into the form when a section is edited.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillTranslations(array $data, MenuCategory $record): array
    {
        return $record->fillTranslationsInto($data, ...self::TRANSLATED);
    }

    /**
     * This restaurant's menus, in the order it arranged them.
     *
     * Read as models rather than plucked, because the label is a translated
     * column: `pluck('name->en')` comes back keyed by the path itself, and
     * `$menu->name` is both simpler and already language-aware.
     *
     * @return array<int, string>
     */
    public static function menuOptions(): array
    {
        return Menu::query()
            ->where('restaurant_id', Filament::getTenant()?->getKey())
            ->inMenuOrder()
            ->get()
            ->mapWithKeys(fn (Menu $menu): array => [$menu->getKey() => $menu->name])
            ->all();
    }

    /**
     * The menu to preselect when there is only one to choose.
     *
     * Most restaurants have exactly one, and making them pick it every time is
     * a step that never has a second answer.
     */
    private static function onlyMenuKey(): ?int
    {
        $menus = self::menuOptions();

        return count($menus) === 1 ? array_key_first($menus) : null;
    }
}
