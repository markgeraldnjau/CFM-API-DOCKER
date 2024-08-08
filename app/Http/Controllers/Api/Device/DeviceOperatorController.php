<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\DeviceOperator;
use App\Models\Operator;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;


class DeviceOperatorController extends Controller
{
    use ApiResponse, CommonTrait, AuditTrail;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $operators = DB::table('operators')
                ->join('train_stations', 'train_stations.id', '=', 'operators.station_id')
                ->leftJoin('operator_categories as oc', 'oc.id', '=', 'operators.operator_category_id')
                ->select('operators.*', 'train_stations.station_name', 'oc.category as operator_category')
                ->get();

            return response()->json($operators);
        } catch (\Throwable $th) {
            Log::channel('pos')->error(json_encode($this->errorPayload($th)));
            return response()->json($th->getMessage());
        }
    }

    public function device_operators_details(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'token' => ['nullable', 'string', 'exists:operators,token'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return response()->json($errors);
        }

        try {
            $operator = DB::table('operators')
                ->where('token', $request->token)
                ->leftJoin('train_stations as ts', 'ts.id', '=', 'operators.station_id')
                ->select('operators.*', 'ts.station_name')->first();

            if (empty($operator)){
                return $this->error(null, "Operator Not found", HTTP_INTERNAL_SERVER_ERROR);
            }
            $this->auditLog("View Operator Details: ". $operator->full_name, PORTAL, null, null);
            return $this->success($operator, DATA_RETRIEVED);
        } catch (\Throwable $th) {
            Log::channel('pos')->error($th->getMessage());
            return response()->json($th->getMessage());
        }
    }

    public function device_operators_api()
    {
        try {
            $operators = DB::table('operators')->get();
            return response()->json($operators, \HttpResponseCode::SUCCESS);
        } catch (\Throwable $th) {
            Log::channel('pos')->error($th->getMessage());
            return response()->json($th->getMessage());
        }
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
            ], HTTP_UNPROCESSABLE_ENTITY);
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
            $operator->save();

            if (!$operator){
                return response()->json([
                    'status' => 'failed',
                    'message' => \ResponseMessages::OPERATOR_ACCOUNT_CREATION_FAILED
                ], HTTP_INTERNAL_SERVER_ERROR);
            }

            $operatorAccount = $this->createOperatorAccount($operator->id);

            if (!$operatorAccount) {
                return response()->json([
                    'status' => 'failed',
                    'message' => \ResponseMessages::OPERATOR_ACCOUNT_CREATION_FAILED
                ], HTTP_INTERNAL_SERVER_ERROR);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => \ResponseMessages::OPERATOR_CREATED_SUCCESSFULLY
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('pos')->info($e->getMessage());

            return response()->json([
                'status' => 'failed',
                'message' => \ResponseMessages::OPERATOR_CREATION_FAILED
            ], HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Validate the ID
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => 'validation_error',
                'message' => 'Validation failed',
                'errors' => 'Invalid ID provided'
            ], HTTP_BAD_REQUEST);
        }

        // Validation rules
        $validator = validator($request->all(), [
            'ID' => 'string|exists:operators,id',
            'agent_number' => 'required|string|max:255',
            'operator_name' => 'required|string|max:255',
            'user_name' => 'required|string|max:255',
            'phone' => 'required|regex:/^\d{9,12}$/',
            'email' => 'required|email|max:255',
            'password' => 'sometimes|nullable',
            'line_id' => 'required|integer',
            'operator_category' => 'required|integer',
            'station_id' => 'required|integer',
            'zone' => 'required|boolean',
            'normal' => 'nullable|boolean',
            'changing_class' => 'nullable|boolean',
            'automotora' => 'nullable|boolean',
            'top_up' => 'nullable|boolean',
            'registration' => 'nullable|boolean',
            'scanning' => 'nullable|boolean',
            'incentive' => 'nullable|boolean',
        ]);

        // Handle validation errors
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json([
                'status' => 'validation_error',
                'message' => 'Validation failed',
                'errors' => $errors
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        DB::beginTransaction();
        try {
            // Find the existing operator by ID
            $operator = Operator::find($id);
            if (!$operator) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Operator not found'
                ], HTTP_NOT_FOUND);
            }

            // Update the operator fields
            $operator->agent_number = $validated['agent_number'];
            $operator->full_name = $validated['operator_name'];
            $operator->username = $validated['user_name'];
            $operator->phone = $validated['phone'];
            $operator->email = $validated['email'];

            // Update password only if provided
            if (isset($validated['password']) && $validated['password']) {
                $operator->password = Hash::make($validated['password']);
            }

            $operator->train_line_id = $validated['line_id'];
            $operator->operator_category_id = $validated['operator_category'];
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
            $operator->save();

            if (!$operator){
                Log::channel('portal')->error("Failed to update Operator with token: ". $operator->token);
                return $this->error(null, "Error on update Operator details");
            }

            DB::commit();

            return $this->success(null, "Operator updated successfully");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('pos')->info($e->getMessage());

            return response()->json([
                'status' => 'failed',
                'message' => 'Operator update failed'
            ], HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
