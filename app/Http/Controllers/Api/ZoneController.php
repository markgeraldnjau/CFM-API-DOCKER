<?php

namespace App\Http\Controllers\Api;


use App\Models\Zone;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponse;

class ZoneController extends Controller
{
    use ApiResponse, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $zoneTrainStations = DB::select('SELECT * FROM zones INNER JOIN zone_lists ON zone_lists.id = zones.name
            inner join cfm_classes on cfm_classes.id = zones.class_id ');
            return response()->json($zoneTrainStations);
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
        $validatedData = $request->validate([
            'zone' => 'required|string|max:9',
            'class' => 'required|integer|between:1,127',
            'offtrainprice' => 'required|numeric|min:0|max:999999.99',
            'ontrainprice' => 'required|numeric|min:0|max:999999.99',
            'pricegroup' => 'required|numeric|min:0|max:999999.99',
        ]);

        DB::beginTransaction();
        try {
            $zone = new Zone();
            $zone->name = $validatedData['zone'];
            $zone->class_id = $validatedData['class'];
            $zone->price = $validatedData['offtrainprice'];
            $zone->price_on_train = $validatedData['ontrainprice'];
            $zone->price_group = $validatedData['pricegroup'];
            $zone->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully Create New Zone Price'], HTTP_OK);
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
        $validatedData = $request->validate([
            'name' => 'required|string|max:9',
            'price' => 'required|numeric|min:0|max:999999.99',
        ]);

        try {
            $zone = Zone::find($id);
            if ($zone) {
                $zone->name = $validatedData['name'];
                $zone->price = $validatedData['price'];
                $zone->update();
                return response()->json(['status' => 'success', 'message' => 'Successfully update Zone Price'], HTTP_OK);
            } else {
                return response()->json(['status' => 'failed', 'message' => 'Zone Price not found'], HTTP_NOT_FOUND);
            }
        } catch (\Throwable $th) {
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => 'fail', 'message' => SOMETHING_WENT_WRONG]);
        }

    }

}
