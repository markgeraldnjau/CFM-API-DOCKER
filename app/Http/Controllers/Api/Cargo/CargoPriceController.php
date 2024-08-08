<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cargo\CargoPriceRequest;
use App\Imports\Cargo\CargoPricesImport;
use App\Models\Cargo\CargoCategory;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\AuthTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CargoPriceController extends Controller
{
    use ApiResponse, AuthTrait, AuditTrail;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'search_query' => ['nullable', 'string', 'max:255'],
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return $this->error(null, $errors, HTTP_UNPROCESSABLE_ENTITY);
        }

        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = DB::table('cargo_prices as cp')
                ->join('km_ranges as km', 'km.id', 'cp.km_id')
                ->join('kg_ranges as kg', 'kg.id', 'cp.kg_id')
                ->join('cargo_categories as cc', 'cc.id', 'cp.cargo_category_id')
                ->select(
                    'cp.id',
                    'cp.token',
                    'cp.charge',
                    'km.from as km_from',
                    'km.to as km_to',
                    'kg.from as kg_from',
                    'kg.to as kg_to',
                    'cc.name as cargo_category_name',
                )->whereNull('cp.deleted_at');


            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('cp.charge', 'like', "%$searchQuery%")
                    ->where('cc.name', 'like', "%$searchQuery%");
                });
            }

            $cargoPrices = $query->orderBy('cp.updated_at')->paginate($itemPerPage);

            if (!$cargoPrices) {
                return $this->error(null, "No Cargo Price found!");
            }

            return $this->success($cargoPrices, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    public function store(CargoPriceRequest $request)
    {
        //
        DB::beginTransaction();
        try {

            $category = CargoCategory::find($request->category_id, ['id', 'name', 'code']);

            if (empty($category) || $category->code == 0){
                return $this->error(null, "Invalid category selected!");
            }

            if ($category->code == NORMAL_CARGO_CATEGORY){

                $import = new CargoPricesImport($category);
                Excel::import($import, request()->file('new_cargo_price_file'));

                if (!$import->importSuccess) {
                    return $this->error(null, $import->errors, HTTP_INTERNAL_SERVER_ERROR);
                }

            } else if ($category->code == SPECIAL_ONE_CARGO_CATEGORY){

            } else if ($category->code == SPECIAL_TWO_CARGO_CATEGORY){

            }
            DB::commit();
            return $this->success(null, DATA_SAVED. " for ". $category->name);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
