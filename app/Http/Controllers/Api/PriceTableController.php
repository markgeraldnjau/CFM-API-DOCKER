<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceTable;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PriceTableController extends Controller
{
    use CommonTrait, ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return $this->error(null, $errors, HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $priceTables = PriceTable::with([
                'trainLine:id,line_name',
                'trainStationStopFrom:id,station_name,distance_Maputo',
                'trainStationStopTo:id,station_name,distance_Maputo',
                'cfmClass:id,class_type'
            ])->select([
                        'id',
                        'train_line_id',
                        'train_station_stop_from',
                        'train_station_stop_to',
                        'distance',
                        'fare_charge',
                        'cfm_class_id',
                    ])->latest('id')->paginate($validator['items_per_page']);
            return response()->json($priceTables);
        } catch (\Throwable $th) {
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(["error" => $th->getMessage()]);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'train_line_id' => 'required|integer|exists:train_lines,id',
            'train_station_stop_from' => 'nullable|integer|exists:train_stations,id',
            'train_station_stop_to' => 'nullable|integer|exists:train_stations,id',
            'distance' => 'nullable|string|max:10',
            'fare_charge' => 'required|numeric|min:0|max:999999.99',
            'cfm_class_id' => 'required|integer|between:1,127',
        ]);

        DB::beginTransaction();
        try {
            $priceTable = new PriceTable();
            $priceTable->train_line_id = $validatedData['train_line_id'];
            $priceTable->train_station_stop_from = $validatedData['train_station_stop_from'];
            $priceTable->train_station_stop_to = $validatedData['train_station_stop_to'];
            $priceTable->distance = $validatedData['distance'];
            $priceTable->fare_charge = $validatedData['fare_charge'];
            $priceTable->cfm_class_id = $validatedData['cfm_class_id'];
            $priceTable->save();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully Created New Train Price'], HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'train_line_id' => 'required|integer|exists:train_lines,id',
            'train_station_stop_from' => 'nullable|integer|exists:train_stations,id',
            'train_station_stop_to' => 'nullable|integer|exists:train_stations,id',
            'distance' => 'nullable|string|max:10',
            'fare_charge' => 'required|numeric|min:0|max:999999.99',
            'cfm_class_id' => 'required|integer|between:1,127',
        ]);

        try{
            $priceTable = PriceTable::find($id);
            if ($priceTable) {
                $priceTable->train_line_id = $validatedData['train_line_id'];
                $priceTable->train_station_stop_from = $validatedData['train_station_stop_from'];
                $priceTable->train_station_stop_to = $validatedData['train_station_stop_to'];
                $priceTable->distance = $validatedData['distance'];
                $priceTable->fare_charge = $validatedData['fare_charge'];
                $priceTable->cfm_class_id = $validatedData['cfm_class_id'];
                $priceTable->update();
                return response()->json(['status' => 'success', 'message' => 'Successfully update Train Price'], HTTP_OK);
            } else {
                return response()->json(['status' => 'failed', 'message' => 'Train Price not found'], HTTP_OK);
            }
        }catch(\Exception $e){
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
