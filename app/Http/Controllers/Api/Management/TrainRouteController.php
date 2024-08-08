<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainDirection;
use App\Models\TrainLine;
use App\Models\TrainRoute;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainRouteController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */

    public function trainRoutesApi()
    {
        try {
            $lineRoute = TrainRoute::select('id', 'route_name', 'route_direction')->get();

            // Check if any branches were found
            if (!$lineRoute) {
                throw new RestApiException(404, 'No train line found!');
            }

            return $this->success($lineRoute, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function index()
    {
        try {
            $trainRoutes = DB::table('train_routes')
                ->join('train_lines', 'train_lines.id', '=', 'train_routes.train_line_id')
                ->select([
                    'train_routes.id',
                    'train_routes.route_name',
                    'train_routes.route_direction',
                    'train_lines.id as train_line_id',
                    'train_lines.line_name',
                    'train_lines.line_code',
                    'train_lines.line_distance',
                    'train_lines.region_id'
                ])
                ->get();

            return response()->json($trainRoutes);

        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            return response()->json(['error' => $errorMessage], $statusCode);
        }
    }


    private function getStationNameById(int $stationId): ?string
    {
        if (is_null($stationId) || !is_numeric($stationId)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
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
        $validator = validator($request->all(), [
            'line_name' => 'required|integer',
            'start_station' => 'required|integer',
            'end_station' => 'required|integer',
            'direction' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::beginTransaction();
        try {
            $start_station_id = $request->input('start_station');
            $end_station_id = $request->input('end_station');

            // Fetching station names
            $start_station_name = $this->getStationNameById($start_station_id);
            $end_station_name = $this->getStationNameById($end_station_id);

            // Parsing station names
            $start_station = explode(' ', $start_station_name);
            $end_station = explode(' ', $end_station_name);
            $start_station = isset($start_station[1]) ? $start_station[1] : '';
            $end_station = isset($end_station[1]) ? $end_station[1] : '';
            $direction = $request->direction;
            if ($start_station_id > $end_station_id) {
                $direction = '2';
            } else {
                $direction = '1';
            }

            $trainRoute = new TrainRoute();
            $trainRoute->route_name = "Route " . $start_station . " - " . $end_station;
            $trainRoute->route_direction = TrainDirection::find($direction)->name;
            $trainRoute->train_direction_id = $direction;
            $trainRoute->train_line_id = $request->line_name;
            $trainRoute->save();

            DB::commit();

            return response()->json(['status' => SUCCESS_RESPONSE, 'message' => DATA_SAVED], HTTP_OK);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => FAILED, 'message' => SOMETHING_WENT_WRONG], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }

        try {
            $trainLine = TrainLine::findOrFail($id, ['id', 'line_code', 'line_name']);

            if (!$trainLine) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train line found!');
            }
            return $this->success($trainLine, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        $validator = validator($request->all(), [
            'route_name' => 'required|integer',
            'line_name' => 'required|integer',
            'price_formula' => 'required|integer',
            'direction' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $trainRoute = TrainRoute::findOrFail($id);
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
                return response()->json(['status' => SUCCESS_RESPONSE, 'message' => DATA_UPDATED], HTTP_OK);
            } catch (\Throwable $th) {
                Log::error(json_encode($this->errorPayload($th)));
                return response()->json(['status' => 'failed', 'message' => $th->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
            }

        } else {
            return response()->json(['status' => 'failed', 'message' => NOT_FOUND], HTTP_NOT_FOUND);
        }
    }


}
