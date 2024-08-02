<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    use HasFactory;

    // protected $connection = 'db2';
    protected $table = 'genders';


    protected $fillable = ['gender'];



}
