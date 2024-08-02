<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainStationScheduleTime extends Model
{
    use HasFactory;
    public function train()
    {
        return $this->belongsTo(Train::class, "train_id", "id");
    }

    public function trainStation()
    {
        return $this->belongsTo(TrainStation::class, "train_station_id", "id");
    }
    public function departure()
    {
        return $this->belongsTo(WeekDayName::class, "departure_day", "id");
    }
    public function arrival()
    {
        return $this->belongsTo(WeekDayName::class, "arrival_day", "id");
    }
}
