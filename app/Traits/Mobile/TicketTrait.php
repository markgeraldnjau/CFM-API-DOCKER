<?php
namespace App\Traits\Mobile;

use Illuminate\Support\Facades\DB;

trait TicketTrait
{
    public function getTicketHistory($customerId){
        return DB::table('ticket_transactions as tnx')->select(
            'tnx.id as tnx_id',
                'cc.class_type as class_name',
                'train_from.stop_name as from_stop',
                'train_to.stop_name as to_stop',
                'train.train_number',
                'tnx.trnx_receipt as ticket_receipt',
                'tnx.trnx_amount',
                'group.title AS special_ group_name',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS operator_name"),
                'tnx.trnx_date AS date',
                'tnx.trnx_time AS time',
                'tnx.validation_status AS status',
            )
            ->join('users AS u', 'u.id', '=', 'tnx.operator_id')
            ->join('trains AS train', 'train.id', '=', 'tnx.train_id')
            ->leftJoin('train_stops as train_from', 'train_from.id', '=', 'tnx.station_from')
            ->leftJoin('train_stops as train_to', 'train_to.id', '=', 'tnx.station_to')
            ->Join('special_groups as group', 'group.id', '=', 'tnx.category_id')
            ->leftJoin('cfm_classes as cc', 'cc.id', '=', 'tnx.class_id')
            ->where('tnx.customer_id', $customerId)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->whereNull('tnx.deleted_at')
            ->orderBy('tnx.updated_at')
            ->get();
    }

    public function getTicketMetrics($customerId) {
        // Get the total amount and count of transactions
        return DB::table('ticket_transactions as tnx')
            ->selectRaw('COUNT(tnx.id) as total_transactions, SUM(tnx.trnx_amount) as total_amount')
            ->where('tnx.customer_id', $customerId)
            ->whereIn('tnx.trnx_status', ['00', '0'])
            ->whereNull('tnx.deleted_at')
            ->first();
    }

}
