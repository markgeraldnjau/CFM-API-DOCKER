<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;

class AccountPackageController extends Controller
{
    public function index(Request $request)
    {
        $account_packages = AccountPackage::
            // with(['zone:id,name', 'mainLine:line_ID,line_Name', 'cfmClass:id,class_type'])
            // ->
            select([
                '*'
            ]) ->latest('id')->paginate($request->items_per_page);

            // $account_packages = DB::select('SELECT * FROM `customer_account_package_types` ');

            // $account_packages = DB::table('customer_account_package_types')->get();

        return response()->json($account_packages);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'packageCode' => 'required',
            'packageName' => 'required',
            'packageDescription' => 'required',
            'packageAmount' => 'required',
            'packageValidityType' => 'required',
            'tripPerDay' => 'required',
            'tripPerMonth' => 'required',
            'debitActionOn' => 'required',
            'packageUsageType' => 'required',
            'packageTrip' => 'required',
            'packageDiscountPercent' => 'required',
            'minBalance' => 'required',
            'isPackageForSale' => 'required',
            'isPackageValid' => 'required',
            'isSendDeviceOption' => 'required',
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
            return response()->json(['message' => 'Package detail created successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to create account_package detail'], 500);
        }
    }



    public function show($id)
    {
        $account_package = AccountPackage::find($id);
        if (!$account_package) {
            return response()->json(['message' => 'Package detail not found'], 404);
        }
        return response()->json($account_package, 200);
    }

    public function edit($id)
    {
        $account_package = AccountPackage::with(['zone:id,name', 'mainLine:line_ID,line_Name', 'cfmClass:id,class_type'])
            ->
            select([
                '*',
                DB::raw("IF(`package_sale`=0,'No','Yes') as package_sale "),
                DB::raw("IF(`package_valid`=0,'No','Yes') as package_valid")
            ])->find($id);
        if (!$account_package) {
            return response()->json(['message' => 'Package detail not found'], 404);
        }
        return response()->json($account_package, 200);
    }

    public function update(Request $request, $id)
    {
        // Similar logic as store method with update data
    }

    public function destroy($id)
    {
        // Similar logic as other destroy methods
    }
}
