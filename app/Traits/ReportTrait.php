<?php
// CartTrait.php

namespace App\Traits;

use App\Exports\TicketTransactionSummaryExport;
use App\Models\ExtendedTransactionType;
use App\Models\Report\ReportRequest;
use App\Models\TrainLine;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

trait ReportTrait
{

    public function decideReportQuery($reportCode, $parameters)
    {
        switch ($reportCode) {
            case TNX_SUMMARY_REPORT:
                return $this->generateTnxSummaryReport($parameters);
            case TNX_TO_FROM_STATION_REPORT:
                return $this->generateTnxToFromStationReport($parameters);
            case TNX_INCENTIVE_REPORT:
                return $this->generateTnxIncentiveReport($parameters);
            case TNX_PASSENGER_REPORT:
                return $this->generateTnxPassengerReport($parameters);
            case TNX_ON_OFF_REPORT:
                return $this->generateTnxOnOffReport($parameters);
            default:
                // Handle unknown report types or provide a default action
                return $this->error(null, "Invalid report type", 404);
        }
    }

    // Define functions for generating each type of report
    private function generateTnxSummaryReport($parameters)
    {
//        if (!empty($parameters->transaction_report_options)){
//            return $this->operatorTransactionSummaryReport($parameters);
//        } else {
            $query = DB::table('ticket_transactions as tkt')
                ->leftJoin('trains as t', 't.id', 'tkt.train_id')
                ->leftJoin('operators as o', 'o.id', 'tkt.operator_id')
                ->select(
                    't.train_number',
                    't.train_name',
                    't.train_number',
                    'tkt.trnx_number',
                    'tkt.trnx_quantity as ticket_quantity',
                    DB::raw("CASE WHEN tkt.on_off = 1 THEN 'on' ELSE 'off' END as on_off_train"),
                    DB::raw("CASE WHEN tkt.is_collected = 1 THEN 'collected' ELSE 'not collected' END as collected"),
                    'o.full_name as operator_name',
                    'tkt.trnx_amount',
                    'tkt.net_status as network_status',
                    'tkt.trnx_date',
                    'tkt.trnx_time',
                );

            $this->summaryExtracted($parameters, $query);


            if (!empty($parameters->train_number)) {
                $query->where('t.train_number', $parameters->train_number);
            }


            if (isset($parameters->start_date) & isset($parameters->end_date)){
                $query->whereBetween('tkt.trnx_date', [$parameters->start_date, $parameters->end_date]);
            }

            // Get the total amount
            $totalAmount = $query->sum('tkt.trnx_amount');
            $totalTicket = $query->count();

            // Get the data
            $data = $query->get();
            return [
                'data' => $data,
                'total_amount' => $totalAmount,
                'total_tickets' => $totalTicket,
            ];
//        }
    }


    public function operatorTransactionSummaryReport($parameters)
    {
        $query = DB::table('operators')
            ->select(
                'operators.full_name as OperatorName',
                'trains.train_number as TrainNumber',
                DB::raw('SUM(tkt.amount) as TotalTransactionAmount'),
                DB::raw('COUNT(tkt.id) as TotalTickets')
            )
            ->from('ticket_transactions as tkt')
            ->join('trains', 'tkt.train_id', '=', 'trains.id')
            ->join('operators', 'operators.id', '=', 'tkt.operator_id')
            ->whereBetween('tkt.trnx_date', [$parameters->start_date, $parameters->end_date])
            ->groupBy('operators.id', 'trains.train_number');

        $this->summaryExtracted($parameters, $query);

        return $query->get();

    }

