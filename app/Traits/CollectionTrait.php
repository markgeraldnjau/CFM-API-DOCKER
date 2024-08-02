<?php
// CartTrait.php

namespace App\Traits;

use App\Models\Cargo\CargoTransaction;
use App\Models\OperatorCollection;
use App\Models\Transaction\TicketTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait CollectionTrait
{

    public function getOperationCollections($operatorId, $dataPerPage, $status)
    {
        $query = OperatorCollection::query()
            ->select([
                'operator_collections.id',
                'operator_collections.token',
                'operators.full_name as operator_name',
                'td1.train_number as td1_Train_Number',
                'operator_collections.asc_system_amount as asc_SystemAmount',
                'operator_collections.asc_system_tickets as asc_SystemTickets',
                'operator_collections.asc_print_out_amount as asc_PrintOutAmount',
                'operator_collections.asc_arrival_date as asc_ArrivalDate',
                'operator_collections.asc_physical_amount as asc_PhysicalAmount',
                'td2.train_Number as td2_Train_Number',
                'operator_collections.desc_system_amount as desc_SystemAmount',
                'operator_collections.desc_system_tickets as desc_SystemTickets',
                'operator_collections.desc_print_out_amount as desc_PrintOutAmount',
                'operator_collections.desc_arrival_date as desc_ArrivalDate',
                'operator_collections.desc_physical_amount as desc_PhysicalAmount',
                'operator_collections.collection_status',
                'ocs.name as collection_status',
                'operator_collections.deposited_amount',
                'operator_collections.updated_at',
                'operator_collections.transaction_type',
                'ett.name as extended_transaction_name',
                'operator_collections.asc_departure_date',
                'operator_collections.asc_arrival_date',
                'operator_collections.desc_arrival_date',
                'operator_collections.desc_departure_date',
            ])
            ->leftJoin('operators', 'operator_collections.operator_id', 'operators.id')
            ->leftJoin('trains as td1', 'operator_collections.asc_train_id', 'td1.id')
            ->leftJoin('trains as td2', 'operator_collections.desc_train_id', 'td2.id')
            ->join('extended_transaction_types as ett', 'ett.code', 'operator_collections.transaction_type')
            ->join('operator_collection_statuses as ocs', 'ocs.code', 'operator_collections.collection_status')
            ->where('operator_collections.status', $status);

        if ($operatorId) {
            $query->where('operator_collections.operator_id', $operatorId);
        }


        return $query->orderByDesc('operator_collections.id')->paginate($dataPerPage);
    }

    public function getOperationCollectionDetailByToken($token)
    {
        return DB::table('operator_collections as oc')
            ->select([
                'oc.id',
                'oc.token',
                'operators.full_name as operator_name',
                'oc.transaction_type',
                'asc_t.train_number as asc_train_number',
                'oc.asc_system_amount as asc_system_amount',
                'oc.asc_system_tickets as asc_system_tickets',
                'oc.asc_print_out_amount',
                'oc.asc_print_out_tickets',
                'oc.asc_physical_amount',
                'oc.asc_physical_tickets',
                'oc.asc_manual_amount',
                'oc.asc_manual_tickets',
                'desc_t.train_number as desc_train_number',
                'oc.desc_system_amount as desc_system_amount',
                'oc.desc_system_tickets as desc_system_tickets',
                'oc.desc_print_out_amount',
                'oc.desc_print_out_tickets',
                'oc.desc_physical_amount',
                'oc.desc_physical_tickets',
                'oc.desc_manual_amount',
                'oc.desc_manual_tickets',
                'oc.collection_status',
                'ocs.name as collection_status',
                'oc.deposited_amount',
                'oc.updated_at',
                'oc.transaction_type',
                'ett.name as extended_transaction_name',
                'oc.asc_arrival_date',
                'oc.asc_departure_date',
                'oc.desc_arrival_date',
                'oc.desc_departure_date',
            ])
            ->leftJoin('operators', 'oc.operator_id', 'operators.id')
            ->leftJoin('trains as asc_t', 'oc.asc_train_id', 'asc_t.id')
            ->leftJoin('trains as desc_t', 'oc.desc_train_id', 'desc_t.id')
            ->join('extended_transaction_types as ett', 'ett.code', 'oc.transaction_type')
            ->join('operator_collection_statuses as ocs', 'ocs.code', 'oc.collection_status')
            ->where('oc.token', $token)
            ->first();
    }

    public function getOperationCollectionDetailById($id)
    {
        return DB::table('operator_collections as oc')
            ->select([
                'oc.id',
                'oc.token',
                'operators.full_name as operator_name',
                'oc.transaction_type',
                'asc_t.train_number as asc_train_number',
                'oc.asc_system_amount as asc_system_amount',
                'oc.asc_system_tickets as asc_system_tickets',
                'oc.asc_print_out_amount',
                'oc.asc_print_out_tickets',
                'oc.asc_physical_amount',
                'oc.asc_physical_tickets',
                'oc.asc_manual_amount',
                'oc.asc_manual_tickets',
                'desc_t.train_number as desc_train_number',
                'oc.desc_system_amount as desc_system_amount',
                'oc.desc_system_tickets as desc_system_tickets',
                'oc.desc_print_out_amount',
                'oc.desc_print_out_tickets',
                'oc.desc_physical_amount',
                'oc.desc_physical_tickets',
                'ocs.name as collection_status',
                'oc.desc_manual_amount',
                'oc.desc_manual_tickets',
                'oc.paid_amount as received_amount',
                'oc.collection_status',
                'oc.deposited_amount',
                'oc.updated_at',
                'oc.transaction_type',
                'ett.name as extended_transaction_name',
                'oc.asc_arrival_date',
                'oc.asc_departure_date',
                'oc.desc_arrival_date',
                'oc.desc_departure_date',
            ])
            ->leftJoin('operators', 'oc.operator_id', 'operators.id')
            ->leftJoin('trains as asc_t', 'oc.asc_train_id', 'asc_t.id')
            ->leftJoin('trains as desc_t', 'oc.desc_train_id', 'desc_t.id')
            ->join('extended_transaction_types as ett', 'ett.code', 'oc.transaction_type')
            ->join('operator_collection_statuses as ocs', 'ocs.code', 'oc.collection_status')
            ->where('oc.id', $id)
            ->first();
    }

    public function getCollectionTransactions($collectionId, $tnxType)
    {
        $transactions = [];
        if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])){
            $transactions = $this->getTrainTransactionsByCollectionId($collectionId);
        } else if ($tnxType == CARGO){
            $transactions = $this->getCargoTransactionsByCollectionId($collectionId);
        }
        return $transactions;
    }

    public function getTrainTransactionsByCollectionId($collectionId)
    {
        return DB::table('ticket_transactions as tnx')
            ->join('trains as t', 't.id', 'tnx.train_id')
            ->where('tnx.collection_batch_number_id', $collectionId)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->select(
                'tnx.id',
                't.id as train_id',
                't.train_number as train_number',
                'tnx.trnx_amount as transaction_amount',
                DB::raw('CONCAT(tnx.trnx_date, " ", tnx.trnx_time) as transaction_date')
            )->get();
    }

    public function getTrainTransactionsByAscTrainNumber($operatorId, $tnxType, $ascTrainNumber, $descTrainNumber)
    {
        return DB::table('ticket_transactions as tnx')
            ->join('trains as t', 't.id', 'tnx.train_id')
            ->where('tnx.operator_id', $operatorId)
            ->where('tnx.extended_trnx_type', $tnxType)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->where('tnx.is_collected', 0)
            ->where('tnx.collection_batch_number_id', 0)
            ->where(function ($query) use ($ascTrainNumber, $descTrainNumber) {
                $query->where('t.train_number', $ascTrainNumber)
                    ->orWhere('t.train_number', $descTrainNumber);
            })
            ->select(
                'tnx.id',
                'tnx.operator_id',
                't.id as train_id',
                't.train_number as train_number',
                'tnx.trnx_amount',
            )->get();
    }

    public function getTrainCollectionsData($operatorId, $dataPerPage, $tnxType)
    {
        $query = OperatorCollection::query()
            ->select([
                'operator_collections.id',
                'operators.full_name as operator_name',
                'td1.train_number as td1_Train_Number',
                'operator_collections.asc_system_amount as asc_SystemAmount',
                'operator_collections.asc_system_tickets as asc_SystemTickets',
                'operator_collections.asc_print_out_amount as asc_PrintOutAmount',
                'operator_collections.asc_arrival_date as asc_ArrivalDate',
                'operator_collections.asc_physical_amount as asc_PhysicalAmount',
                'td2.train_number as td2_Train_Number',
                'operator_collections.desc_system_amount as desc_SystemAmount',
                'operator_collections.desc_system_tickets as desc_SystemTickets',
                'operator_collections.desc_print_out_amount as desc_PrintOutAmount',
                'operator_collections.desc_arrival_date as desc_ArrivalDate',
                'operator_collections.desc_physical_amount as desc_PhysicalAmount',
                'operator_collections.collection_status',
                'ocs.name as collection_status',
                'operator_collections.deposited_amount',
                'operator_collections.updated_at',
                'operator_collections.transaction_type',
                'ett.name as extended_transaction_name',
                'operator_collections.asc_arrival_date',
                'operator_collections.asc_departure_date',
                'operator_collections.desc_arrival_date',
                'operator_collections.desc_departure_date',
            ])
            ->leftJoin('operators', 'operator_collections.operator_id', 'operators.id')
            ->leftJoin('trains as td1', 'operator_collections.asc_train_id', 'td1.id')
            ->leftJoin('trains as td2', 'operator_collections.desc_train_id', 'td2.id')
            ->join('extended_transaction_types as ett', 'ett.code', 'operator_collections.transaction_type')
            ->join('operator_collection_statuses as ocs', 'ocs.code', 'operator_collections.collection_status')
            ->where('operator_collections.transaction_type', $tnxType);

        if (Auth::user()) {
            $query->where('operators.operator_id', $operatorId);
        }

        return $query->orderByDesc('operator_collections.id')->paginate($dataPerPage);
    }

    public function getOperatorTrainTransactionsCount($operatorId, $extendedTnxType)
    {
        return DB::table('ticket_transactions as tnx')
            ->select(
                'tnx.operator_id',
                'tnx.extended_trnx_type as transaction_type',
                DB::raw('COUNT(tnx.id) as transaction_count')
            )
            ->where('tnx.operator_id', $operatorId)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->where('tnx.is_collected', 0)
            ->where('tnx.collection_batch_number_id', 0)
            ->where('tnx.extended_trnx_type', $extendedTnxType)
            ->groupBy('tnx.operator_id', 'tnx.extended_trnx_type') // Include extended_trnx_type in the group by clause
            ->first();

    }

    public function getCargoTransactionCount($operatorId, $extendedTnxType)
    {
        return DB::table('cargo_transactions as ct')
            ->select(
                'ct.operator_id',
                'ct.extended_trnx_type as transaction_type',
                DB::raw('COUNT(ct.id) as transaction_count')
            )
            ->where('ct.operator_id', $operatorId)
            ->whereIn('ct.trnx_status', ['00', '0'])
            ->where('ct.is_collected', 0)
            ->where('ct.collection_batch_number_id', 0)
            ->where('ct.extended_trnx_type', $extendedTnxType)
            ->groupBy('ct.operator_id', 'ct.extended_trnx_type')
            ->first();
    }

    public function getUnCollectedOperatorTransactions($operatorId, $extendedTnxType)
    {

        $data = DB::table('ticket_transactions as tnx')
            ->join('trains as asc_t', 'asc_t.id', 'tnx.train_id')
            ->leftJoin('trains as desc_t', 'desc_t.id', 'asc_t.reverse_train_id')
            ->where('tnx.operator_id', $operatorId)
            ->where('tnx.extended_trnx_type', $extendedTnxType)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->where('tnx.is_collected', 0)
            ->where('tnx.collection_batch_number_id', 0)
            ->select(
                'tnx.operator_id',
                'tnx.extended_trnx_type',
                'asc_t.id as asc_train_id',
                'desc_t.id as desc_train_id',
                'asc_t.train_number as asc_train_number',
                'desc_t.train_number as desc_train_number',
                DB::raw('SUM(CASE WHEN tnx.train_id = asc_t.id THEN tnx.trnx_amount ELSE 0 END) AS total_amount_asc'),
                DB::raw('(SELECT COALESCE(SUM(trnx_amount), 0) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS total_amount_desc'),
                DB::raw('COUNT(CASE WHEN tnx.train_id = asc_t.id THEN 1 END) AS count_asc_transactions'),
                DB::raw('(SELECT COUNT(*) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS count_desc_transactions'),
                DB::raw('COALESCE(MIN(CASE WHEN tnx.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS min_trnx_time_asc'),
                DB::raw('COALESCE(MAX(CASE WHEN tnx.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS max_trnx_time_asc'),
                DB::raw('(SELECT COALESCE(MIN(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS min_trnx_time_desc'),
                DB::raw('(SELECT COALESCE(MAX(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS max_trnx_time_desc')
            )
            ->groupBy(
                'tnx.operator_id',
                'tnx.extended_trnx_type',
                'asc_t.id',
                'desc_t.id',
                'asc_t.train_number',
                'desc_t.train_number',
            )
            ->orderBy('asc_t.id', 'asc')
            ->orderBy('desc_t.id', 'asc')
            ->get();


        $filteredData = [];
        $seenPairs = [];
        foreach ($data as $item) {
            $sortedPair = implode('-', collect([$item->asc_train_id, $item->desc_train_id])->sort()->toArray());
            if (!in_array($sortedPair, $seenPairs)) {
                $filteredData[] = $item;
                $seenPairs[] = $sortedPair;
            }
        }

        return $filteredData;

    }

    public function getUnCollectedTrainOperatorCollection($operatorId, $extendedTnxType, $ascTrainNumber)
    {

        return DB::table('ticket_transactions as tnx')
            ->join('trains as asc_t', 'asc_t.id', 'tnx.train_id')
            ->leftJoin('trains as desc_t', 'desc_t.id', 'asc_t.reverse_train_id')
            ->where('tnx.operator_id', $operatorId)
            ->where('tnx.extended_trnx_type', $extendedTnxType)
            ->where('asc_t.train_number', $ascTrainNumber)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->where('tnx.is_collected', 0)
            ->where('tnx.collection_batch_number_id', 0)
            ->select(
                'tnx.operator_id',
                'tnx.extended_trnx_type',
                'asc_t.id as asc_train_id',
                'desc_t.id as desc_train_id',
                'asc_t.train_number as asc_train_number',
                'desc_t.train_number as desc_train_number',
                DB::raw('SUM(CASE WHEN tnx.train_id = asc_t.id THEN tnx.trnx_amount ELSE 0 END) AS total_amount_asc'),
                DB::raw('SUM(CASE WHEN tnx.train_id = asc_t.id THEN tnx.fine_amount ELSE 0 END) AS asc_multa_amount'),
                DB::raw('(SELECT COALESCE(SUM(trnx_amount), 0) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS total_amount_desc'),
                DB::raw('(SELECT COALESCE(SUM(fine_amount), 0) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS desc_multa_amount'),
                DB::raw('SUM(CASE WHEN tnx.train_id = asc_t.id THEN tnx.trnx_amount ELSE 0 END) + (SELECT COALESCE(SUM(trnx_amount), 0) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS total_amount'),
                DB::raw('COUNT(CASE WHEN tnx.train_id = asc_t.id THEN 1 END) AS count_asc_transactions'),
                DB::raw('(SELECT COUNT(*) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS count_desc_transactions'),
                DB::raw('COALESCE(MIN(CASE WHEN tnx.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS min_trnx_time_asc'),
                DB::raw('COALESCE(MAX(CASE WHEN tnx.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS max_trnx_time_asc'),
                DB::raw('(SELECT COALESCE(MIN(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS min_trnx_time_desc'),
                DB::raw('(SELECT COALESCE(MAX(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM ticket_transactions WHERE train_id = desc_t.id AND operator_id = tnx.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS max_trnx_time_desc')
            )
            ->groupBy(
                'tnx.operator_id',
                'tnx.extended_trnx_type',
                'asc_t.id',
                'desc_t.id',
                'asc_t.train_number',
                'desc_t.train_number',
            )
            ->first();

    }


