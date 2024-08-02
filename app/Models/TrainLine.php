<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function trainRoutes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainRoute::class, 'train_line_id');
    }
}