    private function generateTnxToFromStationReport($parameters)
    {
        $query = DB::table('ticket_transactions as tkt')
            ->leftJoin('trains as t', 't.id', 'tkt.train_id')
            ->leftJoin('train_lines as tl', 'tl.id', 'tkt.line_id')
            ->join('train_stations as tsf', 'tsf.id', 'tkt.station_from')
            ->join('train_stations as tst', 'tst.id', 'tkt.station_to')
            ->select(
                't.train_number',
                't.train_name',
                't.train_number',
                'tkt.trnx_number',
                'tsf.station_name as from_station',
                'tst.station_name as to_station',
                'tkt.trnx_quantity as ticket_quantity',
                DB::raw("CASE WHEN tkt.on_off = 1 THEN 'on' ELSE 'off' END as on_off_train"),
                DB::raw("CASE WHEN tkt.is_collected = 1 THEN 'collected' ELSE 'not collected' END as collected"),
                'tkt.trnx_amount',
                'tkt.net_status as network_status',
                'tkt.trnx_date',
                'tkt.trnx_time',
            )->where('tsf.id', $parameters->from_station_id)
            ->where('tst.id', $parameters->to_station_id);

        if (!empty($parameters->extended_transaction_type)) {
            $query->leftJoin('extended_transaction_types as ett', 'ett.code', 'tkt.extended_trnx_type')
                ->where('tkt.extended_trnx_type', $parameters->extended_transaction_type)
                ->addSelect('ett.name as transaction_type');
        }

        if (!empty($parameters->train_number)) {
            $query->where('t.train_number', $parameters->train_number);
        }

        if (isset($parameters->start_date) & isset($parameters->end_date)){
            $query->whereBetween('tkt.trnx_date', [$parameters->start_date, $parameters->end_date]);
        }

        // Get the total amount
        $totalAmount = $query->sum('tkt.trnx_amount');

        // Get the data
        $data = $query->get();
        return [
            'data' => $data,
            'total_amount' => $totalAmount,
        ];
    }

    private function generateTnxOnOffReport($parameters): array
    {
        $extendedTransactionTypeName = 'ALL';
        $trainLine = 'ALL';
        $query = DB::table('ticket_transactions as tkt')
            ->leftJoin('trains as t', 't.id', 'tkt.train_id')
            ->leftJoin('train_lines as tl', 'tl.id', 'tkt.line_id')
            ->leftJoin('extended_transaction_types as ett', 'ett.code', 'tkt.extended_trnx_type')
            ->select(
                't.train_name',
                't.train_number',
                'tkt.on_off',
                DB::raw('COUNT(*) as ticket_count'),
                DB::raw('SUM(tkt.trnx_amount) as tnx_total_amount'),
                DB::raw('COALESCE(SUM(tkt.fine_amount), 0) as fine_amount'),
                DB::raw('SUM(tkt.trnx_amount + COALESCE(tkt.fine_amount, 0)) as total_amount')
            )
            ->groupBy('t.train_name', 't.train_number', 'tkt.on_off');

        if (!empty($parameters->train_line_id)) {
            $query->where('tkt.line_id', $parameters->train_line_id);
            $trainLine = TrainLine::where('id', $parameters->train_line_id)->value('line_name') ?? 'N/A';
        }


        if (!empty($parameters->extended_transaction_type)) {
            $extendedTransactionType = ExtendedTransactionType::where('id', $parameters->extended_transaction_type)->select('id', 'code', 'name')->first();
            if ($extendedTransactionType){
                $query->where('tkt.extended_trnx_type', $extendedTransactionType->code);
                $extendedTransactionTypeName = $extendedTransactionType->name;
            }

        }


        if (!empty($parameters->train_number)) {
            $query->where('t.train_number', $parameters->train_number);
        }

        if (isset($parameters->start_date) & isset($parameters->end_date)){
            $query->whereBetween('tkt.trnx_date', [$parameters->start_date, $parameters->end_date]);
        }

        //Get the total amount
        $totalAmount = $query->sum('tkt.trnx_amount');

        //Get the data grouped by 'on_off'
        $data = $query->get()->groupBy('on_off');
        return [
            'data' => $data,
            'total_amount' => $totalAmount,
            'extended_transaction_type' => $extendedTransactionTypeName,
            'train_line' => $trainLine,
        ];
    }

    private function generateTnxIncentiveReport($parameters)
    {
        // Logic to generate report type 4
    }

    private function generateTnxPassengerReport($parameters)
    {
        // Logic to generate report type 4
    }

