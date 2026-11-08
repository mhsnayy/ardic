<?php

namespace App\Support;

final class Progress
{
    public static function rate(int $total, int $completed): int
    {
        return $total > 0 ? (int) round($completed / $total * 100) : 0;
    }
}
