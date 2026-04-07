<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_project_id',
        'title',
        'period',
        'type',
        'author_name',
        'date',
        'pages_count',
        'status',
        'file_path',
    ];

    public function project()
    {
        return $this->belongsTo(PortfolioProject::class, 'portfolio_project_id');
    }
}
