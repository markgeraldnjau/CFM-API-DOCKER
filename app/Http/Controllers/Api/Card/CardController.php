<?php

namespace App\Http\Controllers\Api\Card;

use App\Http\Controllers\Controller;
use App\Http\Requests\Card\UpdateCardRequest;
use App\Imports\CardsImport;
use App\Models\Card;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use illuminate\Validation\Validator;

// use Schema;


class CardController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */


    public function index(Request $request)
    {
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

        return response()->json($cards, 200);
    }

    public function card_details(Request $request)
    {

        $cards = DB::table('cards as c')
            ->join('card_types as ct', 'ct.id', 'c.card_type')
            ->select(
                'c.*',
                'ct.type_name as card_type_name'
            )
            ->where('c.id', $request->id)
            ->first();


        return response()->json($cards, 200);
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
            ], 422);
        }

        DB::beginTransaction();
        try {
            Excel::import(new CardsImport($request->card_type), request()->file('new_cards_file'));
            DB::commit();
            return response()->json(['status' => true, 'message' => DATA_UPLOADED], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
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
        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], 422);
        }
        $card = Card::find($request->id);
        if ($card === null) {
            return response()->json([
                'error' => 'Card not found'
            ], Response::HTTP_NOT_FOUND);
        }
        return response()->json($card, 200);
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
            ], 422);
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
                return response()->json(['message' => $message, 'data' => $card], 200);
            } else {
                return response()->json(['message' => DATA_NOT_FOUND], 200);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
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
                return response()->json(['message' => NOT_FOUND, 'request' => $request, 'id' => $id], 200);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json(["message" => $th->getMessage()]);
        }

    }


}
