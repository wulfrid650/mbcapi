<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    protected $fillable = [
        'idempotency_key',
        'user_scope',
        'method',
        'route',
        'request_hash',
        'status_code',
        'response_body',
        'response_headers',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
