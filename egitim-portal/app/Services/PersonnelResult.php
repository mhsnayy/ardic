<?php

namespace App\Services;

final class PersonnelResult
{
    public const LIVE = 'live';

    public const STALE = 'stale';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly mixed $data, public readonly string $source) {}
}
