<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDepartment extends Model
{
    use HasFactory;
    protected $table = 'employee_departments';


    public function cardCustomer()
    {
        return $this->hasMany(CardCustomer::class, 'employee_department_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(CompanyContract::class, 'company_contract_id', 'id');
    }

    public function companyContract()
    {
        return $this->belongsTo(CompanyContract::class, 'company_contract_id', 'id');
    }
}
