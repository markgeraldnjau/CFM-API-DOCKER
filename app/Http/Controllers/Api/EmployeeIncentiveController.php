<?php

namespace App\Http\Controllers\Api;

use App\Models\EmployeeIncentive;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class EmployeeIncentiveController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = validator($request->all(), [
            'items_per_page' => 'nullable|integer|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $employeeIncentives = EmployeeIncentive::with([
            'customerAccount:id,account_number,card_id,customer_id',
            'customerAccount.card' => function ($query) {
                $query->select('id', DB::raw("CONCAT_WS('', LEFT(cards.card_number,4), '****', RIGHT(cards.card_number,5)) AS card_number"));
            },
            'customerAccount.cardCustomer:id,full_name,phone,gender_id,employee_id,birthdate',
            'customerAccount.cardCustomer.gender:id,gender'
        ])->paginate($validator['items_per_page']);

        return response()->json($employeeIncentives, HTTP_OK);
    }

}
