<?php

namespace App\Filament\Admin\Resources\HomeRows\Schemas;

use App\Enums\HomeRowLayout;
use App\Filament\Schemas\TranslatedFields;
use App\Models\HomeRow;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class HomeRowForm
{
    /**
     * The columns this form edits in more than one language.
     *
     * @var list<string>
     */
    public const array TRANSLATED = ['title'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.rows.title_section'))
                    ->description(__('panel.rows.title_section_help'))
                    ->icon(Heroicon::OutlinedTag)
                    // Never required, in any language: a banner into the menu
                    // reads better with nothing over it.
                    ->schema(TranslatedFields::optionalText('title', __('panel.rows.title_field'), maxLength: 48))
                    ->columns(2),

                Section::make(__('panel.rows.layout'))
                    ->description(__('panel.rows.layout_help'))
                    ->icon(Heroicon::OutlinedViewColumns)
                    ->schema([
                        // A radio rather than a select: the layout is the whole
                        // decision on this page, and each option needs its line
                        // of explanation read before it is picked.
                        Radio::make('layout')
                            ->hiddenLabel()
                            ->options(HomeRowLayout::options())
                            ->descriptions(HomeRowLayout::descriptions())
                            ->default(HomeRowLayout::Banner->value)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('panel.rows.placement'))
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
                            ->helperText(__('panel.rows.position_help')),

                        Toggle::make('is_active')
                            ->label(__('panel.rows.is_active'))
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Put every language back into the form when a row is edited.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillTranslations(array $data, HomeRow $record): array
    {
        return $record->fillTranslationsInto($data, ...self::TRANSLATED);
    }
}
