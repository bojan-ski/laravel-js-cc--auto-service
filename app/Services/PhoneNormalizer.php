<?php

namespace App\Services;

final class PhoneNormalizer
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
