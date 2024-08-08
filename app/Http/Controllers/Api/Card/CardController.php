<?php

namespace App\Http\Controllers\Api\Card;

use App\Http\Controllers\Controller;
use App\Imports\CardsImport;
use App\Models\Card;
use App\Models\CardCustomer;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

// use Schema;


class CardController extends Controller
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
            "status" => "nullable|string",
            "items_per_page" => "nullable|numeric",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        if (isset($request->status)) {
            $cards = Card::select([
                'cards.id',
                'cards.tag_id',
                DB::raw("CONCAT_WS('', LEFT(cards.card_number, 4), '****', RIGHT(cards.card_number, 5)) AS card_number"),
                'cards.status',
                'cards.dateuploaded',
                'cards.card_type',
                'cards.expire_date',
                'cards.card_ownership',
                'cards.credit_type',
                'cards.company_id',
                'cards.card_block_action',
                'cards.last_update_time',
                'cards.company_id',
                DB::raw("IFNULL(card_types.type_name, 'N/A') AS type_name")
            ])->leftJoin('card_types', 'cards.card_type', '=', 'card_types.id')->where('cards.status', $request->input('status'))->latest('cards.id')->paginate($request->items_per_page);
        } else {
            $cards = Card::select([
                'cards.id',
                'cards.tag_id',
                DB::raw("CONCAT_WS('', LEFT(cards.card_number, 4), '****', RIGHT(cards.card_number, 5)) AS card_number"),
                'cards.status',
                'cards.dateuploaded',
                'cards.card_type',
                'cards.expire_date',
                'cards.card_ownership',
                'cards.credit_type',
                'cards.company_id',
                'cards.card_block_action',
                'cards.last_update_time',
                'cards.company_id',
                DB::raw("IFNULL(card_types.type_name, 'N/A') AS type_name")
            ])->leftJoin('card_types', 'cards.card_type', '=', 'card_types.id')->latest('cards.id')->paginate($request->items_per_page);
        }

        return response()->json($cards, HTTP_OK);
    }

    public function cardDetails(Request $request)
    {

        $cards = DB::table('cards as c')
            ->leftJoin('card_types as ct', 'ct.id', 'c.card_type')
            ->select(
                'c.*',
                'ct.type_name as card_type_name'
            )
            ->where('c.id', $request->id)
            ->first();


        return response()->json($cards, HTTP_OK);
    }

    public function cardCustomerDetails(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:cards,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        // Wrap the query in a try-catch block
        try {
            // Retrieve the card customer details
            $cards = CardCustomer::join('customer_cards as cuc', 'cuc.customer_id', '=', 'card_customers.id')
                ->join('cards as ca', 'ca.id', '=', 'cuc.card_id')
                ->select(
                    'card_customers.id',
                    'card_customers.full_name',
                    'card_customers.phone',
                    'card_customers.email',
                    'card_customers.app_pin',
                    'card_customers.status as customer_status',
                    'ca.card_number',
                    'ca.expire_date',
                    'ca.status as card_status',
                    'ca.card_block_action'
                )->where('ca.id', $request->id)
                ->whereNull('ca.deleted_at')
                ->whereNull('card_customers.deleted_at')
                ->first();

            if (!$cards) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Card customer not found'
                ], HTTP_NOT_FOUND);
            }

            return response()->json([
                'status' => 'success',
                'data' => $cards
            ], HTTP_OK);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error fetching card customer details: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching card customer details'
            ], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function store(Request $request)
    {

        $validator = validator($request->all(), [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:2048',
            ],
            "card_type" => "required|string"
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            Excel::import(new CardsImport($request->card_type), request()->file('new_cards_file'));
            DB::commit();
            return response()->json(['status' => true, 'message' => DATA_UPLOADED], HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => false, 'message' => $th->getMessage()]);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $validator = validator($request->all(), [
            "id" => "require"
        ]);
        try{
            if ($validator->fails()) {
                return response()->json([
                    'status' => VALIDATION_ERROR,
                    'message' => VALIDATION_FAIL,
                    'errors' => $validator->errors()
                ], HTTP_UNPROCESSABLE_ENTITY);
            }
            $card = Card::find($request->id);
            if ($card === null) {
                return response()->json([
                    'error' => 'Card not found'
                ], Response::HTTP_NOT_FOUND);
            }
            return response()->json($card, HTTP_OK);
        }catch (\Exception $e){
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $message = '';
        $validator = validator($request->all(), [
            'action_type' => "require"
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::beginTransaction();
        try {
            $card = Card::find($id);
            if (!is_null($card)) {
                if ($request->action_type == CHANGE_STATUS) {
                    if ($card->status == ACTIVE_STATUS) {
                        $card->update(['status' => INACTIVE_STATUS]);
                        $message = BLOCK_CARD;
                    } else {
                        $card->update(['status' => INACTIVE_STATUS]);
                        $message = ACTIVE_CARD_MESSAGE;
                    }
                } else {
                    return response()->json(['message' => NOT_CHANGE_STATUS], \HttpResponseCode::BAD_REQUEST);

                }
                DB::commit();
                return response()->json(['message' => $message, 'data' => $card], HTTP_OK);
            } else {
                return response()->json(['message' => DATA_NOT_FOUND], HTTP_OK);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(["message" => $th->getMessage()]);
        }

    }

    public function update_card(Request $request, $id)
    {

        DB::beginTransaction();
        try {
            $card = Card::find($id);
            if (!is_null($card)) {
                $card->expire_date = $request->expire_date;
                $card->card_type = $request->card_type;
                $card->expire_date = $request->expire_date;
                $card->status = $request->card_status;
                if (!empty($request->card_ownership)) {
                    $card->card_ownership = $request->card_ownership;
                }
                if (!empty($request->card_company_id)) {
                    $card->company_id = $request->card_company_id;
                }
                $card->save();
                DB::commit();
                return response()->json(['status' => 'success', 'message' => DATA_UPDATED, 'id' => $id, 'data' => $card], 200);
            } else {
                return response()->json(['message' => NOT_FOUND, 'request' => $request, 'id' => $id], HTTP_OK);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(["message" => $th->getMessage()]);
        }

    }


}
