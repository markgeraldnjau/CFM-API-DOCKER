<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cargo\CargoSubCategoryRequest;
use App\Http\Requests\Cargo\UpdateCargoSubCategoryRequest;
use App\Models\Cargo\CargoCategory;
use App\Models\Cargo\CargoSubCategory;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargoSubCategoryController extends Controller
{
    use ApiResponse, AuditTrail;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $token = $request->input('token');
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            if (!$token){
                return $this->error(null, "Invalid category");
            }

            $query = DB::table('cargo_sub_categories as csb')->select('csb.id', 'csb.token', 'csb.name')
                ->join('cargo_categories as cc', 'cc.id', 'csb.category_id')
                ->where('cc.token', $token);

            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('csb.name', 'like', "%$searchQuery%");
                });
            }
            $subCategories = $query->orderByDesc('csb.updated_at')->whereNull('csb.deleted_at', )->paginate($itemPerPage);

            $cargoCategory = CargoCategory::where('token', $token)->firstOrFail();

            $data = [
              'category' => $cargoCategory,
              'sub_categories' => $subCategories,
            ];
            if (!$data) {
                throw new RestApiException(404, 'No cargo sub category found!');
            }
            $this->auditLog("View Cargo Categories", PORTAL, null, null);
            return $this->success($data, DATA_RETRIEVED);
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
    public function store(CargoSubCategoryRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'category_id' => $request->category_id,
                'name' => $request->name,
            ];

            $category = CargoSubCategory::create($payload);
            $this->auditLog("Create sub cargo category: ". $request->name, PORTAL, $payload, $payload);
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
            $category = CargoSubCategory::findOrFail($id);

            if (!$category) {
                throw new RestApiException(404, 'No cargo sub category found!');
            }

            $this->auditLog("View Cargo Sub Category: ". $category->name, PORTAL, null, null);
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
    public function update(UpdateCargoSubCategoryRequest $request, string $subCategoryId)
    {
        //
        DB::beginTransaction();
        try {
            $subCategory = CargoSubCategory::findOrFail($subCategoryId);
            $oldData = clone $subCategory;
            $payload = [
                'category_id' => $request->category_id,
                'name' => $request->name,
            ];

            $subCategory->update($payload);

            $this->auditLog("Update Cargo Sub Category: ". $request->name, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($subCategory, DATA_UPDATED);
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
        try {
            $subCategory = CargoSubCategory::findOrFail($id);

            if ($subCategory->cargoTransactionItems->count()){
                return $this->error(null, RELATED_DATA_ERROR);
            }
            $subCategory->delete();
            $this->auditLog("Delete Cargo Sub Category: ". $subCategory->name, PORTAL, null, null);
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
