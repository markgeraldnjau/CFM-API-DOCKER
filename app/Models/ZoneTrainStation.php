<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneTrainStation extends Model
{
    use HasFactory;
    public function zone()
    {
        return $this->belongsTo(Zone::class, "zone_id", "id");
    }

    public function trainStation()
    {
        return $this->belongsTo(TrainStation::class, "train_station_id", "id");
    }
}
