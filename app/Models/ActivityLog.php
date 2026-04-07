<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ActivityLog extends Model
{
    private const TECHNICAL_ACTION_PREFIXES = [
        'Administration - ',
        'Secretariat - ',
        'Chantier - ',
        'Espace apprenant - ',
        'Espace client - ',
        'Espace formateur - ',
        'Paiements - ',
        'Authentification - ',
    ];

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function scopeImportant(Builder $query): Builder
    {
        foreach (self::TECHNICAL_ACTION_PREFIXES as $prefix) {
            $query->where('action', 'not like', $prefix . '%');
        }

        return $query;
    }

    public static function log($user, $action, $description = null, $subject = null)
    {
        return self::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
