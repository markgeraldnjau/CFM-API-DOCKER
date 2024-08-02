<?php

namespace App\Http\Controllers\Api;

use App\Models\EmployeeIncentive;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use DB;
class EmployeeIncentiveController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $employeeIncentives = EmployeeIncentive::with([
            'customerAccount:id,account_number,card_id,customer_id',
            'customerAccount.card' => function ($query) {
                $query->select('id', DB::raw("CONCAT_WS('', LEFT(cards.card_number,4), '****', RIGHT(cards.card_number,5)) AS card_number"));
            },
            'customerAccount.cardCustomer:id,full_name,phone,gender_id,employee_id,birthdate',
            'customerAccount.cardCustomer.gender:id,gender'
        ])->paginate($request->items_per_page);
        // $employeeIncentives = EmployeeIncentive::with(['customer:id,full_name,gender_id', 'customer.gender:id,gender'])->paginate($request->items_per_page);

        return response()->json($employeeIncentives, 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeIncentive $employeeIncentive)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmployeeIncentive $employeeIncentive)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmployeeIncentive $employeeIncentive)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeIncentive $employeeIncentive)
    {
        //
    }
}
