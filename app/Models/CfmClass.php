<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CfmClass extends Model
{
    use HasFactory, SoftDeletes;

   //protected $connection = 'db2';

    protected $table = 'cfm_classes';


    protected $guarded = [];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}

