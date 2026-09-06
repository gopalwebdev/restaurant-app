<?php

use App\Enums\CountryCallingCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the country code and the mobile number in columns of their own.
     *
     * A number stored as one free-text string cannot be dialled, searched or
     * validated without being taken apart first. Split, each column holds one
     * fact: a calling code from App\Enums\CountryCallingCode, and the national
     * number, which for India is always ten digits.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('phone_country_code', 4)
                ->default(CountryCallingCode::India->value)
                ->after('email');

            $table->string('secondary_phone_country_code', 4)
                ->nullable()
                ->after('phone');
        });

        $this->splitStoredNumbers();

        // Sized to the longest mobile number any supported country has, so a
        // country with longer numbers needs a migration as well as an enum case.
        $length = CountryCallingCode::longestMobileNumberLength();

        Schema::table('restaurants', function (Blueprint $table) use ($length): void {
            $table->string('phone', $length)->nullable(false)->change();
            $table->string('secondary_phone', $length)->nullable()->change();
        });
    }

    public function down(): void
    {
        $this->rejoinStoredNumbers();

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('phone', 32)->nullable(false)->change();
            $table->string('secondary_phone', 32)->nullable()->change();
            $table->dropColumn(['phone_country_code', 'secondary_phone_country_code']);
        });
    }

    /**
     * Take each stored number apart into its calling code and national number.
     *
     * Anything already there was typed as one string, so the digits are all
     * there is to go on: the last ten are the number, and whatever precedes
     * them is the calling code, defaulting to India when there is nothing.
     */
    private function splitStoredNumbers(): void
    {
        $length = CountryCallingCode::longestMobileNumberLength();

        DB::table('restaurants')
            ->select(['id', 'phone', 'secondary_phone'])
            ->orderBy('id')
            ->each(function (object $restaurant) use ($length): void {
                [$code, $number] = $this->split((string) $restaurant->phone, $length);
                [$secondaryCode, $secondaryNumber] = $this->split((string) $restaurant->secondary_phone, $length);

                DB::table('restaurants')->where('id', $restaurant->id)->update([
                    'phone_country_code' => $code ?? CountryCallingCode::India->value,
                    'phone' => $number ?? '',
                    'secondary_phone_country_code' => $secondaryCode,
                    'secondary_phone' => $secondaryNumber,
                ]);
            });
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function split(string $stored, int $length): array
    {
        $digits = preg_replace('/\D/', '', $stored) ?? '';

        if ($digits === '') {
            return [null, null];
        }

        if (mb_strlen($digits) <= $length) {
            return [CountryCallingCode::India->value, $digits];
        }

        // Whatever precedes the last ten digits is only a calling code if it
        // reads as one. A landline written with its area code does not, and
        // falls back to India rather than storing a code that does not exist.
        $callingCode = CountryCallingCode::fromDigits(mb_substr($digits, 0, -$length))
            ?? CountryCallingCode::India;

        return [$callingCode->value, mb_substr($digits, -$length)];
    }

    /**
     * Put the calling code back in front of the number.
     */
    private function rejoinStoredNumbers(): void
    {
        DB::table('restaurants')
            ->select(['id', 'phone', 'phone_country_code', 'secondary_phone', 'secondary_phone_country_code'])
            ->orderBy('id')
            ->each(function (object $restaurant): void {
                DB::table('restaurants')->where('id', $restaurant->id)->update([
                    'phone' => $restaurant->phone_country_code.$restaurant->phone,
                    'secondary_phone' => filled($restaurant->secondary_phone)
                        ? $restaurant->secondary_phone_country_code.$restaurant->secondary_phone
                        : null,
                ]);
            });
    }
};
