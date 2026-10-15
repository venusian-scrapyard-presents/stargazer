<?php

namespace App\Models;

use Voyager\Database\Instrument\Model;

class PersistentVariable extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function recall(string $key): mixed
    {
        return static::query()->firstWhere('key', $key)?->value;
    }
}
