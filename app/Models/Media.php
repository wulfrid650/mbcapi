<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'daily_log_id',
        'safety_incident_id',
        'portfolio_project_id',
        'url',
        'type',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Relation avec le journal quotidien
     */
    public function dailyLog()
    {
        return $this->belongsTo(DailyLog::class);
    }

    /**
     * Relation avec l'incident de sécurité
     */
    public function safetyIncident()
    {
        return $this->belongsTo(SafetyIncident::class);
    }

    /**
     * Relation avec le projet portfolio
     */
    public function portfolioProject()
    {
        return $this->belongsTo(PortfolioProject::class, 'portfolio_project_id');
    }
}
