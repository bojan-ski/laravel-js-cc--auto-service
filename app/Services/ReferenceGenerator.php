<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReferenceGenerator
{
    public const int SEQUENCE_DIGITS = 4;

    public static function next(string $prefix, string $table, string $column, CarbonInterface $on): string
    {
        $stem = sprintf('%s-%s-', $prefix, $on->format('Y'));

        $last = DB::table($table)
            ->where($column, 'like', $stem.'%')
            ->max($column);

        $next = $last === null ? 1 : (int) substr((string) $last, strlen($stem)) + 1;

        return $stem.str_pad((string) $next, self::SEQUENCE_DIGITS, '0', STR_PAD_LEFT);
    }
}
