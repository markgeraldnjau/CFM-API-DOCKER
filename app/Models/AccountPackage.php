<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPackage extends Model
{
    use HasFactory;

    protected $table = 'customer_account_package_types';


    // public function trainLine()
    // {
    //     return $this->belongsTo(MainLine::class, 'line_id', 'id');
    // }
    // public function zone()
    // {
    //     return $this->belongsTo(Zone::class, 'zone_id', 'id');
    // }

    // public function specialGroup()
    // {
    //     return $this->belongsTo(SpecialGroup::class, 'category_id', 'id');
    // }

    // public function cfmClass()
    // {
    //     return $this->belongsTo(CfmClass::class, 'class_package', 'id');
    // }


    // public function mainLine()
    // {
    //     return $this->belongsTo(MainLine::class, 'line_id', 'id');
    // }


}
