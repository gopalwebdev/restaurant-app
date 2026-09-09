<?php

namespace App\Filament\Admin\Resources\MenuItems\Schemas;

use App\Enums\Currency;
use App\Enums\FoodType;
use App\Enums\Locale;
use App\Filament\Schemas\TranslatedFields;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MenuItemForm
{
    /**
     * The columns this form edits in more than one language.
     *
     * @var list<string>
     */
    public const array TRANSLATED = ['name', 'description'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TranslatedFields::localeSwitcher(),

                Section::make(__('panel.items.dish'))
                    ->description(__('panel.shared.both_languages'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->schema([
                        // Only this restaurant's sections are offered, and the
                        // composite foreign key refuses anything else even if
                        // the submitted id is tampered with.
                        Select::make('menu_category_id')
                            ->label(__('panel.items.section'))
                            ->options(fn (): array => self::sectionOptions())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->prefixIcon(Heroicon::OutlinedRectangleStack)
                            ->helperText(__('panel.items.section_help')),

                        ...self::spanningFull(TranslatedFields::text(
                            'name',
                            __('panel.shared.name'),
                            maxLength: 120,
                            // Unique within the section rather than the whole
                            // restaurant, matching the database index: a lunch
                            // and a dinner menu may both list a "Paneer Tikka".
                            uniqueWithin: fn (Get $get): Builder => MenuItem::query()
                                ->where('menu_category_id', $get('menu_category_id')),
                            uniqueMessage: __('panel.items.unique'),
                        )),

                        Select::make('food_type')
                            ->label(__('panel.items.food_type'))
                            ->options(FoodType::options())
                            ->required()
                            ->default(FoodType::Vegetarian->value)
                            ->native(false)
                            ->helperText(__('panel.items.food_type_help')),

                        ...self::spanningFull(TranslatedFields::textarea('description', __('panel.shared.description'), maxLength: 500, rows: 3)),
                    ])
                    ->columns(2),

                Section::make(__('panel.items.price_section'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->schema([
                        // Typed and shown in major units, stored as an integer
                        // count of minor units. App\Enums\Currency does the
                        // conversion, and this is the only place it happens for
                        // this form — so no float ever reaches the database.
                        TextInput::make('price')
                            ->label(__('panel.items.price'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999)
                            ->step(0.01)
                            ->prefix(fn (): string => self::currency()->symbol())
                            ->helperText(__('panel.items.price_help')),

                        Toggle::make('is_available')
                            ->label(__('panel.items.is_available'))
                            ->default(true)
                            ->inline(false)
                            ->helperText(__('panel.items.is_available_help')),

                        // Featuring puts a dish in the row above the sections
                        // on the guest's menu screen. The order those are read
                        // in is dragged on the menu's own page, not typed here.
                        Toggle::make('is_featured')
                            ->label(__('panel.items.is_featured'))
                            ->default(false)
                            ->inline(false)
                            ->helperText(__('panel.items.is_featured_help')),
                    ])
                    ->columns(3),

                Section::make(__('panel.additions.section'))
                    ->description(__('panel.additions.section_help'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->schema([
                        self::additions(),
                    ])
                    ->collapsed(fn (?MenuItem $record): bool => $record?->additions()->doesntExist() ?? true),
            ]);
    }

    /**
     * Make a set of translated inputs span the section they sit in.
     *
     * Only one language is on screen at a time now, so a translated field is a
     * single box — and a single box in a two-column grid would leave the other
     * half of the line empty.
     *
     * @param  list<TextInput|Textarea>  $fields
     * @return list<TextInput|Textarea>
     */
    private static function spanningFull(array $fields): array
    {
        return array_map(
            static fn (TextInput|Textarea $field): TextInput|Textarea => $field->columnSpanFull(),
            $fields,
        );
    }

    /**
     * The extras a dish can be ordered with.
     *
     * A repeater bound to the relationship, so additions are written in the
     * same save as the dish they belong to. The rule in .ai/rules/filament.md
     * against `->relationship()` is about Spatie roles and permissions, whose
     * cache is only flushed by syncRoles()/syncPermissions(); this is a plain
     * hasMany with no cache behind it, and the rule does not apply.
     */
    private static function additions(): Repeater
    {
        return Repeater::make('additions')
            ->relationship()
            ->hiddenLabel()
            ->schema([
                ...TranslatedFields::text('name', __('panel.additions.label'), maxLength: 64),

                TextInput::make('price')
                    ->label(__('panel.additions.price'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99999)
                    ->step(0.01)
                    ->default(0)
                    ->prefix(fn (): string => self::currency()->symbol())
                    ->helperText(__('panel.additions.price_help')),

                Toggle::make('is_available')
                    ->label(__('panel.additions.is_available'))
                    ->default(true),
            ])
            ->columns(2)
            ->orderColumn('position')
            // Most dishes have none, and a blank row waiting to be filled in
            // would make every save fail validation until it was deleted.
            ->defaultItems(0)
            ->addActionLabel(__('panel.additions.add'))
            ->itemLabel(fn (array $state): ?string => self::additionLabel($state))
            ->collapsible()
            // The repeater edits a major-unit price the same way the dish above
            // does, and each row is converted on its own way in and out.
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::storePrice($data))
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::storePrice($data))
            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::fillPrice($data));
    }

    /**
     * The heading shown on a collapsed addition row.
     *
     * @param  array<string, mixed>  $state
     */
    private static function additionLabel(array $state): ?string
    {
        $name = $state['name'] ?? null;

        if (! is_array($name)) {
            return null;
        }

        $label = $name[Locale::default()->value] ?? null;

        return is_string($label) && filled($label) ? $label : null;
    }

    /**
     * Put every language back into the form when a dish is edited.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillTranslations(array $data, MenuItem $record): array
    {
        return $record->fillTranslationsInto($data, ...self::TRANSLATED);
    }

    /**
     * Turn the typed major-unit price into the integer that gets stored.
     *
     * Both create and edit go through here, so the rounding happens exactly
     * once per save and no float is ever handed to the database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function storePrice(array $data): array
    {
        $data['price_minor_units'] = self::currency()->toMinorUnits($data['price'] ?? 0);

        unset($data['price']);

        return $data;
    }

    /**
     * Turn the stored integer back into the value the form edits.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillPrice(array $data): array
    {
        $data['price'] = self::currency()->toMajorUnits((int) ($data['price_minor_units'] ?? 0));

        return $data;
    }

    /**
     * The currency this restaurant prices in.
     */
    public static function currency(): Currency
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Restaurant
            ? $tenant->currency()
            : Currency::IndianRupee;
    }

    /**
     * This restaurant's sections, labelled with the menu they sit on.
     *
     * Two menus may each have a "Starters", so the menu has to be part of the
     * label or the select offers the same word twice.
     *
     * @return array<int, string>
     */
    public static function sectionOptions(): array
    {
        return MenuCategory::query()
            ->where('tenant_id', self::tenantKey())
            ->with('menu')
            ->inMenuOrder()
            ->get()
            // menu_id is not nullable and cascades, so a section always has a
            // menu — there is nothing to fall back to here.
            ->mapWithKeys(fn (MenuCategory $category): array => [
                $category->getKey() => sprintf('%s · %s', $category->menu->name, $category->name),
            ])
            ->all();
    }

    /**
     * The restaurant the panel is serving, if there is one.
     */
    private static function tenantKey(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Restaurant ? $tenant->getKey() : null;
    }
}
