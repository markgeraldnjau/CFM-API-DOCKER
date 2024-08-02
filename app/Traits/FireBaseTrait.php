<?php

namespace App\Traits;

use App\Models\FirebaseNotification;
use App\Models\FirebaseUserDevice;
use App\Models\IncidentCategory;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

trait FireBaseTrait
{
    public function checkUserDeviceToken($user, $phoneNumber)
    {
        return FirebaseUserDevice::where('user_type', get_class($user))->where('user_id', $user->id)->where('phone_number', $phoneNumber)->exists();
    }
    public function saveFireBaseDevice($user, $phoneNumber, $deviceToken)
    {
        return FirebaseUserDevice::updateOrCreate([
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'phone_number' => $phoneNumber,
            'device_token' => $deviceToken
        ]);
    }

    public function sendFirebaseNotificationToAll($title, $body, $notificationId, $notificationType,): bool
    {
        try {
            $messaging = (new Factory())
                ->withServiceAccount(base_path('config/firebase_credentials.json'))
                ->createMessaging();

            $tokens = FirebaseUserDevice::pluck('device_token')->toArray();

            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body));

            // Sending the message to all device tokens
            $messaging->sendMulticast($message, $tokens);

            $response = $this->saveNotificationToAllUsers($notificationId, $notificationType, $title, $body);

            if (!$response){
                Log::channel('firebase')->error('Failed to save Customer notification entry',
                    ['error' => json_encode([$notificationId, $notificationType, $title, $body])]);
                return false;
            }

            Log::channel('firebase')->info("Notification sent to " . count($tokens) . " devices.", ['title', $title, 'body' => $body]);
            return true;
        }catch (\Throwable $e){
            Log::channel('firebase')->error('Failed to send push notification', ['error' => $e->getMessage()]);
            return false;
        }
    }
    public function saveNotificationToAllUsers($notificationId, $notificationType, $title, $content): bool
    {
        $fireBaseDevices = FirebaseUserDevice::select('user_id')->get();

        foreach ($fireBaseDevices as $fireBaseDevice){
            $notify = FirebaseNotification::create([
               'customer_id' => $fireBaseDevice->user_id,
               'notification_id' => $notificationId,
               'notification_type' => $notificationType,
               'title' => $title,
               'content' => $content,
            ]);

            if (!$notify){
                return false;
            }
        }
        return true;
    }

}
