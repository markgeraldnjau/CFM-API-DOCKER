<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CfmClass;
use App\Models\PriceTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\RestApiException;
use App\Traits\ApiResponse;

class PriceTableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

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
                    ])->latest('id')->paginate($request->items_per_page);
            return response()->json($priceTables);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json(["error" => $th->getMessage()]);
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
        Log::info($request->all());
        DB::beginTransaction();
        try {
            $priceTable = new PriceTable();
            $priceTable->train_line_id = $request->train_line_id;
            $priceTable->train_station_stop_from = $request->train_station_stop_from;
            $priceTable->distance = $request->distance;
            $priceTable->train_station_stop_to = $request->train_station_stop_to;
            $priceTable->fare_charge = $request->fare_charge;
            $priceTable->cfm_class_id = $request->cfm_class_id;
            $priceTable->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully Create New Train Price'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            //throw $th;
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], 200);

        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $Request)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $Request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $priceTable = PriceTable::find($id);
        if ($priceTable) {
            $priceTable->train_line_id = $request->train_line_id;
            $priceTable->train_station_stop_from = $request->train_station_stop_from;
            $priceTable->distance = $request->distance;
            $priceTable->train_station_stop_to = $request->train_station_stop_to;
            $priceTable->fare_charge = $request->fare_charge;
            $priceTable->cfm_class_id = $request->cfm_class_id;
            $priceTable->update();
            return response()->json(['status' => 'success', 'message' => 'Successfully update Train Price'], 200);
        } else {
            return response()->json(['status' => 'failed', 'message' => 'Train Price not found'], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $Request, $id)
    {
        //
    }
}
