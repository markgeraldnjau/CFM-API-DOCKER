<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeviceController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'string', 'max:10'],
            'search_query' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return response()->json($errors, HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $searchQuery = $request->search_query;
            $query = Device::select(
                'device_details.id',
                'device_type',
                'device_name',
                'device_imei',
                'station_id',
                'on_off',
                'allowed_ticket_sale_type',
                'log_off',
                'device_serial',
                'version',
                'last_connect',
                'activation_status',
                'train_stations.station_name'
            )
                ->leftjoin('train_stations','train_stations.id','=','device_details.station_id');

            if (isset($request->status)) {
                $query->where('cards.status', $request->input('status'));
            }

            if (!empty($searchQuery)) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('device_details.device_imei', 'like', "%$searchQuery%")
                        ->orWhere('device_details.device_name', 'like', "%$searchQuery%")
                        ->orWhere('device_details.device_type', 'like', "%$searchQuery%");
                });
            }

            $devices = $query->orderBy('device_details.id', 'DESC')->paginate($request->items_per_page);

            return response()->json($devices);
        } catch (\Throwable $th) {
            Log::channel('pos')->error(json_encode($this->errorPayload($th)));
            return response()->json($th->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function generateUniqueCode(): string
    {
        $codeLength = MAX_TRIES; // Ensure this is an integer
        $tries = DEVICE_TRIES; // Ensure this is an integer
        $maxTries = MAX_TRIES; // Ensure this is an integer

        do {
            $maxValue = (int)pow(10, $codeLength) - 1; // Cast to integer
            $code = str_pad(rand(0, $maxValue), $codeLength, '0', STR_PAD_LEFT);
            $exists = DB::table('device_details')->select('id')->where('device_last_token', $code)->exists();
            $tries++;
        } while ($exists && $tries < $maxTries);

        if ($tries === $maxTries) {
            return MAX_TRIES_RETURN;
        }

        return $code;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'device_type' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
            'device_imei' => 'required|string|unique:devices,device_imei|max:255',
            'device_serial' => 'required|string|max:255',
            'printer_BDA' => 'nullable|string|max:255',
            'version' => 'nullable|string|max:255',
            'activation_status' => 'nullable|boolean',
            'train_station_id' => 'nullable|exists:train_stations,id',
            'log_off' => 'nullable|boolean',
            'on_off_ticket_id' => 'nullable|integer',
            'operator_type_id' => 'nullable|integer',
            'balance_product_id' => 'nullable|string|max:255',
            'balance_vendor_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return response()->json(['status' => 'failed', 'message' => $errors], HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $device = new Device;
            $device->device_type = $request['device_type'];
            $device->device_name = $request['device_name'];
            $device->device_imei = $request['device_imei'];
            $device->device_serial = $request['device_serial'];
            $device->printer_BDA = $request['printer_BDA'];
            $device->version = $request['version'];
            $device->activation_status = $request['activation_status'] == null ? ACTIVE_STATUS : $request['activation_status'];
            $device->station_id = $request->input('train_station_id', null);
            $device->log_off = $request['log_off'] == null ? INT_INCTIVE : $request['log_off'];
            $device->on_off = $request->input('on_off_ticket_id');
            $device->allowed_ticket_sale_type = $request['operator_type_id'];
            $device->balance_product_id = $request['balance_product_id'] == null ? "" : $request['balance_product_id'];
            $device->balance_vendor_id = $request['balance_vendor_id'] == null ? "" : $request['balance_vendor_id'];
            $device->device_last_token = $this->generateUniqueCode();
            $device->save();

            if (!$device){
                return response()->json(['status' => 'failed', 'message' => 'Something went wrong'], HTTP_INTERNAL_SERVER_ERROR);
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => DATA_SAVED], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('pos')->error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'failed', 'message' => 'Failed to create device'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => ['nullable', 'string', 'exists:device_details,id'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return response()->json($errors, HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $devices = DB::table('device_details')->select(
                'id',
                'device_type',
                'device_name',
                'device_imei',
                'station_id',
                'on_off',
                'allowed_ticket_sale_type',
                'log_off',
                'device_serial',
                'version',
                'last_connect',
                'activation_status',
                'device_last_token'
            )->where('id', $request->id)->first();

            return response()->json($devices, HTTP_OK);
        }catch (\Exception $e){
            Log::channel('portal')->error(json_encode($this->errorPayload($e)));
            return response()->json(null, HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'device_type' => 'sometimes|string|max:255',
            'device_name' => 'sometimes|string|max:255',
            'device_imei' => 'sometimes|string|max:255|unique:device_details,device_imei,'.$id,
            'device_serial' => 'sometimes|string|max:255',
            'version' => 'sometimes|string|max:255',
            'activation_status' => 'nullable|string|in:A,I',
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return response()->json(['status' => 'failed', 'message' => $errors], HTTP_UNPROCESSABLE_ENTITY);
        }

        $device = Device::find($id);
        DB::beginTransaction();
        try {
            if (!$device) {
                return response()->json(['status' => 'failed', 'message' => 'Device not found'], HTTP_NOT_FOUND);
            }

            $device->device_type = $request['device_type'];
            $device->device_name = $request['device_name'];
            $device->device_imei = $request['device_imei'];
            $device->device_serial = $request['device_serial'];
            $device->device_last_token = $this->generateUniqueCode();
            $device->version = $request['version'];
            $device->activation_status = $request['activation_status'] == null ? "A" : $request['activation_status'];
            $device->update();

            if (!$device){
                return response()->json(['status' => 'failed', 'message' => 'Failed to update device'], HTTP_INTERNAL_SERVER_ERROR);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Device updated successfully'], HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('pos')->error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'failed', 'message' => 'Failed to update device' . $e->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resetToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['nullable', 'string', 'exists:devices,id'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return response()->json($errors, HTTP_UNPROCESSABLE_ENTITY);
        }

        $device = Device::find($request->id);
        DB::beginTransaction();
        try {
            if (!$device) {
                return response()->json(['status' => 'failed', 'message' => 'Device not found'], HTTP_NOT_FOUND);
            }

            $device->device_last_token = $this->generateUniqueCode();
            $device->update();

            if (!$device){
                return response()->json(['status' => 'failed', 'message' => 'Failed to update device'], HTTP_INTERNAL_SERVER_ERROR);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Device updated successfully'], HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('pos')->error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'failed', 'message' => 'Failed to update device ' . $e->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
