<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRequest extends Model
{
    public const TYPES = ['website', 'web_app', 'template_custom', 'other'];

    public const STATUSES = [
        'new',
        'contacted',
        'quoted',
        'in_progress',
        'done',
        'rejected',
    ];

    protected $fillable = [
        'name',
        'email',
        'whatsapp',
        'project_type',
        'description',
        'deadline',
        'budget',
        'status',
        'admin_notes',
    ];
}
