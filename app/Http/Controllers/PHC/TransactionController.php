<?php

namespace App\Http\Controllers\PHC;

use App\Http\Controllers\Controller;
use App\Http\Requests\PHC\DateRangeTransactionRequest;
use App\Http\Requests\PHC\DateTransactionRequest;
use App\Traits\CommonTrait;
use App\Traits\Phc\PhcApiResponse;
use http\Env\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    use PhcApiResponse, CommonTrait;
    //
    public function ticketTransactions(DateRangeTransactionRequest $request)
    {
        $validated = $request->validated();
        $from_date = $validated['from_date'];
        $to_date = $validated['end_date'];
        try{
            $transactions = DB::select('CALL GetTicketTransactions(?, ?)', [$from_date, $to_date]);

            return $this->success(LOGIN_API, SUCCESS_RESPONSE, 200, ['transactions' => $transactions]);
        } catch (\Exception $e){
            Log::error(json_encode($this->errorPayload($e)));
            return $this->error(LOGIN_API, SERVER_ERROR);
        }
    }


    public function cargoTransactions(DateRangeTransactionRequest $request)
    {
        $validated = $request->validated();
        $from_date = $validated['from_date'];
        $to_date = $validated['end_date'];
        try{
            $transactions = DB::select('CALL GetCargoTransactions(?, ?)', [$from_date, $to_date]);

//            $transactions = DB::table('cargo_transactions as ct')
//                ->join('transaction_statuses as status', 'status.code', 'ct.trnx_status')
//                ->join('train_stations as from_station', 'from_station.id', 'ct.station_from')
//                ->join('train_stations as to_station', 'to_station.id', 'ct.station_to')
//                ->join('transaction_modes as mode', 'mode.id', 'ct.trnx_mode')
//                ->join('trains as train', 'train.id', 'ct.train_id')
//                ->join('operators as op', 'op.id', 'ct.operator_id')
//                ->leftJoin('cargo_categories as cat', 'cat.id', 'ct.category_id')
//                ->leftJoin('cargo_sub_categories as sub_cat', 'sub_cat.id', 'ct.sub_category_id')
//                ->leftJoin('cargo_customers as customer', 'customer.id', 'ct.customer_id')
//                ->leftJoin('extended_transaction_types as extended', 'extended.code', 'ct.extended_trnx_type')
//                ->select(
//                    "ct.item_name",
//                    "ct.quantity",
//                    "ct.total_amount",
//                    "ct.total_kg",
//                    "ct.receipt_number",
//                    "from_station.station_name as from_station",
//                    "to_station.station_name as to_station",
//                    "status.name as trnx_status",
//                    "mode.name as trnx_mode",
//                    "ct.receiver_name",
//                    "ct.receiver_phone",
//                    "ct.sender_phone",
//                    "ct.sender_name",
//                    "train.train_number",
//                    "op.full_name as operator_name",
//                    "ct.device_number",
//                    "ct.reference_number",
//                    "ct.signature",
//                    "cat.name as category",
//                    "sub_cat.name as sub_category",
//                    DB::raw("IF(ct.on_off = 1, 'On Train', 'Off Train') as on_off"),
//                    "customer.name as customer_name",
//                    "extended.name as extended_trnx_type",
//                    "ct.collection_batch_number_id",
//                    "ct.is_collected",
//                    "ct.trnx_date",
//                    "ct.trnx_time",
//                    "ct.system_date",
//                    "ct.tracker_status",
//                )
//                ->whereBetween('ct.trnx_date', [$from_date, $to_date])
//                ->get();

            return $this->success(LOGIN_API, SUCCESS_RESPONSE, 200, ['transactions' => $transactions]);
        } catch (\Exception $e){
            Log::error(json_encode($this->errorPayload($e)));
            return $this->error(LOGIN_API, SERVER_ERROR);
        }
    }

    public function summaryDetails(DateTransactionRequest $request)
    {
        $validated = $request->validated();
        $date = $validated['date'];

        try {
            $transactions = DB::table('device_summary_receipts')
                ->join('operators', 'operators.id', '=', 'device_summary_receipts.operator_id')
                ->join('trains', 'trains.id', '=', 'device_summary_receipts.train_id')
                ->select(
                    'operators.id',
                    'operators.full_name',
                    'device_summary_receipts.device_imei',
                    'device_summary_receipts.train_id',
                    'trains.train_number',
                    'device_summary_receipts.total_tickets',
                    'device_summary_receipts.total_amount',
                    'device_summary_receipts.summary_date_time',
                    DB::raw('STR_TO_DATE(device_summary_receipts.summary_date_time, "%d%m%y%H%i%s") AS formatted_date')
                )
                ->whereRaw('STR_TO_DATE(device_summary_receipts.summary_date_time, "%d%m%y") = ?', [$date])
                ->orderByDesc('device_summary_receipts.id')
                ->get();

            return $this->success(LOGIN_API, SUCCESS_RESPONSE, 200, ['data' => $transactions]);

        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            return $this->error(LOGIN_API, SERVER_ERROR);
        }
    }




    private function encryptAndRespond($responseObject): \Illuminate\Http\JsonResponse
    {
        $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject));
        $dataPayload = new \stdClass();
        $dataPayload->data = $encryptedResponse;

        return response()->json($dataPayload);
    }

}
