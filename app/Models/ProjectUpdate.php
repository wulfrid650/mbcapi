<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_project_id',
        'title',
        'description',
        'date',
        'author_name',
        'images_count',
        'status',
    ];

    public function project()
    {
        return $this->belongsTo(PortfolioProject::class, 'portfolio_project_id');
    }
}
