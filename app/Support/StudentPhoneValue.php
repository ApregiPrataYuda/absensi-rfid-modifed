<?php

namespace App\Support;

class StudentPhoneValue
{
    public static function normalize(mixed $value): ?string
    {
        $phone = trim((string) ($value ?? ''));
        if ($phone === '') {
            return null;
        }

        return preg_match('/^-+$/', $phone) === 1 ? null : $phone;
    }

    public static function resolveForUserSync(mixed $studentPhone, mixed $currentUserPhone = null): ?string
    {
        return self::normalize($studentPhone) ?? self::normalize($currentUserPhone);
    }
}
