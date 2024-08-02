<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardCustomer_bulk extends Model
{
    use HasFactory;

    protected $table = 'card_customers';


    protected $fillable = [
        'full_name',
        'fname',
        'mname',
        'lname',
        'id_number',
        'id_type',
        'emp_id',
        'designation_title',
        'designation_id',
        'employee_department_id',
        // 'department_id',
        'hire_type',
        'entitled_class',
        'image_name',
        'gender',
        'phone_number',
        'email',
        'special_group_id',
        // 'category_id',
        'occupation_id',
        'address',
        'id_image',
        'date_of_birth',
        'finger_print',
        'street',
        'ward',
        'district',
        'region',
        'validity',
        'datetime_reg',
        'app_version',
        'app_pin',
        'app_status',
        'app_imei',
        'app_auth_token',
        'special_category',
        'principal_member_id',
        'session_key',
        'session_validity',
        'customer_device_imei'
    ];

    public function customerAccount()
    {
        return $this->hasOne(CustomerAccount::class,'cust_id','id');
    }
}
