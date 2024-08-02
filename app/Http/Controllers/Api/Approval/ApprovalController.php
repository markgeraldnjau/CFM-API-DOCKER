<?php

namespace App\Http\Controllers\Api\Approval;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ApprovalCollectionRequest;
use App\Models\Approval\ApprovalProcess;
use App\Models\Approval\ProcessFlowActor;
use App\Models\Cargo\CargoCategory;
use App\Traits\ApiResponse;
use App\Traits\ApprovalTrait;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApprovalController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait, ApprovalTrait;

    //
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_query' => ['nullable', 'string', 'max:255'],
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
            'approval_process_code' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!in_array($value, [OPERATOR_COLLECTIONS_APPROVAL_PROCESS])) {
                    $fail("The $attribute is not valid.");
                }
            }],
        ]);


        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $approvalProcessCode = $request->input('approval_process_code');
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        $status = $request->input('status');
        //
        try {
            $query = DB::table('approval_processes as ap')
                ->join('process_flow_configurations as f', 'f.id', 'ap.process_flow_configuration_id')
                ->join('process_flow_actors as pfa', function ($join) {
                    $join->on('pfa.process_flow_configuration_id', '=', 'ap.process_flow_configuration_id')
                        ->on('pfa.sequence', '=', 'ap.current_actor_sequence');
                })
                ->select(
                    'ap.id as approval_process_id',
                    'ap.token',
                    'ap.name',
                    'ap.initiator_id',
                    'ap.current_actor_id',
                    'ap.current_actor_sequence',
                    'pfa.code as actor_code',
                    'pfa.name as current_actor',
                    'ap.status',
                    DB::raw("
                        CASE ap.status
                            WHEN '" . PENDING_STEP . "' THEN 'Pending'
                            WHEN '" . ON_PROGRESS_STEP . "' THEN 'On Progress'
                            WHEN '" . APPROVED_STEP . "' THEN 'Approved'
                            WHEN '" . REJECTED_STEP . "' THEN 'Rejected'
                            ELSE 'Unknown'
                        END AS status_name
                    "),
                    'ap.comments',
                    'ap.created_at as approval_process_created_at',
                    'ap.updated_at as approval_process_updated_at',
                )->where('f.code', $approvalProcessCode);

            if ($request->status){
                $query->where('ap.status', $status);
            }
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('ap.name', 'like', "%$searchQuery%");
                });
            }
            $cargoCategories = $query->orderByDesc('ap.updated_at')->paginate($itemPerPage);

            if (empty($cargoCategories)) {
                return $this->warn(null, 'No approval process found!', 404);
            }
            $this->auditLog("View Approval processes for " . $approvalProcessCode, PORTAL, null, null);
            return $this->success($cargoCategories, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function show(string $token)
    {
        try {
            $approvalProcess = $this->getApprovalDetailsByToken($token);
            $approvalLogs = [];
            $record = [];
            if (!empty($approvalProcess)){
                $approvalLogs = $this->getApprovalLogsByApprovalId($approvalProcess->id);
                $record = $this->getRecordDetails($approvalProcess);
                $this->auditLog("View Approval Process: ". $approvalProcess->name, PORTAL, null, null);
            }
            $data = [
                'approval_process' => $approvalProcess,
                'record' => $record,
                'approval_logs' => $approvalLogs,
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

    public function store(ApprovalCollectionRequest $request)
    {
        DB::beginTransaction();
        try {
            //TODO: Get id based on who have the privelage
            $approvalProcess = ApprovalProcess::findOrFail($request->approval_process_id);
            $nextStepActorId = ProcessFlowActor::where('process_flow_configuration_id', $approvalProcess->process_flow_configuration_id)
                ->where('sequence', $approvalProcess->current_actor_sequence + 1)
                ->value('id');

            $approvalProcessOld = clone $approvalProcess;

            //Decide Action Per Approval Sequence Level
            $action = $this->getApprovalAction($approvalProcess, $request);

            if (empty($nextStepActorId) && $action == APPROVED_AND_FINISHED){
                $nextStepActorId = 0;
            }

            if (in_array($action, [APPROVE, APPROVED_AND_FINISHED])){
                $response = $this->processApproval($approvalProcess, $action, $nextStepActorId, $request->comment, $request, TRUE);
                if (empty($response)){
                    return $this->error(null, "Something wrong, with approval process update, contact admin for more assistance", 500);
                }
                $this->auditLog("Approve Approval Process: ". $approvalProcess->name, PORTAL, $approvalProcess, $approvalProcessOld);
                DB::commit();
                return $this->success(null, DATA_SAVED);
            } else {
                return $this->error(null, $action, 500);
            }
        } catch (RestApiException $e) {
            DB::rollBack();
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }
}
