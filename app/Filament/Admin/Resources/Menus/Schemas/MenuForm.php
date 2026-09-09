<?php

namespace App\Filament\Admin\Resources\Menus\Schemas;

use App\Filament\Schemas\TranslatedFields;
use App\Models\Menu;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MenuForm
{
    /**
     * The columns this form edits in more than one language.
     *
     * Named once here and read by every page that fills the form, so adding a
     * translated field to the schema cannot leave the fill behind.
     *
     * @var list<string>
     */
    public const array TRANSLATED = ['name', 'description'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.menus.name_section'))
                    ->description(__('panel.shared.both_languages'))
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->schema(TranslatedFields::text(
                        'name',
                        __('panel.shared.name'),
                        maxLength: 64,
                        // Scoped to the tenant by Filament's global scope, so
                        // two restaurants may both have a "Dinner" and one
                        // restaurant may not have it twice.
                        uniqueWithin: fn (): Builder => Menu::query(),
                        uniqueMessage: __('panel.menus.unique'),
                    ))
                    ->columns(2),

                Section::make(__('panel.menus.description_section'))
                    ->description(__('panel.menus.description_help'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema(TranslatedFields::textarea('description', __('panel.shared.description'), maxLength: 300, rows: 2))
                    ->columns(2)
                    ->collapsed(),

                Section::make(__('panel.menus.storefront_section'))
                    ->icon(Heroicon::OutlinedEye)
                    ->schema([
                        TextInput::make('position')
                            ->label(__('panel.shared.order'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->required()
                            ->prefixIcon(Heroicon::OutlinedBars3BottomLeft)
                            ->helperText(__('panel.shared.order_help')),

                        Toggle::make('is_active')
                            ->label(__('panel.menus.is_active'))
                            ->default(true)
                            ->helperText(__('panel.menus.is_active_help')),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Put every language back into the form when a menu is edited.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillTranslations(array $data, Menu $record): array
    {
        return $record->fillTranslationsInto($data, ...self::TRANSLATED);
    }
}
