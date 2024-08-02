<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeIncentive extends Model
{
    use HasFactory;
    protected $table = 'employee_monthly_incentives';
    // protected $table = 'tbl_employee_monthly_incentives';


    public function customer()
    {
        return $this->belongsTo(CardCustomer::class, 'customer_id', 'id');
    }
    public function customerAccount()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_id', 'customer_id');
    }
}
