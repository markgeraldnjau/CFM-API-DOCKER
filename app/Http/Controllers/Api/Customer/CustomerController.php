<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CardCustomer;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use App\Traits\Mobile\MobileAppTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CustomerController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {

        $validator = validator($request->all(), [
            'items_per_page' => "nullable|numeric"
        ]);


        $searchQuery = $request->input('search_query');

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = CardCustomer::select('card_customers.*', 'cards.card_number', 'special_groups.title')
            ->join('customer_cards', 'customer_cards.customer_id', '=', 'card_customers.id')
            ->join('cards', 'cards.id', '=', 'customer_cards.card_id')
            ->join('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id');

        if (!empty($searchQuery)) {
            $query->where(function ($query) use ($searchQuery) {
                $query->where('card_customers.full_name', 'like', "%$searchQuery%")
                    ->orWhere('cards.card_number', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.identification_number', 'like', "%$searchQuery%")
                    ->orWhere('card_customers.phone', 'like', "%$searchQuery%");
            });
        }
        $customers = $query->orderBy('card_customers.id', 'DESC')->paginate($request->items_per_page);

        return response()->json($customers, HTTP_OK);
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
            'first_name' => 'required|string|max:50',
            'middle_name' => 'required|string|max:30',
            'last_name' => 'required|string|max:50',
            'identification_number' => 'required|string|max:100',
            'identification_type' => 'required|integer',
            'employee_id' => 'nullable|string|max:25',
            'gender_id' => 'required|exists:genders,id',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:40',
            'address' => 'required|string|max:50',
            'birthdate' => 'required|date',
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

            return response()->json(['status' => 'success', 'message' => DATA_SAVED], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'failed', 'message' => 'Failed to create customer'], HTTP_INTERNAL_SERVER_ERROR);
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
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
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
            return response()->json(['message' => NOT_FOUND], HTTP_NOT_FOUND);
        }
        return response()->json($customer, HTTP_OK);
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
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        $validator = validator($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'identification' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $customer = CardCustomer::find($id);
        if (!$customer) {
            return response()->json(['status' => 'failed', 'message' => NOT_FOUND], HTTP_NOT_FOUND);
        }
        DB::beginTransaction();
        try {
            $customer->full_name = $request['first_name'] . ' ' . $request['middle_name'] . ' ' . $request['last_name'];
            $customer->first_name = $request['first_name'];
            $customer->middle_name = $request['middle_name'];
            $customer->last_name = $request['last_name'];
            $customer->identification_number = $request['identification'];
            $customer->update();

            DB::commit();

            return response()->json(['status' => 'success', 'message' => DATA_UPDATED], HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'failed', 'message' => FAILED . $e->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function customer_details(Request $request)
    {
        $validator = validator($request->all(), [
            'id' => 'required|integer',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $customers = CardCustomer::select(
            'card_customers.*',
            'card_customers.full_name as customer_name',
            'customer_cards.id as card_id',
            'cards.card_number as card_number',
            'special_groups.title as special_group_title',
            'g.gender as gender_name'
        )
            ->join('customer_cards', 'customer_cards.customer_id', '=', 'card_customers.id')
            ->join('cards', 'cards.id', '=', 'customer_cards.card_id')
            ->join('special_groups', 'special_groups.id', '=', 'card_customers.special_group_id')
            ->join('genders as g', 'g.id', 'card_customers.gender_id')
            ->where('card_customers.id', $request->id)
            ->first();


        return response()->json($customers, HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        DB::beginTransaction();
        try {
            $customer = CardCustomer::find($id);
            if (!$customer) {
                return response()->json(['message' => NOT_FOUND], HTTP_NOT_FOUND);
            }
            $customer->delete();
            DB::commit();
            return response()->json(['message' => DATA_DELETED], HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => FAILED], HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
