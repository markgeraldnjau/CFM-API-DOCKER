<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CardCustomer;
use App\Models\Device;
use App\Traits\ApiResponse;
use App\Traits\Mobile\MobileAppTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {

        $searchQuery = $request->search_query;
        $query = CardCustomer::select('card_customers.*',  'cards.card_number','special_groups.title')
            ->leftJoin('customer_cards', 'customer_cards.customer_id', '=', 'card_customers.id')
            ->leftJoin('cards', 'cards.id', '=', 'customer_cards.card_id')
            ->leftJoin('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id');

        if (!empty($searchQuery)) {
            $query->where(function ($query) use ($searchQuery) {
                $query->where('card_customers.full_name', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.first_name', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.middle_name', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.last_name', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.identification_number', 'like', "%$searchQuery%");
            });
        }

        $customers = $query->orderBy('card_customers.id', 'DESC')->paginate($request->items_per_page);

        return response()->json($customers, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        $validatedData = validator($request->all(), [
            'first_name' => 'required',
            'middle_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'birthdate' => 'required',
            'category' => 'required',
            'identification_number' => 'required',
            'identification_type' => 'required',
            'phone' => 'required',
            'address' => 'required',

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
            $customer = new CardCustomer;
            $customer->full_name = $validatedData['first_name'] . ' ' . $validatedData['middle_name'] . ' ' . $validatedData['last_name'];
            $customer->first_name = $validatedData['first_name'];
            $customer->middle_name = $validatedData['middle_name'];
            $customer->last_name = $validatedData['last_name'];
            $customer->gender_id = $validatedData['gender'];
            $customer->birthdate = $validatedData['birthdate'];
            $customer->identification_number = $validatedData['identification_number'];
            $customer->identification_type = $validatedData['identification_type'];
            $customer->employee_id = $request->input('employee_id', null);
            $customer->phone = $validatedData['phone'];
            $customer->email = $request->input('email', null);
            $customer->address = $validatedData['address'];
            $customer->registration_datetime = now();
            $customer->save();
            DB::commit();

            return response()->json(['status' => 'success', 'message' => DATA_SAVED], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());
            return response()->json(['status' => 'failed', 'message' => 'Failed to create customer'], 500);
        }
    }

    use MobileAppTrait;
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        //        return $this->getCustomerDetailsByCustomerId($id);
        $customer = CardCustomer::select(
            'card_customers.*',
            'cards.card_number',
            'special_groups.title',
            'ct.type_name as card_type_name',
        )
            ->join('customer_cards', 'customer_cards.customer_id', '=', 'card_customers.id')
            ->join('cards', 'cards.id', '=', 'customer_cards.card_id')
            ->join('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id')
            ->join('card_types as ct', 'ct.id', '=', 'cards.card_type')
            ->where('card_customers.id', $id)->first();

        if (empty($customer)) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
        return response()->json($customer, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, )
    {
        // $validatedData = $request->validate([
        //     'first_name' => 'required',
        //     'middle_name' => 'required',
        //     'last_name' => 'required',
        //     'gender' => 'required',
        //     'birthdate' => 'required',
        //     'category' => 'required',
        //     'identification_number' => 'required',
        //     'identification_type' => 'required',
        //     'phone' => 'required',
        //     'address' => 'required',
        //     'passportImage' => 'required',
        //     'image' => 'required',
        // ]);

        DB::beginTransaction();

        try {
            $customer = CardCustomer::find($id);
            if (!$customer) {
                return response()->json(['status' => 'failed', 'message' => 'Customer not found'], 404);
            }
            $customer->full_name = $request['first_name'] . ' ' . $request['middle_name'] . ' ' . $request['last_name'];
            $customer->first_name = $request['first_name'];
            $customer->middle_name = $request['middle_name'];
            $customer->last_name = $request['last_name'];
            $customer->identification_number = $request['identification'];
            $customer->update();

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Customer updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['status' => 'failed', 'message' => 'Failed to update customer ' . $e->getMessage()], 500);
        }
    }

    public function customer_details(Request $request)
    {
        $customers = CardCustomer::select(
            'card_customers.*',
            'card_customers.full_name as customer_name',
            'customer_cards.id as card_id',
            'cards.card_number as card_number',
            'special_groups.title as special_group_title'
        )
            ->join('customer_cards', 'customer_cards.customer_id', '=', 'card_customers.id')
            ->join('cards', 'cards.id', '=', 'customer_cards.card_id')
            ->join('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id')
            ->where('card_customers.id', $request->id)
            ->get();


        return response()->json($customers, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $customer = CardCustomer::find($id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }
            $customer->delete();
            DB::commit();
            return response()->json(['message' => 'Customer deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to delete customer'], 500);
        }
    }
}
