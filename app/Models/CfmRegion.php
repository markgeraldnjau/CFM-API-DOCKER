<?php

namespace App\Models;

use App\Models\MainLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CfmRegion extends Model
{
    use SoftDeletes;


    protected $table = 'cfm_regions';

    protected $fillable = [
        'region_code',
        'region_name',
        'number_line',
    ];

    protected $dates = ['deleted_at'];

    public function mainLine()
    {
        return $this->hasOne(MainLine::class,'region_id','id');
    }
}
