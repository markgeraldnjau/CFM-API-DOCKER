<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class WeekDayName extends Model
{
    use SoftDeletes;

    protected $table = 'week_day_names';
    protected $fillable = [
        'week_name',
    ];
}
