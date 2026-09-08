<?php

namespace App\Models\Concerns;

/**
 * Reads a withCount() value that is already on the model.
 *
 * A list page loads its counts once for the whole page. Anything asked per row
 * afterwards should use that number rather than going back to the database, or
 * a page of twenty rows costs twenty extra queries to say what it already knows.
 */
trait ReadsLoadedCounts
{
    /**
     * A withCount() value already loaded, or null when it was not.
     *
     * Read straight out of the attribute array rather than through __get:
     * under Model::shouldBeStrict() touching an attribute that was never
     * selected throws, and "not loaded" is the ordinary case here, not a bug.
     */
    protected function loadedCount(string $key): ?int
    {
        $value = $this->getAttributes()[$key] ?? null;

        return $value === null ? null : (int) $value;
    }
}
