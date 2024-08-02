<?php

namespace App\Http\Controllers\Api\Wagon;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wagon\UpdateWagonRequest;
use App\Http\Requests\Wagon\WagonRequest;
use App\Http\Resources\UserResource;
use App\Models\Wagon;
use App\Models\Wagon\WagonManufacture;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainWagonController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;

    public function index(Request $request)
    {

        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = Wagon::select(
                    'wagons.id',
                    'wagons.token',
                    'wagons.model',
                    'wagons.serial_number',
                    'cc.class_type',
                    'twt.name as wagon_type',
                    'st.name as seat_type_name',
                    'wl.normal_seats',
                    'wl.standing_seats',
                    'wl.total_seats',
                )
                ->join('train_wagon_types as twt', 'twt.id', 'wagons.wagon_type_id')
                ->join('wagon_layouts as wl', 'wl.id', 'wagons.layout_id')
                ->join('cfm_classes as cc', 'cc.id', 'wl.class_id')
                ->join('wagon_manufactures as wm', 'wm.id', 'wl.manufacture_id')
                ->join('seat_types as st', 'st.code', 'wl.seat_type');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('name', 'like', "%$searchQuery%");
                });
            }
            $wagon = $query->orderByDesc('wagons.updated_at')->paginate($itemPerPage);

            if (!$wagon) {
                return $this->error(null, "No wagon found", 404);
            }
            $this->auditLog("View Wagons", PORTAL, null, null);
            return $this->success($wagon, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function allWagons()
    {
        try {
            $wagons = Wagon::select(
                'wagons.id',
                'wagons.token',
                'wagons.model',
                'wagons.serial_number',
                'wl.normal_seats',
                'wl.standing_seats',
            )->join('wagon_layouts as wl', 'wl.id', 'wagons.layout_id')
                ->whereNotIn('wagons.id', function($query) {
                    $query->select('wagon_id')
                        ->from('train_wagons')
                        ->whereNull('deleted_at');
                })->get();

            if (!$wagons) {
                throw new RestApiException(404, 'No wagon found!');
            }
            $this->auditLog("View unused wagons", PORTAL, null, null);
            return $this->success($wagons, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(WagonRequest $request)
    {
        DB::beginTransaction();
        try {

            $payload = [
                'model' => $request->model,
                'serial_number' => $request->serial_number,
                'wagon_type_id' => $request->wagon_type_id,
                'layout_id' => $request->layout_id,
            ];
            $wagon = Wagon::create($payload);
              $this->auditLog("Create Wagon: ". $wagon->model .'-'. $wagon->serial_number, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($wagon, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $token)
    {
        //
        try {
            $wagon = Wagon::where('wagons.token', $token)->select(
                        'wagons.id',
                        'wagons.token',
                        'wagons.model',
                        'wagons.serial_number',
                        'cc.class_type',
                        'twt.name as wagon_type',
                        'st.name as seat_type_name',
                        'wl.normal_seats',
                        'wl.standing_seats',
                    )
                ->join('train_wagon_types as twt', 'twt.id', 'wagons.wagon_type_id')
                ->join('wagon_layouts as wl', 'wl.id', 'wagons.layout_id')
                ->join('cfm_classes as cc', 'cc.id', 'wl.class_id')
                ->join('wagon_manufactures as wm', 'wm.id', 'wl.manufacture_id')
                ->join('seat_types as st', 'st.code', 'wl.seat_type')->firstOrFail();

            if (!$wagon) {
                throw new RestApiException(404, 'No seat configurations found!');
            }

            $this->auditLog("View Seat Configurations: ". $wagon->model .'-'. $wagon->serial_number, PORTAL, null, null);
            return $this->success($wagon, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $wagon = Wagon::find($id);
        if (!$wagon) {
            return response()->json(['message' => 'wagon not found'], 404);
        }
        return response()->json($wagon, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWagonRequest $request, string $wagonId)
    {
        //
        DB::beginTransaction();
        try {
            $wagon = Wagon::findOrFail($wagonId);
            $oldData = clone $wagon;
            $payload = [
                'model' => $request->model,
                'serial_number' => $request->serial_number,
                'wagon_type_id' => $request->wagon_type_id,
                'layout_id' => $request->layout_id,
            ];
            $wagon->update($payload);

              $this->auditLog("Update Coach : ". $wagon->model .'-'. $wagon->serial_number, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($wagon, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
