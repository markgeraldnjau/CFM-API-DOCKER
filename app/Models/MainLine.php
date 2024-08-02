<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainLine extends Model
{
    use SoftDeletes;

    protected $table = 'train_lines';
    protected $fillable = [
        'line_name',
        'line_code',
        'line_distance',
        'region_id',
    ];

    public function cfmRegion()
    {
        return $this->belongsTo(CfmRegion::class, 'region_id', 'id');
    }

    public function lineRoutes()
    {
        return $this->hasMany(LineRoute::class, 'train_line_id', 'id');
    }

}
