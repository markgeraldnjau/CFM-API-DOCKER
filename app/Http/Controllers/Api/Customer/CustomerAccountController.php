<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\CardCustomer;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CustomerAccountController extends Controller
{
    use ApiResponse, CommonTrait;
    public function index(Request $request)
    {
        $validatedData = validator($request->all(), [
            'status' => 'nullable|string',
            'items_per_page' => 'nullable|numeric',
        ]);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        $searchQuery = $request->input('search_query');


        $query = CardCustomer::select('card_customers.full_name', 'card_customers.phone', 'customer_accounts.*', 'customer_account_package_types.package_code', 'cards.card_number', 'special_groups.title')
            ->join('customer_accounts', 'customer_accounts.customer_id', '=', 'card_customers.id')
            ->join('cards', 'cards.id', '=', 'customer_accounts.card_id')
            ->join('customer_account_package_types', 'customer_account_package_types.id', '=', 'customer_accounts.customer_account_package_type')
            ->join('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id');

        if (isset($request->status)) {
            $query->where('customer_accounts.status', $request->status);
        }

        if ($searchQuery !== null) {
            $query->where(function ($query) use ($searchQuery) {
                $query->where('card_customers.full_name', 'like', "%$searchQuery%")
                    ->orWhere('cards.card_number', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.identification_number', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.phone', 'like', "%$searchQuery%");
            });
        }

        $cardAccounts = $query->orderBy('card_customers.id', 'DESC')->paginate($request->items_per_page);

        return response()->json($cardAccounts, HTTP_OK);
    }
    public function account_details(Request $request)
    {
        $cardAccounts = DB::table('customer_accounts')
            ->select('card_customers.first_name', 'card_customers.middle_name', 'card_customers.last_name', 'customer_accounts.*')
            ->join('card_customers', 'customer_accounts.customer_id', '=', 'card_customers.id')
            ->where('customer_accounts.id', $request->id)
            ->first();


        return response()->json($cardAccounts, HTTP_OK);
    }
    public function store(Request $request)
    {
        $validatedData = validator(
            $request->all(),
            [
                'account_number' => 'required|string|max:20',
                'card_id' => 'required|exists:cards,id',
                'customer_id' => 'required|exists:customers,id',
                'account_balance' => 'required|numeric|between:0,9999999999999999.99',
                'status' => [
                    'required',
                    'char:1',
                    Rule::in(['A', 'I']),
                ],
                'linker' => 'required|integer|min:0',
                'account_validity' => 'nullable|date',
                'trips_number_balance' => 'required|integer|min:0',
                'max_trip_per_day' => 'required|integer|min:0',
            ]
        );
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
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
            DB::commit();
            return response()->json(['message' => DATA_SAVED], HTTP_OK);
        } catch (\Exception $e) {
            // Rollback the transaction in case of an exception
            DB::rollback();
            Log::error(json_encode($this->errorPayload($e)));
            // Handle the exception
            return response()->json(['error' => SOMETHING_WENT_WRONG], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        $cardAccount = CustomerAccount::find($id);
        if (!$cardAccount) {
            return response()->json(['message' => NOT_FOUND], HTTP_NOT_FOUND);
        }
    }

    public function update(Request $request, $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }

        $validatedData = Validator::make($request->all(), [
            'account_number' => 'required|string|max:20',
            'trips' => 'required|numeric|between:0,9999999999999999.99',
            'status' => [
                'required',
                'string',
                'size:1',
                Rule::in(['A', 'B']),
            ],
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            $cardAccount = CustomerAccount::find($id);
            if (!$cardAccount) {
                return response()->json(['status' => 'failed', 'message' => NOT_FOUND], HTTP_NOT_FOUND);
            }

            $cardAccount->account_number = $validatedData->validated()['account_number'];
            $cardAccount->trips_number_balance = $validatedData->validated()['trips'];
            $cardAccount->status = $validatedData->validated()['status'];

            $cardAccount->update();

            DB::commit();

            return $this->success(null, DATA_UPDATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return $this->error(null, HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        DB::beginTransaction();

        try {
            $cardAccount = CustomerAccount::find($id);
            if (!$cardAccount) {
                return response()->json(['message' => NOT_FOUND], HTTP_NOT_FOUND);
            }
            $cardAccount->delete();
            DB::commit();
            return response()->json(['message' => DATA_DELETED], HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => FAILED], HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
