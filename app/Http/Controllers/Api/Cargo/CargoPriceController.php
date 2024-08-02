<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cargo\CargoPriceRequest;
use App\Imports\CardsImport;
use App\Imports\Cargo\CargoPricesImport;
use App\Models\Cargo\CargoCategory;
use App\Models\Cargo\CargoCustomerType;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\AuthTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                    return $this->error(null, $import->errors, 500);
                }

            } else if ($category->code == SPECIAL_ONE_CARGO_CATEGORY){

            } else if ($category->code == SPECIAL_TWO_CARGO_CATEGORY){

            }
//            dd('here');

//            $this->auditLog("Create cargo prices for category: ". $category->name, PORTAL, null, null);
            DB::commit();
            return $this->success(null, DATA_SAVED. " for ". $category->name);
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
