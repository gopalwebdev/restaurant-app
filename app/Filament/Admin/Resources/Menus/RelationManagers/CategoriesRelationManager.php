<?php

namespace App\Filament\Admin\Resources\Menus\RelationManagers;

use App\Actions\Menus\MoveCategoryToMenu;
use App\Enums\Locale;
use App\Filament\Admin\Resources\Menus\Schemas\MenuCategoryForm;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuCategory;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The sections of this menu, in the order a guest reads them.
 *
 * Categories used to be a resource of their own in the navigation, listing
 * every category of every menu and grouping them by menu to make sense of it.
 * They live here instead: a category only means anything inside a menu, and the
 * menu's own page is where its structure is arranged — this table and the
 * sub-categories below it are the whole shape of a menu on one screen.
 *
 * Moving a category to another menu is a separate action rather than a select
 * on the form, because it is a different act from renaming one and carries a
 * whole branch — sub-categories, dishes and all — with it.
 */
class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'menuCategories';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedRectangleStack;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.categories.plural');
    }

    public function form(Schema $schema): Schema
    {
        return MenuCategoryForm::configure($schema, $this->menu()->getKey());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading(__('panel.categories.plural'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.shared.name'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    // A translated column holds a JSON document, so searching
                    // and sorting have to name the language they mean.
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction)),

                TextColumn::make('sub_categories_count')
                    ->label(__('panel.sub_categories.plural'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->counts('subCategories')
                    ->sortable(),

                TextColumn::make('menu_items_count')
                    ->label(__('panel.categories.items_count'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->counts('menuItems')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.shared.showing'))
                    ->boolean()
                    ->sortable(),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('panel.categories.create'))
                    ->icon(Heroicon::OutlinedPlus),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    // Spatie hands back one language for a translated column;
                    // this form edits them all, so the whole document is put
                    // back before the fields are filled.
                    ->mutateRecordDataUsing(fn (array $data, MenuCategory $record): array => MenuCategoryForm::fillTranslations($data, $record)),

                // Splitting one card into a lunch and a dinner menu means
                // carrying whole categories across, everything under them
                // included.
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
                                ? self::text('panel.categories.move_none')
                                : null),
                    ])
                    ->action(function (MenuCategory $record, array $data): void {
                        $target = Menu::query()->whereKey($data['menu_id'])->firstOrFail();

                        app(MoveCategoryToMenu::class)($record, $target);

                        Notification::make()
                            ->title(__('panel.categories.moved'))
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    // Deleting a category takes its sub-categories and every
                    // dish under it, by the cascades on the foreign keys.
                    ->modalDescription(__('panel.categories.delete_warning')),
            ])
            ->reorderable('position')
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('position')
            ->emptyStateHeading(__('panel.categories.empty_heading'))
            ->emptyStateDescription(__('panel.categories.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack);
    }

    /**
     * A translation that is definitely a string.
     *
     * `__()` is typed as string|array|null because a key may hold either, so
     * the call sites with a declared return type check rather than cast — the
     * same shape as ListMenuItems::needsASectionTooltip().
     */
    private static function text(string $key): ?string
    {
        $text = __($key);

        return is_string($text) ? $text : null;
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

    private function menu(): Menu
    {
        $menu = $this->getOwnerRecord();

        return $menu instanceof Menu ? $menu : throw new LogicException('The categories relation manager requires a menu.');
    }
}
