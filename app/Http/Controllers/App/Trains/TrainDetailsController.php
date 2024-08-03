<?php

namespace App\Http\Controllers\App\Trains;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\TrainDirection;
use App\Models\TrainLine;
use App\Models\TrainRoute;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\JwtTrait;
use App\Traits\Mobile\MobileAppTrait;
use App\Traits\Mobile\TicketTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TrainDetailsController extends Controller
{
    //
    use MobileAppTrait, ApiResponse, AuditTrail, JwtTrait, TicketTrait;

    public function getTrainLines(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $trainLines = TrainLine::select('id', 'line_code', 'line_name')->latest('id')->get();

            $this->auditLog("Mobile App Get Train Lines for ". $customer->full_name, PORTAL, null, null);
            return $this->success($trainLines, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getTrainRoutes(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        $validator = Validator::make($request->all(), [
            'train_line_id' => 'required|exists:train_lines,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $trainRoutes = TrainRoute::select('train_routes.id', 'train_routes.id', 'train_routes.route_name', 'train_routes.route_direction', 'tl.line_name')
                ->join('train_lines as tl', 'tl.id', 'train_routes.train_line_id')
                ->where('tl.id', $request->train_line_id)
                ->get();

            $this->auditLog("Mobile App Get Train Routes for ". $customer->full_name, PORTAL, null, null);
            return $this->success($trainRoutes, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getTrainDirections(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $trainDirection = TrainDirection::select('id', 'code', 'name')->get();

            $this->auditLog("Mobile App Get Train Directions for ". $customer->full_name, PORTAL, null, null);
            return $this->success($trainDirection, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getTrainLineStops(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        $validator = Validator::make($request->all(), [
            'train_line_id' => 'required|exists:train_lines,id',
        ]);

        if ($validator->fails()) {

            throw new ValidationException($validator);

        }
        try {

            $trainStops = DB::table('train_stations as ts')->join('train_lines as tl', 'tl.id', 'ts.line_id')
                ->select(
                    'ts.id',
                    'ts.station_name',
                    'ts.station_name_erp',
                    'ts.station_type_erp',
                    'ts.longitude',
                    'ts.latitude',
                    'ts.distance_maputo',
                    'ts.line_id',
                    'ts.frst_class',
                    'ts.sec_class',
                    'ts.thr_class',
                )->where('ts.line_id', $request->train_line_id)
                ->orderBy('ts.id')
                ->get();

            $this->auditLog("Mobile App Get Train Line Stops for ". $customer->full_name, PORTAL, null, null);
            return $this->success($trainStops, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    public function getAvailableTrains(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        $validator = Validator::make($request->all(), [
            'train_line_id' => 'required|exists:train_lines,id',
            'train_route_id' => 'required|exists:train_routes,id',
            'start_station_id' => 'required|exists:train_stations,id',
            'stop_station_id' => 'required|exists:train_stations,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'week_day' => 'required|integer|between:1,7',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);

        }

        try {

            $trainRoute = DB::table('train_routes as tr')->join('train_directions as td', 'td.id', 'tr.train_direction_id')
                ->select('tr.route_name', 'td.code as direction_code')->where('tr.id', $request->train_route_id)->whereNull('tr.deleted_at')->first();

            $query = DB::table('trains as t')
                ->join('train_routes as tr', 'tr.id', 't.route_id')
                ->join('train_directions as td', 'td.id', 'tr.train_direction_id')
                ->join('train_lines as tl', 'tl.id', 'tr.train_line_id')
                ->join('train_stations as start_station', 'start_station.id', 't.start_stop_id')
                ->join('train_stations as destination_station', 'destination_station.id', 't.end_stop_id')
                ->join('train_layouts as layout', 'layout.train_id', 't.id')
                ->join('schedules as s', 's.train_id', 't.id')
                ->select(
                    's.id as schedule_id',
                    't.id as train_id',
                    't.train_number',
                    'start_station.station_name as start_station_name',
                    'start_station.id as start_station_id',
                    'destination_station.station_name as destination_station_name',
                    'destination_station.id as destination_station_id',
                    't.train_first_class',
                    't.train_second_class',
                    't.train_third_class',
                    'layout.total_seats',
                    't.travel_hours_duration',
                    't.zone_one',
                    't.zone_two',
                    't.zone_three',
                    't.zone_four',
                    'td.code',
                    't.route_id',
                    'tr.train_line_id',
                    's.departure_time',
                    's.est_destination_time',
                    's.day_of_the_week',
                    DB::raw("'".$request->booking_date."' as booking_date")
                )->where('t.route_id', $request->train_route_id);


                if ($trainRoute->direction_code == ASC){
                    $query->where('start_station.id', '>=', $request->start_station_id)
                        ->where('destination_station.id', '>=', $request->stop_station_id);

                } else if ($trainRoute->direction_code == DESC){
                    $query->where('start_station.id', '>=', $request->start_station_id)
                        ->where('destination_station.id', '<=', $request->stop_station_id);

                }

                $trainStops = $query->get();

            $this->auditLog("Mobile App Get Train Line Stops for ". $customer->full_name, PORTAL, null, null);
            return $this->success($trainStops, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {

            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
