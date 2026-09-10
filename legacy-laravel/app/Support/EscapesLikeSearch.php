<?php

namespace App\Support;

trait EscapesLikeSearch
{
    protected function whereLikeAny($query, array $columns, string $search): void
    {
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';

        $query->where(function ($subQuery) use ($columns, $pattern) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';

                $subQuery->{$method}($column . " LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
