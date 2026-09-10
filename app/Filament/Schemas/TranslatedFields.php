<?php

namespace App\Filament\Schemas;

use App\Enums\Locale;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The inputs an admin types a translated field into: one box per field, and one
 * switcher for the whole form deciding which language those boxes are showing.
 *
 * A field still has an input per language underneath — that is how the state of
 * every language reaches the save in one go — but only the switched-to one is
 * on screen. A form with a name and a description used to be four boxes; it is
 * two, and the form no longer doubles in height for each language added.
 *
 * Two consequences worth knowing:
 *
 * - Hidden languages are still dehydrated, so switching to Tamil and saving
 *   keeps the English that was already typed. Without that, editing one
 *   language would quietly blank the other.
 * - Validation that has to hold whichever language is showing — English is
 *   required, English is unique — is attached to *every* language's input and
 *   reads the English value out of the form state rather than off the input it
 *   happens to be attached to. A rule left only on the English input would
 *   never run while Tamil was the one on screen, and the save would fail at the
 *   database instead.
 */
final class TranslatedFields
{
    /**
     * The form state holding which language is being edited.
     *
     * Never dehydrated: it is a control for the person filling the form in, not
     * a column on anything. Bubbles up from nested sections, so one switcher at
     * the top of a form drives every translated field under it.
     */
    public const string LOCALE_KEY = '_locale';

    /**
     * The control that decides which language every box on this form shows.
     *
     * Place it once, at the top of the form. Toggle buttons rather than a
     * select: with a handful of languages the whole choice should be readable
     * without opening anything, and switching should be one tap.
     */
    public static function localeSwitcher(): ToggleButtons
    {
        return ToggleButtons::make(self::LOCALE_KEY)
            ->label(__('panel.shared.editing_language'))
            ->options(array_reduce(
                Locale::cases(),
                static function (array $options, Locale $locale): array {
                    $options[$locale->value] = $locale->label();

                    return $options;
                },
                [],
            ))
            ->default(Locale::default()->value)
            ->live()
            ->inline()
            ->grouped()
            ->dehydrated(false)
            ->columnSpanFull();
    }

    /**
     * A single-line input, showing whichever language is switched to.
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
                    $name,
                    $label,
                );

                if (! $uniqueWithin instanceof Closure) {
                    return $field;
                }

                return $field->rule(
                    fn (Get $get, ?Model $record): Closure => self::uniqueFallbackValue(
                        fn (): Builder => $uniqueWithin($get),
                        $record,
                        $name,
                        $uniqueMessage,
                        $get($name.'.'.Locale::default()->value),
                    ),
                );
            },
            Locale::cases(),
        );
    }

    /**
     * A single-line input required in no language at all.
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
                $name,
                $label,
                requireFallback: false,
            ),
            Locale::cases(),
        );
    }

    /**
     * A multi-line input, showing whichever language is switched to.
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
                $name,
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
     * $value is passed in rather than taken from the input this is attached to:
     * the rule rides on every language's input so it runs whichever one is on
     * screen, and all of them are asking about the English value.
     *
     * @param  Closure(): Builder<covariant Model>  $query  already narrowed to the parent this belongs to
     */
    public static function uniqueFallbackValue(
        Closure $query,
        ?Model $record = null,
        string $column = 'name',
        string $message = 'Something here already has that name.',
        mixed $value = null,
    ): Closure {
        return static function (string $attribute, mixed $inputValue, Closure $fail) use ($query, $record, $column, $message, $value): void {
            $english = $value ?? $inputValue;

            if (blank($english)) {
                return;
            }

            $matches = $query()->where($column.'->'.Locale::default()->value, $english);

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
     * Which language the form is currently showing.
     *
     * Falls back rather than trusting the state: the switcher is a form field
     * like any other and can arrive as anything.
     */
    private static function editingLocale(Get $get): Locale
    {
        $value = $get(self::LOCALE_KEY);

        return is_string($value)
            ? (Locale::tryFrom($value) ?? Locale::default())
            : Locale::default();
    }

    /**
     * Label, visibility, requiredness and helper text for one language's input.
     *
     * @template TField of TextInput|Textarea
     *
     * @param  TField  $field
     * @return TField
     */
    private static function configure(
        TextInput|Textarea $field,
        Locale $locale,
        string $name,
        string $label,
        bool $requireFallback = true,
    ): TextInput|Textarea {
        $isFallback = $locale === Locale::default();

        $field = $field
            ->label($label)
            ->visible(fn (Get $get): bool => self::editingLocale($get) === $locale)
            // The languages not on screen still travel with the save, or
            // editing one would blank the others.
            ->dehydratedWhenHidden()
            ->required($requireFallback && $isFallback);

        if (! $requireFallback || $isFallback) {
            return $field;
        }

        // English is required whichever language is being looked at, so the
        // rule rides here too rather than only on the English input, which is
        // hidden at exactly the moment it would need to fire.
        return $field->rule(
            fn (Get $get): Closure => static function (string $attribute, mixed $inputValue, Closure $fail) use ($get, $name, $label): void {
                if (filled($get($name.'.'.Locale::default()->value))) {
                    return;
                }

                $fail(__('panel.shared.fallback_required', [
                    'field' => $label,
                    'language' => Locale::default()->englishName(),
                ]));
            },
        );
    }
}
