<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressUpdate extends Model
{
    protected $fillable = [
        'portfolio_project_id',
        'title',
        'description',
        'progress',
        'photos',
    ];

    protected $casts = [
        'photos' => 'array',
        'progress' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(PortfolioProject::class, 'portfolio_project_id');
    }
}
