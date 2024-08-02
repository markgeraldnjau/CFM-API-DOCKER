<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cargo\CargoCategoryRequest;
use App\Http\Requests\Cargo\UpdateCargoCategoryRequest;
use App\Models\Cargo\CargoCategory;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargoCategoryController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        //
        try {
            $query = CargoCategory::select('id', 'token', 'name');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('name', 'like', "%$searchQuery%");
                });
            }
            $cargoCategories = $query->orderByDesc('updated_at')->paginate($itemPerPage);

            if (!$cargoCategories) {
                throw new RestApiException(404, 'No cargo category found!');
            }
            $this->auditLog("View Cargo Categories", PORTAL, null, null);
            return $this->success($cargoCategories, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function defaultCargoCategories()
    {
        try {
            $cargoCustomers = CargoCategory::select('id', 'code', 'name')->whereNot('code', '0')->get();

//            if (!$cargoCustomers) {
//                return $this->error(404, 'No default cargo categories found!');
//            }

            return $this->success($cargoCustomers, DATA_RETRIEVED);
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
    public function store(CargoCategoryRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'name' => $request->name,
            ];

            $category = CargoCategory::create($payload);
            $this->auditLog("Create cargo category: ". $request->name, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($category, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            $category = CargoCategory::findOrFail($id);

            if (!$category) {
                return $this->error(null, 'No cargo category found!', 404);
            }

            $this->auditLog("View Cargo Category: ". $category->name, PORTAL, null, null);
            return $this->success($category, DATA_RETRIEVED);
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
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCargoCategoryRequest $request, string $categoryId)
    {
        //
        DB::beginTransaction();
        try {
            $category = CargoCategory::findOrFail($categoryId);
            $oldData = clone $category;
            $payload = [
                'name' => $request->name,
            ];

            $category->update($payload);

            $this->auditLog("Update Cargo Category: ". $request->name, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($category, DATA_UPDATED);
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
    public function destroy(string $id){
        //
        try {
            $category = CargoCategory::findOrFail($id);

            if ($category->cargoTransactionItems->count()){
                return $this->error(null, RELATED_DATA_ERROR);
            }
            $category->delete();
            $this->auditLog("Delete Cargo Category: ". $category->name, PORTAL, null, null);
            return $this->success(null, DATA_DELETED);
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
}
