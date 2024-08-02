<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainDirection extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function lineRoutes(){
        return $this->hasMany(LineRoute::class,'train_direction_id','id');
    }

}
