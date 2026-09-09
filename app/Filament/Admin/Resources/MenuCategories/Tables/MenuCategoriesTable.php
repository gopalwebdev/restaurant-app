<?php

namespace App\Filament\Admin\Resources\MenuCategories\Tables;

use App\Actions\Menus\MoveCategoryToMenu;
use App\Enums\Locale;
use App\Filament\Admin\Resources\MenuCategories\Schemas\MenuCategoryForm;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuCategory;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuCategoriesTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    // A translated column holds a JSON document, so searching
                    // and sorting have to name the language they mean.
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction)),

                TextColumn::make('menu.name')
                    ->label(__('panel.categories.menu'))
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('menu_items_count')
                    ->label(__('panel.categories.items_count'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->counts('menuItems')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.shared.showing'))
                    ->boolean()
                    ->sortable()
                    ->tooltip(__('panel.categories.showing_tooltip')),
            ])
            ->groups([
                // Grouped on the foreign key rather than menu.name: that
                // column is translated JSON, and Postgres has no ordering
                // operator for json — grouping or sorting by it 500s there,
                // even though SQLite (what the tests run against) tolerates
                // it. The key, the title and the order all come from the
                // parent explicitly instead. See .ai/rules/filament.md.
                Group::make('menu_id')
                    ->label(__('panel.categories.menu'))
                    ->getTitleFromRecordUsing(fn (MenuCategory $record): string => $record->menu->name)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Menu::query()->select('position')->whereColumn('menus.id', 'menu_categories.menu_id'),
                        $direction,
                    )),
            ])
            ->defaultGroup('menu_id')
            ->filters([
                SelectFilter::make('menu_id')
                    ->label(__('panel.categories.menu'))
                    ->options(fn (): array => MenuCategoryForm::menuOptions()),

                TernaryFilter::make('is_active')->label(__('panel.shared.showing')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, MenuCategory $record): array => MenuCategoryForm::fillTranslations($data, $record)),

                // Splitting one card into a lunch and a dinner menu means
                // carrying whole categories across, dishes and all.
                Action::make('moveToMenu')
                    ->label(__('panel.categories.move'))
                    ->iconButton()
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('gray')
                    ->authorize('update')
                    ->modalHeading(__('panel.categories.move'))
                    ->modalDescription(__('panel.categories.move_help'))
                    ->schema([
                        Select::make('menu_id')
                            ->label(__('panel.categories.move_target'))
                            ->options(fn (MenuCategory $record): array => self::otherMenus($record))
                            ->required()
                            ->native(false)
                            ->prefixIcon(Heroicon::OutlinedBookOpen)
                            // Uniqueness is per menu and built on the English
                            // name, so the clash is caught here rather than at
                            // the expression index after the form has passed.
                            ->rule(fn (MenuCategory $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                $taken = MenuCategory::query()
                                    ->where('menu_id', $value)
                                    ->where('name->'.Locale::default()->value, $record->getTranslation('name', Locale::default()->value))
                                    ->exists();

                                if ($taken) {
                                    $fail(__('panel.categories.unique'));
                                }
                            })
                            ->helperText(fn (MenuCategory $record): ?string => self::otherMenus($record) === []
                                ? __('panel.categories.move_none')
                                : null),
                    ])
                    ->action(function (MenuCategory $record, array $data): void {
                        $target = Menu::query()->findOrFail($data['menu_id']);

                        app(MoveCategoryToMenu::class)($record, $target);

                        Notification::make()
                            ->title(__('panel.categories.moved'))
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    // Deleting a section takes its items with it, by the
                    // cascade on the foreign key, so say so before it happens.
                    ->modalDescription(__('panel.categories.delete_warning')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position')
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('position')
            // The menu each category belongs to is shown as a column and grouped
            // on, so it is loaded once for the page rather than per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('menu'));
    }

    /**
     * The menus this category could be moved onto: this restaurant's, minus
     * the one it already sits on.
     *
     * @return array<int, string>
     */
    private static function otherMenus(MenuCategory $record): array
    {
        return array_diff_key(MenuCategoryForm::menuOptions(), [$record->menu_id => null]);
    }
}
