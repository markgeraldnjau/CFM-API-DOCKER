<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\CardCustomer;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CustomerAccountController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        if (isset($request->status)) {
            $cardAccounts = CustomerAccount::with([
                'card' => function ($query) {
                    $query->select('id', DB::raw("CONCAT_WS('', LEFT(cards.card_number,4), '****', RIGHT(cards.card_number,5)) AS card_number"));
                },
                'cardCustomer:id,full_name,phone',
                'accountUsageType:id,type,name',
                'customerAccountPackageType:id,package_name,package_code'
            ])
                ->select([
                    'id',
                    'card_id',
                    'account_number',
                    'customer_id',
                    'accounts_usage_type',
                    'customer_account_package_type',
                    'account_balance',
                    'min_account_balance',
                    'status',
                    'max_trip_per_day',
                    'trips_number_balance'
                ])
                ->where('status', $request->status)
                ->latest('id')
                ->paginate($request->items_per_page);


            $cardAccounts = CardCustomer::select('card_customers.full_name', 'card_customers.phone', 'customer_accounts.*', 'customer_account_package_types.package_code', 'cards.card_number', 'special_groups.title')
                ->join('customer_accounts', 'customer_accounts.customer_id', '=', 'card_customers.id')
                ->join('cards', 'cards.id', '=', 'customer_accounts.card_id')
                ->join('customer_account_package_types', 'customer_account_package_types.id', '=', 'customer_accounts.customer_account_package_type')
                ->join('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id')
                ->where('customer_accounts.status', $request->status)
                ->orderBy('card_customers.id', 'DESC')
                ->paginate($request->items_per_page);

        } else {
            $cardAccounts = CustomerAccount::with([
                'card' => function ($query) {
                    $query->select('id', DB::raw("CONCAT_WS('', LEFT(cards.card_number,4), '****', RIGHT(cards.card_number,5)) AS card_number"));
                },
                'cardCustomer:id,full_name,phone',
                'accountUsageType:id,type,name',
                'customerAccountPackageType:id,package_name,package_code'
            ])
                ->select([
                    'id',
                    'card_id',
                    'account_number',
                    'customer_id',
                    'accounts_usage_type',
                    'customer_account_package_type',
                    'account_balance',
                    'min_account_balance',
                    'status',
                    'max_trip_per_day',
                    'trips_number_balance'
                ])
                ->whereIn('status', ['A', 'I'])
                ->latest('id')
                ->paginate($request->items_per_page);

            $cardAccounts = CardCustomer::select('card_customers.full_name', 'card_customers.phone', 'customer_accounts.*', 'customer_account_package_types.package_code', 'cards.card_number', 'special_groups.title')
                ->join('customer_accounts', 'customer_accounts.customer_id', '=', 'card_customers.id')
                ->join('cards', 'cards.id', '=', 'customer_accounts.card_id')
                ->join('customer_account_package_types', 'customer_account_package_types.id', '=', 'customer_accounts.customer_account_package_type')
                ->join('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id')
                ->where('customer_accounts.status', $request->status)
                ->orderBy('card_customers.id', 'DESC')
                ->paginate($request->items_per_page);
        }

        return response()->json($cardAccounts, 200);
    }
    public function account_details(Request $request)
    {
        $cardAccounts = DB::table('customer_accounts')
            ->select('card_customers.first_name', 'card_customers.middle_name', 'card_customers.last_name', 'customer_accounts.*')
            ->join('card_customers', 'customer_accounts.customer_id', '=', 'card_customers.id')
            ->where('customer_accounts.id', $request->id)
            ->get();


        return response()->json($cardAccounts, 200);
    }
    public function store(Request $request)
    {
        $validatedData = validator($request->all(), [
            'account_number' => 'required',
            'card_id' => 'required',
            'customer_id' => 'required',
            'account_usage' => 'required',
            'account_balance' => 'required',
            'max_trip_per_day' => 'required',
            'creditAmount' => 'required',
            'status' => 'required',
            'account_type' => 'required',
            'account_validity' => 'required',
            'trips_number_balance' => 'required',
            'linker' => 'required',
        ]);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cardAccount = new CustomerAccount;
            // Fill the model with data directly from validatedData
            foreach ($validatedData as $key => $value) {
                $cardAccount->{$key} = $value;
            }
            // Save the model
            $cardAccount->save();

            // Commit the transaction
            DB::commit();
            return response()->json(['message' => DATA_SAVED], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of an exception
            DB::rollback();
            // Handle the exception
            return response()->json(['error' => SOMETHING_WENT_WRONG], 500);
        }
    }

    public function show($id)
    {
        $cardAccount = CustomerAccount::find($id);
        if (!$cardAccount) {
            return response()->json(['message' => 'Card account detail not found'], 404);
        }
        return response()->json($cardAccount, 200);
    }

    public function update(Request $request, $id)
    {
        $validatedData = validator($request->all(), [
            'account_number' => 'required',
            'trips' => 'required',
            'account_balance' => 'required',
            'status' => 'required',

        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $cardAccount = CustomerAccount::find($id);
            if (!$cardAccount) {
                return response()->json(['status' => 'failed', 'message' => 'Card account detail not found'], 404);
            }
            $cardAccount->account_number = $validatedData['account_number'];
            $cardAccount->trips_number_balance = $validatedData['trips'];
            $cardAccount->account_balance = $validatedData['account_balance'];
            $cardAccount->status = $validatedData['status'];

            $cardAccount->update();

            DB::commit();

            return response()->json(['message' => 'Card account detail updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to update card account detail'], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $cardAccount = CustomerAccount::find($id);
            if (!$cardAccount) {
                return response()->json(['message' => 'Card account detail not found'], 404);
            }
            $cardAccount->delete();
            DB::commit();
            return response()->json(['message' => 'Card account detail deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to delete card account detail'], 500);
        }
    }
}
