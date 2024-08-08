<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\News\InfoRequest;
use App\Models\CfmInformation;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use App\Traits\CommonTrait;
use App\Traits\FireBaseTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InformationController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait, FireBaseTrait, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = CfmInformation::select('id', 'token', 'title', 'information', 'status');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('title', 'like', "%$searchQuery%");
                    $query->where('information', 'like', "%$searchQuery%");
                });
            }
            $info = $query->orderByDesc('updated_at')->paginate($itemPerPage);

              $this->auditLog("View Information", PORTAL, null, null);
            return $this->success($info, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(InfoRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'title' => $request->title,
                'information' => $request['information'],
            ];
            $info = CfmInformation::create($payload);
            $this->auditLog("Create Information : ". $request['title'], PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($info, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $token)
    {
        //
        try {
            $info = CfmInformation::where('token', $token)->firstOrFail();

            if (!$info) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No information found!');
            }
            $this->auditLog("View Information : ". $info->title, PORTAL, null, null);
            return $this->success($info, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            LLog::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(InfoRequest $request, string $id)
    {
        //
        DB::beginTransaction();
        try {
            $info = CfmInformation::findOrFail($id);
            $oldData = clone $info;
            $payload = [
                'title' => $request->title,
                'information' => $request['information'],
            ];

            $info->update($payload);

            $this->auditLog("information Update: ". $request->title, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($info, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function updateStatus(Request $request, string $id)
    {
        //
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in([PUBLISHED, CREATED, UNPUBLISHED])],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        DB::beginTransaction();
        try {
            $info = CfmInformation::findOrFail($id);
            if (empty($info)){
                return $this->error(null, DATA_NOT_FOUND);
            }
            $oldData = clone $info;
            $postedOn = null;
            if ($request->status == PUBLISHED){
                $postedOn = Carbon::now();
            }
            $payload = [
                'status' => $request->status,
                'posted_on' => $postedOn
            ];
            $info->update($payload);

            // Firebase Send Pop Up notification
            if ($info->status == PUBLISHED){
                $title = Str::limit($info->title, 50);
                $body = Str::limit($info->information, 100, '...');
                $response = $this->sendFirebaseNotificationToAll($title, $body, $info->id, get_class($info));
                if (!$response){
                    return $this->error(null, "Something wrong on sending push notification to customer app, try again later", HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            $this->auditLog("information Update: ". $info->title, PORTAL, $oldData, $payload);
            DB::commit();
            return $this->success($info, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $new = CfmInformation::findOrFail($id);
            if ($new->status == PUBLISHED){
                return $this->error(null, "Cant not delete published information");
            }
            $new->delete();

            $this->auditLog("Delete News: ". $new ->title, PORTAL, null, null);
            return $this->success(null, DATA_DELETED);
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
}
