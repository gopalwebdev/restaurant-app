<?php

namespace App\Filament\Schemas;

use App\Enums\Locale;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The inputs an admin types a translated field into, one per language.
 *
 * Both languages are shown side by side rather than behind a locale switcher.
 * With two languages and a handful of fields that is less machinery and easier
 * to keep in step: a name and its translation are edited together, so nobody
 * has to remember to go back and switch tabs.
 *
 * English is required and the rest are optional, which is the whole of the
 * fallback story: a dish with no Tamil name is read in English by a guest with
 * Tamil selected, rather than appearing as a blank row.
 */
final class TranslatedFields
{
    /**
     * A single-line input per language.
     *
     * `uniqueWithin` narrows the query the name is checked against — the menu a
     * section sits on, the section a dish sits in — and is only ever applied to
     * the fallback language, because that is what the database's unique indexes
     * are built on.
     *
     * @param  (Closure(Get): Builder<covariant Model>)|null  $uniqueWithin
     * @return list<TextInput>
     */
    public static function text(
        string $name,
        string $label,
        int $maxLength = 120,
        ?Closure $uniqueWithin = null,
        string $uniqueMessage = 'Something here already has that name.',
    ): array {
        return array_map(
            static function (Locale $locale) use ($name, $label, $maxLength, $uniqueWithin, $uniqueMessage): TextInput {
                $field = self::configure(
                    TextInput::make("{$name}.{$locale->value}")->maxLength($maxLength),
                    $locale,
                    $label,
                );

                if (! $uniqueWithin instanceof Closure || $locale !== Locale::default()) {
                    return $field;
                }

                return $field->rule(
                    fn (Get $get, ?Model $record): Closure => self::uniqueFallbackValue(
                        fn (): Builder => $uniqueWithin($get),
                        $record,
                        $name,
                        $uniqueMessage,
                    ),
                );
            },
            Locale::cases(),
        );
    }

    /**
     * A single-line input per language, required in none of them.
     *
     * For text a record may simply not have — a home screen row's heading,
     * where a banner into the menu reads better with nothing over it. Carries
     * no uniqueness rule for the same reason: two untitled rows are not a
     * clash, and the index that backs this skips null titles too.
     *
     * @return list<TextInput>
     */
    public static function optionalText(string $name, string $label, int $maxLength = 120): array
    {
        return array_map(
            static fn (Locale $locale): TextInput => self::configure(
                TextInput::make("{$name}.{$locale->value}")->maxLength($maxLength),
                $locale,
                $label,
                requireFallback: false,
            ),
            Locale::cases(),
        );
    }

    /**
     * A multi-line input per language.
     *
     * Never required, in any language: a description is optional everywhere it
     * appears.
     *
     * @return list<Textarea>
     */
    public static function textarea(string $name, string $label, int $maxLength = 500, int $rows = 3): array
    {
        return array_map(
            static fn (Locale $locale): Textarea => self::configure(
                Textarea::make("{$name}.{$locale->value}")->maxLength($maxLength)->rows($rows),
                $locale,
                $label,
                requireFallback: false,
            ),
            Locale::cases(),
        );
    }

    /**
     * Refuse a name another record in the same place already uses.
     *
     * Checked against the fallback language only, because that is exactly what
     * the database's unique index is built on — so the form and the constraint
     * cannot disagree, and a save can never fail after passing validation.
     *
     * @param  Closure(): Builder<covariant Model>  $query  already narrowed to the parent this belongs to
     */
    public static function uniqueFallbackValue(
        Closure $query,
        ?Model $record = null,
        string $column = 'name',
        string $message = 'Something here already has that name.',
    ): Closure {
        return static function (string $attribute, mixed $value, Closure $fail) use ($query, $record, $column, $message): void {
            if (blank($value)) {
                return;
            }

            $matches = $query()->where($column.'->'.Locale::default()->value, $value);

            // Editing a record must not collide with itself.
            if ($record instanceof Model) {
                $matches->whereKeyNot($record->getKey());
            }

            if ($matches->exists()) {
                $fail($message);
            }
        };
    }

    /**
     * Search a translated column by its fallback-language value.
     *
     * A translated column holds a JSON document, so a plain `like` would be
     * matching braces and locale keys as well as words.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public static function search(Builder $query, string $column, string $search): Builder
    {
        return $query->where($column.'->'.Locale::default()->value, 'like', '%'.$search.'%');
    }

    /**
     * Sort a translated column by its fallback-language value.
     *
     * Filament hands the direction through as a plain string; only the two
     * values are ever sent, and this is where that is made explicit.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public static function sort(Builder $query, string $column, string $direction): Builder
    {
        return $query->orderBy(
            $column.'->'.Locale::default()->value,
            $direction === 'desc' ? 'desc' : 'asc',
        );
    }

    /**
     * Label, requiredness and helper text for one language's input.
     *
     * @template TField of TextInput|Textarea
     *
     * @param  TField  $field
     * @return TField
     */
    private static function configure(
        TextInput|Textarea $field,
        Locale $locale,
        string $label,
        bool $requireFallback = true,
    ): TextInput|Textarea {
        $isFallback = $locale === Locale::default();

        return $field
            ->label($locale->fieldLabel($label))
            ->required($requireFallback && $isFallback)
            ->helperText($isFallback
                ? null
                : sprintf(
                    'Optional. Guests reading in %s see the %s text when this is empty.',
                    $locale->englishName(),
                    Locale::default()->englishName(),
                ));
    }
}
