<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperatorAllocation extends Model
{
    use SoftDeletes;


    protected $table = 'operator_allocations';
    // protected $fillable = [
    //     'operator_id',
    //     'train_id_asc',
    //     'train_id_dec',
    //     'datetime',
    //     'status',
    //     'user_id',
    //     'station_id',
    // ];

    // public function operator()
    // {
    //     return $this->belongsTo(Operator::class, 'operator_id');
    // }

    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'user_id','id');
    // }

    // public function trainAscend()
    // {
    //     return $this->belongsTo(Train::class, 'train_id_asc','id');
    // }

    // public function trainDescend()
    // {
    //     return $this->belongsTo(Train::class, 'train_id_dec','id');
    // }
}
