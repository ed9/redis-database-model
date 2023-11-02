<?php

namespace Ed9\RedisDocument\Casts;

use Illuminate\Support\Str;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class UuidCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): string
    {
        return strval($value);
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        if (empty($value)) {
            $value = Str::uuid()->toString();
        }

        return strval($value);
    }
}
