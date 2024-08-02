<?php

namespace App\Http\Controllers\Api\Management;


use App\Http\Controllers\Controller;
use App\Models\ZoneTrainStation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZoneTrainStationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $zoneTrainStations = ZoneTrainStation::with([
                'zone:id,name',
                'trainStation:id,station_name,distance_Maputo',
            ])->select([
                        'id',
                        'zone_id',
                        'train_station_id',
                    ])->latest('id')->paginate($request->items_per_page);
            return response()->json($zoneTrainStations);
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ZoneTrainStation $zoneTrainStation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ZoneTrainStation $zoneTrainStation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ZoneTrainStation $zoneTrainStation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ZoneTrainStation $zoneTrainStation)
    {
        //
    }
}
