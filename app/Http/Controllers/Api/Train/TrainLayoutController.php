<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Train\TrainLayoutRequest;
use App\Http\Requests\Train\UpdateTrainLayoutRequest;
use App\Models\Train;
use App\Models\Train\TrainLayout;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainLayoutController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = TrainLayout::select(
                'train_layouts.id',
                't.train_name',
                't.train_number',
                'train_layouts.total_wagons',
                'train_layouts.total_normal_seats',
                'train_layouts.total_standing_seats',
                'train_layouts.total_seats',
                'train_layouts.created_at',
                'train_layouts.updated_at',
            )
                ->join('trains as t', 't.id', 'train_layouts.train_id');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('t.train_name', 'like', "%$searchQuery%");
                });
            }
            $trainLayout = $query->orderByDesc('train_layouts.updated_at')->paginate($itemPerPage);

            $this->auditLog("View Train layouts", PORTAL, null, null);
            return $this->success($trainLayout, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function allTrainLayouts(): \Illuminate\Http\JsonResponse
    {
        //
        try {
            $trainLayouts = TrainLayout::select(
                'train_layouts.id',
                't.train_name',
                't.train_number',
                'train_layouts.total_wagons',
                'train_layouts.total_normal_seats',
                'train_layouts.total_standing_seats',
                'train_layouts.total_seats',
                'train_layouts.created_at',
                'train_layouts.updated_at',
            )->join('trains as t', 't.id', 'train_layouts.train_id')->get();

            if (!$trainLayouts) {
                throw new RestApiException(404, 'No train layout found!');
            }
            $this->auditLog("View Train Layouts", PORTAL, null, null);
            return $this->success($trainLayouts, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(TrainLayoutRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $train = Train::findOrFail($request->train_id, ['id', 'train_number']);
            $payload = [
                'train_id' => $request->train_id,
            ];
            $trainLayout = TrainLayout::updateOrCreate($payload);

            // Save Wagons
            $numberOfNormalSeats = 0;
            $numberOfStandingSeats = 0;
            foreach ($request->wagons as $wagon) {
                $wagonSeats = DB::table('wagons as w')->join('wagon_layouts as wl', 'wl.id', 'w.layout_id')
                    ->where('w.id', $wagon['wagon_id'])
                    ->select('normal_seats', 'standing_seats')->first();
                $numberOfNormalSeats += $wagonSeats->normal_seats;
                $numberOfStandingSeats += $wagonSeats->standing_seats;
                $payload = [
                    'train_layout_id' => $trainLayout->id,
                    'wagon_id' => $wagon['wagon_id'],
                ];
                $trainWagon = Train\TrainWagon::updateOrCreate($payload, $payload);

                if (!$trainWagon){
                    return $this->error(null, "Failed to save wagon data, please try again!");
                }
            }
            $numberOfWagons = count($request->wagons);
            $trainLayout->update([
                'total_wagons' => $numberOfWagons,
                'total_normal_seats' => $numberOfNormalSeats,
                'total_standing_seats' => $numberOfStandingSeats,
                'total_seats' => $numberOfStandingSeats + $numberOfNormalSeats,
            ]);
            $this->auditLog("Create Train Layout: ". $train->train_number, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($trainLayout, DATA_SAVED);
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
     * Display the specified resource.
     */
    public function show(string $token)
    {
        //
        try {
            $trainLayout = DB::table('train_layouts as tl')->select(
                'tl.id',
                't.train_name',
                't.train_number',
                'tl.total_wagons',
                'tl.total_normal_seats',
                'tl.total_standing_seats',
                'tl.total_seats',
                'tl.created_at',
                'tl.updated_at',
            )->join('trains as t', 't.id', 'tl.train_id')->where('tl.token', $token)->whereNull('tl.deleted_at')->first();

            $trainWagons = DB::table('train_wagons as tw')
                ->join('wagons as w', 'w.id', 'tw.wagon_id')
                ->join('wagon_layouts as wl', 'wl.id', 'w.layout_id')
                ->select(
                    'w.id as wagon_id',
                    'w.token as wagon_token',
                    'w.model',
                    'w.serial_number',
                    'wl.normal_seats',
                    'wl.standing_seats',
                )->where('tw.train_layout_id', $trainLayout->id)->whereNull('tw.deleted_at')->get();

            $data = [
                'train_layout' => $trainLayout,
                'train_wagons' => $trainWagons,
            ];
            $this->auditLog("View Train Layouts", PORTAL, null, null);
            return $this->success($data, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
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
    public function update(UpdateTrainLayoutRequest $request, string $trainLayoutId)
    {
        //
        DB::beginTransaction();
        try {
            $trainLayout = TrainLayout::findOrFail($trainLayoutId);
            $oldData = clone $trainLayout;

            //delete Removed Wagons
            $existingWagonIds = array_column($request->existing_wagons, 'wagon_id');

            if (Train\TrainWagon::whereNotIn('wagon_id', $existingWagonIds)->where('train_layout_id', $request->id)->exists()){
                $deleteWagons = Train\TrainWagon::whereNotIn('wagon_id', $existingWagonIds)->where('train_layout_id', $request->id)->delete();
                if (!$deleteWagons){
                    return $this->error(null, "Failed to update, existing wagons!");
                }
            }

            foreach ($request->wagons as $wagon) {
                $payload = [
                    'train_layout_id' => $trainLayout->id,
                    'wagon_id' => $wagon['wagon_id'],
                ];
                $trainWagon = Train\TrainWagon::updateOrCreate($payload, $payload);

                if (!$trainWagon){
                    return $this->error(null, "Failed to save wagon data, please try again!");
                }
            }

            $wagonData = DB::table('train_wagons as tw')
                ->join('wagons as w', 'w.id', 'tw.wagon_id')
                ->join('wagon_layouts as wl', 'wl.id', 'w.layout_id')
                ->select(
                    DB::raw('COUNT(tw.id) as total_wagons'),
                    DB::raw('SUM(wl.normal_seats) as total_normal_seats'),
                    DB::raw('SUM(wl.standing_seats) as total_standing_slots'),
                    DB::raw('SUM(wl.normal_seats + wl.standing_seats) as total_seats')
                )
                ->where('tw.train_layout_id', $trainLayout->id)
                ->whereNull('tw.deleted_at')
                ->first();

            $trainLayout->update([
                'total_wagons' => $wagonData->total_wagons,
                'total_normal_seats' => $wagonData->total_normal_seats,
                'total_standing_seats' => $wagonData->total_standing_slots,
                'total_seats' => $wagonData->total_seats,
            ]);

            $this->auditLog("Update Train Layout for Train: ". $trainLayout->train_id, PORTAL, $oldData, $trainLayout);

            DB::commit();
            return $this->success($trainLayout, DATA_UPDATED);
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
     */
    public function destroy(string $id)
    {
        //
    }
}
