<?php

namespace App\Http\Controllers\Api\Wagon;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wagon\UpdateWagonLayoutRequest;
use App\Http\Requests\Wagon\WagonLayoutRequest;
use App\Models\TrainWagonClass;
use App\Models\Wagon;
use App\Models\Wagon\Seat;
use App\Models\Wagon\WagonLayout;
use App\Models\Wagon\WagonManufacture;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WagonLayoutController extends Controller
{
    use ApiResponse,CommonTrait, AuditTrail, checkAuthPermsissionTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $validator = validator($request->all(), [
            'search_query' => 'nullable|string|max:255',
            'item_per_page' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        $searchQuery = strip_tags($request->input('search_query'));
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = WagonLayout::select(
                'wagon_layouts.id',
                'wagon_layouts.token',
                'wagon_layouts.name',
                'wagon_layouts.label',
                'wagon_layouts.class_id',
                'wagon_layouts.seat_type',
                'st.name as seat_type_name',
                'c.class_type',
                'wagon_layouts.manufacture_id',
                'wm.name as manufacture_name',
                'wagon_layouts.normal_seats',
                'wagon_layouts.standing_seats',
                'wagon_layouts.seat_rows',
                'wagon_layouts.seat_columns',
                'wagon_layouts.aisle_interval',
                'wagon_layouts.seat_generated',
            )
                ->join('cfm_classes as c', 'c.id', 'wagon_layouts.class_id')
                ->join('seat_types as st', 'st.code', 'wagon_layouts.seat_type')
                ->join('wagon_manufactures as wm', 'wm.id', 'wagon_layouts.manufacture_id');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('wagon_layouts.name', 'like', "%$searchQuery%")
                        ->orWhere('wm.name', 'like', "%$searchQuery%");
                });
            }
            $wagonLayouts = $query->orderByDesc('wagon_layouts.updated_at')->paginate($itemPerPage);

            if (!$wagonLayouts) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No wagon layouts found!');
            }
            $this->auditLog("View Wagon Layouts", PORTAL, null, null);
            return $this->success($wagonLayouts, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(WagonLayoutRequest $request)
    {


        DB::beginTransaction();
        try {
            $class = TrainWagonClass::findOrFail($request->class_id, ['name']);
            $manufacture = WagonManufacture::findOrFail($request->manufacture_id, ['name']);
            $payload = [
                'name' => $request->name,
                'label' => $request->label,
                'class_id' => $request->class_id,
                'manufacture_id' => $request->manufacture_id,
                'seat_type' => $request->seat_type,
                'normal_seats' => $request->normal_seats,
                'standing_seats' => $request->standing_seats,
                'total_seats' => $request->normal_seats + $request->standing_seats,
                'seat_rows' => $request->seat_rows,
                'seat_columns' => $request->seat_columns,
                'aisle_interval' => $request->aisle_interval ?? 0,
            ];
            $wagonLayout = WagonLayout::create($payload);
            if(!$wagonLayout){
                DB::rollBack();
                return $this->success($wagonLayout, SOMETHING_WENT_WRONG);
            }
             $this->auditLog("Create Wagon Layouts: ". $manufacture->name .'-'. $class->name, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($wagonLayout, DATA_SAVED);
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $token)
    {
        if (is_null($token)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        try {
            $wagonLayout = WagonLayout::where('wagon_layouts.token', $token)->select(
                'wagon_layouts.id',
                'wagon_layouts.token',
                'wagon_layouts.name',
                'wagon_layouts.label',
                'wagon_layouts.class_id',
                'wagon_layouts.seat_type',
                'st.name as seat_type_name',
                'c.name as class_name',
                'wagon_layouts.manufacture_id',
                'wm.name as manufacture_name',
                'wagon_layouts.normal_seats',
                'wagon_layouts.standing_seats',
                'wagon_layouts.seat_rows',
                'wagon_layouts.seat_columns',
                'wagon_layouts.aisle_interval',
                'wagon_layouts.aisle_interval',
                'wagon_layouts.seat_generated',
                'wagon_layouts.created_at',
                'wagon_layouts.updated_at',
            )
                ->join('train_wagon_class as c', 'c.id', 'wagon_layouts.class_id')
                ->join('seat_types as st', 'st.code', 'wagon_layouts.seat_type')
                ->join('wagon_manufactures as wm', 'wm.id', 'wagon_layouts.manufacture_id')->firstOrFail();

            $wagons = Wagon::select(
                'id',
                'token',
                'model',
                'serial_number'
            )->where('layout_id', $wagonLayout->id)->get();

            $seats = Seat::select('id', 'token', 'seat_number', 'row', 'column', 'number', 'has_aisle')->where('wagon_layout_id', $wagonLayout->id)
                ->orderBy('row')->get();

            if (!$wagonLayout) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No wagon layouts found!');
            }

            $data = [
                'wagon_layouts' => $wagonLayout,
                'wagons' => $wagons,
                'seats' => $seats,
            ];

             $this->auditLog("View Wagon Layouts: ". $wagonLayout->manufacture_name .'-'. $wagonLayout->class_name, PORTAL, null, null);
            return $this->success($data, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWagonLayoutRequest $request, string $wagonLayoutId)
    {
        if (is_null($wagonLayoutId)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        DB::beginTransaction();
        try {
            $wagonLayout = WagonLayout::findOrFail($wagonLayoutId);
            $class = TrainWagonClass::findOrFail($wagonLayout->class_id, ['name']);
            $manufacture = WagonManufacture::findOrFail($wagonLayout->manufacture_id, ['name']);
            $oldData = clone $wagonLayout;
            $payload = [
                'name' => $request->name,
                'label' => $request->label,
                'class_id' => $request->class_id,
                'manufacture_id' => $request->manufacture_id,
                'seat_type' => $request->seat_type,
                'normal_seats' => $request->normal_seats,
                'standing_seats' => $request->standing_seats,
                'total_seats' => $request->normal_seats + $request->standing_seats,
                'seat_rows' => $request->seat_rows,
                'seat_columns' => $request->seat_columns,
                'aisle_interval' => $request->aisle_interval,
            ];
            $wagonLayout->update($payload);

             $this->auditLog("Wagon Layouts: ". $manufacture->name .'-'. $class->name, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($wagonLayout, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($token)
    {
        if (is_null($token)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        try {
            $wagonLayout = WagonLayout::where('token', $token)->firstOrFail();
            if ($wagonLayout->wagons){
                return $this->error(null, "Coach Layout is used by some coaches.");
            }
            $wagonLayout->seats()->delete();
            $wagonLayout->delete();
            $this->auditLog("Delete Train Schedule: ". $wagonLayout->name, PORTAL, $wagonLayout, null);
            return $this->success(null, DATA_DELETED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
