<?php

namespace App\Http\Controllers\App\FireBase;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\FirebaseNotification;
use App\Models\NewsAndUpdate;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\JwtTrait;
use App\Traits\Mobile\MobileAppTrait;
use App\Traits\Mobile\TicketTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationController extends Controller
{
    //
    use MobileAppTrait, ApiResponse, AuditTrail, JwtTrait;

    public function sendPushNotification()
    {
        try {
            $firebase = (new Factory())
                ->withServiceAccount(base_path('config/firebase_credentials.json'));

            $messaging = $firebase->createMessaging();

            $message = CloudMessage::fromArray([
                'notification' => [
                    'title' => 'Hello from Firebase!',
                    'body' => 'This is a test notification.'
                ],
                'topic' => 'global'
            ]);

            $result = $messaging->send($message);
            Log::channel('firebase')->info('Push notification sent successfully', ['result' => $result]);
            return response()->json(['message' => 'Push notification sent successfully', 'result' => $result]);
        } catch (\Throwable $e) {
            Log::channel('firebase')->error('Failed to send push notification', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to send push notification', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCustomerNotifications(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        try {
            $pushNotifications = FirebaseNotification::where('customer_id', $customer->id)->select('token', 'notification_type', 'title', 'content')->orderByDesc('updated_at')->get();
            $this->auditLog("Mobile App Get All Push notifications for: ". $customer->full_name, PORTAL, null, null);
            return $this->success($pushNotifications, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getNotificationInfo(Request $request, $token)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        try {
            $notification = FirebaseNotification::where('token', $token)->select('token', 'notification_id', 'notification_type')->first();

            if (! $notification){
                return $this->error(null, NOT_FOUND, 404);
            }

            $data = $notification->notification_type::find($notification->notification_id);
            $this->auditLog("Mobile App Get Push notifications details ($notification->notification_type) for: ". $customer->full_name, PORTAL, null, null);
            return $this->success($data, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
