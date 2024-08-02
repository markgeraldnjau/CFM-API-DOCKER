<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceTable extends Model
{
    use SoftDeletes;


    // protected $table = 'price_table';
    protected $fillable = [
        'train_line_id',
        'train_station_stop_from',
        'train_station_stop_to',
        'distance',
        'fare_charge',
        'cfm_class_id',
    ];

    public function trainLine()
    {
        return $this->belongsTo(TrainLine::class, 'train_line_id', 'id');
    }

    public function trainStationStopFrom()
    {
        return $this->belongsTo(TrainStation::class, 'train_station_stop_from', 'id');
    }
    public function trainStationStopTo()
    {
        return $this->belongsTo(TrainStation::class, 'train_station_stop_to', 'id');
    }
    public function cfmClass()
    {
        return $this->belongsTo(CfmClass::class, 'cfm_class_id', 'id');
    }
}