    /**
     * @param $parameters
     * @param \Illuminate\Database\Query\Builder $query
     * @return void
     */
    public function summaryExtracted($parameters, \Illuminate\Database\Query\Builder $query): void
    {
        if (!empty($parameters->train_line_id)) {
            $query->leftJoin('train_lines as tl', 'tl.id', 'tkt.line_id')
                ->where('tkt.line_id', $parameters->train_line_id)
                ->addSelect('tl.line_name as train_line_name');
        }

        if (!empty($parameters->extended_transaction_type)) {
            $extendedTransactionType = ExtendedTransactionType::where('id', $parameters->extended_transaction_type)->select('id', 'code', 'name')->first();
            if ($extendedTransactionType) {
                $query->leftJoin('extended_transaction_types as ett', 'ett.code', 'tkt.extended_trnx_type')
                    ->where('tkt.extended_trnx_type', $extendedTransactionType->code)
                    ->addSelect('ett.name as transaction_type');
            }
        }
    }

    private function file($fileType, $report, $response, $startDate, $endDate, $printedBy)
    {
        $startDate = Carbon::parse($startDate)->format('d - M - Y H:i');
        $endDate = Carbon::parse($endDate)->format('d - M - Y H:i');

        if ($fileType == EXCEL){
            return $this->excel($report, $response, $startDate, $endDate, $printedBy);
        } else if ($fileType == PDF){
            return $this->pdf($report, $response, $startDate, $endDate, $printedBy);
        } else if ($fileType == CSV){

        } else if ($fileType == TXT){

        }
    }

    private function excel($report, $response, $startDate, $endDate, $printedBy)
    {
        switch ($report->code) {
            case TNX_SUMMARY_REPORT:
                $data = $response['data'];
                $totalAmount = $response['total_amount'];
                $reportName = $report->name;
                $fileName = 'ticket_transaction_summary_' . uniqid() . '.xlsx';
                Excel::store(new TicketTransactionSummaryExport($data, $totalAmount, $startDate, $endDate, $printedBy, $reportName), 'reports/excel/' . $fileName);
                // Get the URL of the saved PDF
                return Storage::url('reports/reports/' . $fileName);
            case TNX_TO_FROM_STATION_REPORT:
                $data = $response['data'];
                $totalAmount = $response['total_amount'];
                $totalTickets = $response['total_tickets'];
                $reportName = $report->name;
                $pdf = PDF::loadView('pdf.reports.transactions.to-from-station-transaction-report', compact(
                    'data',
                    'totalAmount',
                    'totalTickets',
                    'startDate',
                    'endDate',
                    'printedBy',
                    'reportName'));
                // Save the PDF to the storage
                $fileName = 'report_' . uniqid() . '.pdf';
                $pdf->save(storage_path('app/public/reports/' . $fileName));

                // Get the URL of the saved PDF
                return Storage::url('reports/' . $fileName);
                break;
            case TNX_INCENTIVE_REPORT:
            case TNX_PASSENGER_REPORT:
                break;
            case TNX_ON_OFF_REPORT:
                $data = $response['data'];
                $totalAmount = $response['total_amount'];
                $extendedTransactionType = $response['extended_transaction_type'];
                $trainLine = $response['train_line'];
                $reportName = $report->name;
                $pdf = PDF::loadView('pdf.reports.transactions.on-off-train-transaction-report', compact('data',
                    'totalAmount',
                    'extendedTransactionType',
                    'trainLine',
                    'startDate',
                    'endDate',
                    'printedBy',
                    'reportName'
                ));
                // Save the PDF to the storage
                $fileName = 'report_' . uniqid() . '.pdf';
                $pdf->save(storage_path('app/public/reports/' . $fileName));

                // Get the URL of the saved PDF
                return Storage::url('reports/' . $fileName);
            default:
                // Handle unknown report types or provide a default action
                break;
        }

    }

