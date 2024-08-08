<?php

namespace App\Http\Controllers\Api\Operator;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\OperatorCollectionTransactionRequest;
use App\Models\Approval\ProcessFlowActor;
use App\Models\Cargo\CargoTransaction;
use App\Models\Operator;
use App\Models\OperatorCollection;
use App\Models\Transaction\TicketTransaction;
use App\Traits\ApiResponse;
use App\Traits\ApprovalTrait;
use App\Traits\AuditTrail;
use App\Traits\CollectionTrait;
use App\Traits\OperationTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $validatedData = validator($request->all(), [
            'status' => [
                'required',
                'char:1',
                Rule::in([ACTIVE_STATUS, INACTIVE_STATUS]),
            ],
            'item_per_page' => 'nullable|integer',
        ]);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        try {

            $itemPerPage = $request['item_per_page'];
            $operatorId = $this->getUserOperatorId();
            $collections = $this->getOperationCollections($operatorId, $itemPerPage, $request->status);
            return $this->success($collections, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
             Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
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
                return $this->error(null, 'No operator collection found!', HTTP_NOT_FOUND);
            }

            $this->auditLog("View Operator Collection for  (" . $operatorCollection->transaction_type . ') ' . $operatorCollection->operator_name, PORTAL, null, null);

            $data = [
                'operator_collection' => $operatorCollection,
                'transactions' => $transactions,
            ];
            return $this->success($data, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
             Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
             Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getTrainCollections(Request $request)
    {
        $validatedData = validator($request->all(), [
            'tnxType' => 'required|integer',
            'item_per_page' => 'nullable|integer',
        ]);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        try {

            $itemPerPage = $request['item_per_page'];
            $operatorId = $this->getUserOperatorId();
            $collections = $this->getTrainCollectionsData($operatorId, $itemPerPage, $request->tnxType);
            return $this->success($collections, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
             Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getOperatorTrainTransactions(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'tnx_type' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!in_array($value, [TRAIN_CASH_PAYMENT, TOP_UP_CARD, CARGO])) {
                        $fail("The $attribute must be one of 'Cash', 'Top Up', or 'Cargo' transactions.");
                    }
                }
            ],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $operatorId = $this->getUserOperatorId();
        $tnxType = $request->input('tnx_type');
        try {
            if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])) {
                $transactions = $this->getUnCollectedOperatorTransactions($operatorId, $tnxType);
            } else if ($tnxType == CARGO) {
                $transactions = $this->getUncollectedCargoTransactions($operatorId, $tnxType);
            } else {
                return $this->error(null, 'No transactions found!', HTTP_NOT_FOUND);
            }

            if (!$transactions) {
                return $this->success([], DATA_RETRIEVED);
            }
            $this->auditLog("View all uncollected operator transactions", PORTAL, null, null);
            return $this->success($transactions, DATA_RETRIEVED);
        } catch (\Exception $e) {
             Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
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
             Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
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

            if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])) {
                $transactionCollection = $this->getUnCollectedTrainOperatorCollection($operatorId, $tnxType, $ascTrainNumber);
                $transactions = $this->getTrainTransactionsByAscTrainNumber($operatorId, $tnxType, $ascTrainNumber, $descTrainNumber);

            } else if ($tnxType == CARGO) {
                $transactionCollection = $this->getUnCollectedCargoOperatorCollection($operatorId, $tnxType, $ascTrainNumber);
                $transactions = $this->getCargoTransactionsByAscTrainNumber($operatorId, $tnxType, $ascTrainNumber, $descTrainNumber);
            }



            if (empty($transactionCollection)) {
                return $this->error(null, "No Collection for train number: " . $request->asc_train_number, 404);
            }
            $payload = [
                'operator_id' => $transactionCollection->operator_id,
                'processor_id' => Auth::user()->id,
                'transaction_type' => $transactionCollection->extended_trnx_type,
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
                } else if ($tnxType == CARGO) {
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

            if (empty($nextStepActorId)) {
                return $this->error(null, "Can not find the next actor on approval", HTTP_NOT_FOUND);
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

            if (empty($response)) {
                return $this->error(null, "Something wrong, with initialize approval process, contact admin for more assistance", HTTP_INTERNAL_SERVER_ERROR);
            }

            DB::commit();
            return $this->success(null, DATA_SAVED);
        } catch (\Exception $e) {
             Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
