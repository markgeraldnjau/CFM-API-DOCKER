<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\DeviceOperator;
use App\Models\Operator;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class DeviceOperatorController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $operators = DB::table('operators')
                ->select('operators.*')
            ->join('train_stations' ,'train_stations.id','=','operators.station_id')
            ->get();
            return response()->json($operators);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json($th->getMessage());
        }
    }

    public function device_operators_details(Request $request)
    {
        try {
            $operators = DB::table('operators')
            ->where('id', $request->id)
            ->first();
            return response()->json($operators);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json($th->getMessage());
        }
    }

    public function device_operators_api()
    {
        try {
            $operators = DB::table('operators')->get();
            return response()->json($operators, \HttpResponseCode::SUCCESS);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            return response()->json($th->getMessage());
        }
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
        $validator = validator($request->all(), [
            'agent_number' => 'required|string|max:255',
            'operator_name' => 'required|string|max:255',
            'user_name' => 'required|string|max:255',
            'phone' => 'required|regex:/^\d{10,15}$/',
            'email' => 'required|email|max:255',
            'password' => 'required',
            'line_id' => 'required|integer',
            'operator_category' => 'required|integer',
            'log_off' => 'required|string',
            'station_id' => 'required|integer',
            'zone' => 'required|string|max:255',
            'normal' => 'nullable|string|max:255',
            'changing_class' => 'nullable|string|max:255',
            'automotora' => 'nullable|string|max:255',
            'top_up' => 'nullable|string|max:255',
            'registration' => 'nullable|string|max:255',
            'scanning' => 'nullable|string|max:255',
            'incentive' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        DB::beginTransaction();
        try {
            $operator = new Operator;
            $operator->operator_id = $validated['agent_number'];
            $operator->full_name = $validated['operator_name'];
            $operator->username = $validated['user_name'];
            $operator->phone = $validated['phone'];
            $operator->email = $validated['email'];
            $operator->password = $validated['password'];
            $operator->train_line_id = $validated['line_id'];
            $operator->operator_category_id = $validated['operator_category'];
            $operator->operator_type_code = $validated['log_off'];
            $operator->status = "1";
            $operator->station_id = $validated['station_id'];
            $operator->zone = $validated['zone'];
            $operator->normal = $validated['normal'];
            $operator->changing_class = $validated['changing_class'];
            $operator->automotora = $validated['automotora'];
            $operator->top_up = $validated['top_up'];
            $operator->registration = $validated['registration'];
            $operator->scanning = $validated['scanning'];
            $operator->incentive = $validated['incentive'];
            // $operator->balance_product_id = $request['balance_product_id'];
            // $operator->balance_vendor_id = $request['balance_vendor_id'];
            $operator->save();

            $operatorAccount = $this->createOperatorAccount($operator->id);

            if (!$operatorAccount) {
                return response()->json([
                    'status' => 'failed',
                    'message' => \ResponseMessages::OPERATOR_ACCOUNT_CREATION_FAILED
                ], 500);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => \ResponseMessages::OPERATOR_CREATED_SUCCESSFULLY
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());

            return response()->json([
                'status' => 'failed',
                'message' => \ResponseMessages::OPERATOR_CREATION_FAILED
            ], 500);
        }
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeviceOperator $deviceOperator, $id)
    {
        $deviceOperator = DeviceOperator::find($id);
        if ($deviceOperator) {
            $deviceOperator->code = strtoupper($request->code);
            $deviceOperator->class_type = $request->class_type;
            $deviceOperator->update();
            return response()->json(['status' => 'success', 'message' => 'Successfully update Device Operator'], 200);
        } else {
            return response()->json(['message' => 'Train Class not found'], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeviceOperator $deviceOperator)
    {
        //
    }
}
