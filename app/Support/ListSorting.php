<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Lets any paginated list be sorted by the person looking at it.
 *
 * Registered as the Eloquent builder macro `sortFromRequest()`, which each list
 * query calls just before `paginate()`. It reads `sort_by` and `sort_direction`
 * from the request and, when they name a real column of the list's own table,
 * replaces the query's ordering with that. With no sort requested — or one that
 * is not recognised — the query keeps whatever order it already had, so a
 * screen that sends nothing behaves exactly as it did before.
 *
 * Sorting happens in the database rather than the browser on purpose: a list
 * shows ten or fifteen rows of possibly thousands, and sorting only the rows on
 * screen would never bring the true first record to the top.
 *
 * Safety: a column name cannot be bound as a query parameter, so `sort_by` is
 * only ever used after it has matched the table's actual column list.
 */
class ListSorting
{
    /** Columns nobody should be able to order a list by. */
    private const NEVER = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @var array<string, array<int, string>> column lists already looked up this request */
    private static array $columns = [];

    public static function register(): void
    {
        Builder::macro('sortFromRequest', function (?string $sortBy = null, ?string $direction = null) {
            /** @var Builder $this */
            return ListSorting::apply($this, $sortBy ?? request('sort_by'), $direction ?? request('sort_direction'));
        });
    }

    public static function apply(Builder $query, $sortBy, $direction): Builder
    {
        if (! is_string($sortBy) || $sortBy === '') {
            return $query;
        }

        $model = $query->getModel();
        $table = $model->getTable();

        if (in_array($sortBy, self::NEVER, true) || ! in_array($sortBy, self::columnsOf($table), true)) {
            return $query;
        }

        $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        return $query
            ->reorder($table.'.'.$sortBy, $direction)
            // Rows that tie would otherwise be free to swap between pages.
            ->orderBy($model->getQualifiedKeyName(), $direction);
    }

    private static function columnsOf(string $table): array
    {
        return self::$columns[$table] ??= Schema::getColumnListing($table);
    }
}
