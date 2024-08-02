<?php

namespace App\Http\Controllers\Api\Report;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Cargo\CargoCategory;
use App\Models\Report;
use App\Models\ReportModule;
use App\Models\ReportParameter;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportModuleController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;
    //

    public function index(Request $request)
    {
        //
        try {
            $reportModules = ReportModule::all();

            if (!$reportModules) {
                throw new RestApiException(404, 'No report modules found!');
            }
            $this->auditLog("View Report Modules", PORTAL, null, null);
            return $this->success($reportModules, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getReports(Request $request)
    {
        $moduleCode = $request->input('module_code');
        //
        try {
            $reportModules = Report::where('report_module_code', $moduleCode)->select('id', 'token', 'name', 'code', 'has_parameters')->get();

            if (!$reportModules) {
                throw new RestApiException(404, 'No reports found!');
            }
//            $this->auditLog("View Reports", PORTAL, null, null);
            return $this->success($reportModules, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getReportParameter(Request $request)
    {
        $reportCode = $request->input('report_code');
        //
        try {
            $reportModules = ReportParameter::where('report_code', $reportCode)->select('id', 'token', 'name', 'input_type', 'input_label', 'api_for_data', 'param')->get();

            if (!$reportModules) {
                throw new RestApiException(404, 'No reports found!');
            }
//            $this->auditLog("View Reports", PORTAL, null, null);
            return $this->success($reportModules, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
