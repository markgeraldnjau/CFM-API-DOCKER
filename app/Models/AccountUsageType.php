<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountUsageType extends Model
{
    use HasFactory;

    protected $table = 'account_usage_types';


    public function customerAccount()
    {
        return $this->hasMany(AccountUsageType::class, 'accounts_usage_type', 'id');
    }


}
