<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAccountPackageUsage extends Model
{
    use SoftDeletes;


    protected $table = 'customer_account_package_usages';
    protected $guarded = ['id'];
    protected $fillable = [
        'usage_id',
        'usage_description',
    ];

}
