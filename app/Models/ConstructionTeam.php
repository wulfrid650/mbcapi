<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConstructionTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'leader_name',
        'members_count',
        'phone',
        'email',
        'specialization',
        'projects_count',
        'status',
    ];
}
