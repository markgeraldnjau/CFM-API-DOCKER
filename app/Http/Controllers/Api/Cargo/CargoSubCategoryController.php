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
use App\Traits\CommonTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CargoSubCategoryController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'search_query' => ['nullable', 'string', 'max:255'],
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return $this->error(null, $errors, HTTP_UNPROCESSABLE_ENTITY);
        }

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
                throw new RestApiException(HTTP_NOT_FOUND, 'No cargo sub category found!');
            }
            $this->auditLog("View Cargo Categories", PORTAL, null, null);
            return $this->success($data, DATA_RETRIEVED);
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
        try {
            $subCategory = CargoSubCategory::query()
                ->with('cargoCategory')
                ->where('token', $token)
                ->firstOrFail();

            if (!$subCategory) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No cargo sub category found!');
            }

            $this->auditLog("View Cargo Sub Category: ". $subCategory->name, PORTAL, null, null);
            return $this->success($subCategory, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
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
    public function update(UpdateCargoSubCategoryRequest $request, string $token)
    {
        try {
            $subCategory = CargoSubCategory::query()->where('token', $token)->firstOrFail();
            $category = CargoCategory::query()->where('token', $token)->firstOrFail();

            if (!$subCategory) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No cargo sub category found!');
            }

            $exists = CargoSubCategory::query()
                ->where('name', $request->name)
                ->where('category_id', $category->id)
                ->exists();

            if ($exists) {
                return response()->json(
                    $this->validationError('name', 'The name already exists for this category.'),
                    HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $oldData = clone $subCategory;
            DB::beginTransaction();
            $payload = [
                'category_id' => $category->id,
                'name' => $request->name,
            ];

            $subCategory->update($payload);
            $this->auditLog("Update Cargo Sub Category: ". $request->name, PORTAL, $oldData, $payload);
            DB::commit();
            return $this->success($subCategory, DATA_UPDATED);
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
    public function destroy(string $token)
    {
        //
        try {
            $subCategory = CargoSubCategory::where('token', $token)->first();

            if ($subCategory->cargoTransactionItems->count()){
                return $this->error(null, RELATED_DATA_ERROR);
            }
            $subCategory->delete();
            $this->auditLog("Delete Cargo Sub Category: ". $subCategory->name, PORTAL, null, null);
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
