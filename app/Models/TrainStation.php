<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainStation extends Model
{
    use SoftDeletes;


    protected $table = 'train_stations';
    protected $fillable = [
        'station_name',
        'station_name_erp',
        'station_type_erp',
        'province',
        'lat',
        'lng',
        'distance_maputo',
        'line_id',
        'frst_class',
        'sec_class',
        'thr_class',
        'zone_st',
        'line_pass',
        'is_off_train_ticket_available',
    ];
    // public function priceTables()
    // {
    //     return $this->hasMany(PriceTable::class,'','id');
    // }

    public function province()
    {
        return $this->belongsTo(Province::class, 'province', 'name');
    }
}
