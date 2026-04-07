<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialReport extends Model
{
    protected $fillable = [
        'title',
        'type',
        'period_start',
        'period_end',
        'file_path',
        'generated_by',
        'is_auto_generated',
        'status',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'is_auto_generated' => 'boolean',
        'metadata' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