    private function pdf($report, $response, $startDate, $endDate, $printedBy)
    {
        switch ($report->code) {
            case TNX_SUMMARY_REPORT:
                $data = $response['data'];
                $totalAmount = $response['total_amount'];
                $reportName = $report->name;
                // Generate PDF using the template and transaction data
                $pdf = PDF::loadView('pdf.reports.transactions.ticket-transaction-report-summary', compact('data',
                    'totalAmount',
                    'startDate',
                    'endDate',
                    'printedBy',
                    'reportName'));
                // Save the PDF to the storage
                $fileName = 'report_' . uniqid() . '.pdf';
                $pdf->save(storage_path('app/public/reports/' . $fileName));
                // Get the URL of the saved PDF
                return Storage::url('reports/' . $fileName);
            case TNX_TO_FROM_STATION_REPORT:
                $data = $response['data'];
                $totalAmount = $response['total_amount'];
                $reportName = $report->name;
                $pdf = PDF::loadView('pdf.reports.transactions.to-from-station-transaction-report', compact(
                    'data',
                    'totalAmount',
                    'startDate',
                    'endDate',
                    'printedBy',
                    'reportName'));
                // Save the PDF to the storage
                $fileName = 'report_' . uniqid() . '.pdf';
                $pdf->save(storage_path('app/public/reports/' . $fileName));

                // Get the URL of the saved PDF
                return Storage::url('reports/' . $fileName);
                break;
            case TNX_INCENTIVE_REPORT:
            case TNX_PASSENGER_REPORT:
                break;
            case TNX_ON_OFF_REPORT:
                $data = $response['data'];
                $totalAmount = $response['total_amount'];
                $extendedTransactionType = $response['extended_transaction_type'];
                $trainLine = $response['train_line'];
                $reportName = $report->name;
                $pdf = PDF::loadView('pdf.reports.transactions.on-off-train-transaction-report', compact('data',
                    'totalAmount',
                    'extendedTransactionType',
                    'trainLine',
                    'startDate',
                    'endDate',
                    'printedBy',
                    'reportName'
                ));
                // Save the PDF to the storage
                $fileName = 'report_' . uniqid() . '.pdf';
                $pdf->save(storage_path('app/public/reports/' . $fileName));

                // Get the URL of the saved PDF
                return Storage::url('reports/' . $fileName);
            default:
                // Handle unknown report types or provide a default action
                break;
        }

    }


    private function updateStatus($status)
    {
        return ReportRequest::where('id', $this->reportRequestId)
            ->update(['status' => $status]);
    }

//    private function openReportFile($fileType, $downloadUrl)
//    {
//        if ($fileType == EXCEL){
//            // Get the full path to the temporary file
//            $filePath = env('APP_URL') . $downloadUrl;
//            $path = parse_url($filePath, PHP_URL_PATH);
//            $fullPath = public_path($path);
//            // Check if the file exists
//            if (!file_exists($fullPath)) {
//                return $this->error(null, "File not found", 404);
//            }
//
//            // Set the headers for Excel file download
//            $headers = [
//                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
//            ];
//
//            // Return the headers and URL of the file
//            return [
//                'headers' => $headers,
//                'url' => $filePath
//            ];
//        } else if ($fileType == PDF){
//            // Get the full path to the temporary file
//            $filePath = env('APP_URL') . $downloadUrl;
//            $path = parse_url($filePath, PHP_URL_PATH);
//            $fullPath = public_path($path);
//            // Check if the file exists
//            if (!file_exists($fullPath)) {
//                return $this->error(null, "File not found", 404);
//            }
//
//            return [
//                'Content-Type' => 'application/pdf',
//                'url' => $filePath
//            ];
//        } else if ($fileType == CSV){
//
//        } else if ($fileType == TXT){
//
//        }
//    }

    private function openReportFile($fileType, $downloadUrl)
    {
        $filePath = env('APP_URL') . $downloadUrl;
        $path = parse_url($filePath, PHP_URL_PATH);
        $fullPath = public_path($path);

        if ($fileType == EXCEL) {
            // Check if the file exists
            if (!file_exists($fullPath)) {
                return ['code' => 404, 'status' => 'error', 'message' => 'File not found', 'data' => null];
            }

            // Set the headers for Excel file download
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];

            return [
                'headers' => $headers,
                'url' => $filePath
            ];
        } else if ($fileType == PDF) {

            // Check if the file exists
            if (!file_exists($fullPath)) {
                return ['code' => 404, 'status' => 'error', 'message' => 'File not found', 'data' => null];
            }

            return [
                'Content-Type' => 'application/pdf',
                'url' => $filePath
            ];
        } else if ($fileType == CSV) {
            // Implement CSV file handling logic here
        } else if ($fileType == TXT) {
            // Implement TXT file handling logic here
        }

        return ['code' => 400, 'status' => 'error', 'message' => 'Invalid file type', 'data' => null];
    }

}
