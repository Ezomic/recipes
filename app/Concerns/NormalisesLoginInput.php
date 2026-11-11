<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Str;

trait NormalisesLoginInput
{
    private const EDGE = '[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+';

    protected function trimmed(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/^'.self::EDGE.'|'.self::EDGE.'$/u', '', $value) ?? '';
    }

    protected function normalisedEmail(mixed $value): mixed
    {
        $trimmed = $this->trimmed($value);

        return is_string($trimmed) ? Str::lower($trimmed) : $trimmed;
    }
}
