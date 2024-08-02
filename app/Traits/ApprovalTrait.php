<?php
// CartTrait.php

namespace App\Traits;

use App\Models\Approval\ApprovalLog;
use App\Models\Approval\ApprovalProcess;
use App\Models\Approval\ProcessFlowActor;
use App\Models\Approval\ProcessFlowConfiguration;
use App\Models\OperatorCollection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait ApprovalTrait
{
    use CollectionTrait;

    private function getProcessFlowConfiguration($code){
        return ProcessFlowConfiguration::where('code', $code)->select('id','code', 'name')->first();
    }

    private function initiateApproval($processFlowConfigurationId, $approvalProcessName, $recordId, $recordType, $initiatorId, $currentActorId, $comments = null)
    {
        return ApprovalProcess::updateOrCreate([
            'process_flow_configuration_id' => $processFlowConfigurationId,
            'record_id' => $recordId,
            'name' => $approvalProcessName,
            'record_type' => $recordType,
            'initiator_id' => $initiatorId,
            'current_actor_id' => $currentActorId,
            'current_actor_sequence' => 1,
            'comments' => $comments,
        ]);
    }

    private function logActivity($approvalProcessId, $actorId, $action, $comments = null)
    {
        return ApprovalLog::create([
            'approval_process_id' => $approvalProcessId,
            'actor_id' => $actorId,
            'action' => $action,
            'comments' => $comments,
        ]);
    }


    public function processApproval($approvalProcess, $action, $actorId, $comment, $request, $direction = null)
    {

        $currentActor = $this->getCurrentActor($approvalProcess->id);
        if (empty($currentActor)){
            Log::error("can not find current actor: ". json_encode($approvalProcess));
            return false;
        }

        $status = PENDING_STEP;
        $actionResponse = false;
        // Logic to handle approval process flow based on the action
        switch ($action) {
            case APPROVED:
                $status = ON_PROGRESS_STEP;
                $direction = 1;
                $actionResponse = true;
                $actionResponse = $this->approveAndNextAction($approvalProcess, $request, $comment, $currentActor);
                // Logic to approve the process
                break;
            case APPROVED_AND_FINISHED:
                $status = APPROVED_STEP;
                $actionResponse = $this->approveAndFinish($approvalProcess, $request, $comment, $currentActor);
                // Logic to APPROVED_AND_FINISHED
                break;
            case CANCEL_AND_FINISH:
                $status = REJECTED_STEP;
                $actionResponse = true;
                // Logic to CANCEL_AND_FINISH
                break;
            case REJECTED_AND_RETURNED:
                $direction = 0;
                $actionResponse = true;
                // Logic to rREJECTED_AND_RETURNED
                break;
            default:
                // Handle invalid action
        }

        if (!$actionResponse){
            Log::error("Failed To Execute Step: ". $status);
            return false;
        }

        // Determine the next actor based on the process flow configuration
        $nextActor = $this->getNextActor($approvalProcess, $direction);
        if (!$nextActor){
            Log::error("Failed To get Next Actor: ");
            Log::error(json_encode($approvalProcess));
            return false;
        }

        // dd($nextActor);
        // Update the current actor in the approval process
        $approvalProcess->current_actor_id = $currentActor->actor_id;
        $approvalProcess->comments = $comment;
        $approvalProcess->status = $status;
        $approvalProcess->current_actor_sequence = $nextActor->sequence ?? null;
        $approvalProcess->save();

        // Log the approval/rejection activity
        return $this->logActivity($approvalProcess->id, $currentActor->actor_id, $action, $comment);
    }

    public function approveAndFinish($approvalProcess, $request, $comment, $currentActor)
    {

        if ($approvalProcess->record_type == OperatorCollection::class){
            $tnxType = OperatorCollection::query()->where('id', $approvalProcess->record_id)->value('transaction_type');
            $updateData = OperatorCollection::find($approvalProcess->record_id);
            $updateData->collection_status = APPROVE;
            $updateData->save();

            if (!$updateData || !$tnxType){
                return false;
            }
            $updateTransactionResponse = $this->updateCollectedTransactions($approvalProcess->record_id, $tnxType);
            if (!$updateTransactionResponse){
                Log::error("Failed To Update Operator Collected Transactions for: ". json_encode($approvalProcess->record_id, $approvalProcess->record_type, $tnxType));
                return false;
            }
            return true;
        } else {
            Log::error("Failed To Find Record: ". json_encode($approvalProcess->record_id, $approvalProcess->record_type));
            return false;
        }

    }

    public function getApprovalAction($approvalProcess, $request)
    {
        if ($approvalProcess->record_type == OperatorCollection::class) {

            $currectActor = $this->getCurrentActor($approvalProcess->id);
//            dd($currectActor->actor_code, SERVICO_DE_TRANSPORTE);
            if ($currectActor->actor_code == SERVICO_DE_TRANSPORTE){
                return APPROVED_AND_FINISHED;
            }

            // For Ascending Train
            $collectionData = OperatorCollection::find($approvalProcess->record_id);

            // Check if collectionData exists
            if (!$collectionData) {
                // Handle the case where the collection data is not found
                return NOT_FOUND;
            }

            // Ensure amounts are numeric
            $ascSystemAmount = $collectionData->asc_system_amount;
            $ascPrintOutAmount = $collectionData->asc_print_out_amount;
            $ascPhysicalAmount = $request->asc_physical_amount;
            $descSystemAmount = $collectionData->desc_system_amount;
            $descPrintOutAmount = $collectionData->desc_print_out_amount;
            $descPhysicalAmount = $request->desc_physical_amount;

            if (
                !is_numeric($ascSystemAmount) || !is_numeric($ascPrintOutAmount) || !is_numeric($ascPhysicalAmount) ||
                !is_numeric($descSystemAmount) || !is_numeric($descPrintOutAmount) || !is_numeric($descPhysicalAmount)
            ) {
                // Handle invalid data
                return INVALID_DATA; // Define a constant or appropriate response
            }

            // Cast to float for comparison
            $ascSystemAmount = (float)$ascSystemAmount;
            $ascPrintOutAmount = (float)$ascPrintOutAmount;
            $ascPhysicalAmount = (float)$ascPhysicalAmount;
            $descSystemAmount = (float)$descSystemAmount;
            $descPrintOutAmount = (float)$descPrintOutAmount;
            $descPhysicalAmount = (float)$descPhysicalAmount;

            // Check for mismatches with physical amounts
            $ascMismatch = ($ascPhysicalAmount != $ascSystemAmount) || ($ascPhysicalAmount != $ascPrintOutAmount);
            $descMismatch = ($descPhysicalAmount != $descSystemAmount) || ($descPhysicalAmount != $descPrintOutAmount);

            if (($ascSystemAmount == $ascPrintOutAmount) && ($descSystemAmount == $descPrintOutAmount)) {
                if (!$ascMismatch && !$descMismatch) {
                    return APPROVED_AND_FINISHED;
                } else {
                    return APPROVED; // With mismatches, it's approved but not finished
                }
            } else {
                return APPROVED;
            }
            // Optionally, handle an unexpected case if needed
        }

        return UNEXPECTED_CASE;
    }

    public function approveAndNextAction($approvalProcess, $request, $comment, $currentActor)
    {
        if ($approvalProcess->record_type == OperatorCollection::class){
            if ($currentActor->actor_code == REVISOR){
                $tnxType = OperatorCollection::query()->where('id', $approvalProcess->record_id)->value('transaction_type');
                $receiver_id = Auth::user()->id;
                $updateData = OperatorCollection::find($approvalProcess->record_id);
                if (!$updateData->receive_id){
                    $updateData->receive_id = $receiver_id;
                    $updateData->processor_id = $receiver_id;
                    $updateData->receiver_comment = $comment;
                    $updateData->date_received = Carbon::now();
                }
                //Asc
                $updateData->asc_physical_amount = $request->asc_physical_amount;
                $updateData->asc_physical_tickets = 0;
                //Desc
                $updateData->desc_physical_amount = $request->desc_physical_amount;
                $updateData->desc_physical_tickets = 0;

                if ($request->any_asc_data) {
                    //Asc
                    $updateData->asc_manual_amount = $request->asc_manual_amount;
                    $updateData->asc_manual_tickets = $request->asc_manual_tickets;
                }

                if ($request->any_desc_data){
                    //Desc
                    $updateData->desc_manual_amount = $request->desc_manual_amount;
                    $updateData->desc_manual_tickets = $request->desc_manual_tickets;
                }

                $updateData->paid_amount = $request->received_amount;
                $updateData->collection_status = PENDING_STEP;
                $updateData->save();

                if (!$updateData || !$tnxType){
                    return false;
                }
            }
            return true;
        } else {
            Log::error("Failed To Find Record: ". json_encode($approvalProcess));
            return false;
        }
    }

    private function getCurrentActor($approvalProcessId)
    {
        return DB::table('approval_processes as ap')->where('ap.id', $approvalProcessId)
            ->join('users as u', 'u.id', 'ap.initiator_id')
            ->join('process_flow_actors as pfa', function ($join) {
                $join->on('pfa.process_flow_configuration_id', '=', 'ap.process_flow_configuration_id')
                    ->on('pfa.sequence', '=', 'ap.current_actor_sequence');
            })
            ->select(
                'ap.id',
                'pfa.id as actor_id',
                'pfa.code as actor_code',
                'pfa.name as current_actor',
            )->first();
    }

    private function getNextActor($approvalProcess, $direction)
    {
        // Logic to determine the next actor based on the current state of the process
        $nextActorSequence = $approvalProcess->current_actor_sequence;
        $nextActor = null;
        if ($direction){
            $nextActorSequence += 1;
        } else {
            $nextActorSequence -= 1;
        }
        if ($nextActorSequence >= 1){
            $nextActor = ProcessFlowActor::where('process_flow_configuration_id', $approvalProcess->process_flow_configuration_id)->where('sequence', $nextActorSequence)->first();
            if (empty($nextActor)){
                $nextActor = ProcessFlowActor::where('process_flow_configuration_id', $approvalProcess->process_flow_configuration_id)->where('sequence', $approvalProcess->current_actor_sequence)->first();
            }
        }

        return $nextActor;
    }


    public function getApprovalLogsByApprovalId($approvalId)
    {
        return DB::table('approval_logs as al')
            ->join('approval_processes as ap', 'ap.id', 'al.approval_process_id')
            ->join('process_flow_actors as pfa', 'pfa.id', 'al.actor_id')
            ->select(
                'al.id',
                'al.token',
                'al.approval_process_id',
                'al.actor_id',
                'pfa.name as current_actor',
                'al.action',
                DB::raw("
                        CASE al.action
                            WHEN '" . REJECTED_AND_RETURNED . "' THEN 'Rejected & Returned'
                            WHEN '" . APPROVED . "' THEN 'Approved'
                            WHEN '" . APPROVED_AND_FINISHED . "' THEN 'Approved & Finished'
                            WHEN '" . CANCEL_AND_FINISH . "' THEN    'Cancel & Finish'
                            ELSE 'Unknown'
                        END AS action_name
                    "),
                'al.comments',
                'al.created_at',
                'al.updated_at',
            )->where('al.approval_process_id', $approvalId)->get();
    }


    public function getRecordDetails($approvalProcess)
    {

        switch ($approvalProcess->record_type) {
            case OperatorCollection::class:
                return $this->getOperationCollectionDetailById($approvalProcess->record_id);
            default:
                return [];
        }

    }

    public function getApprovalDetailsByToken($token)
    {
        return DB::table('approval_processes as ap')->where('ap.token', $token)
            ->join('users as u', 'u.id', 'ap.initiator_id')
            ->join('process_flow_actors as pfa', function ($join) {
                $join->on('pfa.process_flow_configuration_id', '=', 'ap.process_flow_configuration_id')
                    ->on('pfa.sequence', '=', 'ap.current_actor_sequence');
            })
            ->select(
                'ap.id',
                'ap.token',
                'ap.name',
                'ap.initiator_id',
                DB::raw('CONCAT(u.first_name, " " , u.last_name) as inititator_name'),
                'ap.record_id',
                'ap.record_type',
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
            )->first();
    }


}
