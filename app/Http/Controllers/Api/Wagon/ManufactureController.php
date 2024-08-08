<?php

namespace App\Http\Controllers\Api\Wagon;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wagon\UpdateWagonManufactureRequest;
use App\Http\Requests\Wagon\WagonManufactureRequest;
use App\Models\Wagon\WagonManufacture;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManufactureController extends Controller
{
    use ApiResponse, CommonTrait, AuditTrail, checkAuthPermsissionTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
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
            $query = WagonManufacture::select('id', 'token', 'name');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('name', 'like', "%$searchQuery%");
                });
            }
            $wagonManufactures = $query->orderByDesc('updated_at')->paginate($itemPerPage);

            $this->auditLog("View Wagon Manufactures", PORTAL, null, null);
            return $this->success($wagonManufactures, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(WagonManufactureRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'name' => $request->name,
            ];
            $manufacture = WagonManufacture::create($payload);
            $this->auditLog("Create wagon manufacture: " . $request->name, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($manufacture, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $token)
    {
        if (is_null($token) ) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        try {
            $manufacture = WagonManufacture::where('token', $token)->firstOrFail();

            if (!$manufacture) {
                throw new RestApiException(404, 'No wagon manufacture found!');
            }

            $this->auditLog("View Wagon Manufacture: " . $manufacture->name, PORTAL, null, null);
            return $this->success($manufacture, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWagonManufactureRequest $request, $token)
    {
        DB::beginTransaction();
        try {
            $manufacture = WagonManufacture::query()
                ->where('token', $token)
                ->firstOrFail();

            $oldData = clone $manufacture;
            $payload = [
                'name' => $request->name,
            ];

            $manufacture->update($payload);
            $this->auditLog("Wagon Manufacture: " . $request->name, PORTAL, $oldData, $payload);
            DB::commit();
            return $this->success($manufacture, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        try {
            $manufacture = WagonManufacture::findOrFail($id);
            $manufacture->delete();
            $this->auditLog("Delete Wagon Manufacture: " . $manufacture->name, PORTAL, null, null);
            return $this->success(null, DATA_DELETED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
