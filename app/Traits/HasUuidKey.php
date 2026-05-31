<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuidKey
{
    protected static function bootHasUuidKey(): void
    {
        static::creating(function ($model) {
            if (! $model->{$model->getKeyName()}) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
