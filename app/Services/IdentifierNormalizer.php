<?php

namespace App\Services;

final class IdentifierNormalizer
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(trim((string) $value))) ?? '';
    }
}
