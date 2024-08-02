<?php

namespace App\Http\Controllers\Api\Operator;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\OperatorCollectionTransactionRequest;
use App\Models\Approval\ApprovalProcess;
use App\Models\Approval\ProcessFlowActor;
use App\Models\Cargo\CargoTransaction;
use App\Models\Operator;
use App\Models\OperatorCollection;
use App\Models\Package;
use App\Models\Role;
use App\Models\Transaction\TicketTransaction;
use App\Models\WeightTransaction;
use App\Traits\ApiResponse;
use App\Traits\ApprovalTrait;
use App\Traits\AuditTrail;
use App\Traits\CollectionTrait;
use App\Traits\OperationTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OperatorCollectionsController extends Controller
{
    use AuditTrail, CollectionTrait, ApiResponse, OperationTrait, ApprovalTrait;
    //
    public function allOperationCollection(Request $request)
    {
        try {

            $itemPerPage = $request['item_per_page'];
            $operatorId = $this->getUserOperatorId();
            $collections = $this->getOperationCollections($operatorId, $itemPerPage, $request->status);
            return $this->success($collections, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getOperationCollectionDetails(string $token)
    {
        //
        try {
            $operatorCollection = $this->getOperationCollectionDetailByToken($token);
            $transactions = $this->getCollectionTransactions($operatorCollection->id, $operatorCollection->transaction_type);
            if (!$operatorCollection) {
                return $this->error(null, 'No operator collection found!', 404);
            }

            $this->auditLog("View Operator Collection for  (". $operatorCollection->transaction_type .') '. $operatorCollection->operator_name, PORTAL, null, null);

            $data = [
                'operator_collection' => $operatorCollection,
                'transactions' => $transactions,
            ];
            return $this->success($data, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getTrainCollections(Request $request)
    {
        try {

            $itemPerPage = $request['item_per_page'];
            $operatorId = $this->getUserOperatorId();
            $collections = $this->getTrainCollectionsData($operatorId, $itemPerPage, $request->tnxType);
            return $this->success($collections, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getOperatorTrainTransactions(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'tnx_type' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!in_array($value, [TRAIN_CASH_PAYMENT, TOP_UP_CARD, CARGO])) {
                    $fail("The $attribute must be one of 'Cash', 'Top Up', or 'Cargo' transactions.");
                }
            }],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $operatorId = $this->getUserOperatorId();
        $tnxType = $request->input('tnx_type');
        try {
            if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])){
                $transactions = $this->getUnCollectedOperatorTransactions($operatorId, $tnxType);
            } else if ($tnxType == CARGO){
                $transactions = $this->getUncollectedCargoTransactions($operatorId, $tnxType);
            } else {
                return $this->error(null, 'No transactions found!', 404);
            }

            if (!$transactions) {
                return $this->success([], DATA_RETRIEVED);
            }
            $this->auditLog("View all uncollected operator transactions", PORTAL, null, null);
            return $this->success($transactions, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getPrePostItems()
    {
        try {
            $operatorId = $this->getUserOperatorId();
            $cashTrainCollections = $this->getOperatorTrainTransactionsCount($operatorId, TRAIN_CASH_PAYMENT);
            $topUpTransactions = $this->getOperatorTrainTransactionsCount($operatorId, TOP_UP_CARD);
            $cargoTransactions = $this->getCargoTransactionCount($operatorId, CARGO);

            $response = [
                'Train Cash Transactions' => $cashTrainCollections,
                'Top Up Transactions' => $topUpTransactions,
                'Cargo Transactions' => $cargoTransactions
            ];
            return $this->success($response, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }

    public function postOperatorTransactions(OperatorCollectionTransactionRequest $request)
    {
        DB::beginTransaction();
        try {
            $operatorId = $this->getUserOperatorId();
            $ascTrainNumber = $request->asc_train_number;
            $descTrainNumber = $request->desc_train_number;
            $tnxType = $request->transaction_type;
            $transactionCollection = null;
            $transactions = [];

            if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])){
                $transactionCollection = $this->getUnCollectedTrainOperatorCollection($operatorId, $tnxType, $ascTrainNumber);
                $transactions = $this->getTrainTransactionsByAscTrainNumber($operatorId, $tnxType, $ascTrainNumber, $descTrainNumber);

            }else if($tnxType == CARGO){
                $transactionCollection = $this->getUnCollectedCargoOperatorCollection($operatorId, $tnxType, $ascTrainNumber);
                $transactions = $this->getCargoTransactionsByAscTrainNumber($operatorId, $tnxType, $ascTrainNumber, $descTrainNumber);
            }



            if (empty($transactionCollection)){
                return $this->error(null, "No Collection for train number: ". $request->asc_train_number, 404);
            }
            $payload = [
                'operator_id' => $transactionCollection->operator_id,
                'processor_id' => Auth::user()->id,
                'transaction_type' => $transactionCollection->extended_trnx_type,
//                'deposited_amount' => $transactionCollection->total_amount ?? 0,
//                'paid_amount' => $transactionCollection->total_amount ?? 0,
                'recebedor_status' => 0,
                'asc_train_id' => $transactionCollection->asc_train_id,
                'asc_system_amount' => $transactionCollection->total_amount_asc ?? 0,
                'asc_multa' => $transactionCollection->asc_multa_amount ?? 0,
                'asc_system_tickets' => $transactionCollection->count_asc_transactions ?? 0,
                'asc_departure_date' => $transactionCollection->min_trnx_time_asc,
                'asc_arrival_date' => $transactionCollection->max_trnx_time_asc,
                'desc_train_id' => $transactionCollection->desc_train_id,
                'desc_system_amount' => $transactionCollection->total_amount_desc ?? 0,
                'desc_multa' => $transactionCollection->desc_multa_amount ?? 0,
                'desc_system_tickets' => $transactionCollection->count_desc_transactions ?? 0,
                'desc_departure_date' => $transactionCollection->min_trnx_time_desc,
                'desc_arrival_date' => $transactionCollection->max_trnx_time_desc
            ];

            $collection = OperatorCollection::updateOrCreate($payload);
            foreach ($transactions as $transaction) {
                if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])) {
                    TicketTransaction::where('id', $transaction->id)
                        ->update([
                            'collection_batch_number_id' => $collection->id,
                        ]);
                }else if($tnxType == CARGO){
                    CargoTransaction::where('id', $transaction->id)
                        ->update([
                            'collection_batch_number_id' => $collection->id,
                        ]);
                }
            }

            //initialize Approval Process
            $approvalProcessConfiguration = $this->getProcessFlowConfiguration(OPERATOR_COLLECTIONS_APPROVAL_PROCESS);

            $nextStepActorId = ProcessFlowActor::where('process_flow_configuration_id', $approvalProcessConfiguration->id)
                ->where('sequence', 2)
                ->value('id');

            if (empty($nextStepActorId)){
                return $this->error(null, "Can not find the next actor on approval", 404);
            }

            $operator = Operator::findOrFail($operatorId, ['full_name']);
            $approvalProcessName = $approvalProcessConfiguration->name . " for operator " . $operator->full_name;
            $approvalProcess = $this->initiateApproval(
                $approvalProcessConfiguration->id,
                $approvalProcessName,
                $collection->id,
                OperatorCollection::class,
                Auth::user()->id,
                $nextStepActorId,
                "Initialize Approval"
            );
            $response = $this->processApproval($approvalProcess, APPROVED, $nextStepActorId, $approvalProcess->comments, $request, TRUE);

            if (empty($response)){
                return $this->error(null, "Something wrong, with initialize approval process, contact admin for more assistance", 500);
            }

            DB::commit();
            return $this->success(null, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


//    public function postOperatorTransactions(Request $request)
//    {
//        DB::beginTransaction();
//        try {
//            $operatorId = $this->getUserOperatorId();
//            $tnxType = $request->transaction_type;
//            $transactions = [];
//            $collectedAmountPerTrains = [];
//            if ($tnxType == CARGO){
//                $cargoTransactions = $this->getUncollectedCargoTransactions($operatorId, $tnxType);
//                $cargoSumAmount = $this->getUnCollectedCargoSum($operatorId, $tnxType);
////                return $cargoTransactions;
//            } else if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])){
//                $transactions = $this->getUnCollectedOperatorTransactions($operatorId, $tnxType);
//                if ($tnxType == TRAIN_CASH_PAYMENT){
//                    $collectedAmountPerTrains = $this->getUncollectedOperatorSum($operatorId, $tnxType);
//                    return $transactions;
//                } else if ($tnxType == TOP_UP_CARD) {
//                    $collectedAmountPerTrains = $this->getTopUpTransactions($operatorId, $tnxType);
//                    return $transactions;
//                }
//            }
//
//            if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])) {
//                if ($collectedAmountPerTrains){
//                    foreach ($collectedAmountPerTrains as $collectedAmountPerTrain) {
////                        return $collectedAmountPerTrain;
//                        $collection = OperatorCollection::create([
//                            'operator_ID' => $operatorId,
//                            'processor_ID' => Auth::user() ?? 0, //TODO: To put it after creating processor
//                            'transaction_type' => $tnxType,
//                            'deposited_amount' => $collectedAmountPerTrain->total_amount,
//                            'paid_amount' => $collectedAmountPerTrain->total_amount,
//                            'recebedor_status' => 0,
//                            // 'asc_TrainId' => $trainAsc,
//                            // 'asc_DepartureDate' => $ascDepartureDate,
//                            // 'asc_ArrivalDate' => $ascArrivalDate,
//                            // 'desc_TrainId' => $trainDesc,
//                            // 'desc_DepartureDate' => $descDepartureDate,
//                            // 'desc_ArrivalDate' => $descArrivalDate
//                        ]);
//
//                        foreach($transactions as $transaction){
//                            Transaction::where('id', $transaction->id)
//                                ->update([
//                                    'collection_batch_number_id' => $collection->id,
//                                    'is_collected' => true
//                                ]);
//                        }
//                    }
//                }
//            } elseif ($tnxType == CARGO){
//                if ($cargoSumAmount && $cargoTransactions){
//                    $collection = OperatorCollection::create([
//                        'operator_ID' => $operatorId,
//                        'processor_ID' => Auth::user() ?? 0, //TODO: To put it after creating processor
//                        'transaction_type' => $tnxType,
//                        'deposited_amount' => $cargoSumAmount->grand_total_amount,
//                        'paid_amount' => $cargoSumAmount->grand_total_amount,
//                        'recebedor_status' => 0,
//                    ]);
//                    foreach ($cargoTransactions as $cargoTransaction){
//                        WeightTransaction::where('id', $cargoTransaction->id)
//                            ->update([
//                                'collection_batch_number_id' => $collection->id,
//                                'is_collected' => true
//                            ]);
//                    }
//                }
//
//            }
//
//            DB::commit();
//            return $this->success(null, DATA_SAVED);
//        } catch (\Exception $e) {
//
//            Log::error($e->getMessage());
//            $statusCode = $e->getCode() ?: 500;
//            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
//            throw new RestApiException($statusCode, $errorMessage);
//        }
//
//        //        $payload = [
////            'operator_id' => $operatorId,
////            'receive_id' => 1,
////            'date_received' => Carbon::now(),
////            'receiver_Comment' => 'Received with thanks',
////            'veto_Id' => 0,
////            'veto_date' => null,
////            'veto_comment' => null,
////            'approval_id' => 1,
////            'data_approved_at' => now(),
////            'approval_comment' => 'Approved',
////            'char_confirm_status' => 'A',
////            'receipt_number' => '123456789',
////            'deposited_amount' => 5000.00,
////            'transaction_type' => 1,
////            'asc_train_id' => 2,
////            'asc_train_direction_id' => 1,
////            'asc_system_amount' => 2000.00,
////            'asc_system_tickets' => 100,
////            'asc_print_out_amount' => 1500.00,
////            'asc_print_out_tickets' => 75,
////            'asc_physical_amount' => 500.00,
////            'asc_physical_tickets' => 25,
////            'asc_manual_amount' => 0.00,
////            'asc_manual_tickets' => 0,
////            'asc_arrival_date' => now(),
////            'asc_departure_date' => now(),
////            'asc_multa' => 0,
////            'desc_train_id' => 6,
////            'desc_train_direction' => 2,
////            'desc_system_amount' => 1000.00,
////            'desc_system_tickets' => 50,
////            'desc_print_out_amount' => 500.00,
////            'desc_print_out_tickets' => 25,
////            'desc_physical_amount' => 250.00,
////            'desc_physical_tickets' => 10,
////            'desc_manual_amount' => 0.00,
////            'desc_manual_tickets' => 0,
////            'paid_amount' => 0.00,
////            'desc_departure_date' => now(),
////            'desc_arrival_date' => now(),
////            'desc_multa' => 0,
////            'stp_approval_status' => null,
////            'fiscal_raised_amount' => 0.00,
////            'recebedor_status' => 1,
////            'status' => 'A',
////            'created_at' => now(),
////            'updated_at' => now(),
////        ];
//    }


}
