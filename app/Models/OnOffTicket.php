<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnOffTicket extends Model
{
    use HasFactory;

    public function devices(){
        return $this->hasMany(Device::class);
    }
}