//    *************************************  CARGO ***************************************
    public function getUncollectedCargoTransactions($operatorId, $extendedTnxType)
    {
        $data = DB::table('cargo_transactions as ct')
            ->join('trains as asc_t', 'asc_t.id', 'ct.train_id')
            ->join('trains as desc_t', 'desc_t.id', 'asc_t.reverse_train_id')
            ->where('ct.operator_id', $operatorId)
            ->where('ct.extended_trnx_type', $extendedTnxType)
            ->whereIn('ct.trnx_status', ['00', '0'])
            ->where('ct.is_collected', 0)
            ->where('ct.collection_batch_number_id', 0)
            ->select(
                'ct.operator_id',
                'ct.extended_trnx_type',
                'asc_t.id as asc_train_id',
                'desc_t.id as desc_train_id',
                'asc_t.train_number as asc_train_number',
                'desc_t.train_number as desc_train_number',
                DB::raw('SUM(CASE WHEN ct.train_id = asc_t.id THEN ct.total_amount ELSE 0 END) AS total_amount_asc'),
                DB::raw('(SELECT COALESCE(SUM(total_amount), 0) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS total_amount_desc'),
                DB::raw('COUNT(CASE WHEN ct.train_id = asc_t.id THEN 1 END) AS count_asc_transactions'),
                DB::raw('COUNT(CASE WHEN ct.train_id = desc_t.id THEN 1 END) AS count_desc_transactions'),
                DB::raw('(SELECT COUNT(*) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS count_desc_transactions'),
                DB::raw('COALESCE(MIN(CASE WHEN ct.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS min_trnx_time_asc'),
                DB::raw('COALESCE(MAX(CASE WHEN ct.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS max_trnx_time_asc'),
                DB::raw('(SELECT COALESCE(MIN(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS min_trnx_time_desc'),
                DB::raw('(SELECT COALESCE(MAX(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS max_trnx_time_desc'),
            )
            ->groupBy(
                'ct.operator_id',
                'ct.extended_trnx_type',
                'asc_t.id',
                'desc_t.id',
                'asc_t.train_number',
                'desc_t.train_number',
            )
            ->orderBy('asc_t.id', 'asc')
            ->orderBy('desc_t.id', 'asc')
            ->get();


        $filteredData = [];
        $seenPairs = [];
        foreach ($data as $item) {
            $sortedPair = implode('-', collect([$item->asc_train_id, $item->desc_train_id])->sort()->toArray());
            if (!in_array($sortedPair, $seenPairs)) {
                $filteredData[] = $item;
                $seenPairs[] = $sortedPair;
            }
        }

        return $filteredData;
    }

    public function getUnCollectedCargoOperatorCollection($operatorId, $extendedTnxType, $ascTrainNumber)
    {

        return DB::table('cargo_transactions as ct')
            ->join('trains as asc_t', 'asc_t.id', 'ct.train_id')
            ->join('trains as desc_t', 'desc_t.id', 'asc_t.reverse_train_id')
            ->where('ct.operator_id', $operatorId)
            ->where('ct.extended_trnx_type', $extendedTnxType)
            ->where('asc_t.train_number', $ascTrainNumber)
            ->whereIn('ct.trnx_status', ['00', '0'])
            ->where('ct.is_collected', 0)
            ->where('ct.collection_batch_number_id', 0)
            ->select(
                'ct.operator_id',
                'ct.extended_trnx_type',
                'asc_t.id as asc_train_id',
                'desc_t.id as desc_train_id',
                'asc_t.train_number as asc_train_number',
                'desc_t.train_number as desc_train_number',
                DB::raw('SUM(CASE WHEN ct.train_id = asc_t.id THEN ct.total_amount ELSE 0 END) AS total_amount_asc'),
                DB::raw('(SELECT COALESCE(SUM(total_amount), 0) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS total_amount_desc'),
                DB::raw('SUM(CASE WHEN ct.train_id = asc_t.id THEN ct.total_amount ELSE 0 END) + (SELECT COALESCE(SUM(total_amount), 0) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS total_amount'),
                DB::raw('COUNT(CASE WHEN ct.train_id = asc_t.id THEN 1 END) AS count_asc_transactions'),
                DB::raw('COUNT(CASE WHEN ct.train_id = desc_t.id THEN 1 END) AS count_desc_transactions'),
                DB::raw('(SELECT COUNT(*) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS count_desc_transactions'),
                DB::raw('COALESCE(MIN(CASE WHEN ct.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS min_trnx_time_asc'),
                DB::raw('COALESCE(MAX(CASE WHEN ct.train_id = asc_t.id THEN CONCAT(trnx_date, " ", trnx_time) END), NULL) AS max_trnx_time_asc'),
                DB::raw('(SELECT COALESCE(MIN(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS min_trnx_time_desc'),
                DB::raw('(SELECT COALESCE(MAX(CONCAT(trnx_date, " ", trnx_time)), NULL) FROM cargo_transactions WHERE train_id = desc_t.id AND operator_id = ct.operator_id AND extended_trnx_type = "'.$extendedTnxType.'" AND trnx_status IN ("00", "0") AND is_collected = 0 AND collection_batch_number_id = 0) AS max_trnx_time_desc'),
            )
            ->groupBy(
                'ct.operator_id',
                'ct.extended_trnx_type',
                'asc_t.id',
                'desc_t.id',
                'asc_t.train_number',
                'desc_t.train_number',
            )
            ->first();

    }

    public function getCargoTransactionsByCollectionId($collectionId)
    {
        return DB::table('cargo_transactions as ct')
            ->join('trains as t', 't.id', 'ct.train_id')
            ->where('ct.collection_batch_number_id', $collectionId)
            ->whereIn('ct.trnx_status', ['00', '0'])
//            ->where('ct.is_collected', 0)
            ->select(
                'ct.id',
                't.id as train_id',
                't.train_number as train_number',
                'ct.total_amount as transaction_amount',
                DB::raw('CONCAT(ct.trnx_date, " ", ct.trnx_time) as transaction_date')
            )->get();
    }

    public function getCargoTransactionsByAscTrainNumber($operatorId, $tnxType, $ascTrainNumber, $descTrainNumber)
    {
        return DB::table('cargo_transactions as ct')
            ->join('trains as t', 't.id', 'ct.train_id')
            ->where('ct.operator_id', $operatorId)
            ->where('ct.extended_trnx_type', $tnxType)
            ->whereIn('ct.trnx_status', ['00', '0'])
            ->where('ct.is_collected', 0)
            ->where('ct.collection_batch_number_id', 0)
            ->where(function ($query) use ($ascTrainNumber, $descTrainNumber) {
                $query->where('t.train_number', $ascTrainNumber)
                    ->orWhere('t.train_number', $descTrainNumber);
            })
            ->select(
                'ct.id',
                'ct.operator_id',
                't.id as train_id',
                't.train_number as train_number',
                'ct.total_amount as trnx_amount',
            )->get();
    }

    public function getTransactionsIdsByCollectionBatchId($collectionBatchId)
    {
        return DB::table('ticket_transactions as tnx')
            ->where('tnx.collection_batch_number_id', $collectionBatchId)
            ->select(
                'tnx.id',
            )->get();
    }
    public function updateCollectedTransactions($operatorCollectionId, $tnxType)
    {
        $transactions = $this->getTransactionsIdsByCollectionBatchId($operatorCollectionId);
        if ($transactions){
            foreach ($transactions as $transaction) {
                if (in_array($tnxType, [TRAIN_CASH_PAYMENT, TOP_UP_CARD])) {
                    $data = TicketTransaction::where('id', $transaction->id)
                        ->update([
                            'is_collected' => true,
                        ]);

                    if (!$data){
                        return false;
                    }
                }else if($tnxType == CARGO){
                    $data = CargoTransaction::where('id', $transaction->id)
                        ->update([
                            'is_collected' => true,
                        ]);
                    if (!$data){
                        return false;
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

}
