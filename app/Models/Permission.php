<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'sys_module_id'
    ];

    protected $with = ['module'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'roles_permissions');
    }

    public function module()
    {
        return $this->belongsTo(SysModule::class, 'sys_module_id');
    }
}
