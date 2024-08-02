<?php

namespace App\Models;

use App\Models\Operator\OperatorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Train extends Model
{
    use HasFactory;

    protected $table = 'trains';
    protected $guarded = [];

    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function trainRoute()
    {
        return $this->belongsTo(LineRoute::class, 'train_route_id', 'id');
    }

    public function operatorType()
    {
        return $this->belongsTo(OperatorType::class, 'train_type', 'id');
    }

    public function startTrainStation()
    {
        return $this->belongsTo(TrainStation::class, 'start_train_station_id', 'id');
    }

    public function endTrainStation()
    {
        return $this->belongsTo(TrainStation::class, 'end_train_station_id', 'id');
    }

    public function departureDay()
    {
        return $this->belongsTo(WeekDayName::class, 'departure_day', 'id');
    }

    public function arrivalDay()
    {
        return $this->belongsTo(WeekDayName::class, 'arrival_day', 'id');
    }
}
