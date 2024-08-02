<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FarePriceCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'price_formula',
        'more',
    ];

    public function lineRoutes(){
        return $this->hasMany(LineRoute::class,'fare_price_category_id','id');
    }
}
