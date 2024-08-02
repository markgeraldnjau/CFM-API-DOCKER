<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PermissionAction extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'code'];

    public function sysModuleActions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SysModuleAction::class);
    }
}
