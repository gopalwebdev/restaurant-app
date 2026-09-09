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
                Section::make('Name')
                    ->description('Both languages are edited together. English is required; a guest reading in Tamil sees the English name wherever the Tamil one is blank.')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->schema(TranslatedFields::text(
                        'name',
                        'Name',
                        maxLength: 64,
                        // Scoped to the tenant by Filament's global scope, so
                        // two restaurants may both have a "Dinner" and one
                        // restaurant may not have it twice.
                        uniqueWithin: fn (): Builder => Menu::query(),
                        uniqueMessage: 'This restaurant already has a menu with that name.',
                    ))
                    ->columns(2),

                Section::make('Description')
                    ->description('Optional. A line about what this menu is — "Served 12pm to 3pm", say.')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema(TranslatedFields::textarea('description', 'Description', maxLength: 300, rows: 2))
                    ->columns(2)
                    ->collapsed(),

                Section::make('On the storefront')
                    ->icon(Heroicon::OutlinedEye)
                    ->schema([
                        TextInput::make('position')
                            ->label('Order')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->required()
                            ->prefixIcon(Heroicon::OutlinedBars3BottomLeft)
                            ->helperText('Lower numbers come first. Ties fall back to the name.'),

                        Toggle::make('is_active')
                            ->label('Showing to guests')
                            ->default(true)
                            ->helperText('Turn this off to take the whole menu down — its sections and dishes with it — without deleting anything. Home screen tiles pointing at it stop being shown too.'),
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
