<?php

namespace App\Http\Controllers\Api;


use App\Models\Zone;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Exceptions\RestApiException;
use App\Traits\ApiResponse;

class ZoneController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $zoneTrainStations = DB::select('SELECT * FROM zones INNER JOIN zone_lists ON zone_lists.id = zones.name
            inner join cfm_classes on cfm_classes.id = zones.class_id ');
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
        DB::beginTransaction();
        try {
            $zone = new Zone();
            $zone->name = $request->zone;
            $zone->class_id = $request->class;
            $zone->price = $request->offtrainprice;
            $zone->price_on_train = $request->ontrainprice;
            $zone->price_group = $request->pricegroup;
            $zone->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully Create New Zone Price'], 200);
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
    public function show(Zone $zone)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Zone $zone)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Zone $zone, $id)
    {
        $zone = Zone::find($id);
        try {
            if ($zone) {
                $zone->name = $request->name;
                $zone->price = $request->price;
                $zone->update();
                return response()->json(['status' => 'success', 'message' => 'Successfully update Zone Price'], 200);
            } else {
                return response()->json(['status' => 'failed', 'message' => 'Zone Price not found'], 200);
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::info('error message on update is ' . $th->getMessage());
            return response()->json(['status' => 'fail', 'message' => 'Something went wrong on update zone price']);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Zone $zone)
    {
        //
    }
}
