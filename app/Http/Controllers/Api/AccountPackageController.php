<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountPackage;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountPackageController extends Controller
{
    use CommonTrait,ApiResponse;
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return $this->error(null, $errors, HTTP_UNPROCESSABLE_ENTITY);
        }
        $account_packages = AccountPackage::
        select([
            'id',
            'package_code',
            'package_name',
            'package_description',
            'package_amount',
            'package_validity_type',
            'package_usage_type',
            'package_trip',
            'package_discount_percent',
            'min_balance',
            'package_sale',
            'package_valid',
            'send_device_option',
            'trips_lpd',
            'trips_lpm',
            'debit_field_type',
            'price',
            'zone_id',
            'cfm_class_id',
            'created_at',
            'updated_at'
        ]) ->latest('id')->paginate($validator['items_per_page']);


        return response()->json($account_packages);
    }


    public function getPackagesWithFewDetails()
    {
        $account_packages = AccountPackage::select('id', 'package_code', 'package_name')->where('send_device_option', true)->orderByDesc('updated_at')->get();
        return response()->json($account_packages);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'packageCode' => 'required|string|max:6',
            'packageName' => 'required|string|max:20',
            'packageDescription' => 'required|string|max:35',
            'packageAmount' => 'required|numeric|min:0',
            'packageValidityType' => 'required|integer|min:0',
            'tripPerDay' => 'required|integer|min:0',
            'tripPerMonth' => 'required|Integer|min:0',
            'debitActionOn' => 'required|char',
            'packageUsageType' => 'required|integer|min:0',
            'packageTrip' => 'required|integer|min:0',
            'packageDiscountPercent' => 'required|numeric|min:0',
            'minBalance' => 'required|numeric|min:0',
            'isPackageForSale' => 'required|integer|min:0',
            'isPackageValid' => 'required|integer|min:0',
            'isSendDeviceOption' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $account_package = new AccountPackage;
            $account_package->package_code = $validatedData['packageCode'];
            $account_package->package_name = $validatedData['packageName'];
            $account_package->package_description = $validatedData['packageDescription'];
            $account_package->package_amount = $validatedData['packageAmount'];
            $account_package->package_validity_type = $validatedData['packageValidityType'];
            $account_package->trips_lpd = $validatedData['tripPerDay'];
            $account_package->trips_lpm = $validatedData['tripPerMonth'];
            $account_package->debit_field_type = $validatedData['debitActionOn'];
            $account_package->package_usage_type = $validatedData['packageUsageType'];
            $account_package->package_trip = $validatedData['packageTrip'];
            $account_package->package_discount_percent = $validatedData['packageDiscountPercent'];
            $account_package->min_balance = $validatedData['minBalance'];
            $account_package->package_sale = $validatedData['isPackageForSale'];
            $account_package->package_valid = $validatedData['isPackageValid'];
            $account_package->send_device_option = $validatedData['isSendDeviceOption'];

            $account_package->save();

            DB::commit();
            return response()->json(['message' => 'Package detail created successfully'], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to create account_package detail'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    public function show($id)
    {
        try{
            $account_package = AccountPackage::find($id);
            if (!$account_package) {
                return response()->json(['message' => 'Package detail not found'], HTTP_NOT_FOUND);
            }
            return response()->json($account_package, HTTP_OK);
        }catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to get account_package'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function edit($id)
    {
        $account_package = AccountPackage::with(['zone:id,name', 'mainLine:line_ID,line_Name', 'cfmClass:id,class_type'])
            ->
            select([
                'id',
                'package_code',
                'package_name',
                'package_description',
                'package_amount',
                'package_validity_type',
                'package_usage_type',
                'package_trip',
                'package_discount_percent',
                'min_balance',
                'package_sale',
                'package_valid',
                'send_device_option',
                'trips_lpd',
                'trips_lpm',
                'debit_field_type',
                'price',
                'zone_id',
                'cfm_class_id',
                'created_at',
                'updated_at',
                DB::raw("IF(`package_sale`=0,'No','Yes') as package_sale "),
                DB::raw("IF(`package_valid`=0,'No','Yes') as package_valid")
            ])->find($id);
        if (!$account_package) {
            return response()->json(['message' => 'Package detail not found'], HTTP_NOT_FOUND);
        }
        return response()->json($account_package, HTTP_OK);
    }

}
