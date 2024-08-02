<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SysModule extends Model
{
    use HasFactory;


    protected $table = 'sys_modules';
    protected $fillable = [
        'name',
    ];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'sys_module_id');


    }
}
