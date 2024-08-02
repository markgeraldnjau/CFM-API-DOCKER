<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cargo\createCargoCustomerRequest;
use App\Http\Requests\Cargo\UpdateCargoCustomerRequest;
use App\Http\Requests\Cargo\UpdateCargoCustomerStatusRequest;
use App\Models\Cargo\CargoCustomer;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargoCustomerController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;

    /**
     * Display a listing of the resource.
     * @throws RestApiException
     */
    public function index(Request $request)
    {
        $this->checkPermissionFn($request, VIEW);

        $status = $request->input('status');
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);

        try {
            $query = DB::table('cargo_customers as cc')->select(
                'cc.id',
                'cc.name',
                'cc.address',
                'cc.customer_number',
                'cc.phone',
                'cc.email',
                'cc.tax_number',
                'cc.company_reg_number',
                'cc.status',
                'cc.customer_type',
                'cct.name as customer_type_name',
                'cc.customer_pay_type',
                'ccp.name as customer_pay_type_name',
                'cc.service_type',
                'ccs.name as service_type_name'
            )->join('cargo_customer_types as cct', 'cct.code', 'cc.customer_type')
                ->join('cargo_customer_service_types as ccs', 'ccs.code', 'cc.service_type')
                ->join('cargo_customer_pay_types as ccp', 'ccp.code', 'cc.customer_pay_type');

            if ($status !== null) {
                $query->where('status', $status);
            }

            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('cc.name', 'like', "%$searchQuery%")
                        ->orWhere('cc.customer_number', 'like', "%$searchQuery%")
                        ->orWhere('cc.email', 'like', "%$searchQuery%")
                        ->orWhere('cc.phone', 'like', "%$searchQuery%")
                        ->orWhere('cc.address', 'like', "%$searchQuery%")
                        ->orWhere('cc.tax_number', 'like', "%$searchQuery%")
                        ->orWhere('cc.company_reg_number', 'like', "%$searchQuery%");
                });
            }

            $cargoCustomers = $query->orderByDesc('cc.updated_at')->paginate($itemPerPage);

            if (!$cargoCustomers) {
                return $this->error(404, 'No cargo customer found!');
            }

            $this->auditLog("View Cargo Customers", PORTAL, null, null);
            return $this->success($cargoCustomers, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
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
    public function store(createCargoCustomerRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'name' => $request->name,
                'customer_number' => $request->customer_number,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'customer_type' => $request->customer_type,
                'customer_pay_type' => $request->customer_pay_type,
                'company_reg_number' =>  $request->customer_type == ORGANIZATION_CUSTOMER ? $request->company_reg_number : null,
                'tax_number' => $request->customer_type == ORGANIZATION_CUSTOMER ? $request->tax_number : null,
                'service_type' => $request->service_type,
            ];

            $customer = CargoCustomer::create($payload);
            $this->auditLog("Create Cargo Customer: ". $request->name, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($customer, DATA_SAVED);
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
            $cargoCustomer = CargoCustomer::find($id, [
                'id',
                'name',
                'address',
                'customer_number',
                'phone',
                'email',
                'customer_type',
                'customer_pay_type',
                'tax_number',
                'company_reg_number',
                'status',
                'service_type',
                'created_at',
                'updated_at'
            ]);

            if (!$cargoCustomer) {
                throw new RestApiException(404, 'No cargo customer found!');
            }

            $this->auditLog("View Cargo Customer: ". $cargoCustomer->name, PORTAL, null, null);

            return $this->success($cargoCustomer, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
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
    public function update(UpdateCargoCustomerRequest $request, string $customerId)
    {
        //
        DB::beginTransaction();
        try {
            $customer = CargoCustomer::findOrFail($customerId);
            $oldData = clone $customer;
            $payload = [
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'customer_type' => $request->customer_type,
                'customer_pay_type' => $request->customer_pay_type,
                'company_reg_number' => $request->company_reg_number,
                'tax_number' => $request->tax_number,
                'service_type' => $request->service_type,
            ];

            $customer->update($payload);

            $this->auditLog("Update Cargo Customer: ". $request->name, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($customer, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {

            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500);
        }
    }

    public function changeStatus(UpdateCargoCustomerStatusRequest $request)
    {

        try {
            $customer = CargoCustomer::find($request->id);
            $oldData = $customer;

            if (!$customer) {
                throw new RestApiException(404, 'No cargo customer found!');
            }

            $newStatus = !$request->status;
            $customer->status = $newStatus;
            $customer->save();


            $this->auditLog("Change status for Cargo Customer: ". $customer->name, PORTAL, $oldData, $customer);

            return $this->success($customer, DATA_UPDATED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
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
