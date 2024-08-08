<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\News\NewsRequest;
use App\Models\NewsAndUpdate;
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

class NewsController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait, FireBaseTrait, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = NewsAndUpdate::select('id', 'token', 'title', 'content', 'platform', 'status');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('title', 'like', "%$searchQuery%");
                    $query->where('content', 'like', "%$searchQuery%");
                });
            }
            $news = $query->orderByDesc('updated_at')->paginate($itemPerPage);

              $this->auditLog("View News and Updates", PORTAL, null, null);
            return $this->success($news, DATA_RETRIEVED);
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
    public function store(NewsRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'title' => $request->title,
                'content' => $request['content'],
                'platform' => $request->platform,
            ];
            $news = NewsAndUpdate::create($payload);
            $this->auditLog("Create News for : ". $request['platform'], PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($news, DATA_SAVED);
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
            $news = NewsAndUpdate::where('token', $token)->firstOrFail();

            if (!$news) {
                throw new RestApiException(404, 'No news found!');
            }

            $this->auditLog("View News : ". $news->title, PORTAL, null, null);
            return $this->success($news, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            throw new RestApiException(404, DATA_NOT_FOUND);
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
    public function update(NewsRequest $request, string $id)
    {
        $news = NewsAndUpdate::findOrFail($id);
        DB::beginTransaction();
        try {
            $oldData = clone $news;
            $payload = [
                'title' => $request->title,
                'content' => $request['content'],
                'platform' => $request->platform,
            ];

            $news->update($payload);

            $this->auditLog("News Update: ". $request->title . "for " . $request->platform, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($news, DATA_UPDATED);
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
            $new = NewsAndUpdate::findOrFail($id);
            if (empty($new)){
                return $this->error(null, DATA_NOT_FOUND);
            }
            $oldData = clone $new;
            $postedOn = null;
            if ($request->status == PUBLISHED){
                $postedOn = Carbon::now();
            }
            $payload = [
                'status' => $request->status,
                'posted_on' => $postedOn
            ];
            $new->update($payload);

            // Firebase Send Pop Up notification
            if ($new->status == PUBLISHED){
                $title = Str::limit($new->title, 50);
                $body = Str::limit($new->content, 100, '...');
                $response = $this->sendFirebaseNotificationToAll($title, $body, $new->id, get_class($new));
                if (!$response){
                    return $this->error(null, "Something wrong on sending push notification to customer app, try again later", 500);
                }
            }

            $this->auditLog("News Update status: ". $new->title . "for " . $new->platform, PORTAL, $oldData, $payload);
            DB::commit();
            return $this->success($new, DATA_UPDATED);
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
            $new = NewsAndUpdate::findOrFail($id);
            if ($new->status == PUBLISHED){
                return $this->error(null, "Cant not delete published news");
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
