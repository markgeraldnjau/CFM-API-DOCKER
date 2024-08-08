<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\checkAuthPermsissionTrait;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    use ApiResponse, CommonTrait;
    //
    public function getDashboardSummary(Request $request)
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

            $type = $request->type;
            $currentDate  = Carbon::now();
            $formattedDate = $currentDate->format('Y-m-d');
            $lastSevenDays = [];

        // Get dates for the last 7 days (inclusive of today)
        for ($i = 0; $i <= 6; $i++) {
            $pastDate = $currentDate->subDays($i);
            $lastSevenDays[] = $pastDate->format('Y-m-d');
        }

        if($type=="today"){

            $operatorTransactions = DB::table('ticket_transactions')
                ->join('operators' ,'operators.id','=','ticket_transactions.operator_id')
                ->where('ticket_transactions.trnx_date', $formattedDate)
                ->get();


            $zoneTrainDetails = DB::table('ticket_transactions')
            ->join('trains' ,'trains.id','=','ticket_transactions.train_id')
            ->where('trains.train_type','1')
            ->whereDate('ticket_transactions.trnx_date', $currentDate)
            ->get();

            $total = DB::table('ticket_transactions')
                    ->join('trains', 'trains.id', '=', 'ticket_transactions.train_id')
                    ->where('trains.train_type', 1)
                    ->where('ticket_transactions.trnx_date', $formattedDate)
                    ->selectRaw('SUM(ticket_transactions.trnx_amount) AS a, SUM(ticket_transactions.fine_amount) AS b,COUNT(ticket_transactions.id) AS c')
                    ->first();

                $totalAmount = $total->a + $total->b;
                $totalcount = $total->c;
                $longTrainDetails = ['amount'=>$totalAmount,'total_number'=>$totalcount,'date'=>$formattedDate];


            $total = DB::table('ticket_transactions')
                    ->leftjoin('trains', 'trains.id', '=', 'ticket_transactions.train_id')
                    ->whereNotNull('ticket_transactions.acc_number')
                    ->where('ticket_transactions.trnx_date', $formattedDate)
                    ->selectRaw('SUM(ticket_transactions.trnx_amount) AS a, SUM(ticket_transactions.fine_amount) AS b,COUNT(ticket_transactions.id) AS c')
                    ->first();

                $totalAmount = $total->a + $total->b;
                $totalcount = $total->c;
                $cardsDetails = ['amount'=>$totalAmount,'total_number'=>$totalcount];


                //cards end

                //function for extracting train graph details for dashboard
                $graphdetailspertrain = DB::table('ticket_transactions')
                ->join('trains' ,'trains.id','=','ticket_transactions.train_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, train_number')
                ->where('ticket_transactions.trnx_date', $formattedDate)
                ->groupBy('ticket_transactions.train_id','trains.train_number')
                ->get();

                $graphdetailsperoperators = DB::table('ticket_transactions')
                ->join('operators' ,'operators.id','=','ticket_transactions.operator_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, full_name')
                ->where('ticket_transactions.trnx_date', $formattedDate)
                ->groupBy('operators.full_name')
                ->get();

                $graphdetailsperzones = DB::table('ticket_transactions')
                ->join('zone_lists' ,'zone_lists.id','=','ticket_transactions.zone_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, zone_lists.name')
                ->where('ticket_transactions.trnx_date', $formattedDate)
                ->groupBy('zone_lists.name')
                ->get();

                $graphdetailspercategory = DB::table('ticket_transactions')
                ->join('special_groups' ,'special_groups.id','=','ticket_transactions.category_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, special_groups.title')
                ->where('ticket_transactions.trnx_date', $formattedDate)
                ->groupBy('special_groups.title')
                ->get();


                $total = DB::table('ticket_transactions')
                ->join('trains', 'trains.id', '=', 'ticket_transactions.train_id')
                ->where('trains.train_type', 2)
                ->where('ticket_transactions.trnx_date', $formattedDate)
                ->selectRaw('SUM(ticket_transactions.trnx_amount) AS a, SUM(ticket_transactions.fine_amount) AS b,COUNT(ticket_transactions.id) AS c')
                ->first();

                $totalAmount = $total->a + $total->b;
                $totalcount = $total->c;
                $zoneTrainDetails = ['amount'=>$totalAmount,'total_number'=>$totalcount];
                $transactionByZones = ['amount'=>$totalAmount,'total_number'=>$totalcount];


                $cargoTrainDetails = DB::table('ticket_transactions')
                ->join('trains' ,'trains.id','=','ticket_transactions.train_id')
                ->where('trains.train_type','4')
                ->whereDate('ticket_transactions.trnx_date', $currentDate)
                ->get();

            $transactionByClass = DB::table('cfm_classes as class')
                ->leftJoin('ticket_transactions as tnx', 'class.id', '=', 'tnx.class_id')
                ->whereIn('tnx.trnx_status', ['00', '0'])
                ->select(
                    'class.id',
                    'class.class_type',
                    'class.code',
                    DB::raw('COALESCE(SUM(tnx.trnx_amount), 0) as total_amount')
                )
                ->groupBy('class.id', 'class.class_type', 'class.code')
                ->orderBy('class.id')
                ->get();

            $totalClassTransactionAmount = DB::table('ticket_transactions as tnx')
                ->join('cfm_classes as class', 'class.id', '=', 'tnx.class_id')
                ->whereIn('tnx.trnx_status', ['00', '0'])
                ->whereNotNull('tnx.class_id')
                ->sum('tnx.trnx_amount');


                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->select(
                    'trains.id',
                    'trains.train_number',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.id) as total_trnx'),
                    DB::raw('COUNT(CASE WHEN tnx.class_id = 3 THEN 1 END) AS third_trnx_amount'),
                    DB::raw('COUNT(CASE WHEN tnx.class_id = 2 THEN 1 END) AS second_trnx_amount'),
                    DB::raw('COUNT(CASE WHEN tnx.class_id = 1 THEN 1 END) AS first_trnx_amount'),
                    DB::raw('MAX(CONCAT(tnx.trnx_date, " ", tnx.trnx_time)) as trnx_date'),
                )
                ->where('tnx.trnx_date', $formattedDate)
                ->groupBy('trains.id', 'trains.train_number')
                ->orderBy(DB::raw('MAX(tnx.created_at)'), 'desc') // Assuming `tnx.created_at` is the correct field for sorting
                ->take(5)
                ->get();


                $suspiciousTransactions = DB::table('ticket_transactions as tnx')
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->join('operators' ,'operators.id','=','tnx.operator_id')
                ->join('special_groups' ,'special_groups.id','=','tnx.category_id')
                ->select(
                    'trains.id',
                    'trains.train_number',
                    'full_name',
                    'title',
                    'tnx.operator_id',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.trnx_amount) as total_trnx'),
                    DB::raw('MAX(CONCAT(tnx.trnx_date, "", tnx.trnx_time)) as trnx_date'),
                )
                ->where('tnx.category_id','!=','1')
                ->where('tnx.trnx_date', $formattedDate)
                ->groupBy('trains.id', 'trains.train_number','full_name','title','tnx.operator_id')
                ->orderBy(DB::raw('MAX(tnx.created_at)'), 'desc')
                ->take(5)
                ->get();


                $lastFiveOperatorsTransactions = DB::table('ticket_transactions as tnx')
                ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                ->select(
                    'operators.id',
                    'operators.full_name',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.trnx_amount) as total_trnx'),
                    DB::raw('MAX(CONCAT(tnx.trnx_date, " ", tnx.trnx_time)) as trnx_date'),
                )
                ->where('tnx.trnx_date', $formattedDate)
                ->groupBy( 'operators.id','operators.full_name')
                ->orderBy(DB::raw('MAX(tnx.created_at)'), 'desc') // Assuming `tnx.created_at` is the correct field for sorting
                ->take(5)
                ->get();

            $lastFiveAuditTrails = DB::table('audit_trails as au')
                ->select('user_name', 'action as activity', 'created_at')
                ->orderBy('created_at')
                ->take(5)
                ->get();


                }else{


                    $operatorTransactions = DB::table('ticket_transactions')
                ->join('operators' ,'operators.id','=','ticket_transactions.operator_id')
                ->where('ticket_transactions.trnx_date', $formattedDate)
                ->get();


            $total = DB::table('ticket_transactions')
                    ->join('trains', 'trains.id', '=', 'ticket_transactions.train_id')
                    ->where('trains.train_type', 1)
                    ->selectRaw('SUM(ticket_transactions.trnx_amount) AS a, SUM(ticket_transactions.fine_amount) AS b,COUNT(ticket_transactions.id) AS c')
                    ->first();

                $totalAmount = $total->a + $total->b;
                $totalcount = $total->c;
                $longTrainDetails = ['amount'=>$totalAmount,'total_number'=>$totalcount];


            $total = DB::table('ticket_transactions')
                    ->join('trains', 'trains.id', '=', 'ticket_transactions.train_id')
                    ->whereNotNull('ticket_transactions.acc_number')
                    ->selectRaw('SUM(ticket_transactions.trnx_amount) AS a, SUM(ticket_transactions.fine_amount) AS b,COUNT(ticket_transactions.id) AS c')
                    ->first();

                $totalAmount = $total->a + $total->b;
                $totalcount = $total->c;
                $cardsDetails = ['amount'=>$totalAmount,'total_number'=>$totalcount];


                //cards end

                //function for extracting train graph details for dashboard
                $graphdetailspertrain = DB::table('ticket_transactions')
                ->join('trains' ,'trains.id','=','ticket_transactions.train_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, train_number')
                ->groupBy('ticket_transactions.train_id','trains.train_number')
                ->get();

                $graphdetailsperoperators = DB::table('ticket_transactions')
                ->join('operators' ,'operators.id','=','ticket_transactions.operator_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, full_name')
                ->groupBy('operators.full_name')
                ->get();

                $graphdetailsperzones = DB::table('ticket_transactions')
                ->join('zone_lists' ,'zone_lists.id','=','ticket_transactions.zone_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, zone_lists.name')
                ->groupBy('zone_lists.name')
                ->get();

                $graphdetailspercategory = DB::table('ticket_transactions')
                ->join('special_groups' ,'special_groups.id','=','ticket_transactions.category_id')
                ->selectRaw('SUM(trnx_amount) AS trnx_amount, special_groups.title')
                ->groupBy('special_groups.title')
                ->get();


                //End of the function for extracting train details for graph

                $total = DB::table('ticket_transactions')
                ->join('trains', 'trains.id', '=', 'ticket_transactions.train_id')
                ->where('trains.train_type', 2)

                ->selectRaw('SUM(ticket_transactions.trnx_amount) AS a, SUM(ticket_transactions.fine_amount) AS b,COUNT(ticket_transactions.id) AS c')
                ->first();

                $totalAmount = $total->a + $total->b;
                $totalcount = $total->c;
                $zoneTrainDetails = ['amount'=>$totalAmount,'total_number'=>$totalcount];
                $transactionByZones = ['amount'=>$totalAmount,'total_number'=>$totalcount];


                $cargoTrainDetails = DB::table('ticket_transactions')
                ->join('trains' ,'trains.id','=','ticket_transactions.train_id')
                ->where('trains.train_type','4')
                ->whereDate('ticket_transactions.trnx_date', $currentDate)
                ->get();

            $transactionByClass = DB::table('cfm_classes as class')
                ->leftJoin('ticket_transactions as tnx', 'class.id', '=', 'tnx.class_id')
                ->whereIn('tnx.trnx_status', ['00', '0'])
                ->select(
                    'class.id',
                    'class.class_type',
                    'class.code',
                    DB::raw('COALESCE(SUM(tnx.trnx_amount), 0) as total_amount')
                )
                ->groupBy('class.id', 'class.class_type', 'class.code')
                ->orderBy('class.id')
                ->get();

            $totalClassTransactionAmount = DB::table('ticket_transactions as tnx')
                ->join('cfm_classes as class', 'class.id', '=', 'tnx.class_id')
                ->whereIn('tnx.trnx_status', ['00', '0'])
                ->whereNotNull('tnx.class_id')
                ->sum('tnx.trnx_amount');


                $lastFiveTransactions = DB::table('ticket_transactions as tnx')
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->select(
                    'trains.id',
                    'trains.train_number',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.id) as total_trnx'),
                    DB::raw('COUNT(CASE WHEN tnx.class_id = 3 THEN 1 END) AS third_trnx_amount'),
                    DB::raw('COUNT(CASE WHEN tnx.class_id = 2 THEN 1 END) AS second_trnx_amount'),
                    DB::raw('COUNT(CASE WHEN tnx.class_id = 1 THEN 1 END) AS first_trnx_amount'),
                    DB::raw('MAX(CONCAT(tnx.trnx_date, " ", tnx.trnx_time)) as trnx_date'),
                )
                ->groupBy('trains.id', 'trains.train_number')
                ->orderBy(DB::raw('MAX(tnx.created_at)'), 'desc') // Assuming `tnx.created_at` is the correct field for sorting
                ->take(5)
                ->get();

                $suspiciousTransactions = DB::table('ticket_transactions as tnx')
                ->join('trains', 'trains.id', '=', 'tnx.train_id')
                ->join('operators' ,'operators.id','=','tnx.operator_id')
                ->join('special_groups' ,'special_groups.id','=','tnx.category_id')
                ->select(
                    'trains.id',
                    'trains.train_number',
                    'full_name',
                    'title',
                    'tnx.operator_id',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.trnx_amount) as total_trnx'),
                    DB::raw('MAX(CONCAT(tnx.trnx_date, " ", tnx.trnx_time)) as trnx_date'),
                )
                ->where('tnx.category_id','!=','1')
                ->groupBy('trains.id', 'trains.train_number','full_name','title','tnx.operator_id')
                ->orderBy(DB::raw('MAX(tnx.created_at)'), 'desc')
                ->take(5)
                ->get();


                $lastFiveOperatorsTransactions = DB::table('ticket_transactions as tnx')
                ->join('operators', 'operators.id', '=', 'tnx.operator_id')
                ->select(
                    'operators.id',
                    'operators.full_name',
                    DB::raw('SUM(tnx.trnx_amount) as total_trnx_amount'),
                    DB::raw('COUNT(tnx.trnx_amount) as total_trnx'),
                    DB::raw('MAX(CONCAT(tnx.trnx_date, " ", tnx.trnx_time)) as trnx_date'),
                )
                ->groupBy( 'operators.id','operators.full_name')
                ->orderBy(DB::raw('MAX(tnx.created_at)'), 'desc') // Assuming `tnx.created_at` is the correct field for sorting
                ->take(5)
                ->get();

            $lastFiveAuditTrails = DB::table('audit_trails as au')
                ->select('user_name', 'action as activity', 'created_at')
                ->orderBy('created_at')
                ->take(5)
                ->get();
                }

            $response = [
                'cardsDetails' => $cardsDetails,
                'graphdetailspertrain' => $graphdetailspertrain,
                'graphdetailsperoperators' => $graphdetailsperoperators,
                'graphdetailsperzones' => $graphdetailsperzones,
                'graphdetailspercategory' => $graphdetailspercategory,
                'longTrainDetails' => $longTrainDetails,
                'zoneTrainDetails' => $zoneTrainDetails,
                'cargoTrainDetails' =>  $cargoTrainDetails,
                'transactionByClass' => $transactionByClass,
                'totalClassTransactionAmount' => $totalClassTransactionAmount,
                'transactionByZones' => $transactionByZones,
                'lastFiveTransactions' => $lastFiveTransactions,
                'suspiciousTransactions' => $suspiciousTransactions,
                'lastFiveOperatorsTransactions' => $lastFiveOperatorsTransactions,
                'lastFiveAuditTrails' => $lastFiveAuditTrails,
                'operatorTransactions' => $operatorTransactions,
                'type'=>$type
            ];
            return $this->success($response, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }

    public function getTransactionsByClasses()
    {
        try {
            // Fetch transaction data grouped by class and joined with cfm_classes table
            $transactionByClass = DB::table('cfm_classes as class')
                ->leftJoin('ticket_transactions as tnx', 'class.id', '=', 'tnx.class_id')
                ->whereIn('tnx.trnx_status', ['00', '0'])
                ->select(
                    'class.class_type',
                    DB::raw('COALESCE(SUM(tnx.trnx_amount), 0) as total_amount')
                )
                ->groupBy('class.class_type')
                ->orderBy('class.class_type')
                ->get();

            // Reformat the data with class types as labels and total amounts as values
            $labels = $transactionByClass->pluck('class_type')->toArray();
            $values = $transactionByClass->pluck('total_amount')->toArray();

            $response = [
                'labels' => $labels,
                'values' => $values,
            ];

            return $this->success($response, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    use checkAuthPermsissionTrait;

    public function checkPermission()
    {
        return $this->getRolePermission(Auth::user()->role_id);

    }


}
