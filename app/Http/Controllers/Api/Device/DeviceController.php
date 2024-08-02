<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
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
                    $query->where('devices.device_imei', 'like', "%$searchQuery%")
                        ->orWhere('devices.device_name', 'like', "%$searchQuery%")
                        ->orWhere('devices.device_type', 'like', "%$searchQuery%");
                });
            }

            $devices = $query->orderBy('device_details.id', 'DESC')->paginate($request->items_per_page);

            return response()->json($devices);
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

    public function generateUniqueCode()
    {
        $codeLength = 5;
        $tries = 0;
        $maxTries = 100; // Adjust this value as needed

        do {
            $code = str_pad(rand(0, pow(10, $codeLength) - 1), $codeLength, '0', STR_PAD_LEFT);
            $exists = DB::table('device_details')->where('device_last_token', $code)->exists();
            $tries++;
        } while ($exists && $tries < $maxTries);

        if ($tries === $maxTries) {
            // Handle case where a unique code couldn't be generated after retries
            return '000000';
        }

        return $code;
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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
            DB::commit();
            return response()->json(['status' => 'success', 'message' => DATA_SAVED], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());
            // \Log::error($e->getMessage());
            return response()->json(['status' => 'failed', 'message' => 'Failed to create device'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    public function device_details(Request $request)
    {

        // $cards = DB::table('cards')
        // ->where('id',$request->id)
        // ->get();

        $customers = $devices = Device::select(
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
        )
            ->where('id', $request->id)
            ->get();


        return response()->json($customers, 200);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $device = Device::find($id);
            if (!$device) {
                return response()->json(['status' => 'failed', 'message' => 'Device not found'], 404);
            }

            $device->device_type = $request['device_type'];
            $device->device_name = $request['device_name'];
            $device->device_imei = $request['device_imei'];
            $device->device_serial = $request['device_serial'];
            // $device->printer_BDA = $request['printer_BDA'];
            $device->device_last_token = $this->generateUniqueCode();
            $device->version = $request['version'];
            $device->activation_status = $request['activation_status'] == null ? "A" : $request['activation_status'];
            // $device->log_off = $request['log_off'];
            // $device->on_off = $request->input('on_off_ticket_id');
            // $device->allowed_ticket_sale_type = $request['operator_type_id'];
            // $device->balance_product_id = $request['balance_product_id'];
            // $device->balance_vendor_id = $request['balance_vendor_id'];
            $device->update();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Device updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['status' => 'failed', 'message' => 'Failed to update device ' . $e->getMessage()], 500);
        }

    }

    public function resetToken(Request $request)
    {
        DB::beginTransaction();

        try {
            $device = Device::find($request->id);
            if (!$device) {
                return response()->json(['status' => 'failed', 'message' => 'Device not found'], 404);
            }

            $device->device_last_token = $this->generateUniqueCode();
            $device->update();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Device updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['status' => 'failed', 'message' => 'Failed to update device ' . $e->getMessage()], 500);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
