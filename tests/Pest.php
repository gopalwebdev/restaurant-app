<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full application and run against a migrated,
| transaction-wrapped database. Unit tests stay free of the framework so
| they remain fast and isolated.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->afterEach(function (): void {
    File::deleteDirectory(storage_path('logs/testing'));
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Redirect the one-time password log channel into a file this test owns.
 *
 * Sign-in codes are delivered by being written to a log, so reading them back
 * out of one is how a test walks through the real sign-in flow.
 *
 * @return Closure(): list<string> a reader returning every code logged so far
 */
function captureIssuedCodes(): Closure
{
    $path = storage_path('logs/testing/otp-'.Str::random(8).'.log');

    // Codes reach people by email; the log copy is opt-in and off by default.
    config()->set('otp.log_codes', true);

    config()->set('logging.channels.otp', [
        'driver' => 'single',
        'path' => $path,
    ]);

    Log::forgetChannel('otp');

    return function () use ($path): array {
        if (! file_exists($path)) {
            return [];
        }

        preg_match_all('/"code":"(\d+)"/', (string) file_get_contents($path), $matches);

        return $matches[1];
    };
}
