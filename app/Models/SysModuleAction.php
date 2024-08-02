<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SysModuleAction extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'actions' => 'array',
    ];

    public function setActionsAttribute($value): void
    {
        $this->attributes['actions'] = json_encode($value);
    }

    public function permissionAction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PermissionAction::class);
    }
}
