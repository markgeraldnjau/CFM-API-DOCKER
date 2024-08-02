<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LineRoute extends Model
{
    use SoftDeletes;


    protected $table = 'train_routes';
    protected $fillable = [
        'route_name',
        'route_direction',
        'train_direction_id',
        'train_line_id',
        'train_number',
        'price_id',
        'etd',
        'first_class_penalty_value',
        'second_class_penalty_value',
        'third_class_penalty_value',
    ];

    public function trainLine()
    {
        return $this->belongsTo(MainLine::class, 'train_line_id', 'id');
    }

    public function farePriceCategory()
    {
        return $this->belongsTo(FarePriceCategory::class, 'fare_price_category_id', 'id');
    }
    public function direction()
    {
        return $this->belongsTo(TrainDirection::class, 'train_direction_id', 'id');
    }
}
