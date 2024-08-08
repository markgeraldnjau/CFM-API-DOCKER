<?php

namespace App\Http\Controllers\Api\Management;


use App\Http\Controllers\Controller;
use App\Models\ZoneTrainStation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\CommonTrait;

class ZoneTrainStationController extends Controller
{
    use CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = validator($request->all(), [
            "items_per_page" => "nullable|numeric",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
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
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(["error" => $th->getMessage()]);
        }
    }


}
