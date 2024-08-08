<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\OperationTrait;
use App\Traits\TransactionTrait;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\HasApiTokens;

class TransactionController extends Controller
{
    use ApiResponse, OperationTrait, CommonTrait, TransactionTrait, HasApiTokens;

    public function index(Request $request)
    {
        $validator = validator($request->all(), [
            'type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $operatorId = $this->getUserOperatorId();
            if ($request->type == 'CASH') {
                // $transactions = $this->getTransactions($operatorId,TRAIN_CASH_PAYMENT);
                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                    ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                    ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                    ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                    ->join('trains', 'trains.id', '=', 'tnx.train_id')
                    ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                    ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                    ->select(
                        'tnx.id',
                        'trains.train_number',
                        'tnx.customer_name',
                        'cfm_classes.class_type',
                        'tnx.trnx_amount',
                        'tnx.created_at',
                        'tnx.trnx_date',
                        'tnx.trnx_time',
                        'operators.full_name',
                        'start.station_name as start_name',
                        'tnx.trnx_status',
                        'end.station_name as end_name',
                        'tnx.trnx_number',
                        'tnx.trnx_receipt',
                        'special_groups.title',
                        'tnx.device_number'
                    )
                    ->whereNull('tnx.card_number')
                    ->where('tnx.trnx_status', '=', '0')
                    ->orderBy('tnx.system_date', 'desc')
                    ->paginate(10);
            } else if ($request->type == 'CANCELLED') {
                // $transactions = $this->getTransactions($operatorId,TRAIN_CASH_PAYMENT);
                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                    ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                    ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                    ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                    ->join('trains', 'trains.id', '=', 'tnx.train_id')
                    ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                    ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                    ->select(
                        'tnx.id',
                        'tnx.trnx_status',
                        'trains.train_number',
                        'tnx.customer_name',
                        'cfm_classes.class_type',
                        'tnx.trnx_amount',
                        'tnx.created_at',
                        'tnx.trnx_date',
                        'tnx.trnx_time',
                        'operators.full_name',
                        'start.station_name as start_name',
                        'end.station_name as end_name',
                        'tnx.trnx_number',
                        'tnx.trnx_receipt',
                        'special_groups.title',
                        'tnx.device_number'
                    )
                    ->where('tnx.trnx_status', '=', '2')
                    ->orderBy('tnx.system_date', 'desc')
                    ->paginate(10);
            } elseif ($request->type == 'CARD') {
                // $transactions = $this->getTransactions($operatorId,CARD_PAYMENT);
                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                    ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                    ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                    ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                    ->join('trains', 'trains.id', '=', 'tnx.train_id')
                    ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                    ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                    ->select(
                        'tnx.id',
                        'trains.train_number',
                        'tnx.customer_name',
                        'cfm_classes.class_type',
                        'tnx.trnx_amount',
                        'tnx.created_at',
                        'tnx.trnx_date',
                        'tnx.trnx_time',
                        'operators.full_name',
                        'tnx.trnx_receipt',
                        'start.station_name as start_name',
                        'end.station_name as end_name',
                        'tnx.trnx_number',
                        'special_groups.title',
                        'tnx.device_number',
                        'tnx.acc_number',
                        'tnx.card_number'
                    )
                    ->whereNotNull('tnx.card_number')
                    ->orderBy('system_date', 'desc')
                    ->paginate(10);
            } else if ($request->type == 'ZONE') {
                // $transactions = $this->getTransactions($operatorId,CARD_PAYMENT);
                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                    ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                    ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                    ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                    ->join('trains', 'trains.id', '=', 'tnx.train_id')
                    ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                    ->join('zone_lists', 'zone_lists.id', '=', 'tnx.zone_id')
                    ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                    ->select(
                        'zone_lists.name as zone',
                        'tnx.id',
                        'trains.train_number',
                        'tnx.customer_name',
                        'cfm_classes.class_type',
                        'tnx.trnx_amount',
                        'tnx.created_at',
                        'tnx.trnx_date',
                        'tnx.trnx_time',
                        'operators.full_name',
                        'tnx.trnx_receipt',
                        'start.station_name as start_name',
                        'end.station_name as end_name',
                        'tnx.trnx_number',
                        'special_groups.title',
                        'tnx.device_number',
                        'tnx.acc_number',
                        'tnx.card_number'
                    )
                    ->where('tnx.zone_id', '!=', '0')
                    ->orderBy('system_date', 'desc')
                    ->paginate(10);
            } else if ($request->type == 'PENALTY') {
                // $transactions = $this->getTransactions($operatorId,CARD_PAYMENT);
                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                    ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                    ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                    ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                    ->join('trains', 'trains.id', '=', 'tnx.train_id')
                    ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                    ->leftjoin('zone_lists', 'zone_lists.id', '=', 'tnx.zone_id')
                    ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                    ->select(
                        'zone_lists.name as zone',
                        'tnx.id',
                        'trains.train_number',
                        'tnx.customer_name',
                        'cfm_classes.class_type',
                        'tnx.trnx_amount',
                        'tnx.created_at',
                        'tnx.trnx_date',
                        'tnx.trnx_time',
                        'operators.full_name',
                        'tnx.trnx_receipt',
                        'start.station_name as start_name',
                        'end.station_name as end_name',
                        'tnx.trnx_number',
                        'special_groups.title',
                        'tnx.device_number',
                        'tnx.acc_number',
                        'tnx.card_number'
                    )
                    ->where('tnx.fine_amount', '!=', '0')
                    ->orderBy('system_date', 'desc')
                    ->paginate(10);
            } else if ($request->type == 'AUTOMOTORA') {
                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                    ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                    ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                    ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                    ->join('trains', 'trains.id', '=', 'tnx.train_id')
                    ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                    ->leftjoin('zone_lists', 'zone_lists.id', '=', 'tnx.zone_id')
                    ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                    ->select(
                        'zone_lists.name as zone',
                        'tnx.id',
                        'trains.train_number',
                        'tnx.customer_name',
                        'cfm_classes.class_type',
                        'tnx.trnx_amount',
                        'tnx.created_at',
                        'tnx.trnx_date',
                        'tnx.trnx_time',
                        'operators.full_name',
                        'tnx.trnx_receipt',
                        'start.station_name as start_name',
                        'end.station_name as end_name',
                        'tnx.trnx_number',
                        'special_groups.title',
                        'tnx.device_number',
                        'tnx.acc_number',
                        'tnx.card_number'
                    )
                    ->where('trains.train_type', '=', '3')
                    ->orderBy('system_date', 'desc')
                    ->paginate(10);
            } else if ($request->type == 'TOPUP') {
                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                    ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                    ->select(
                        'tnx.id',
                        'tnx.customer_name',
                        'tnx.trnx_amount',
                        'tnx.created_at',
                        'tnx.trnx_date',
                        'tnx.trnx_time',
                        'operators.full_name',
                        'tnx.trnx_receipt',
                        'tnx.trnx_number',
                        'tnx.device_number',
                        'tnx.acc_number',
                        'tnx.card_number'
                    )
                    ->whereNotNull('tnx.card_number')
                    ->where('extended_trnx_type', '=', '9009')
                    ->orderBy('system_date', 'desc')
                    ->paginate(10);
            } else {
                $transactions = $this->getTransactions($operatorId, TRAIN_CASH_PAYMENT);

            }


            return $this->success($lastFiveTransactions, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function suspicious(Request $request)
    {
        $validatedData = $request->validate([
            'type' => [
                'required',
                'string',
                Rule::in(['CASH', 'CARD'])
            ],
        ]);
        try {
            $operatorId = $this->getUserOperatorId();
            if ($validatedData['type'] == 'CASH') {
                 $this->getTransactions($operatorId, TRAIN_CASH_PAYMENT);
            } elseif ($validatedData['type'] == 'CARD') {
                 $this->getTransactions($operatorId, CARD_PAYMENT);
            } else {
                 $this->getTransactions($operatorId, TRAIN_CASH_PAYMENT);

            }
            $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                ->select(
                    'tnx.id',
                    'trains.train_number',
                    'tnx.customer_name',
                    'tnx.trnx_receipt',
                    'cfm_classes.class_type',
                    'tnx.trnx_amount',
                    'tnx.created_at',
                    'tnx.trnx_date',
                    'tnx.trnx_time',
                    'operators.full_name',
                    'start.station_name as start_name',
                    'end.station_name as end_name',
                    'tnx.trnx_number',
                    'special_groups.title',
                    'tnx.device_number'
                )

                ->orderBy('system_date', 'desc')
                ->get();

            $suspiciousTransactions = DB::table('ticket_transactions as tnx')
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                ->select(
                    'trains.id',
                    'trains.train_number',
                    'full_name',
                    'title',
                    'tnx.operator_id',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.trnx_amount) as total_trnx'),
                    DB::raw('MAX(tnx.trnx_date) as trnx_date'),
                    DB::raw('MAX(tnx.trnx_time) as trnx_time'),
                )
                ->where('tnx.category_id', '!=', '1')
                ->groupBy('trains.id', 'trains.train_number', 'full_name', 'title', 'tnx.operator_id')
                ->orderBy(DB::raw('MAX(tnx.created_at)'), 'desc')
                ->get();


            return $this->success($suspiciousTransactions, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function traincapacity()
    {
        try {
            $subquery = DB::table('ticket_transactions as tnx')
                ->select(
                    'tnx.train_id',
                    'tnx.trnx_date',
                    DB::raw('MAX(tnx.created_at) as latest_created_at')
                )
                ->groupBy('tnx.train_id', 'tnx.trnx_date');

            $trainCapasity = DB::table('ticket_transactions as tnx')
                ->joinSub($subquery, 'sub', function($join) {
                    $join->on('tnx.train_id', '=', 'sub.train_id')
                        ->on('tnx.trnx_date', '=', 'sub.trnx_date')
                        ->on('tnx.created_at', '=', 'sub.latest_created_at');
                })
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->join('train_layouts', 'train_layouts.id', '=', 'trains.id')
                ->select(
                    'trains.id',
                    'trains.train_number',
                    'train_layouts.total_seats',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.trnx_amount) as total_trnx'),
                    'tnx.trnx_date',
                    'tnx.created_at'
                )
                ->groupBy('trains.id', 'trains.train_number', 'tnx.trnx_date', 'train_layouts.total_seats', 'tnx.created_at')
                ->orderBy('tnx.created_at', 'desc')
                ->get();



            return $this->success($trainCapasity, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function cancellation(Request $request)
    {
        try {
            $user = Auth::user();
            $userId = $user->id;
            DB::update('update ticket_transactions set trnx_status = ? where id = ?', ['02', $request->transaction_id]);
            DB::insert('insert into transaction_cancellation (transaction_id, reason,user_id,token) values (?, ?,?,?)', [$request->transaction_id, $request->reason, $userId, $request->transaction_id]);
            return response()->json(['status' => 'success', 'message' => 'Transaction Cancelled successfully'], HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function validation(Request $request)
    {
        $validatedData = $request->validate([
            'type' => [
                'required',
                'string',
                Rule::in(['CASH', 'CARD'])
            ],
        ]);

        try {
            $operatorId = $this->getUserOperatorId();
            if ($validatedData['type'] == 'CASH') {
                 $this->getTransactions($operatorId, TRAIN_CASH_PAYMENT);
            } elseif ($validatedData['type'] == 'CARD') {
                $this->getTransactions($operatorId, CARD_PAYMENT);
            } else {
                $this->getTransactions($operatorId, TRAIN_CASH_PAYMENT);

            }
            $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                ->join('train_stations as start', 'start.id', '=', 'tnx.station_from')
                ->join('train_stations as end', 'end.id', '=', 'tnx.station_to')
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->join('cfm_classes', 'cfm_classes.id', '=', 'tnx.class_id')
                ->join('special_groups', 'special_groups.id', '=', 'tnx.category_id')
                ->select(
                    'tnx.id',
                    'trains.train_number',
                    'tnx.customer_name',
                    'cfm_classes.class_type',
                    'tnx.trnx_amount',
                    'tnx.created_at',
                    'tnx.trnx_date',
                    'tnx.trnx_time',
                    'operators.full_name',
                    'start.station_name as start_name',
                    'end.station_name as end_name',
                    'tnx.trnx_number',
                    'special_groups.title',
                    'tnx.device_number'
                )
                ->orderBy('system_date', 'desc')
                ->get();
            return $this->success($lastFiveTransactions, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getZonesTransactions()
    {
        try {
            $operatorId = $this->getUserOperatorId();
            $transactions = $this->getZoneTransactions($operatorId);
            return $this->success($transactions, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
