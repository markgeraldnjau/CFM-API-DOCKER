<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\LineRoute;
use App\Models\TrainDirection;
use App\Models\TrainLine;
use App\Models\TrainRoute;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainRouteController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */

     public function trainRoutesApi(){
        try {
            $lineRoute = TrainRoute::select('id', 'route_name', 'route_direction')->get();

            // Check if any branches were found
            if (!$lineRoute) {
                throw new RestApiException(404, 'No train line found!');
            }

            return $this->success($lineRoute, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
     }

    public function index(Request $request)
    {
        //
        try {
            // $trainRoutes = LineRoute::with(['trainLine:id,line_name,line_code,line_distance,region_id', 'farePriceCategory:id,price_formula,more', 'direction:id,name'])->latest('id')
            //     ->paginate($request->items_per_page);
                $trainRoutes = DB::table('train_routes')
                ->join('train_lines' ,'train_lines.id','=','train_routes.train_line_id')
                ->get();

            // Check if any branches were found
            // if (!$trainRoutes) {
            //     throw new RestApiException(404, 'No train line found!');
            // }

            // return $this->success($trainLines, DATA_RETRIEVED);
            return response()->json($trainRoutes);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    private function getStationNameById(int $stationId): ?string
{
    $stationName = DB::table('train_stations')
        ->where('id', $stationId)
        ->value('station_name');

    return $stationName;
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Extracting and preparing data from the request
            $start_station_id = $request->input('start_station');
            $end_station_id = $request->input('end_station');

            // Fetching station names
            $start_station_name = $this->getStationNameById($start_station_id);
            $end_station_name = $this->getStationNameById($end_station_id);

            // Parsing station names
            $start_station = explode(' ', $start_station_name);
            $end_station = explode(' ', $end_station_name);
            $start_station_name = $start_station[1] ?? '';
            $end_station_name = $end_station[1] ?? '';

            // Determining the direction
            $direction = $start_station_id > $end_station_id ? '2' : '1';
            $direction_name = TrainDirection::find($direction)->name;

            // Creating a new TrainRoute instance
            $trainRoute = new TrainRoute();
            $trainRoute->route_name = "Route {$start_station_name} - {$end_station_name}";
            $trainRoute->route_direction = $direction_name;
            $trainRoute->train_direction_id = $direction;
            $trainRoute->train_line_id = $request->input('line_name');
            $trainRoute->save();

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Successfully created Train Route'], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Failed to create Train Route:', ['error' => $th->getMessage()]);

            return response()->json(['status' => 'failed', 'message' => 'Something went wrong while creating Train Route'], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            $trainLine = TrainLine::findOrFail($id, ['id', 'line_code', 'line_name']);

            if (!$trainLine) {
                throw new RestApiException(404, 'No train line found!');
            }
            return $this->success($trainLine, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $trainRoute = TrainRoute::find($id);
        if ($trainRoute) {
            DB::beginTransaction();
            try {
                $trainRoute->route_name = $request->route_name;
                $trainRoute->route_direction = TrainDirection::find($request->direction)->name;
                $trainRoute->train_direction_id = $request->direction;
                $trainRoute->train_line_id = $request->line_name;
                $trainRoute->fare_price_category_id = $request->price_formula;
                $trainRoute->update();
                DB::commit();
                return response()->json(['status' => 'success', 'message' => 'Successfully update Train Route'], 200);
            } catch (\Throwable $th) {
                //throw $th;
                Log::error($th->getMessage());
                return response()->json(['status' => 'failed', 'message' => $th->getMessage()], 200);
            }

        } else {
            return response()->json(['status' => 'failed', 'message' => 'Train Route not found'], 200);
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
