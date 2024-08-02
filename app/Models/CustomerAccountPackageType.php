<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAccountPackageType extends Model
{
    use SoftDeletes;

    protected $table = 'customer_account_package_types';

    protected $fillable = [
        'package_code',
        'package_name',
        'package_description',
        'package_amount',
        'package_validity_type',
        'package_usage_type',
        'package_trip',
        'package_discount_percent',
        'min_balance',
        'package_sale',
        'package_valid',
        'send_device_option',
        'trips_lpd',
        'trips_lpm',
        'debit_field_type',
        'price',
        'zone',
        'class',
    ];

    public function customerAccount()
    {
        return $this->hasMany(CustomerAccount::class, 'customer_account_package_type', 'id');
    }

    public function trainLine()
    {
        return $this->belongsTo(MainLine::class, 'line_id', 'line_ID');
    }
    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id', 'id');
    }

    public function specialGroup()
    {
        return $this->belongsTo(SpecialGroup::class, 'category_id', 'id');
    }

    public function cfmClass()
    {
        return $this->belongsTo(CfmClass::class, 'class_package', 'class_ID');
    }


    public function mainLine()
    {
        return $this->belongsTo(MainLine::class, 'line_id', 'line_ID');
    }
}
