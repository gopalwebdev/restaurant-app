<?php

namespace App\Exceptions;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pipeline\Pipeline;
use RuntimeException;

/**
 * One request ran the same query, with the same bindings, more than once.
 *
 * Thrown only in local development and the test suite — see
 * AppServiceProvider::preventDuplicateQueries(). The fix is almost always to
 * read the answer once and hand it down, to eager load what a loop keeps asking
 * for, or — where Filament asks the same question several times while building
 * one page — to memoize it for the request with once().
 */
class DuplicateQueryException extends RuntimeException
{
    public static function fromQuery(QueryExecuted $query, string $origin): self
    {
        return new self(sprintf(
            'This request already ran this query (from %s): %s',
            $origin,
            $query->toRawSql(),
        ));
    }

    /**
     * The line of application code that asked for the query, or null when none did.
     *
     * The backtrace is walked from the query outwards. A middleware frame that is
     * only handing the request to the next layer of Laravel's pipeline is not
     * asking for anything, so it is passed over, as are this guard's own frames.
     * A query that only package code asked for — Filament or Spatie doing their
     * own work twice — comes back null, because nothing here can fix it.
     */
    public static function applicationOrigin(): ?string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file) || ! str_starts_with($file, app_path())) {
                continue;
            }

            if (str_ends_with($file, 'AppServiceProvider.php') || str_ends_with($file, 'DuplicateQueryException.php')) {
                continue;
            }

            $called = $frame['class'] ?? null;

            if (is_string($called) && is_a($called, Pipeline::class, true)) {
                continue;
            }

            return sprintf('%s:%d', str_replace(base_path().'/', '', $file), $frame['line'] ?? 0);
        }

        return null;
    }
}
