<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    public function scopeWherePublicId(Builder $query, string $publicId): Builder
    {
        return $query->where($this->qualifyColumn('public_id'), $publicId);
    }

    public static function resolvePublicIdOrFail(string $publicId): static
    {
        return static::query()->where('public_id', $publicId)->firstOrFail();
    }

    public function getPublicId(): string
    {
        return (string) $this->public_id;
    }
}
