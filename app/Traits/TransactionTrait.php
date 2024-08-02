<?php
// CartTrait.php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait TransactionTrait
{

    public function getTransactions($operatorId,$tnxType)
    {
        return DB::table('ticket_transactions as tnx')
            ->select(
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS operator_name"),
                DB::raw("CONCAT(train_from.stop_name, '-', train_to.stop_name) AS from_to"),
                'train.train_number',
                'tnx.trnx_receipt',
                'tnx.trnx_amount',
                'group.title AS group_name',
                'tnx.system_date AS date',
                'cc.class_type as class_name',
                'tnx.net_status AS status',
                'd.device_type AS device_name')
            ->join('users AS u', 'u.id', '=', 'tnx.operator_id')
            ->Join('trains AS train', 'train.id', '=', 'tnx.train_id')
            ->join('train_stops AS train_from', 'train_from.id', '=', 'tnx.station_from')
            ->join('train_stops AS train_to', 'train_to.id', '=', 'tnx.station_to')
            ->Join('special_groups AS group', 'group.id', '=', 'tnx.category_id')
            ->leftJoin('cfm_classes as cc', 'cc.id', '=', 'tnx.class_id')
            ->leftJoin('device_details AS d', 'd.device_imei', '=', 'tnx.device_number')
            ->where('tnx.extended_trnx_type', $tnxType)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->whereNull('tnx.deleted_at')
            ->get();
    }


    public function getZoneTransactions($operatorId)
    {
        return DB::table('ticket_transactions as tnx')
            ->select(
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS operator_name"),
                DB::raw("CONCAT(train_from.stop_name, '-', train_to.stop_name) AS from_to"),
                'train.train_number',
                'z.name as zone',
                'tnx.trnx_receipt',
                'tnx.trnx_amount',
                'group.title AS group_name',
                'tnx.system_date AS date',
                'cc.class_type as class_name',
                'tnx.net_status AS status',
                'd.device_type AS device_name')
            ->join('users AS u', 'u.id', '=', 'tnx.operator_id')
            ->join('trains AS train', 'train.id', '=', 'tnx.train_id')
            ->join('train_stops AS train_from', 'train_from.id', '=', 'tnx.station_from')
            ->join('train_stops AS train_to', 'train_to.id', '=', 'tnx.station_to')
            ->join('special_groups AS group', 'group.id', '=', 'tnx.category_id')
            ->leftJoin('cfm_classes as cc', 'cc.id', '=', 'tnx.class_id')
            ->leftJoin('device_details AS d', 'd.device_imei', '=', 'tnx.device_number')
            ->leftJoin('zone_lists AS z', 'z.id', '=', 'tnx.zone_id')
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->get();
    }

    public function getTopUpTransactions($operatorId, $extendedTnxType)
    {
        return DB::table('ticket_transactions as tnx')
            ->select(
                DB::raw('SUM(tnx.trnx_amount) as total_amount')
            )
            ->where('tnx.operator_id', $operatorId)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->where('tnx.is_collected', 0)
            ->where('tnx.collection_batch_number_id', 0)
            ->where('tnx.extended_trnx_type', $extendedTnxType)
            ->groupBy('tnx.operator_id')
            ->get();
    }

}
