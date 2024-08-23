<?php

namespace App\Http\Controllers\Api\Device;

use App\Encryption\AsymmetricEncryption;
use App\Encryption\EncryptionHelper;
use App\Events\SendMail;
use App\Events\SendSms;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceApiRequest;
use App\Http\Requests\UpdateDeviceApiRequest;

use App\Models\Operator;
use App\Models\Transaction\TicketTransaction;
use App\Traits\CustomerTrait;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Traits\ApprovalTrait;
use App\Traits\ApiResponse;
use App\Models\Approval\ProcessFlowActor;
use App\Services\RequestLogger;
use App\Models\OperatorCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Storage;

use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;



class DeviceApiController extends Controller
{
    use ApiResponse;

    protected $messageCode;
    protected $sqlErrorMsg;
    protected $exceptionErrorMsg;
    protected $maskSensitiveData = false;
    protected $ZONA_TRAIN_TIME_LIMIT_HRS = -4; //DONT CHANGE THIS IS MAPPED TO ACCOUNT
    protected $NORMAL_TRAIN_TIME_LIMIT_HRS = 24; //DONT CHANGE THIS
    protected $ZONA_ZONE_SECOND_CLASS_PRICE = 23; //DONT CHANGE THIS
    protected $PRINT_TRACER = true;

    protected $LOG_FILE_TYPE_REG = 'log_reg';
    protected $LOG_FILE_TYPE_TRX = 'log_trx';
    protected $LOG_FILE_TYPE_TOP = 'log_top';
    protected $LOG_FILE_MAX_SIZE = 1; //1Mb

    protected $EMPLOYEE_ZONE_1CLASS_MONTHLY_FARE = 15;
    protected $EMPLOYEE_ZONE_2CLASS_MONTHLY_FARE = 15;
    protected $EMPLOYEE_ZONE_3CLASS_MONTHLY_FARE = 15;


    protected $accountDrId = 0;
    protected $accountCrId = 0;

    protected $CFMCAS0 = 0; //DONT CHANGE THIS IS MAPPED TO ACCOUNT
    protected $CFMCAR1 = 1; //DONT CHANGE THIS IS MAPPED TO ACCOUNT
    protected $CFMTOP2 = 2; //DONT CHANGE THIS IS MAPPED TO ACCOUNT
    protected $CFMEMT3 = 3; //DONT CHANGE THIS IS MAPPED TO ACCOUNT  -EMPLOY0 Account
    protected $CFMEMP4 = 4;
    protected $OPERATOR_TOPUP_ACCOUNT_TYPE = 6; //DONT CHANGE THIS IS MAPPED TO ACCOUNT
    protected $OPERATOR_CASH_ACCOUNT_TYPE = 7; //DONT CHANGE THIS IS MAPPED TO ACCOUNT
    protected $DEFAULT_PACKAGE_ID = 20;
    protected $logger;

    public function __construct(RequestLogger $logger)
    {
        $this->logger = $logger;
    }

    public function get_app_parameters($deviceIMEI, $deviceSerial)
    {

        $result = DB::select("SELECT `version`,`printer_BDA`, `device_type`, `log_Off`,`station_ID` ,`id`,`device_last_token`
        FROM `device_details`
        WHERE (`device_imei`=:device_imei OR `device_serial`=:sim_serial)  AND `activation_status`='A'", ['device_imei' => $deviceIMEI, 'sim_serial' => $deviceSerial]);

        return $result;

    }

    public function db_select($sql,$data){
        $result = DB::select($sql, $data);

        return $result;
    }

    public function db_insert($sql,$data){
        DB::insert($sql, $data);
        $statusId = DB::getPdo()->lastInsertId();
        return $statusId;
    }

    public function db_update($sql,$data){
        DB::update($sql, $data);
        return true;
    }

    function encryptAES($plaintext, $pw)
    {
        $plaintext = json_encode($plaintext);
        $iv_len = 12;
        $iv = random_bytes($iv_len);
        $salt_len = 16;
        $salt = random_bytes($salt_len);

        $tag = ""; // will be filled by openssl_encrypt
        $key = hash_pbkdf2('sha256', $pw, $salt, 10000, 32, true);

        $encrypted = openssl_encrypt(
            $plaintext,
            "aes-256-gcm",
            $key,
            $options = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv,
            $tag,
            "",
            16
        );
        $result = $iv . $salt . $encrypted . $tag;
        return base64_encode($result);
    }

    function decryptMobileRequest($encodedData, $pw)
    {
        $decodedData = base64_decode($encodedData);

        // Extract IV, salt, tag, and ciphertext
        $iv_len = 12;
        $iv = substr($decodedData, 0, $iv_len);
        $salt_len = 16;
        $salt = substr($decodedData, $iv_len, $salt_len);
        $tag_len = 16;
        $ciphertext = substr($decodedData, $iv_len + $salt_len, -16); // Exclude last 16 bytes for tag
        $tag = substr($decodedData, -$tag_len); // Extract last 16 bytes for tag

        // Generate key using PBKDF2
        $key = hash_pbkdf2('sha256', $pw, $salt, 10000, 32, true);

        // Decrypt using AES-256-GCM
        $decrypted = openssl_decrypt(
            $ciphertext,
            "aes-256-gcm",
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted;
    }

    //     openssl genrsa -out private.pem 2048
// openssl rsa -in private.pem -pubout -out public.pem


    public static function opensslPrivateDecrypt($data)
    {
        $filePath = public_path('assets/public.key');
        // Check if the file exists
        if (file_exists($filePath)) {
            // Read the contents of the file
            // $fileContents = file_get_contents($filePath);
            // $privateKeyPath =  'private.pem';
            $privateKey = openssl_pkey_get_private(file_get_contents($filePath));
            openssl_private_decrypt(base64_decode($data), $decryptedData, $privateKey);


        } else {
            // Handle the case where the file does not exist
            return response()->json([
                'status' => 'error',
                'message' => 'File not found.'
            ]);
        }


        return $decryptedData;
    }

    public static function opensslPrivateEncrypt($data)
    {
        $publicKey = 'public.pem';
        openssl_public_encrypt($data, $encrypted, file_get_contents($publicKey));
        return base64_encode($encrypted);
    }

    /**
     * Created By : Gabriel R Assenga
     * On : 01/08/2024
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function splash(Request $request){

        $request->validate([
            'data' => 'required',
        ]);
        $responsePayload = (object)null;
        $lastResponse = (object)null;
        try {
            FacadesLog::info("data", ["encryptedData" => $request->data]);
            $decryptedJson = AsymmetricEncryption::decryptAsymmetric($request->data);
            FacadesLog::info("data", ["decryptedJson" => $decryptedJson]);
            $outerArray = json_decode($decryptedJson);
            $privateKey = $outerArray->key;
            $imei1 = $outerArray->field_68;
            $androidId = $outerArray->androidId;
            $errorMessage = (object)null;
            $getKeyIfExist = FacadesDB::table('keys')->select('id')
                ->where("android_id", $androidId)->first();
            if ($getKeyIfExist) {
                $updateExistKey = FacadesDB::table('keys')
                    ->where('id', $getKeyIfExist->id)
                    ->update([
                        'key' => $privateKey,
                        'imei_1' => $imei1,
                        'imei_2' => $imei1,
                        'updated_at' => now(),
                    ]);
                if ($updateExistKey > 0) {
                    FacadesLog::info("KeyUpdated", ["message" => "Key Updated Successfully", "Result" => $updateExistKey]);
                } else {
                    FacadesLog::info("KeyUpdated", ["message" => "Fail To Update Key", "Result" => $updateExistKey]);
                    $errorMessage->message = "Fail To Update Key";
                    return response()->json($errorMessage);
                }

            } else {
                $insertNewKey = FacadesDB::table('keys')
                    ->insert([
                        'android_id' => $androidId,
                        'key' => $privateKey,
                        'imei_1' => $imei1,
                        'imei_2' => $imei1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                if ($insertNewKey > 0) {
                    FacadesLog::info("KeyUpdated", ["message" => "Key Inserted Successfully", "Result" => $insertNewKey]);
                } else {
                    FacadesLog::info("KeyUpdated", ["message" => "Fail To Insert Key", "Result" => $insertNewKey]);
                    $errorMessage->message = "Fail To Insert Key";
                    return response()->json($errorMessage);
                }

            }
            $responsePayload->message = "Successfully";
            $responsePayload->response_code = \HttpResponseCode::SUCCESS;
            FacadesLog::info("payload", ["payload" => $responsePayload]);
            $res = EncryptionHelper::encrypt(json_encode($responsePayload), $privateKey);
            FacadesLog::info("encryptedPayload", ["encryptedPayload" => $res]);
            $lastResponse->data = $res;
            return response()->json($lastResponse);
        }catch (Exception $e){
            FacadesLog::error("EXCEPTION", [ "MESSAGE"=> "CAUGHT-ON-CATCH" ,"ERROR" => $e]);
            $responsePayload->message = "Failed, Contact System Administrator";
            $responsePayload->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
//            $res = EncryptionHelper::encrypt(json_encode($responsePayload), $privateKey);
            $res =  AsymmetricEncryption::encryptAsymmetric($responsePayload);
            FacadesLog::info("encryptedPayload", ["encryptedPayload" => $res]);
            $lastResponse->data = $res;
            return response()->json($lastResponse);
        }
    }
    public function otp_verification(Request $request){
        $msg = null;
        $this->msg = $request;
        $this->msg['MTI'] = "0630";
        $this->msg['field_68'] = $request->simSerial;
        $this->msg['operator_id'] = $request->operator_id;
        $this->msg['otp'] = $request->otp_code_number;
        $params = $this->get_app_parameters($this->msg['field_68'], $this->msg['field_69']);
        if (!empty($params)) {

            if($this->verifyOtp($this->msg['field_68'],$this->msg['otp'],$this->msg['operator_id'])){
                $this->msg['field_39'] = '00';
            }else{
                $this->msg['field_39'] = '99';
            }
        } else {
            $this->msg['field_39'] = '25';
        }

        return response()->json($this->msg);
    }

    public function verifyOtp($imei, $otp,$operator)
    {

        $sql = "SELECT `otpcode` FROM `otps` WHERE operator=:operator AND device=:device AND status=:status";


        try {
            $status = "A";
            $data = ['operator' => $operator,'device' => $imei,'status' => $status ];
            $result = $this->db_select($sql,$data);
            $this->msg['result'] = $result[0]->otpcode;
            if(empty($result)){
                $status = false;
            }else if($result[0]->otpcode == $otp){
                $status = true;
            }else{
                $status = false;
            }

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $status = false;
        }
        return $status;
    }

    public function insertDeviceVersion($imei, $appVersion, $operator)
    {
        $now = Carbon::now();
        $sql = "INSERT INTO `device_details_tracking` (`device_imei`, `app_version`, `operator`,`created_at`)
    VALUES (:deviceImei, :app_version, :operator, :datetime)";
        try {
            $data = ['deviceImei' => $imei, 'app_version' => $appVersion, 'operator' => $operator, 'datetime' => $now];
            $result = $this->db_select($sql, $data);
            $status = (bool)$result;
        } catch (\Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $status = 0;
        }
        return $status;
    }

    public function get_station_list()
    {
        $sql = "SELECT train_stations.`id`, `station_Name`,`longitude`,`latitude`,thr_Class,frst_Class,sec_Class,class_type ,`province`,stations_lines.line_id,`distance_Maputo`,`is_off_train_ticket_available`
         FROM `train_stations` INNER JOIN cfm_classes ON cfm_classes.id=train_stations.thr_Class
        INNER JOIN stations_lines ON stations_lines.station_id=train_stations.id";
        $data = [ ];
        $result = $this->db_select($sql,$data);
        try {

            $this->msg['field_48'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return  $this->msg['field_48'];
    }

    public function get_zones_list()
    {
        $sql = "SELECT `id`, `name`
        FROM `zone_lists` ";
        $data = [ ];
        $result = $this->db_select($sql,$data);
        try {

            $this->msg['zones'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return  $this->msg['zones'];
    }

    public function get_operator_allocation($id)
    {
        $sql = "SELECT train_id_asc,wagons.id,model,serial_number,class_id,normal_seats,standing_seats,total_seats
        FROM `operator_allocations` INNER JOIN wagons ON wagons.id = operator_allocations.wagon_id
        INNER JOIN wagon_layouts ON wagon_layouts.id=wagons.layout_id
        WHERE operator_allocations.operator_id=:id";
        $data = [ 'id'=>$id];
        $result = $this->db_select($sql,$data);
        try {

            $this->msg['operator_allocations'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return  $this->msg['operator_allocations'];
    }







    public function fetchTrainData()
    {
        $trainId = '123'; // Example train ID

        // Retrieve the train with its classes, carriages, and seats
        $train = Train::with('classes.carriages.seats')->find($trainId);

        // Return the JSON response or use it as needed
        return response()->json($train);
    }
    public function train_layout(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->input('key');
        $dataPayload = (object)null;
        $responseObject = (object) null;
        $this->msg = $request;
        try {
            $sql = "SELECT train_id,wagons.id as wagoon_id,serial_number,class_id,`number`,manufacture_id FROM `wagons`
         INNER JOIN train_wagons ON train_wagons.wagon_id=wagons.id
         INNER JOIN train_layouts ON train_layouts.id = train_wagons.train_layout_id
         INNER JOIN wagon_layouts ON wagon_layouts.id=wagons.layout_id
         INNER JOIN seats ON seats.wagon_layout_id=wagons.layout_id
         WHERE train_id=:id";
            $id = $this->msg['id'];
            $data = [ 'id'=>  $id];
            $result = $this->db_select($sql,$data);

            $this->msg['train_layout'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'data' => $result];

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['train_layout'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }



    }

    public function card_transactions(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->input('key');
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            $sql = "SELECT * FROM ticket_transactions
         WHERE card_number=:card_number";
            $card_number = $this->msg['card_number'];
            $data = [ 'card_number'=>  $card_number];
            $result = $this->db_select($sql,$data);


            $this->msg['card_transactions'] = $result;

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['card_transactions'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }

    }

    public function packages(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->input('key');
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $sql = "SELECT * FROM customer_account_package_types where send_device_option = :send_device_option";
            $send_device_option = "1";
            $data = [ 'send_device_option'=>  $send_device_option];
            $result = $this->db_select($sql,$data);

            $this->msg['packages'] = $result;

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['packages'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }


    }

    public function pin_validation(Request $request){

        try {

            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'data' => ["message"=>"Pin successfully changed"]];


        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return  $this->msg['response'];
    }

    public function transfer_verification(Request $request){
        $this->msg = $request;
        // $sql = "SELECT * FROM customer_account_package_types where send_device_option = :send_device_option";
        // $send_device_option = "1";
        // $data = [ 'send_device_option'=>  $send_device_option];
        // $result = $this->db_select($sql,$data);
        try {

            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success", 'data'=>['from_card_number' => $this->msg['from_card_number'],'to_card_number' => $this->msg['to_card_number']
                    ,'amount' => $this->msg['amount'],'to_card_owner' => "Filbert Nyakunga"]];

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return  $this->msg['response'];
    }

    public function mobile_customer_registration(Request $request){

        $this->msg = $request;
        // $sql = "SELECT * FROM customer_account_package_types where send_device_option = :send_device_option";
        // $send_device_option = "1";
        // $data = [ 'send_device_option'=>  $send_device_option];
        // $result = $this->db_select($sql,$data);
        try {

            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'data' => ["message"=>"Registado com sucesso"]];

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return  $this->msg['response'];
    }

    public function authentication(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            $username = $this->msg['username'];
            $sql = "SELECT * FROM users where username = :username";
            $data = [ 'username'=>  $username];
            $result = $this->db_select($sql,$data);
            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'token' => $result[0]->token,'data' => ["message"=>"Registado com sucesso"]];

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['response'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }

    }


    public function ticket_transactions(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        $token = $this->msg['token'];
        $from_date = $this->msg['from_date'];
        $to_date = $this->msg['end_date'];

        try {
            $sql = "SELECT * FROM users where token = :token";
            $data = [ 'token'=>  $token];
            $result = $this->db_select($sql,$data);
            if(!empty($result[0]->username)){
                $sql = "SELECT * FROM ticket_transactions where trnx_date >= :from_date AND trnx_date <= :to_date";
                $data = [ 'from_date'=>  $from_date,'to_date'=>  $to_date ];
                $transactions = $this->db_select($sql,$data);
            }
            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'transactions' => $transactions,'data' => ["message"=>"Registado com sucesso"]];
            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['response'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);


        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }

    }

    public function cargo_transactions(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        $token = $this->msg['token'];
        $from_date = $this->msg['from_date'];
        $to_date = $this->msg['end_date'];
        try {
            $sql = "SELECT * FROM users where token = :token";
            $data = [ 'token'=>  $token];
            $result = $this->db_select($sql,$data);
            if(!empty($result[0]->username)){
                $sql = "SELECT * FROM tbl_weight_transactions where trnx_date >= :from_date AND trnx_date <= :to_date";
                $data = [ 'from_date'=>  $from_date,'to_date'=>  $to_date ];
                $transactions = $this->db_select($sql,$data);
            }


            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'transactions' => $transactions,'data' => ["message"=>"Registado com sucesso"]];
            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['response'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }


    }

    public function summary_details(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        $token = $this->msg['token'];
        $date = $this->msg['date'];
        $sql = "SELECT * FROM users where token = :token";
        $data = [ 'token'=>  $token];
        try {
            $result = $this->db_select($sql,$data);
            if(!empty($result[0]->username)){
                $sql = "SELECT operators.id,operators.full_name,device_imei,train_id,trains.train_number,total_tickets,total_amount,summary_date_time,
            STR_TO_DATE(summary_date_time, '%d%m%y%H%i%s') AS formatted_date
            FROM `device_summary_receipts`
            INNER JOIN operators ON operators.id = device_summary_receipts.operator_id
            INNER JOIN trains ON trains.id = device_summary_receipts.train_id
            WHERE STR_TO_DATE(summary_date_time, '%d%m%y') =:currentdate ORDER BY device_summary_receipts.id DESC";
                $data = [ 'currentdate'=>  $date ];
                $transactions = $this->db_select($sql,$data);
            }


            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'data' => $transactions];
            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['response'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }


    }




    public function balance_transfer(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {

            $this->msg['response'] = ['code' => "200",'status' => "success"
                ,'message' => "Success",'data' => ["message"=>"Pagamento efectuado com sucesso"]];

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg['response'];
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }

    }

    public function get_operator_allocation_seats($id)
    {
        $sql = "SELECT wagons.id,class_id,seats.id as seat_id,CONCAT('A','',seats.id) AS seats
        FROM `operator_allocations` INNER JOIN wagons ON wagons.id = operator_allocations.wagon_id
        INNER JOIN wagon_layouts ON wagon_layouts.id=wagons.layout_id
        INNER JOIN seats ON seats.wagon_layout_id=wagons.layout_id
        WHERE operator_allocations.operator_id=:id";
        $data = [ 'id'=>$id];
        $result = $this->db_select($sql,$data);
        try {

            $this->msg['seats'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return  $this->msg['seats'];
    }




    public function get_train_class()
    {
        $sql = "SELECT `id`,`class_type` FROM `cfm_classes` WHERE 1";
        try {
            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['class'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return $this->msg['class'];
    }


    public function get_price_table()
    {
        $sql = "SELECT *
		FROM `normal_prices` ";

        try {
            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['field_49'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return $this->msg['field_49'];
    }


    public function get_passenger_category()
    {
        $sql = "SELECT `id`, `title`, `percent`,`is_used_for_tranx`,`main_category` FROM `special_groups`";
        try {
            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['field_50'] = $result;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return $this->msg['field_50'];
    }


    public function get_zone_details()
    {

        $sql = "SELECT train_stations.`id`,`station_Name`,`zone_st`,'1' as zone_Border,stations_lines.line_id ,zone_id_desc FROM `train_stations`
           INNER JOIN stations_lines ON stations_lines.station_id=train_stations.id WHERE zone_st !=0";


        try {
            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['field_51'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return $this->msg['field_51'];
    }

    public function get_train_details_new()
    {
        try {

            $sql = "SELECT `t`.`id`,`t`.`train_Number`,`r`.`train_line_id`,`t`.`ETD`,`r`.`route_name`,`r`.`id` AS route_id, `r`.`route_direction`,`r`.`train_direction_id`,`t`.`train_name`,
                `t`.`train_type`,`t`.`start_Stop_ID`,`t`.`end_Stop_ID`,`t`.`train_third_class`,`t`.`train_first_class`,`t`.`train_second_class`,`t`.`zone_one`,`t`.`zone_two`,`t`.`zone_three`,`t`.`zone_four`,`t`.`int_price_group`,`t`.`travel_hours_duration`,`r`.`first_class_penalty_value`,`r`.`second_class_penalty_value`,`r`.`third_class_penalty_value`,`t`.`reverse_train_id`
                FROM `trains` as `t` INNER JOIN `train_routes` AS `r` ON `t`.route_id=`r`.`id` WHERE `t`.activated=1";

            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['field_52'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return $this->msg['field_52'];
    }

    public function get_train_schedule()
    {
        try {
            $sql = "SELECT id,train_id,station_id,time_arrive,time_departure FROM `train_station_schedule_times`";

            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['field_53'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return $this->msg['field_53'];
    }


    public function get_zone_price()
    {
        $sql = "SELECT `id`, `name`,`price`,`class_id`,price_group,price_on_train FROM `zones` WHERE price_group_status=1 ORDER BY class_id DESC"; //`tbl_zones`.`price_group`=0
        $this->msg['field_54'] = "";
        try {
            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['field_54'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return $this->msg['field_54'];
    }


    public function getOperatorCollectionId($receipt_number)
    {
        $sql = "SELECT `id` FROM `operator_collections` WHERE receipt_number=:receipt_number";
        try {

            $data = ['receipt_number' => $receipt_number ];
            $result = $this->db_select($sql,$data);

        } catch (Exception $e) {
            Log::channel('pos')->error(json_encode($this->errorPayload($e)));
            return false;
        }
        return $result;
    }

    public function get_lagguage_sub_categories()
    {
        $sql = "SELECT `id`, `name`, '0' as min_Kg, `category_id` FROM `cargo_sub_categories` WHERE 1";
        try {

            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['field_56'] = $result;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return $this->msg['field_56'];
    }


    public function get_automotora_stations( )
    {
        $sql = "SELECT train_stations.`id`, `station_Name`,`longitude`,`latitude`,thr_Class,frst_Class,sec_Class,class_type ,`province`,`line_id`,`distance_Maputo`,`is_off_train_ticket_available`
        FROM `train_stations` INNER JOIN cfm_classes ON cfm_classes.id=train_stations.thr_Class";



        try {
            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['automotora_stations'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return $this->msg['automotora_stations'];
    }

    public function get_automotora_price()
    {

        $sql = "SELECT line_id,origin_station,destination_station,fare_charge FROM `automotora_prices`        ";



        try {
            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['automotora_price'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return $this->msg['automotora_price'];
    }


    public function get_train_details_new_automotora()
    {
        try {

            $sql = "SELECT `t`.`id`,`t`.`train_Number`,`r`.`train_line_id`,`t`.`ETD`,`r`.`route_name`,`r`.`id` AS route_id, `r`.`route_direction`,`r`.`train_direction_id`,`t`.`train_name`,`t`.`train_type`,`t`.`start_Stop_ID`,`t`.`end_Stop_ID`,`t`.`train_third_class`,`t`.`zone_one`,`t`.`zone_two`,`t`.`zone_three`,`t`.`zone_four`,`t`.`int_price_group`,`t`.`travel_hours_duration`,`r`.`first_class_penalty_value`,`r`.`second_class_penalty_value`,`r`.`third_class_penalty_value`,`t`.`reverse_train_id`
                FROM `trains` as `t` INNER JOIN `train_routes` AS `r` ON `t`.route_id=`r`.`id` WHERE `t`.activated=1";

            $data = [ ];
            $result = $this->db_select($sql,$data);
            $this->msg['automotora_train'] = $result;

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }

        return $this->msg['automotora_train'];
    }


    //Get Device properties
    public function get_device_properties($deviceIMEI, $deviceSerial)
    {
        $sql = 'SELECT
                 `d`.`id`,
                 `d`.`device_type`,
                 `d`.`device_name`,
                 `d`.`device_imei`,
                 `d`.`device_serial`,
                 `d`.`printer_BDA`,
                 `d`.`version`,
                 `d`.`activation_status`,
                 `d`.`station_id`,
                 `d`.`log_Off`,
                 `d`.`On_Off`,
                 `s`.`station_name` ,
                 `s`.`station_Name_ERP`,
                 `s`.`station_Type_ERP`,
                 `s`.`province`,
                 `s`.`latitude`,
                 `s`.`longitude`,
                 `s`.`distance_Maputo`,
                 `s`.`line_ID`,
                 `s`.`frst_Class`,
                 `s`.`sec_Class`,
                 `s`.`thr_Class`,
                 `s`.`zone_ST`,
                 `s`.`line_pass`,
                 `d`.`allowed_ticket_sale_type`,
                 `d`.`balance_vendor_id`,
                 `d`.`balance_product_id`,
                 d.last_connect
             FROM `device_details` AS `d`
             LEFT JOIN `train_stations` AS `s` ON `d`.`station_id`=`s`.`id`
             WHERE (`device_imei`=:device_imei OR `device_serial`=:sim_serial)
              AND `activation_status`="A"';

        $data = [ 'device_imei' => $deviceIMEI, 'sim_serial' => $deviceSerial ];
        $result = $this->db_select($sql,$data);

        return $result;
    }


    public function login(Request $request){

        validator([
            'data' => 'required'
        ]);

        $successResponse = (object)null;
        $failedResponse = (object)null;
        $dataPayload = (object)null;
        try {
            $headers = getallheaders();
            $stringEncryptedByteAndroidId = $headers['Encrypted-Android-Id'] ?? null;
            $jsonEncryptedAndroidId = json_decode($stringEncryptedByteAndroidId);
            FacadesLog::info("Payload", ["data" => $request->data]);
            FacadesLog::info("Header", ["encryptedByteAndroidId" => $jsonEncryptedAndroidId->data]);
            if (!$jsonEncryptedAndroidId) {
                return response()->json(['error' => "AndroidId Not Provided"]);
            }
            $decryptedAByteAndroidId = AsymmetricEncryption::decryptAsymmetric($jsonEncryptedAndroidId->data);
            FacadesLog::info('AndroidId', ["decryptedAByteAndroidId" => $decryptedAByteAndroidId]);

            $keys = DB::table('keys')
                ->select('key')
                ->where(['android_id' => $decryptedAByteAndroidId])->first();
            if (!$keys) {
                return response()->json(['error' => "No Key Found"]);
            }
            $pwKey = $keys->key;
            $decryptedJsonString = EncryptionHelper::decrypt($request->data, $pwKey);
            $decodedJson = json_decode($decryptedJsonString);
            FacadesLog::info(["DecryptedJson" => $decodedJson->MTI]);
            $outerArray = $decodedJson;
            $username = $outerArray->field_42;
            $password = $outerArray->field_52;
            $deviceId = $outerArray->code_number;
            if(empty($username) || empty($password)){
                $failedResponse->message = VALIDATION_FAIL . " : " . USERNAME_AND_PASSWORD_REQUIRED;
                $failedResponse->response_code = BAD_REQUEST;
                $failedResponse->msg = "";
                $encryptedFailure = EncryptionHelper::encrypt(json_encode($failedResponse), $pwKey);
                $dataPayload->data = $encryptedFailure;
                return response()->json($dataPayload);
            }
            /**
             * End Of Decryption during Login
             */


            /**
             * Start of the logic of other logic
             */
            $msg = null;
            $this->msg = (array) $decodedJson;
            $this->msg['MTI'] = "0630";
            $params = $this->get_app_parameters($this->msg['field_68'], $this->msg['field_69']);

            $agent_username = $this->msg['field_42'];
            $code_number = $this->msg['code_number'];

            $sql = "SELECT  `o`.`password`,`o`.`full_name`,`o`.`train_line_id`,`o`.`operator_category_id`,`o`.`id`,`o`.`operator_Type_code`,
        IF(`a`.`train_ID_asc` IS NULL or `a`.`train_ID_asc` = '', '0', `a`.`train_ID_asc`) as `train_ID_asc` ,
        IF(`a`.`train_ID_dec` IS NULL or `a`.`train_ID_dec` = '', '0', `a`.`train_ID_dec`) as `train_ID_dec` ,
        `o`.`station_ID` as `station_ID`,
        o.zone,o.normal,o.changing_class,o.automotora,o.top_up,o.registration,o.scanning,o.incentive

        FROM `operators` AS `o` LEFT JOIN `operator_allocations` AS a ON `o`.`operator_ID`=`a`.`operator_ID`
        WHERE `username`=:username";

            $data = ['username' => $agent_username];
            $result = $this->db_select($sql, $data);

            if (!empty($result)) {
                if(count($params) > 0) {
                    FacadesLog::info(["Password" => $result[0]->password]);
                    if (($code_number != 'NOT SET') && ($code_number != $params[0]->device_last_token)) {
                        $this->msg['field_39'] = '55';
                    } else
                        if (Hash::check($password, $result[0]->password)) {
                            $this->msg['field_52'] = '****';
                            $this->msg['otp'] = $this->generate_otp($result[0]->id, $this->msg['field_68']);
                            if (isset($this->msg['version'])) {
                                $this->insertDeviceVersion($params[0]->id, $this->msg['version'], $agent_username);
                            }
                            // Get Station List
                            $this->msg['operator_category'] = $result[0]->operator_category_id; //1=conductor, 2=Inseptor, 3=CargoMaster
                            $this->msg['operator_type'] = $result[0]->operator_Type_code; //1 Normal, 2, Zone, 3, Both
                            $this->msg['stationId'] = $result[0]->station_ID; //station id
                            $this->msg['operator_id'] = $result[0]->id;
                            $this->msg['operator_full_name'] = $result[0]->full_name;
                            $this->msg['type_zone'] = $result[0]->zone;
                            $this->msg['type_normal'] = $result[0]->normal;
                            $this->msg['type_changing_class'] = $result[0]->changing_class;
                            $this->msg['type_automotora'] = $result[0]->automotora;
                            $this->msg['type_top_up'] = $result[0]->top_up;
                            $this->msg['type_registration'] = $result[0]->registration;
                            $this->msg['type_scanning'] = $result[0]->scanning;
                            //station List
                            $this->get_station_list($result[0]->train_line_id);
                            $this->get_zones_list();
                            $this->get_operator_allocation($result[0]->id);
                            $this->get_operator_allocation_seats($result[0]->id);
                            //Get Train Class
                            $this->get_train_class();
                            //Get Price Table
                            $this->get_price_table();
                            //Get Passenger Category
                            $this->get_passenger_category();
                            //Get Zone detail
                            $this->get_zone_details();


                            //Get Train Detail
                            $this->get_train_details_new();
                            // Get Train Station Schedule
                            $this->get_train_schedule();
                            // // Zonalprice
                            $this->get_zone_price();
                            //automototra stations
                            $this->get_automotora_stations();
                            //automototra price
                            $this->get_automotora_price();
                            //automotora trains
                            $this->get_train_details_new_automotora();

                            $this->get_exchange_rate();
                            $this->get_lagguage_sub_categories();

                            // // //card informations
                            // // //Author Filbert
                            $this->getCustomersCardAccountsInformations();
                            $this->msg['field_128'] = '1234567890';
                            $this->msg['field_39'] = '00';

                            $divice_properties = $this->get_device_properties($this->msg['field_68'], $this->msg['field_69']);
                            if (!empty($divice_properties)) {

                                $this->msg['field_4'] = '0.00';

                                $this->msg['device_id'] = $divice_properties[0]->id;
                                $this->msg['device_type'] = $divice_properties[0]->device_type;
                                $this->msg['device_name'] = $divice_properties[0]->device_name;
                                $this->msg['device_imei'] = $divice_properties[0]->device_imei;
                                $this->msg['device_serial'] = $divice_properties[0]->device_serial;
                                $this->msg['device_app_version'] = $divice_properties[0]->version;
                                $this->msg['device_status'] = $divice_properties[0]->activation_status;
                                $this->msg['device_station_id'] = $divice_properties[0]->station_id;
                                $this->msg['device_logoff_time'] = $divice_properties[0]->last_connect;
                                $this->msg['device_on_off'] = $divice_properties[0]->On_Off;
                                $this->msg['station_name'] = $divice_properties[0]->station_name;
                                $this->msg['province'] = "";
                                $this->msg['station_latitude'] = "";
                                $this->msg['station_longtude'] = "";
                                $this->msg['station_distance'] = "";

                                $this->msg['device_ticket_sale_type'] = $divice_properties[0]->allowed_ticket_sale_type;


                                $this->msg['balance_vendor_id'] = $divice_properties[0]->balance_vendor_id;
                                $this->msg['balance_product_id'] = $divice_properties[0]->balance_product_id;

                                $this->msg['station_lines'] = $this->get_station_passing_lines();
                            }

                        } else {
                            $this->msg['field_39'] = '75';
                        }
                } else {
                    $this->msg['field_39'] = '55';
                }
            } else {
                $this->msg['field_39'] = '55';
            }


            /**
             * Incase of success login credentials
             * Start of JWT token and Encryption Logic
             */
            // Get the token from the request
            try {
                $oldToken = JWTAuth::getToken();
                // Invalidate the token
                JWTAuth::invalidate($oldToken);
                FacadesLog::info('Old-Token', [ "Message" => "Invalidated" , "New-Token" => $oldToken]);
            }catch (\Exception $e){
                FacadesLog::info('Old-Token', [ "Message" => " Not Invalidated , No Token"]);
            }


            $token = auth()->guard('operator')->attempt([
                'username' => $username,
                'password' => $password
            ]);
            if (!empty($token)) {
                $successResponse->message = LOGIN_SUCCESSFULLY;
                $successResponse->status = true;
                $successResponse->response_code = \HttpResponseCode::SUCCESS;
                $successResponse->token = $token;
                $successResponse->msg = $this->msg;
                $encryptedSuccess = EncryptionHelper::encrypt(json_encode($successResponse), $pwKey);
                $dataPayload->data = $encryptedSuccess;
                return response()->json($dataPayload);
            }

            /**
             * this should also be implemented in the logic of failed credentials provided
             */
            $failedResponse->message = \HttpResponseCode::BAD_REQUEST;
            $failedResponse->status = false;
            $failedResponse->response_code = \HttpResponseCode::UNAUTHORIZED;
            $failedResponse->msg = "";
            $encryptedFailure = EncryptionHelper::encrypt(json_encode($failedResponse), $pwKey);
            FacadesLog::info('Failure', ["encryptedResponse" => $encryptedFailure]);
            $dataPayload->data = $encryptedFailure;
            return response()->json($dataPayload);

        }catch (\Exception $e){
            $failedResponse->message = "System Error, Contact Administrator";
            $failedResponse->status = false;
            $failedResponse->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $successResponse->msg = "";
            $encryptedFailure = EncryptionHelper::encrypt(json_encode($failedResponse), $pwKey);
            FacadesLog::info('ERROR-ON-CACHE', ["Error Message" => $e]);
            $dataPayload->data = $encryptedFailure;
            return response()->json($dataPayload);
        }
    }

    public function get_station_passing_lines()
    {

        $sql = 'SELECT `l`.`id`,`l`.`line_code`,`l`.`line_name`,`l`.`line_distance` FROM `train_lines` AS `l` '; //$operatorID
        $data = [ ];
        $result = $this->db_select($sql,$data);


        return $result;

    }

    public function get_exchange_rate()
    {
        try {


            $sql = "SELECT id,currency,price,description
									FROM `exchange_rates` where status=1 ";
            $data = [ ];
            $result = $this->db_select($sql,$data);

            $this->msg['exchange_rate'] = $result;

        } catch (Exception $e) {
        }

        return $this->msg['exchange_rate'];
    }


    public function scanning(Request $request){
        try {
            $device_imei = $request[0]['device_imei'];;
            $application_version = $request[0]['application_version'];
            $checkDeviceDetails = DB::table('device_details')
                ->where('device_imei',$device_imei)
                ->first();
            $stationsDetails = DB::table('train_stations')
                ->get();

            $trainsDetails = DB::table('trains')
                ->get();

            $lineDetails = DB::table('train_lines')
                ->get();
            $classDetails = DB::table('cfm_classes')
                ->get();

            $categoryDetails = DB::table('cfm_classes')
                ->get();

            $zoneDetails = DB::table('zone_lists')
                ->get();

            $zone_price = DB::table('zones')
                ->get();
            $automotora_prices = DB::table('automotora_prices')
                ->get();
            $normal_prices = DB::table('normal_prices')
                ->get();
            $categories = DB::table('special_groups')
                ->get();

            if($checkDeviceDetails) {
                $operatorCategories = DB::table('operator_categories')->get();
                return response()->json([['status' => 'success', 'message' => 'Device Registered',
                    'device_details' =>$checkDeviceDetails,
                    'stations' => $stationsDetails,
                    'trains' => $trainsDetails,
                    'lines' => $lineDetails,
                    'class' => $classDetails,
                    'zones' => $zoneDetails,
                    'zones_price' => $zone_price,
                    'automotora_prices' => $automotora_prices,
                    'normal_prices' => $normal_prices,
                    'categories' => $categories,
                    'code' =>200 ]], 200);


            } else {
                return response()->json([['status' => 'failed', 'message' => 'Device Not Registered','data' =>$device_imei ,'code'=>201]], 200);
            }


        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            return response()->json($th->getMessage());

        }
    }
    public function transaction_details(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $id =  $request->data['id'];
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            $transactions = DB::table('ticket_transactions')
                ->join('operators','operators.id','=','ticket_transactions.operator_id')
                ->join('device_details','device_details.device_imei','=','ticket_transactions.device_number')
                ->join('train_stations AS origin','origin.id','=','ticket_transactions.station_from')
                ->join('train_stations AS destination','destination.id','=','ticket_transactions.station_to')
                ->join('special_groups','special_groups.id','=','ticket_transactions.category_id')
                ->join('cfm_classes','cfm_classes.id','=','ticket_transactions.class_id')
                ->join('trains','trains.id','=','ticket_transactions.train_id')
                ->join('train_routes','train_routes.id','=','trains.route_id')
                ->select('train_routes.route_name',
                    'trains.train_number',
                    'cfm_classes.class_type',
                    'special_groups.title',
                    'origin.station_name as origin',
                    'destination.station_name as destination',
                    'ticket_transactions.*',
                    'device_details.device_type',
                    'operators.full_name',
                    'operators.phone',
                    'operators.username'
                )
                ->where('ticket_transactions.id',$id)
                ->first();

            if($transactions) {
                $responseObject->message = "";
                $responseObject->response_code = \HttpResponseCode::SUCCESS;
                $responseObject->transactions = $transactions;
                $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                $dataPayload->data = $encryptedResponse;
                return response()->json($dataPayload);

            } else {
                $responseObject->message = "Transactions Details";
                $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
                $responseObject->status = "failed";
                $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                $dataPayload->data = $encryptedResponse;
                return response()->json($dataPayload);
            }


        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }

    }

    public function transaction_topup_details(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $id =  $request->data['id'];
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $transactions = DB::table('ticket_transactions')
                ->join('operators','operators.id','=','ticket_transactions.operator_id')
                ->join('device_details','device_details.device_imei','=','ticket_transactions.device_number')

                ->select('ticket_transactions.*','device_details.device_type','operators.full_name','operators.phone','operators.username')
                ->where('ticket_transactions.id',$id)
                ->first();

            if($transactions) {
                $responseObject->message = "";
                $responseObject->response_code = \HttpResponseCode::SUCCESS;
                $responseObject->transactions = $transactions;
                $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                $dataPayload->data = $encryptedResponse;
                return response()->json($dataPayload);

            } else {
                $responseObject->message = "Transactions Details";
                $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
                $responseObject->status = "failed";
                $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                $dataPayload->data = $encryptedResponse;
                return response()->json($dataPayload);

            }


        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }

    }
    public function operator_login(Request $request){
        try {
            $username = $request[0]['username'];
            $password = $request[0]['password'];
            $operators = DB::table('operators')
                ->where('username',$username)
                ->first();
            $stationsDetails = DB::table('train_stations')
                ->get();
            if($operators) {
                $operatorCategories = DB::table('operator_categories')->get();
                return response()->json([['code'=>'200','status' => 'success', 'message' => 'Device Registered',
                    'operators' =>$operators,'stations' =>$stationsDetails,

                ]], 200);

            } else {
                return response()->json([['code'=>'201','status' => 'failed', 'message' => 'Device Not Registered' ]], 200);
            }


        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            return response()->json($th->getMessage());

        }
    }

    public function card_topup(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $msg = null;
        $this->msg = $request->data; //Process Login Message
        $this->msg['MTI'] = "0630";
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            //$this->log_event('TopUp',$this->msg['field_61']);
            if (!isset($this->msg['seat'], $this->msg['category'], $this->msg['zone_id'], $this->msg['fromStop'], $this->msg['toStop'])) {

                $this->msg['seat'] = 0;
                $this->msg['zone_id'] = 0;
                $this->msg['fromStop'] = 0;
                $this->msg['toStop'] = 0;
                $this->msg['longitude'] = 0;
                $this->msg['latitude'] = 0;
            }
            if (!isset($this->msg['field_4'])) {
                $this->msg['field_4'] = number_format((float)$this->msg['field_4'], 2, '.', '');
            }
            if (!isset($this->msg['train_id']) || empty($this->msg['train_id'])) {
                $this->msg['train_id'] = '0';
            }
            if (!isset($this->msg['quantity']) || empty($this->msg['quantity'])) {
                $this->msg['quantity'] = '1';
            }

            if (!isset($this->msg['class_id']) || empty($this->msg['class_id'])) {
                if (!isset($this->msg['field_102']) || empty($this->msg['field_102'])) {
                    $this->msg['class_id'] = '3';
                } else {
                    $class = substr($this->msg['field_102'], -1);
                    if ($class > 0)
                        $this->msg['class_id'] = $class;
                    else
                        $this->msg['class_id'] = '3';

                }
            }
            if (!isset($this->msg['category']) || empty($this->msg['category'])) {
                $this->msg['category'] = '0';
            }
            if (!isset($this->msg['penalty'], $this->msg['fine_status'])) {
                $this->msg['penalty'] = '';
                $this->msg['fine_status'] = 1;
            }
            if (!isset($this->msg['field_102']) || empty($this->msg['field_102'])) {
                $this->msg['field_102'] = 'L0Z0N0';
            }

            $this->msg['field_4'] = sprintf('%.2f', $this->msg['field_4'], 2);
            $this->process_card_topup();

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);


        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $responseObject->message =  $th->getMessage();
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }


    }


    private function process_card_topup()
    {
        if (isset($this->msg['tag_id'])) {
            $card = $this->check_card_existance($this->msg, A_STATUS);
            if (!empty($card)) {
                if ($card[0]->status == ACTIVE_STATUS && $card[0]->validity == VALID_STATUS_NAME) {
                    if ($card[0]->card_ownership == 0) //Individual card
                    {
                        if (!$this->check_if_the_package_belong_only_to_company($this->msg['field_102'])) {
                            $this->proccessing_individual_card_topup($card[0]->id); //card_id
                        } else {
                            $this->msg['field_39'] = FIELD_39_12;
                            $this->msg['message'] = 'Transaction not allowed, Wrong package';
                        }
                    } else if ($card[0]->card_ownership == COMPANY_CARD_INTERGER) //Company card
                    {
                        if ($this->check_if_this_campany_card_package_belong_to_this_company($card[0]->id, $this->msg['field_102'])) {
                            $this->proccessing_company_card_topup($card[0]->id); //card_id

                        } else {
                            $this->msg['field_39'] = '12';
                            $this->msg['message'] = 'Transaction not allowed, Wrong package';
                        }
                    }
                } else {
                    if ($card[0]->status == B_STATUS) {
                        $this->msg['field_39'] = FIELD_39_14;
                        $this->msg['message'] = 'card not blocked';
                    } else if ($card[0]->status == I_STATUS) {
                        $this->msg['field_39'] = FIELD_39_14;
                        $this->msg['message'] = 'card not activated blocked';
                    } else if ($card[0]->status == S_STATUS) {
                        $this->msg['field_39'] = FIELD_39_43;
                        $this->msg['message'] = 'card stolen pickup';
                    } else if ($card[0]->status == L_STATUS) {
                        $this->msg['field_39'] = FIELD_39_41;
                        $this->msg['message'] = LOST_STATUS;
                    } else if ($card[0]->validity !== VALID_STATUS_NAME) {
                        $this->msg['field_39'] = FIELD_39_54;
                        $this->msg['message'] = EXPIRED_CARD;
                    }
                }
            } else {
                $this->msg['field_39'] = FIELD_39_14;
                $this->msg['message'] = CARD_DOES_NOT_EXIST;
                unset($this->msg['tag_id']);
            }
        } else {
            $this->msg['field_39'] = FIELD_39_30;
            $this->msg['message'] = CARD_TAG_NOT_SENT;
            unset($this->msg['tag_id']);
        }
        unset($this->msg['seat']);
        unset($this->msg['train_id']);
        unset($this->msg['fromStop']);
        unset($this->msg['toStop']);
        unset($this->msg['longitude']);
        unset($this->msg['latitude']);
        unset($this->msg['field_69']);
    }


    public function proccessing_company_card_topup($cardId)
    {
        $receiptNumber = $this->generate_daily_receipt();


        if ($this->check_if_the_card_has_this_package($cardId, $this->msg['field_102'])) {
            //Package Exist
            $custDetails = $this->customer_account_details($this->msg['tag_id'], $this->msg['field_102']);
            $output = $this->verify_message_source($this->msg['field_58'], null);
            $operator = $output[0];
            $trnx_No = $this->msg['field_7'];


            $topupAmount = $this->msg['field_4'];
            $oldPackageType = $this->msg['field_102'];
            $newPackageType = $this->msg['field_102'];
            $newPackageRules = $this->get_package_details($newPackageType);

            $dateValidity = $newPackageRules[0]->package_validity_type;
            $tripNumber = $newPackageRules[0]->package_trip;
            $dateValidity = $this->msg['quantity'] * $dateValidity;
            $replaceDate = 0;
            if ($dateValidity == 0) {
                $dateValidity = 12;
                $replaceDate = 1;
            }

            $creditDays = $this->get_credit_days($dateValidity, true);
            $packageTripPrice = 0;
            if ($tripNumber > 0)
                $packageTripPrice = ROUND(($topupAmount / $tripNumber), 2);

            if ($this->update_customer_account_package_new($cardId, $topupAmount, $oldPackageType, $newPackageType, $creditDays, $tripNumber, $packageTripPrice, $replaceDate)) {
                if (!empty($custDetails[7]) && !empty($custDetails[6])) {
                    if ($this->record_temporary_payment_message($this->msg, 1, 1, 2, $operator, $receiptNumber, 1, 'Online', $custDetails[7], $custDetails[6], 1, $trnx_No, 0)) {
                        $this->msg['field_39'] = '00';

                        $this->update_transaction_status($operator, $receiptNumber, 0);
                        $this->update_other_system_accounts($this->accountDrId, (-1 * $this->msg['field_4']));
                        $custDetails = $this->customer_account_details($this->msg['tag_id'], $this->msg['field_102']);//get new balance data
                        $this->msg['customer'] = $custDetails[1];
                        $this->msg['card_number'] = $custDetails[3];
                        $this->msg['balance'] = $custDetails[4];
                        $this->msg['package_name'] = $custDetails[13];
                        $this->msg['package_code'] = $custDetails[9];
                        $this->msg['transaction_number'] = $receiptNumber;
                        $this->msg['date_deposit'] = date('d-m-Y H:i:s');
                    } else {
                        if ($this->messageCode == '1062') {
                            $this->msg['field_39'] = '94';
                            $this->msg['message'] = 'Duplicate Transaction ' . __LINE__;
                        } else {
                            $this->msg['field_39'] = '05';
                            $this->msg['message'] = 'Failed to credit card account ' . __LINE__;
                        }
                    }
                } else {
                    $this->msg['field_39'] = '05';
                    $this->msg['message'] = 'Details Missing/Error add new package';
                }
            } else {
                $this->msg['field_39'] = '05';
                $this->msg['message'] = 'Failed to update the balance ' . __LINE__;
            }
        } else {
            $this->msg['test3'] = 'test3';

            $custDetails = $this->get_customer_account_package_details_by_tag($this->msg['tag_id']);
            $output = $this->verify_message_source($this->msg['field_58'], null);
            $operator = $output[0]->id;
            $trnx_No = $this->msg['field_7'];


            $topupAmount = $this->msg['field_4'];


            $oldPackageType = $this->check_customer_non_company_package_code($this->msg['tag_id'], $this->msg['field_102']);
            $checkPackageBelongToCompany = $this->check_customer_package_code_if_belong_to_company($this->msg['tag_id'], $oldPackageType);

            if (empty($checkPackageBelongToCompany)) {
                $this->msg['test4'] = 'test4';
                //Add New Package Account
                $accNumber = $this->get_next_account_number('C');
                $accBalance = $this->msg['field_4'];
                $accStatus = "A";
                $package = $this->get_package_details($this->msg['field_102']);
                $packageType = $package[0]->id;//id
                $dateValidity = $package[0]->package_validity_type;
                $tripNumber = $package[0]->package_trip;

                $this->add_new_customer_account_with_package($accNumber, $cardId, $custDetails[0]->customer_id, 0, $accStatus, $operator, $packageType, $dateValidity, $tripNumber);
                $oldPackageType = $this->msg['field_102'];
            }


            $newPackageType = $this->msg['field_102'];
            $newPackageRules = $this->get_package_details($newPackageType);
            // `txt_package_code`,
            // `int_package_validity_type`,
            // `int_package_usage_type`,
            // `int_package_trip`,
            // `dec_package_discount_percent`,
            // `int_min_balance`,
            // `id`,
            // `dec_package_amount`
            $dateValidity = $newPackageRules[0]->package_validity_type;
            $tripNumber = $newPackageRules[0]->package_trip;
            $dateValidity = $this->msg['quantity'] * $dateValidity;
            $replaceDate = 0;
            if ($dateValidity == 0) {
                $dateValidity = 12;
                $replaceDate = 1;
            }

            $creditDays = $this->get_credit_days($dateValidity, true);

            $packageTripPrice = 0;
            if ($tripNumber > 0)
                $packageTripPrice = ROUND(($topupAmount / $tripNumber), 2);

            if ($this->update_customer_account_package_new($cardId, $topupAmount, $oldPackageType, $newPackageType, $creditDays, $tripNumber, $packageTripPrice, $replaceDate)) {
                $this->msg['test5'] = 'test5';
                if (!empty($custDetails[7]) && !empty($custDetails[6])) {
                    if ($this->record_temporary_payment_message($this->msg, 1, 1, 2, $operator, $receiptNumber, 1, 'Online', $custDetails[7], $custDetails[6], 1, $trnx_No, 0)) {
                        $this->msg['field_39'] = '00';

                        $this->update_transaction_status($operator, $receiptNumber, 0);
                        $this->update_other_system_accounts($this->accountDrId, (-1 * $this->msg['field_4']));
                        $custDetails = $this->customer_account_details($this->msg['tag_id'], $this->msg['field_102']);//get new balance data
                        $this->msg['customer'] = $custDetails[1];
                        $this->msg['card_number'] = $custDetails[3];
                        $this->msg['balance'] = $custDetails[4];
                        $this->msg['package_name'] = $custDetails[13];
                        $this->msg['package_code'] = $custDetails[9];
                        $this->msg['transaction_number'] = $receiptNumber;
                        $this->msg['date_deposit'] = date('d-m-Y H:i:s');
                    } else {
                        if ($this->messageCode == '1062') {
                            $this->msg['field_39'] = '94';
                            $this->msg['message'] = 'Duplicate Transaction ' . __LINE__;
                        } else {
                            $this->msg['field_39'] = '05';
                            $this->msg['message'] = 'Failed to credit card account ' . __LINE__;
                        }
                    }
                } else {
                    $this->msg['field_39'] = '05';
                    $this->msg['message'] = 'Details Missing/Error add new package';
                }
            } else {
                $this->msg['field_39'] = '05';
            }
        }
    }

    public function getAscSystemTransactionDetails($operatorID, $asc_train_id, $transaction_type,$asc_arrival_date,$asc_departure_date)
    {

        $sql = "SELECT SUM(trnx_amount) AS total_amount, SUM(fine_amount) as fine_amount, COUNT(fine_amount) as tickets
            FROM ticket_transactions
            WHERE operator_id=:operator_id AND train_id=:train_id AND extended_trnx_type=:extended_trnx_type AND CONCAT(trnx_date,' ',trnx_time) BETWEEN :asc_arrival_date and :asc_departure_date" ;
        try {

            $parameters = array(
                'operator_id' => $operatorID,
                'train_id' => $asc_train_id,
                'extended_trnx_type' => $transaction_type,
                'asc_arrival_date' => $asc_arrival_date,
                'asc_departure_date' => $asc_departure_date,
            );
            $result = $this->db_select($sql,$parameters);
            return $result;

        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
        return "";

    }


    use ApprovalTrait;
    public function update_descending_operator_collection($msg){

        $params = $this->get_app_parameters($msg['field_68'], $msg['field_69']);
        $operator = $this->verify_message_source($msg['field_58'], null);
        $deviceID = $params[0]->id;
        $operatorID = $operator[0]->id;
        $desc_train_id = $msg['desc_train_id'];
        $transaction_type = $msg['transaction_type'];
        $desc_arrival_date = $msg['desc_arrival_date'];
        $desc_departure_date = $msg['desc_departure_date'];
        $desc_system_details = $this->getAscSystemTransactionDetails($operatorID,$desc_train_id,$transaction_type,$desc_arrival_date,$desc_departure_date);

        $sql = "UPDATE `operator_collections`
                SET `desc_train_id` = :desc_train_id,
                `desc_system_amount` = :desc_system_amount,
                `desc_system_tickets` = :desc_system_ticktes,
                `desc_print_out_amount` = :desc_print_out_amount,
                `desc_print_out_tickets` = :desc_print_out_tickets,
                `desc_departure_date` = :desc_departure_date,
                `desc_arrival_date` = :desc_arrival_date,
                `desc_multa` = :desc_multa
                WHERE receipt_number = :receipt_number
                AND `operator_id` = :operator_id AND transaction_type = :transaction_type";



        $data = [
            'desc_train_id' => $desc_train_id,
            'desc_print_out_amount' => $msg['desc_print_out_amount'],
            'desc_print_out_tickets' => $msg['desc_print_out_tickets'],
            'desc_departure_date' => $msg['desc_departure_date'],
            'desc_arrival_date' => $msg['desc_arrival_date'],
            'desc_multa' => $msg['desc_multa'],
            'receipt_number' => $msg['receipt_number'],
            'operator_id' => $operatorID,
            'transaction_type' => $msg['transaction_type'],
            'desc_system_amount' => $desc_system_details[0]->total_amount == null ? 0 : $desc_system_details[0]->total_amount ,
            'desc_system_ticktes' => $desc_system_details[0]->tickets == null ? 0 : $desc_system_details[0]->tickets
        ];

        $result = $this->db_update($sql, $data);

        //Appoval Process

        $approvalProcessConfiguration = $this->getProcessFlowConfiguration(OPERATOR_COLLECTIONS_APPROVAL_PROCESS);

        $nextStepActorId = ProcessFlowActor::where('process_flow_configuration_id', $approvalProcessConfiguration->id)
            ->where('sequence', 2)
            ->value('id');

        if (empty($nextStepActorId)){
            return $this->error(null, "Can not find the next actor on approval", 404);
        }

        $operator = Operator::findOrFail($operatorID, ['full_name']);
        $approvalProcessName = $approvalProcessConfiguration->name . " for operator " . $operator->full_name;

        $approvalProcess = $this->initiateApproval(
            $approvalProcessConfiguration->id,
            $approvalProcessName,
            $this->getOperatorCollectionId($msg['receipt_number'])[0]->id,
            OperatorCollection::class,
            $operatorID,
            $nextStepActorId,
            "Initialize Approval"
        );

        $payload = [
            'asc_physical_amount' => 0,
            'desc_physical_amount' => 0,
            'any_asc_data' => false,
            'any_desc_data' => false,
        ];

        $response = $this->processApproval($approvalProcess, APPROVED, $nextStepActorId, $approvalProcess->comments, $payload, TRUE);

        if (empty($response)){
            return $this->error(null, "Something wrong, with initialize approval process, contact admin for more assistance", 500);
        }

    }

    public function check_summary_exist($receipt_number,$operatorID){

        $collection_details = DB::table('operator_collections')
            ->where('receipt_number', $receipt_number)
            ->where('operator_id', $operatorID)
            ->first();

        return $collection_details;
    }

    public function operator_collection(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $id =  $request->data['id'];
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            $msg = null;
            $this->msg = [];
            $jsonMessage = json_encode([
                'custom_message' => 'Request:',
                'data' => $request->data // Example data from the request
            ]);
            $this->logger->log($jsonMessage);
            foreach ($request->all() as $key => $value) {
                $this->msg[$key]= $value;
                $this->msg[$key]['MTI'] = "0210";

                $output = $this->verify_message_source($this->msg[$key]['field_58'], null);
                // $this->msg = $request; //Process Login Message
                // $this->msg['MTI'] = "0630";
                $params = $this->get_app_parameters($this->msg[$key]['field_68'], $this->msg[$key]['field_69']);
                $operator = $this->verify_message_source($this->msg[$key]['field_58'], null);
                $deviceID = $params[0]->id;
                $operatorID = $operator[0]->id;
                $receipt = $this->msg[$key]['receipt_number'];
                $transaction_type = $this->msg[$key]['transaction_type'];
                $asc_train_id = $this->msg[$key]['asc_train_id'];
                $asc_train_direction_id = $this->msg[$key]['asc_train_direction_id'];
                $asc_arrival_date = $this->msg[$key]['asc_arrival_date'];
                $asc_departure_date = $this->msg[$key]['asc_departure_date'];
                $asc_print_out_amount = $this->msg[$key]['asc_print_out_amount'];
                $asc_print_out_tickets = $this->msg[$key]['asc_print_out_tickets'];
                $asc_multa = $this->msg[$key]['asc_multa'];
                $asc_system_details = $this->getAscSystemTransactionDetails($operatorID,$asc_train_id,$transaction_type,$asc_arrival_date,$asc_departure_date);
                if(empty($this->check_summary_exist($receipt,$operatorID))){
                    $sql = "INSERT INTO `operator_collections` ( `operator_id`, `receipt_number`,  `transaction_type`, `asc_train_id`,
            `asc_train_direction_id`,`asc_system_amount`,`asc_system_tickets`,`asc_print_out_amount`,`asc_print_out_tickets`,asc_arrival_date,asc_departure_date,
            asc_multa)
            VALUES	(:operator_id, :receipt_number,:transaction_type,:asc_train_id,:asc_train_direction_id,:asc_system_amount,
            :asc_system_tickets, :asc_print_out_amount,:asc_print_out_tickets,:asc_arrival_date,:asc_departure_date,:asc_multa)";
                    $parameters = array(
                        'asc_train_direction_id' => $asc_train_direction_id,
                        'asc_train_id' => $asc_train_id,
                        'transaction_type' => $transaction_type,
                        'operator_id' => $operatorID,
                        'receipt_number' => $receipt,
                        'asc_system_amount' => $asc_system_details[0]->total_amount == null ? 0 : $asc_system_details[0]->total_amount,
                        'asc_system_tickets' => $asc_system_details[0]->tickets == null ? 0 : $asc_system_details[0]->tickets,
                        'asc_print_out_amount' => $asc_print_out_amount,
                        'asc_print_out_tickets' => $asc_print_out_tickets,
                        'asc_arrival_date' => $asc_arrival_date,
                        'asc_departure_date' => $asc_departure_date,
                        'asc_multa' => $asc_multa
                    );
                    $result = $this->db_insert($sql,$parameters);
                    $status = $result;
                    $this->msg['field_39'] = '00';
                }else{

                    $this->update_descending_operator_collection($this->msg[$key]);
                    $this->msg[$key]['field_39'] = '00';
                }

            }

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }

    }



    public function report_incident(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $id =  $request->data['id'];
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        $msg = null;
        $this->msg['MTI'] = "0630";
        try {
            $params = $this->get_app_parameters($this->msg['field_68'], $this->msg['field_69']);
            $operator = $this->verify_message_source($this->msg['field_58'], null);
            $deviceID = $params[0]->id;
            $operatorID = $operator[0]->id;
            if ($this->insert_incident_detail($operatorID, $deviceID, $this->msg)) {
                $this->msg['field_39'] = '00';
            } else {
                $this->msg['field_39'] = '05';
            }


            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }


    }


    public function check_if_the_card_has_this_package($card_id, $package)
    {

        $sql = "SELECT customer_account_package_types.package_code
              FROM customer_accounts INNER JOIN customer_account_package_types ON customer_accounts.customer_account_package_type=customer_account_package_types.id
              WHERE customer_accounts.id=:card_id AND customer_account_package_types.package_code=:package	";
        try {

            $parameters = array(
                'card_id' => $card_id,
                'package' => $package,
            );
            $result = $this->db_select($sql,$parameters);
            if (isset($result[0])) {
                if(!empty($result[0])){
                    return true;
                }else{
                    return false;
                }
            }

        } catch (Exception $e) {
        }
        return false;

    }

    public function check_if_this_campany_card_package_belong_to_this_company($cardId, $packageCode)
    {
        $sql = "SELECT txt_package_code FROM tbl_cards_details INNER JOIN  tbl_company_contracts ON tbl_cards_details.int_company_id=tbl_company_contracts.int_company_id
              INNER JOIN tbl_customer_account_package_type ON tbl_customer_account_package_type.id=tbl_company_contracts.int_package_allowed
              WHERE tbl_customer_account_package_type.txt_package_code=:packageCode AND tbl_cards_details.id= :cardId";
        try {
            $sql = $this->db->prepare($sql);
            $sql->bindParam(":packageCode", $packageCode);
            $sql->bindParam(":cardId", $cardId);
            $sql->execute();
            $result = $sql->fetch();
            if (isset($result[0])) {
                return true;
            } else {
                if ($this->check_if_the_package_belong_only_to_company($packageCode))
                    return false;
                else
                    return true;

            }

        } catch (Exception $e) {
        }
        return false;
    }



    public function proccessing_individual_card_topup($cardId)
    {

        $receiptNumber = $this->generate_daily_receipt();
        if ($this->check_if_the_card_has_this_package($cardId, $this->msg['field_102'])) {
            //Package Exist

            $custDetails = $this->customer_account_details($this->msg['tag_id'], $this->msg['field_102']);

            $output = $this->verify_message_source($this->msg['field_58'], null);
            $operator = $output[0]->id;
            $trnx_No = $this->msg['field_7'];

            $topupAmount = $this->msg['field_4'];
            $oldPackageType = $this->msg['field_102'];
            $newPackageType = $this->msg['field_102'];

            $newPackageRules = $this->get_package_details($newPackageType);
            $dateValidity = $newPackageRules[0]->package_validity_type;
            $tripNumber = $newPackageRules[0]->package_usage_type;
            $dateValidity = $this->msg['quantity'] * $dateValidity;
            $replaceDate = 0;
            if ($dateValidity == 0) {
                $dateValidity = 12;
                $replaceDate = 1;
            }

            $creditDays = $this->get_credit_days($dateValidity, true);
            $packageTripPrice = 0;
            if ($tripNumber > 0)
                $packageTripPrice = ROUND(($topupAmount / $tripNumber), 2);


            if ($this->update_customer_account_package_new($cardId, $topupAmount, $oldPackageType, $newPackageType, $creditDays, $tripNumber, $packageTripPrice, $replaceDate)) {
                if (!empty($custDetails[0]->account_number) && !empty($custDetails[0]->card_number)) {
                    if ($this->record_temporary_payment_message($this->msg, 1, 1, 2, $operator, $receiptNumber, 1, 'Online', $custDetails[0]->account_number, $custDetails[0]->card_number, 1, $trnx_No, 0)) {
                        $this->msg['field_39'] = '00';
                        $this->msg['message'] = 'Sucess';
                        $this->update_transaction_status($operator, $receiptNumber, 0);
                        $this->update_other_system_accounts($this->accountDrId, (-1 * $this->msg['field_4']));
                        $custDetails = $this->customer_account_details($this->msg['tag_id'], $this->msg['field_102']);//get new balance data
                        $this->msg['customer'] = $custDetails[0]->fullname;
                        $this->msg['card_number'] = $custDetails[0]->mask_card;
                        $this->msg['balance'] = $custDetails[0]->account_balance;
                        $this->msg['package_name'] = $custDetails[0]->package_name;
                        $this->msg['package_code'] = $custDetails[0]->package_code;
                        $this->msg['transaction_number'] = $receiptNumber;
                        $this->msg['date_deposit'] = date('d-m-Y H:i:s');
                    } else {
                        if ($this->messageCode == '1062') {
                            $this->msg['field_39'] = '94';
                            $this->msg['message'] = 'Duplicate Transaction ' . __LINE__;
                        } else {
                            $this->msg['field_39'] = '05';
                            $this->msg['message'] = 'Failed to credit card account ' . __LINE__;
                        }
                    }
                } else {
                    $this->msg['field_39'] = '05';
                    $this->msg['message'] = 'Details Missing/Error add new package';
                }
            } else {
                $this->msg['field_39'] = '05';
                $this->msg['message'] = 'Failed to update the balance ' . __LINE__;
            }
        } else {    //Package tried to be charge does not exit
            //check if its is allowed packages based on registered category and exception
            //Other customer should not treated as employees, or normal customer should not change category as per card registration
            //Package Does NOT Exist
            $custDetails = $this->get_customer_account_package_details_by_tag($this->msg['tag_id']);

            $output = $this->verify_message_source($this->msg['field_58'], null);
            $operator = $output[0]->id;
            $trnx_No = $this->msg['field_7'];

            $topupAmount = $this->msg['field_4'];
            $oldPackageType = $this->check_customer_non_company_package_code($this->msg['tag_id'], $this->msg['field_102']);
            if ($oldPackageType[0]->package_code == L0Z0N0) {
                //Add New Package Account
                $accNumber = $this->get_next_account_number(CUSTOMER_MODE);
                $accBalance = $this->msg['field_4'];
                $accStatus = "A";
                $package = $this->get_package_details($this->msg['field_102']);
                $packageType = $package[0]->id;//id
                $dateValidity = $package[0]->package_validity_type;
                $tripNumber = $package[0]->package_trip;

                $this->add_new_customer_account_with_package($accNumber, $cardId, $custDetails[0]->customer_id, 0, $accStatus, $operator, $packageType, $dateValidity, $tripNumber);
                $oldPackageType = $this->msg['field_102'];
            }

            $newPackageType = $this->msg['field_102'];
            $newPackageRules = $this->get_package_details($newPackageType);
            $dateValidity = $newPackageRules[0]->package_validity_type;
            $tripNumber = $newPackageRules[0]->package_usage_type;
            $dateValidity = $this->msg['quantity'] * $dateValidity;
            $replaceDate = 0;
            if ($dateValidity == 0) {
                $dateValidity = 12;
                $replaceDate = 1;
            }

            $creditDays = $this->get_credit_days($dateValidity, true);
            $packageTripPrice = 0;
            if ($tripNumber > 0)
                $packageTripPrice = ROUND(($topupAmount / $tripNumber), 2);

            if ($this->update_customer_account_package_new($cardId, $topupAmount, $oldPackageType, $newPackageType, $creditDays, $tripNumber, $packageTripPrice, $replaceDate)) {
                if (!empty($custDetails[0]->account_number) && !empty($custDetails[0]->card_number)) {
                    if ($this->record_temporary_payment_message($this->msg, 1, 1, 2, $operator, $receiptNumber, 1, 'Online', $custDetails[0]->account_number, $custDetails[0]->card_number, 1, $trnx_No, 0)) {
                        $this->msg['field_39'] = '00';
                        $this->msg['message'] = 'Sucess';
                        $this->update_transaction_status($operator, $receiptNumber, 0);
                        $this->update_other_system_accounts($this->accountDrId, (-1 * $this->msg['field_4']));
                        $custDetails = $this->customer_account_details($this->msg['tag_id'], $this->msg['field_102']);//get new balance data
                        $this->msg['customer'] = $custDetails[0]->fullname;
                        $this->msg['card_number'] = $custDetails[0]->mask_card;
                        $this->msg['balance'] = $custDetails[0]->account_balance;
                        $this->msg['package_name'] = $custDetails[0]->package_name;
                        $this->msg['package_code'] = $custDetails[0]->package_code;
                        $this->msg['transaction_number'] = $receiptNumber;
                        $this->msg['date_deposit'] = date('d-m-Y H:i:s');
                    } else {
                        if ($this->messageCode == '1062') {
                            $this->msg['field_39'] = '94';
                            $this->msg['message'] = 'Duplicate Transaction ' . __LINE__;
                        } else {
                            $this->msg['field_39'] = '05';
                            $this->msg['message'] = 'Failed to credit card account ' . __LINE__;
                        }
                    }
                } else {
                    $this->msg['field_39'] = '05';
                    $this->msg['message'] = 'Details Missing/Error add new package';
                }
            } else {
                $this->msg['field_39'] = '05';
                $this->msg['message'] = 'Failed to update the balance .. ' . __LINE__;
            }
        }
    }


    public function add_new_customer_account_with_package($accNumber, $cardId, $cusomertId, $accBalance, $accStatus, $linker, $packageType, $dateValidity, $tripNumber)
    {

        $sql = "INSERT INTO `customer_accounts`(
				`account_number`,
				`card_id`,
				`customer_id`,
				`account_balance`,
				`status`,
				`linker`,
				`customer_account_package_type`,
				`account_validity`,
				`trips_number_balance`
			)
			VALUES
			(
				:accNumber,
				:cardId,
				:cusomertId,
				:accBalance,
				:accStatus,
				:linker,
				:packageType,
				:dateValidity,
				:tripNumber
			)";

        $date = date("Y-m-d");
        $date = str_replace('-', '/', $date);
        $dateValidity = date("Y-m-d", strtotime($date . "+" . $dateValidity . " days"));

        $parameters = array(
            'accNumber' => $accNumber,
            'cardId' => $cardId,
            'cusomertId' => $cusomertId,
            'accBalance' => $accBalance,
            'accStatus' => $accStatus,
            'linker' => $linker,
            'packageType' => $packageType,
            'dateValidity' => $dateValidity,
            'tripNumber' => $tripNumber
        );

        $status = $this->db_insert($sql, $parameters);
        return $status;
    }



    public function check_customer_non_company_package_code($tagID, $packageCode)
    {
        $sql = "SELECT
		`P`.`package_code`
		FROM `customer_accounts` AS `A`
		INNER JOIN `card_customers` AS `C` ON `A`.`customer_id`=`C`.`id`
		INNER JOIN `cards` AS `D` ON `D`.`id`=`A`.`card_id`
		INNER JOIN `customer_account_package_types` as `P` ON `A`.`customer_account_package_type`=`P`.`id`
		WHERE A.status IN ('A' ,'I') AND `tag_id`=:tag AND `P`.`package_code`=:packageCode  ORDER BY `P`.id DESC LIMIT 1";
        try {

            $data = [ 'tag' => $tagID,'packageCode' => $packageCode];
            $result = $this->db_select($sql,$data);


            return $result;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
        }
        return '';
    }


    public function get_customer_account_package_details_by_tag($tagID)
    {
        $sql = "SELECT
		`D`.`id` AS `card_id`,
		`C`.`id` AS `customer_id`,
		`card_number` ,
		`account_number`,
		`account_balance`,
		 IF(`full_name` IS NULL OR `full_name`='',CONCAT_WS(' ', `first_name`,`last_name`),`full_name`) AS `fullname`,
		`phone`,
		CONCAT_WS('',LEFT(`card_number`,4),'*******',RIGHT(`card_number`,5)) AS `mask_card`,
		A.`status`,
		`P`.`package_code`,
		`P`.`package_validity_type`,
		`P`.`package_trip`,
		`P`.`id` AS `account_packege_type`,
		`P`.`package_name`,
		`P`.`package_validity_type`,
		`P`.`package_discount_percent`,
		`A`.`id`,
		`D`.`card_Type`,
		`C`.`special_group_id`,
		`A`.`max_trip_per_day`,
		`A`.`max_trip_per_month`
		FROM `customer_accounts` AS `A`
		INNER JOIN `card_customers` AS `C` ON `A`.`customer_id`=`C`.`id`
		INNER JOIN `cards` AS `D` ON `D`.`id`=`A`.`card_id`
		INNER JOIN `customer_account_package_types` AS `P` ON `A`.`customer_account_package_type`=`P`.`id`
		WHERE A.status='A'  AND `tag_id`=:tag 	LIMIT 1";
        try {
            $data = [ 'tag' => $tagID];
            $result = $this->db_select($sql,$data);



            return $result;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();;
        }

    }


    public function check_card_balance($msg, $tag)
    {
        $this->log_tracer(__METHOD__, 'LINE#_' . __LINE__);
        $status = false;
        $sql = "SELECT IFNULL(SUM(`account_balance` -`min_acc_balance`),0) AS `balance`
		FROM `tbl_customer_account`
		WHERE `card_id`=:tag
		AND customer_account_package_type=" . $this->DEFAULT_PACKAGE_ID;
        try {
            $result = $this->db->prepare($sql);
            $result->bindParam(':tag', $tag);
            $result->execute();
            $result = $result->fetchAll();
            $output = array();
            foreach ($result as $row) {
                $output[] = "$row[0]";
            }

            if (!isset($msg['penalty'])) {
                if ($msg['field_4'] <= $output[0]) {
                    $this->log_tracer(__METHOD__, $output[0] . ' _L#_' . __LINE__);
                    $status = true;
                }
            } else {
                if (($msg['field_4'] + $msg['penalty']) <= $output[0]) {
                    $status = true;
                    $this->log_tracer(__METHOD__, $output[0] . ' _L#_' . __LINE__);
                }
            }

        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();;
        }
        return $status;
    }



    public function check_card_package_balancex($msg, $cardId, $packageCodeTypeId)
    {

        $status = false;
        $sql = "SELECT SUM(IF(`account_balance`IS NULL,0.00,`account_balance`) - IF(`min_acc_balance`IS NULL,0.00,`min_acc_balance`) ) AS `balance`
		FROM `tbl_customer_account`
		WHERE `card_id`=:cardId
		AND customer_account_package_type=:packageType";
        try {
            $result = $this->db->prepare($sql);
            $result->bindParam(':tag', $cardId);
            $result->bindParam(':packageType', $packageCodeTypeId);
            $result->execute();
            $result = $result->fetch();
            if (!empty($result)) {
                return $result[0];
            }
            return 0.00;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();;
        }
        return $status;
    }


    public function get_customer_account_package_details_employees($tagID)
    {
        $this->log_tracer(__METHOD__, __LINE__ . ' ' . $tagID);
        $sql = "SELECT
		`D`.`id` AS `card_id`,
		 IF(`txt_full_name` IS NULL OR `txt_full_name`='',CONCAT_WS(' ', `first_name`,`last_name`),`txt_full_name`) AS `fullname`,
		`phone`,
		CONCAT_WS('',LEFT(`card_number`,4),'*******',RIGHT(`card_number`,5)) AS `mask_card`,
		`account_balance`,
		A.`status`,
		`card_number` ,
		`account_number`,
		`C`.`id` AS `customer_id`,
		`P`.`package_code`,
		`P`.`package_validity_type`,
		`P`.`package_trip`,
		`P`.`id` AS `account_packege_type`,
		`P`.`package_name`,
		`P`.`package_validity_type`,
		`P`.`package_discount_percent`,
		`A`.`id`,
		IFNULL(`C`.`emp_ID`,'0') AS `emp_ID`,
		IFNULL(`ED`.`dept_code`,'') AS `department`,
		`D`.`card_Type`,
		`C`.`special_group_id`,
		`A`.`max_trip_per_day`,
		`A`.`max_trip_per_month`,
		`P`.`trips_lpd`,
		DATEDIFF(`A`.`account_validity`,CURDATE())

		FROM `customer_accounts` AS `A`
		INNER JOIN `card_customers` AS `C` ON `A`.`customer_id`=`C`.`id`
		INNER JOIN `cards` AS `D` ON `D`.`id`=`A`.`card_id`
		INNER JOIN `customer_account_package_types` AS `P` ON `A`.`customer_account_package_type`=`P`.`id`
		LEFT JOIN  `employee_departments` AS `ED` ON `C`.`employee_department_id`=`ED`.`id`
		WHERE A.status='A'  AND `tag_id`=:tag AND account_usage_type IN ('E','X') AND `A`.`customer_account_package_type`<>:defaultPackage   LIMIT 1";//AND char_account_usage IN ('E','X')
        try {

            // $result = $this->db->prepare($sql);
            // $result->bindParam(":tag", $tagID);
            // $result->bindParam(":defaultPackage", $this->DEFAULT_PACKAGE_ID);
            // $result->execute();
            // $result = $result->fetchAll();
            $data = ['tag'=>$tagID,'defaultPackage'=>$this->DEFAULT_PACKAGE_ID];
            $result = $this->db_select($sql,$data);
            // $output[] = array();
            // if (!empty($result)) {
            //     foreach ($result as $row) {
            //         //$this->log_event("default1",$row[9]);
            //         $output = $row;
            //         break;
            //     }
            //     if (empty($output[0])) {
            //         return false;
            //     }
            // } else {
            //     return false;
            // }
            return $result;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();;
        }
        return '';
    }



    public function check_card_package_balance($accountId)
    {
        $this->log_tracer(__METHOD__, 'LINE#_' . __LINE__);
        //$this->log_tracer(__METHOD__,'accountId:'.$accountId.' _L#_'.__LINE__);
        $status = false;
        $sql = "SELECT SUM(IF(`account_balance`IS NULL,0.00,`account_balance`) - IF(`min_acc_balance`IS NULL,0.00,`min_acc_balance`) ) AS `balance`
		FROM `tbl_customer_account`
		WHERE `id`=:accountId ";
        try {
            $result = $this->db->prepare($sql);
            $result->bindParam(':accountId', $accountId);
            //$result->bindParam(':packageType',$packageCodeTypeId);
            $result->execute();
            $result = $result->fetch();
            if (!empty($result)) {
                return $result[0];
            }
            return 0.00;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();;
        }
        return $status;
    }


    public function update_other_system_accounts($accountId, $topupAmount)
    {
        $status = 0;


        $sql = "UPDATE `customer_accounts` SET `account_balance` = (`account_balance` + :topupAmount),last_update= CURRENT_TIMESTAMP WHERE id=:accountId";
        try {
            $data = [ 'topupAmount' => $topupAmount,'accountId' => $accountId];
            $result = $this->db_select($sql,$data);
            if (!empty($result)) {
                $status = 1;
            } else {
                $status = 0;
            }
        } catch (Exception $e) {
            $status = 0;
        }

        return $status;
    }

    public function update_transaction_status($operator, $transactiontReceipt, $trxStatus)
    {

        $sql = "UPDATE `ticket_transactions`
		SET `trnx_status`=:transactionStatus
		WHERE `trnx_receipt`=:transactionReceipt
		AND `operator_id`=:operatorID";
        //$parameters=array('transactionStatus'=>$status,'transactionReceipt'=>$transactiontReceipt,'operatorID'=>$operator);
        $status = true;
        try {
            // $result = $this->db->prepare($sql);
            // $result->bindParam(":transactionStatus", $trxStatus);
            // $result->bindParam(":transactionReceipt", $transactiontReceipt);
            // $result->bindParam(":operatorID", $operator);

            $data = [ 'transactionStatus' => $trxStatus,'transactionReceipt' => $transactiontReceipt,
                'operatorID' => $operator];
            $result = $this->db_select($sql,$data);
            if (!empty($result)) {
                $status = 1;
            } else {
                $status = null;
            }

        } catch (Exception $e) {
            return false;
        }
        return $status;
    }

    public function update_transaction_status_with_account($operator, $transactiontReceipt, $trxStatus, $accountID, $typeCrDr)
    {

        $sql = "UPDATE `ticket_transactions`
		SET `trnx_status`=:transactionStatus,
		`int_credit_account_id`= IF(:typeCrDr='CR',:accountID,`int_credit_account_id`) ,
		`int_debit_account_id`= IF(:typeCrDr='DR',:accountID,`int_debit_account_id`)
		WHERE `trnx_receipt`=:transactionReceipt
		AND `operator_id`=:operatorID";
        //$parameters=array('transactionStatus'=>$status,'transactionReceipt'=>$transactiontReceipt,'operatorID'=>$operator);
        $status = true;
        try {


            $data = [ 'transactionStatus' => $trxStatus,'transactionReceipt' => $transactiontReceipt,
                'operatorID' => $operator , 'typeCrDr' => $typeCrDr, 'accountID' => $accountID ];
            $result = $this->db_select($sql,$data);


            if (!empty($result)) {
                $status = 1;
            } else {
                $status = null;
            }

        } catch (Exception $e) {
            return false;
        }
        return $status;
    }

    public function update_customer_account_package_new($cardId, $topupAmount, $oldPackageType, $newPackageType, $creditDays, $tripNumber, $packageTripPrice, $replaceDate)
    {
        $status = false;
        // -- `reset_employee_balance` = IF(:replaceDate = 1, NULL, DAY(IF(`account_validity` IS NULL OR `account_validity`
        // -- < CURDATE(), DATE_ADD(CURDATE(), INTERVAL +:creditDays DAY), IF(:replaceDate = 1, DATE_ADD(CURDATE(), INTERVAL +:creditDays DAY), DATE_ADD(`account_validity`, INTERVAL +:creditDays DAY)))))
        // -- `customer_account_package_type` = :newPackageType,
        // -- `account_validity` = IF(`account_validity` IS NULL OR `account_validity` < CURDATE(), DATE_ADD(CURDATE(), INTERVAL +:creditDays DAY), IF(:replaceDate = 1, DATE_ADD(CURDATE(), INTERVAL +:creditDays DAY), DATE_ADD(`account_validity`, INTERVAL +:creditDays DAY))),
        // `trips_number_balance` = IF(:packageTripPrice > 0, (:tripNumber + ROUND((`account_balance` + :topupAmount) / :packageTripPrice)), `trips_number_balance`),

        $package = $this->get_package_details($newPackageType);
        $sql = "UPDATE `customer_accounts`
                SET `account_balance` = (`account_balance` + :topupAmount),
                `max_trip_per_day` = :max_trips_lpd,
                `max_trip_per_month` = :max_trips_lpm

                WHERE card_id = :cardId
                AND `customer_account_package_type` = :oldPackageType";

        try {
            $details = $this->get_package_details($oldPackageType);

            $data = [
                'cardId' => $cardId,
                'topupAmount' => $topupAmount,
                'oldPackageType' => $details[0]->id,
                'max_trips_lpd' => $package[0]->trips_lpd,
                'max_trips_lpm' => $package[0]->trips_lpm
            ];

            $result = $this->db_update($sql, $data);

            if ($result) {
                $status = true;
            } else {
                $status = false;
            }
        } catch (Exception $e) {
            $status = false;
            $this->msg['error_message'] =  $e;
        }

        return $status;
    }





    public function check_if_the_package_belong_only_to_company($packageCode)
    {
        $sql = "SELECT package_code FROM customer_account_package_types INNER JOIN company_contracts ON customer_account_package_types.id=company_contracts.package_allowed WHERE customer_account_package_types.package_code=:packageCode ";
        try {

            $data = [ 'packageCode' => $packageCode];
            $result = $this->db_select($sql,$data);
            if (isset($result[0])) {
                return true;
            } else {
                return false;

            }

        } catch (Exception $e) {
            Log::channel('pos')->error(json_encode($this->errorPayload($e)));
            return false;
        }
        return false;
    }

    private function get_credit_days($offset, $isInMonth)
    {
        $date1 = date("Y-m-d");
        $diff = 0;
        if ($isInMonth == true) {

            $diff = abs(strtotime($this->get_credit_end_date_by_month($offset)) - strtotime($date1));
        } else {
            $diff = abs(strtotime($this->get_credit_end_date_by_day($offset)) - strtotime($date1));
        }

        $days = floor($diff / (60 * 60 * 24));
        return $days;
    }

    private function get_credit_end_date_by_month($monthOffset)
    {

        $date = date("Y-m-d");
        return date('Y-m-d', strtotime("+$monthOffset months", strtotime($date)));
    }

    private function get_credit_end_date_by_day($dayOffset)
    {

        $date = date("Y-m-d");
        return date('Y-m-d', strtotime("+$dayOffset days", strtotime($date)));
    }

    public function get_package_details($packageCode)
    {
        $status = false;
        $sql = "SELECT
        id,
		`package_code`,
		`package_validity_type`,
		`package_usage_type`,
		`package_trip`,
		`package_discount_percent`,
		`min_balance`,
		`id`,
		`package_amount`,
		`trips_lpd`,
		`trips_lpm`
		FROM `customer_account_package_types`
		WHERE `package_code`=:packageCode";
        try {

            $parameters = array(
                'packageCode' => $packageCode,
            );

            $result = $this->db_select($sql,$parameters);
            return $result;

        } catch (Exception $e) {
        }

    }

    public function insert_incident_detail($operatorID, $deviceID, $msg)
    {
        $sql = 'INSERT INTO `general_incidents`
          (`operator_id`, `title`, `description`, `device_imei`, `incident_category_id`)
          VALUES
          (:operator, :title,:details,:device,:report_type)';

        $parameters = array(
            'operator' => $operatorID,
            'title' => $msg['brief_description'],
            'details' => $msg['problem_reported'],
            'device' => $deviceID,
            'report_type' => $msg['report_type'],
        );

        $result = $this->db_insert($sql,$parameters);

        return true;
    }


    public function changepassword(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $id =  $request->data['id'];
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        $msg = null;
        $this->msg = $request->data; //Process Login Message
        $this->msg['MTI'] = "0630";
        try {
            $old = $this->msg['old_password'];
            $new = $this->msg['new_password'];
            $agent_username = $this->msg['field_58'];
            $output = $this->verify_message_source($this->msg['field_58'], null);
            //load operator details
            $sql = 'SELECT `password`, `operator_ID`
                    FROM `operators`
                    WHERE `username`=:username';
            $parameters = array(
                'username' => $agent_username,
            );

            $result = $this->db_select($sql,$parameters);


            if ($result[0]->password == $old) {
                if ($this->update_operator_credential($output[0]->id, $new)) {
                    $this->msg['field_39'] = '00';
                } else {
                    $this->msg['field_39'] = '96';
                }
            } else {
                $this->msg['field_39'] = '80'; //old password not match
            }


            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);



        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }

    public function check_customer_existance($msg)
    {
        $sql = "";
        try {
            $result = null;
            $sql = "SELECT `id`,CONCAT_WS(' ',`first_name`,`last_name`) AS `name`,`identification_number`,`phone`,`image`, IF(gender_id=1,'Masculino','Masculina') AS `gender`
				 FROM `card_customers`
					WHERE `identification_number`=:idnum OR `phone`=:phone limit 1";

            $parameters = array(
                'idnum' => $msg['id_number'],
                'phone' => $msg['phone_number'],
            );

            $result = $this->db_select($sql,$parameters);

        } catch (Exception $e) {

        }
        return $result;
    }


    public function check_card_existance($msg, $status)
    {
        $cardInfo = false;
        $sql = "SELECT
		 cd.`id`,
		`status`,
		`card_number`,
        cd.`card_type`,
        cd.`expire_date`,
		 IF(DATEDIFF(`expire_date`,CURDATE())>0,'VALID','EXPIRED') AS `validity`,
		 CONCAT_WS('',LEFT(`card_number`,6),'*****',RIGHT(`card_number`,4)) AS `mask_card` ,
		 `card_ownership`,
		 IFNULL(`cd`.`credit_type`,0)
		FROM `cards` AS cd
		LEFT JOIN `company_contracts` AS cc ON  cd.`id`= cc.`id`
		WHERE `tag_id`=:tag AND status =:status ";
        try {

            $parameters = array(
                'tag' => $msg['tag_id'],
                'status' => $status,
            );

            $result = $this->db_select($sql,$parameters);



        } catch (Exception $e) {
            $result = "";
        }
        return $result;
    }

    public function activate_card($msg)
    {
        if($msg['category']==6){
            $cardType = 1;
        }else{
            $cardType = 2;
        }
        $sql = "UPDATE `cards` SET `status`='A', `card_Type`=:card_type WHERE `tag_id`=:tag";
        try {


            $parameters = array(
                'tag' => $msg['tag_id'],
                'card_type' => $cardType,
            );

            $result = $this->db_select($sql,$parameters);
            $status = true;
        } catch (Exception $e) {
            $status = false;
        }
    }


    public function getOcupationId($msg)
    {
        $cardInfo = '0';
        $sql = "SELECT
     `id`
    FROM `special_groups`
    WHERE `title`=:title ";
        try {

            $parameters = array(
                'title' => $msg['occupation'],
            );

            $result = $this->db_select($sql,$parameters);

            $cardInfo = $result[0]->id;

        } catch (Exception $e) {
            $cardInfo = '0';
        }
        return $cardInfo;
    }

    public function imageConvert($img, $imageType)
    {
        if (isset($img) && !empty($img)) {
            $t = $imageType . '_' . time() . ".png";
            file_put_contents("../" . $t, base64_decode($img));
            return $t;
        } else {
            return '';
        }
    }


    public function get_customer_category($specialCategoryName)
    {

        $sql = "SELECT `main_category` FROM `special_groups` where id=:specialCategoryName";
        $parameters = array(
            'specialCategoryName' => $specialCategoryName,
        );
        $result = $this->db_select($sql,$parameters);

        return $result[0]->main_category;
    }

    use CustomerTrait;
    public function insertCustomer($msg,$oid, $defaultPin)
    {
        $dateReg = date('Ymdhis');
        $dob = date("Y-m-d", strtotime($msg['dob']));
        $occupation = $this->getOcupationId($msg);
        $imgnm = $this->imageConvert($msg['customer_image'], 'ci_' . $oid);
        $imgid = $this->imageConvert($msg['id_image'], 'ii_' . $oid);
        $statusId = false;
        $sql = "INSERT INTO `card_customers`
				(
					`full_name`,`first_name`, `middle_name`, `last_name`,
					`identification_number`,`image`,
					`id_image`,`gender_id`,
					`phone`,
					`address`, `birthdate`,
					 `registration_datetime`,
					`special_group_id`,
					`identification_type`,`occupation_id`, `app_pin`
				)
				VALUES
				(
					:fullname,:fname,:mname,:lname,
					:idnum,:imagenm,:imageid,
					:gender,:phone,
					:loca,
					:dob,:datereg	,
					:specialCategory,
					:idType,:occupation
					:defaultPin
				)";
        $parameters = array(
            'fullname' => $msg['f_name'] . ' ' . $msg['m_name'] . ' ' . $msg['l_name'],
            'fname' => $msg['f_name'],
            'mname' => $msg['m_name'],
            'lname' => $msg['l_name'],
            'idnum' => $msg['id_number'],
            'imagenm' => $imgnm,
            'imageid' => $imgid,
            'phone' => $msg['phone_number'],
            'gender' => $msg['gender'],
            'loca' => $msg['address'],
            'dob' => $dob,
            'datereg' => $dateReg,
            'specialCategory' => $msg['category'],
            'idType' => $msg['id_type'],
            'occupation' => $occupation,
            "app_pin" => $defaultPin

        );

        $result = $this->db_insert($sql,$parameters);

        $statusId = $result;


        return $statusId;
    }


    public function linkCardToCustomer($customerId,$cardId){

        $sql="INSERT INTO `customer_cards` (customer_id, card_id)   VALUES  (:customerId,:cardId)";
        try
        {

            $parameters = array(
                'customerId' => $customerId,
                'cardId' => $cardId );

            $result = $this->db_insert($sql,$parameters);

            return $result;
        }
        catch(Exception $e)
        {
            Log::error('Failed to link card to customer: ' . $e->getMessage());
            return false;

        }
        return false;
    }

    public function get_next_account_number($accountUsage)
    {
        $status = false;
        $sql = "SELECT (MAX(CAST(IFNULL(`account_number`,0) AS UNSIGNED))+1) AS `new_account` FROM `customer_accounts` WHERE `account_number`<>:CFMGL";
        try {
            $parameters = array(
                'CFMGL' => CFM_MAIN_GL,
            );
            $result = $this->db_select($sql, $parameters);



            if (!empty($result)) {
                if ($result[0]->new_account != 0) {
                    return $result[0]->new_account;
                } else {
                    if ($accountUsage === CUSTOMER_MODE) {
                        return CUSTOMER_MODE_RETURN_CODE;
                    } else if ($accountUsage === EMPLOYEES_MODE) {
                        return EMPLOYEES_MODE_RETURN_CODE;
                    } else if ($accountUsage === OPERATOR_MODE) {
                        return OPERATOR_MODE_RETURN_CODE;
                    } else if ($accountUsage === INTERNAL_MODE) {
                        return INTERNAL_MODE_RETURN_CODE;
                    }
                }
            } else {
                if ($accountUsage === CUSTOMER_MODE) {
                    return CUSTOMER_MODE_RETURN_CODE;
                } else if ($accountUsage === EMPLOYEES_MODE) {
                    return EMPLOYEES_MODE_RETURN_CODE;
                } else if ($accountUsage === OPERATOR_MODE) {
                    return OPERATOR_MODE_RETURN_CODE;
                } else if ($accountUsage === INTERNAL_MODE) {
                    return INTERNAL_MODE_RETURN_CODE;
                }
            }
            return DEFAULT_MODE_RETURN_CODE;
        } catch (\Exception $e) {
            Log::error('Failed to get next account number: ' . $e->getMessage());
            return false;
        }
        return $status;
    }


    public function set_customer_account($customerId, $tag, $operatorId, $accountType)
    {

        $accountUsage = CUSTOMER_MODE;
        if ($accountType == 8) {
            $acc = $this->get_next_account_number(CUSTOMER_MODE);
            if ($acc == 0) {
                $acc = time() . date('d');
            }

            $sql = "INSERT INTO `customer_accounts` ( `account_number`, `card_id`, `customer_id`,  `status`, `linker`,`accounts_usage_type`,`customer_account_package_type`	)
          VALUES	(:acc, :tag,:cust,'A',:opID,:accountUsage,'19')";
            $parameters = array(
                'acc' => $acc,
                'tag' => $tag,
                'cust' => $customerId,
                'opID' => $operatorId,
                'accountUsage' => $accountUsage,
            );
            $result = $this->db_insert($sql, $parameters);
            $status = $result;
            if (empty($status)) {
                $status = false;
            }
        } else {
            if ($accountType == 6) {
                $acc = $this->get_next_account_number(EMPLOYEES_MODE);
            } else if ($accountType == OPERATOR_MODE) {
                $acc = $this->get_next_account_number(OPERATOR_MODE);
            } else {
                $acc = $this->get_next_account_number(CUSTOMER_MODE);
            }

            if ($acc == 0) {
                $acc = time() . date('d');
            }

            $sql = "INSERT INTO `customer_accounts` ( `account_number`, `card_id`, `customer_id`,  `status`, `linker`,`accounts_usage_type`	)
                    VALUES	(:acc, :tag,:cust,'A',:opID,:accountUsage)";
            $parameters = array(
                'acc' => $acc,
                'tag' => $tag,
                'cust' => $customerId,
                'opID' => $operatorId,
                'accountUsage' => $accountUsage,
            );
            $result = $this->db_insert($sql, $parameters);
            $status = $result;
            if (empty($status)) {
                $status = false;
            }
        }

        return $status;
    }


    public function customer_account_details($tagId, $accountPackageType)
    {

        $sql = "SELECT
			`D`.`id`,
			IF (`full_name` IS NULL OR `full_name`='',CONCAT_WS(' ', `full_name`,`last_name`),`full_name`) AS `fullname`,
			`phone`,
			CONCAT_WS('',SUBSTRING(card_number,1,4),'*******',SUBSTRING(card_number,12,5)) AS `mask_card`,
			`account_balance`,
			`A`.`status`,
			`card_number`,
			`account_number`,
			`C`.`id` AS `customer_id`,
			`P`.`package_code`,
			`P`.`package_validity_type`,
			`P`.`package_trip`,
			`P`.`id` AS `account_package_type`,
			`P`.`package_name`,
			`P`.`package_validity_type`,
			IF(`P`.`package_discount_percent` IS NULL, '0.00',`P`.`package_discount_percent`) AS `package_discount_percent`,
			`A`.`account_validity`,
			IF(DATEDIFF(`A`.`account_validity`,CURDATE())>0,'VALID','EXPIRED') AS `package_validity` ,
			DATEDIFF(`A`.`account_validity`,CURDATE()) AS `daysBalance`,
			`C`.`identification_number`,
			`P`.`package_amount`,
			`C`.`special_group_id`
		FROM `customer_accounts` as `A`
		INNER JOIN `card_customers` AS C ON `A`.`customer_id`=`C`.`id`
		LEFT JOIN `cards` as D ON `D`.`id`=`A`.`card_id`
		LEFT JOIN `customer_account_package_types` as `P` ON `A`.`customer_account_package_type`=`P`.`id`
		WHERE A.status='A'  AND D.tag_id=:tag AND P.package_code=:packageCode
		LIMIT 1"; //`A`.`customer_account_package_type`=:accountPackageType
        try {
            if (!isset($accountPackageType)) {
                $accountPackageType = L0Z0N0;
            }


            $parameters = array(
                'packageCode' => $accountPackageType,
                'tag' => $tagId,
            );
            $result = $this->db_select($sql,$parameters);

            return $result;

        } catch (Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
        }
        return [];
    }


    public function customerRegistration($msg){
        //check customer existence
        if(isset($msg['category'],$msg['id_number'],$msg['id_type'],$msg['f_name'],$msg['l_name'])){
            $customer = $this->check_customer_existance($msg);
            if (empty($customer)) {
                $cardDetails = $this->check_card_existance($msg, "A");


                if(!empty($cardDetails[0])){
                    if ($cardDetails[0]->status == "I") {
                        $this->activate_card($msg);
                        $operatorDetails = $this->verify_message_source($msg['field_58'], null);
                        if ($operatorDetails !== null) {
                            $defaultPin = $this->generateDefaultPin($operatorDetails);
                            $hashedPin = Hash::make($defaultPin);
                            $customerId = $this->insertCustomer($msg, $operatorDetails[0]->id, $hashedPin);
                            if (!empty($customerId)) {
                                $this->linkCardToCustomer($customerId, $cardDetails[0]->id);
                                if ($this->set_customer_account($customerId, $cardDetails[0]->id, $operatorDetails[0]->id, $msg['category']) !== false) {
                                    $msg['field_39'] = '00';
                                    $msg['message'] = 'Account created with default package';
                                    $custDetails = $this->customer_account_details($msg['tag_id'], "L0Z0N0");
                                    $msg['customer'] = $custDetails[0]->fullname;
                                    $msg['card_number'] = $custDetails[0]->mask_card;
                                    $msg['balance'] = $custDetails[0]->account_balance;
                                    $msg['discount_rate'] = ($custDetails[0]->package_discount_percent * 100) . '%';
                                    $msg['package_name'] = $custDetails[0]->package_name;

                                    if ($msg['phone_number']){
                                        //Send Sms With Credentials
                                        $message = "Caro Cliente, Agora pode aceder à aplicação móvel XITIMELA usando o seu número de cartão e o PIN padrão: $defaultPin. Obrigado,";
                                        $payload = [
                                            'message' => $message,
                                            'phone_number' => $msg['phone_number']
                                        ];
                                        event(new SendSms(SEND_CUSTOMER_DEFAULT_PIN, $payload));
                                    }

                                } else {
                                    $this->delete_customer_details($customerId);
                                    $msg['field_39'] = '05';
                                    $msg['message'] = 'Failed to create register cardholder due to account problem';
                                }
                            } else {
                                $msg['field_39'] = '96';
                                $msg['message'] = 'Failed to create register cardholder details';
                            }
                        } else {
                            $msg['field_39'] = '96';
                            $msg['message'] = 'Failed to create register cardholder operator or device problems';
                        }
                    } else {
                        $msg['field_39'] = '15'; //card in use already exist
                        $msg['message'] = 'Card in used already';
                    }
                } else {
                    $msg['field_39'] = '14'; //card in use already exist
                    $msg['message'] = 'Card does not exist';
                }
            } else {
                $msg['field_39'] = '15'; //customer exist
                $msg['message'] = 'customer already exist..';
            }
        }
        else{
            $msg['field_39'] = '13';
            $msg['message'] = 'Some key fields are missing ';
        }


        return $msg;
    }

    public function delete_customer_details($customerId)
    {
        $sql = "DELETE `tbl_customer_account`WHERE `id`=:customerId";
        $parameters = array(
            'customerId' => $customerId,
        );
        $result = $this->db_select($sql,$parameters);

    }
    public function customer_registration(Request $request){
        try {
            $msg = null;
            $this->msg = $request; //Process Login Message
            $this->msg['MTI'] = "0630";

            $this->msg = $this->customerRegistration($this->msg);

            return response()->json($this->msg);


        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            return response()->json($th->getMessage());

        }
    }

    public function update_operator_credential($operID, $new)
    {
        $sql = "UPDATE `operators` SET `password`=:new WHERE `id`=:operID";
        $parameters = array(
            'operID' => $operID,
            'new' => $new,
        );
        $result = $this->db_select($sql,$parameters);
        if(!empty($result)){
            return  true;
        }else{
            return true;
        }

    }

    public function getCustomersCardAccountsInformations()
    {
        $sql = "SELECT
		`D`.`id` AS `card_id`,
		 IF(`full_name` IS NULL OR `full_name`='',CONCAT_WS(' ', `first_name`,`last_name`),`full_name`) AS `fullname`,
		CONCAT_WS('',LEFT(`card_number`,4),'*******',RIGHT(`card_number`,5)) AS `mask_card`,
		`account_balance`,
		`card_number`,
		`account_number`,
		`C`.`id` AS `customer_id`,
		`P`.`package_code`,
		`P`.`package_validity_type`,
		`P`.`package_trip`,
		`P`.`id` AS `account_packege_type`,
		`P`.`package_name`,
		`P`.`package_discount_percent`,
		`A`.`id` ,
		`A`.`customer_account_package_type`,
		`A`.`accounts_usage_type`,
		`D`.`card_Type`,
		`D`.`card_ownership`,
		`D`.`credit_type`,
		`D`.`company_id`,
		`C`.`special_category_id`,
		(`A`.`max_trip_per_day`) AS `max_trip_per_day`,
		`A`.`max_trip_per_month`,
        tag_id,
        A.`status`,
        A.last_update,
        `P`.`cfm_class_id`,
        `P`.`line_id`,
        `P`.`zone_id`,
        `P`.`category_id`
		FROM `customer_accounts` AS `A`
		INNER JOIN `card_customers` AS `C` ON `A`.`id`=`C`.`id`
		INNER JOIN `cards` AS `D` ON `D`.`id`=`A`.`id`
		INNER JOIN `customer_account_package_types` AS `P` ON `A`.`customer_account_package_type`=`P`.`id`
		WHERE A.status='A' AND accounts_usage_type IN ('C', 'E', 'X');";

        $data = [ ];
        $result = $this->db_select($sql,$data);
        $this->msg['card_customer_accounts'] = $result;

        return $this->msg['card_customer_accounts'];
    }

    public function verify_message_source($operator, $num)
    {

        $sql = 'SELECT `id`, `full_name`,`operator_category_id`,`operator_Type_code`,`status`
		      FROM `operators`
		      WHERE `username`=:operator_username';


        $data = ['operator_username' => $operator ];
        $result = $this->db_select($sql,$data);


        return $result;
    }

    public function check_transaction_existance($operator, $field_7)
    {

        $sql = 'SELECT `id`
		      FROM `ticket_transactions`
		      WHERE `operator_id`=:operator_username AND signature=:field_7';


        $data = ['operator_username' => $operator,'field_7' => $field_7 ];
        $result = $this->db_select($sql,$data);

        if(!empty($result)){
            $sql = 'UPDATE  `ticket_transactions`
            SET `trnx_status`="99" WHERE signature=:field_7 AND operator_id=:operator_username';

            $data = ['operator_username' => $operator,'field_7' => $field_7 ];
            $result = $this->db_update($sql,$data);
        }
        return $result;
    }

    public function generate_otp($id,$imei)
    {
        $dateTime = now();
        $otp = date('His', $dateTime->timestamp) .  str_pad($id, 2, '0', STR_PAD_LEFT);

        // Insert current timestamp into the table
        DB::update('update otps set status = "I" where operator = ?', [$id]);
        DB::table('otps')->insert([
            'otpcode' => $otp,'operator'=>$id,'device'=>$imei,'status'=>'A'
        ]);

        // Retrieve the last insert ID
        $lastInsertId = DB::getPdo()->lastInsertId();

        // Generate receipt number

        return $otp;
    }

    public function generate_daily_receipt()
    {
        $dateTime = now();


        DB::table('daily_receipt_counts')->insert([
            'time_recorded' => $dateTime,
        ]);
        $lastInsertId = DB::getPdo()->lastInsertId();
        $receipt_number = date('dmy', $dateTime->timestamp) . '1' . str_pad($lastInsertId, 5, '0', STR_PAD_LEFT);

        return $receipt_number;
    }

    private function checkTrainClassAvailability($trainId, $class_id)
    {

        if($class_id == 1){
            $sql = "SELECT train_first_class as available_classes FROM `trains`
        WHERE id =:trainId AND train_first_class=:classId LIMIT 1";
        }else if($class_id == 2){
            $sql = "SELECT train_second_class as available_classes FROM `trains`
        WHERE id =:trainId AND train_second_class=:classId LIMIT 1";
        }else{
            $sql = "SELECT train_third_class as available_classes FROM `trains`
        WHERE id =:trainId AND train_third_class=:classId LIMIT 1";
        }

        $data = ['trainId' => $trainId,'classId' => $class_id ];
        $result = $this->db_select($sql,$data);

        if (isset($result[0]->available_classes)) {
            if (!empty($result[0]->available_classes)) {
                return true;
            }
        }
        return false;


    }


    public function getCustomerAccounts($tagID)
    {
        $sql = "SELECT
        `D`.`id` AS `card_id`,
         IF(`txt_full_name` IS NULL OR `txt_full_name`='',CONCAT_WS(' ', `fname`,`lname`),`txt_full_name`) AS `fullname`,
        CONCAT_WS('',LEFT(`card_number`,4),'*******',RIGHT(`card_number`,5)) AS `mask_card`,
        `acc_balance`,
        `acc_status`,
        `card_number`,
        `acc_num`,
        `C`.`id` AS `customer_id`,
        `P`.`txt_package_code`,
        `P`.`int_package_validity_type`,
        `P`.`int_package_trip`,
        `P`.`id` AS `account_packege_type`,
        `P`.`txt_package_name`,
        `P`.`int_package_validity_type`,
        `P`.`dec_package_discount_percent`,
        `A`.`id` ,
        `A`.`int_account_type`,
        `A`.`char_account_usage`,
        `D`.`card_Type`,
        `D`.`int_card_ownership`,
        `D`.`int_credit_type`,
        `D`.`id` AS `card_id`,
        `D`.`int_company_id`,
        `S`.`int_main_category` AS `category_ID`,
        `A`.`int_max_trip_per_day`,
        `A`.`int_max_trip_per_month`
        FROM `tbl_customer_account` AS `A`
        INNER JOIN `tbl_card_customer_details` AS `C` ON `A`.`cust_id`=`C`.`id`
        INNER JOIN `tbl_cards_details` AS `D` ON `D`.`id`=`A`.`card_id`
        INNER JOIN `tbl_special_group` AS `S` ON `S`.`id`=`C`.`category_ID`
        INNER JOIN `tbl_customer_account_package_type` AS `P` ON `A`.`int_account_type`=`P`.`id`
        WHERE acc_status='A' AND char_account_usage IN ('C', 'E', 'X')  AND `tag_id`='" . $tagID . "'   LIMIT 3";

        $sql="SELECT
		`D`.`id` AS `card_id`,
		 IF(`full_name` IS NULL OR `full_name`='',CONCAT_WS(' ', `first_name`,`last_name`),`full_name`) AS `fullname`,
		CONCAT_WS('',LEFT(`card_number`,4),'*******',RIGHT(`card_number`,5)) AS `mask_card`,
		`account_balance`,
		`card_number`,
		`account_number`,
		`C`.`id` AS `customer_id`,
		`P`.`package_code`,
		`P`.`package_validity_type`,
		`P`.`package_trip`,
		`P`.`id` AS `account_packege_type`,
		`P`.`package_name`,
		`P`.`package_discount_percent`,
		`A`.`id` ,
		`A`.`customer_account_package_type`,
		`A`.`accounts_usage_type`,
		`D`.`card_Type`,
		`D`.`card_ownership`,
		`D`.`credit_type`,
		`D`.`company_id`,
		`C`.`special_category_id`,
		(`A`.`max_trip_per_day`) AS `max_trip_per_day`,
		`A`.`max_trip_per_month`,
        tag_id,
        A.`status`,
        A.last_update,
        `P`.`cfm_class_id`,
        `P`.`line_id`,
        `P`.`zone_id`,
        `P`.`category_id`
		FROM `customer_accounts` AS `A`
		INNER JOIN `card_customers` AS `C` ON `A`.`id`=`C`.`id`
		INNER JOIN `cards` AS `D` ON `D`.`id`=`A`.`id`
		INNER JOIN `customer_account_package_types` AS `P` ON `A`.`customer_account_package_type`=`P`.`id`
		WHERE A.status='A' AND accounts_usage_type IN (CUSTOMER_MODE, EMPLOYEES_MODE, 'X') AND tag_id=:tag_id";

        $data = ['tag_id' => $tagID];

        // Execute the query
        $result = DB::select($sql, $data);

        // Convert the result to an array of arrays
        $resultArray = array_map(function($item) {
            return (array) $item;
        }, $result);

        // Optionally convert the result to a collection
        $resultCollection = collect($resultArray);

        return response()->json($resultCollection);
    }

    public function getMaxTransaction()
    {
        $sql = "SELECT MAX(id) AS 'id' FROM ticket_transactions WHERE 1";
        $data = [];
        $result = $this->db_select($sql,$data);
        return $result[0]->id;
    }

    public function recordCustomerTicketBuyTransaction($msg, $type, $nature, $mode, $operator, $receipt, $source, $net, $account, $card, $status, $trnxNo, $onoff)
    {
        //        transaction number is being concatenated with "0" for Operator
        //start//

        $maxId0=$this->getMaxTransaction();
        $date = date("Y-m-d h:i:s");
        if (!isset($msg['penalty'])) {
            $msg['penalty'] = '0';
        }
        if (!isset($msg['fine_status']) || $msg['fine_status']==0) {
            $msg['fine_status'] = '1';
        }


        if (!isset($msg['fromStop'])) {
            if (isset($msg['zone_id'])) {
                $msg['fromStop'] = $this->getZoneStationID($msg['zone_id'], 'S', $msg['train_id']);
            }
        } else {

            if (empty($msg['fromStop'])) {
                if (isset($msg['zone_id'])) {
                    if ($msg['zone_id'] == '1' || $msg['zone_id'] == '2' || $msg['zone_id'] == '3') {
                        $msg['fromStop'] = $this->getZoneStationID($msg['zone_id'], 'S', $msg['train_id']);
                    }

                }
            }
        }
        if (!isset($msg['toStop'])) {
            if (isset($msg['zone_id'])) {
                $msg['toStop'] = $this->getZoneStationID($msg['zone_id'], 'E', $msg['train_id']);
            }
        } else {
            if (empty($msg['toStop'])) {
                if (isset($msg['zone_id'])) {
                    if ($msg['zone_id'] == '1' || $msg['zone_id'] == '2' || $msg['zone_id'] == '3') {
                        $msg['toStop'] = $this->getZoneStationID($msg['zone_id'], 'E', $msg['train_id']);
                    }

                }
            }
        }
        $froStationId = $this->get_station_id($msg['field_68']); //POS


        if ($froStationId != false) {
            if ($froStationId[0]->device_type == 'P') {
                if ($froStationId[0]->station_id > 0) {
                    if ($froStationId[0]->station_id != $msg['fromStop']) {
                        $msg['fromStop'] = $froStationId[0]->station_id;
                    }
                    //This one is edited to resolve the issue of appedairo

                    //for topup
                    if ($onoff == 0) {
                        if ($this->msg['field_61'] == '9009') {
                            $onoff = 2;
                            $msg['fromStop'] = $froStationId[0]->station_id;
                        }
                    }
                } else {
                    //check if its maputo or caben B and do exception

                    if ($onoff == 2) {
                        $chkResult = $this->check_station_if_estacao($msg['fromStop']);
                        if (!empty($froStationId[0]->type)) {
                            if (trim($froStationId[0]->type) != 'Estacao' && trim($froStationId[0]->type) != 'estacao' && strcmp('Esta', trim($chkResult)) === false && strcmp('esta', trim($chkResult)) === false) {
                                if ($msg['fromStop'] != '2') {
                                    $onoff = 1;
                                }
                            }
                        } else {
                            $chkResult = $this->check_station_if_estacao($msg['fromStop']);
                            if (!empty($chkResult)) {
                                if (trim($chkResult) != 'Estacao' && trim($chkResult) != 'estacao' && strcmp('Esta', trim($chkResult)) === false && strcmp('esta', trim($chkResult)) === false) {
                                    if ($msg['fromStop'] != '2') {
                                        $onoff = 1;
                                    }
                                }
                            }
                        }
                    }
                    //for topup
                    if ($onoff == 0) {
                        if ($this->msg['field_61'] == '9009') {
                            $onoff = 1;
                        }
                    }
                }
            } else {

                if ($onoff == 0) {
                    if ($this->msg['field_61'] == '9009') {
                        $onoff = 2;
                        $msg['fromStop'] = $froStationId[0]->station_id;
                    }
                }
            }
        }

        $device_id = $this->get_device_ID($msg['field_68']);
        $deviceID = $device_id;
        $transactionRef = $msg['field_7'] . $operator. $deviceID;
        $date = date("Y-m-d h:i:s");
        $sql = "INSERT INTO `ticket_transactions`
				(
					`trnx_Date`,
					`trnx_Time`,
					`trnx_Number`,
					`trnx_Type`,
					`trnx_Nature`,
					`trnx_Mode`,
					`acc_Number`,
					`card_Number`,
					`trnx_Amount`,
					`device_Number`,
					`operator_ID`,
					`trnx_Receipt`,
					`trnx_Source`,
					`signature`,
					`class_ID`,
					`Train_ID`,
					`seat_No`,
					`station_From`,
					`station_To`,
					`longitude`,
					`latitude`,
					`category_ID`,
					`net_Status`,
					`zone_ID`,
					`trnx_Quantity`,
					`trnx_Status`,
					`on_Off`,
					`fine_Amount`,
					`fine_Status`,
					`int_debit_account_id`,
					`int_credit_account_id`,
					`extended_trnx_type`,
					`customer_name`,
					`customer_id`,
                    `currency`,
                    `rate`,
                    `paid_amount`
				)
				VALUES
				(
					:tranxdt,
					:tranxtime,
					:trnxNo,
					:type,
					:nature,
					:mode,
					:acc,
					:card,
					:amount,
					:device,
					:operator,
					:receipt,
					:source,
					:field_7,
					:class,
					:train,
					:seat,
					:station1,
					:station2,
					:long,
					:lat,
					:cate,
					:net,
					:zone,
					:qnty,
					:stat,
					:onoff,
					:penalty,
					:fine,
					:debitAccount,
					:creditAccount,
					:extTrnxType,
					:customerName,
					:customerId,
                    :currency,
                    :rate,
                    :paid_amount
				)";

        $msg['field_4'] = round($msg['field_4']);

        if (!isset($msg['customer'])) {
            $msg['customer'] = "";
        }
        if (!isset($msg['customer_id'])) {
            $msg['customer_id'] = "";
        }
        if (!isset($msg['seat']) || $msg['seat']=="") {
            $msg['seat'] = "0";
        }

        // $accountCrId = '0';
        $currency = "1";
        $rate ="1";
        $paid_amount = $msg['field_4'];
        $parameters = array(
            'field_7' => $msg['field_7'] . $operator . $deviceID,
            'tranxdt' => $msg['field_7'],
            'tranxtime' => $msg['field_7'],
            'amount' => $msg['field_4'],
            'type' => $type,
            'nature' => $nature,
            'mode' => $mode,
            'acc' => $account,
            'card' => $card,
            'device' => $msg['field_68'],
            'operator' => $operator,
            'receipt' => $receipt,
            'source' => $source,
            'train' => $msg['train_id'],
            'class' => $msg['class_id'],
            'seat' => $msg['seat'],
            'long' => $msg['longitude'],
            'lat' => $msg['latitude'],
            'cate' => $msg['category'],
            'net' => $net,
            'zone' => $msg['zone_id'],
            'qnty' => $msg['quantity'],
            'station1' => $msg['fromStop'],
            'station2' => $msg['toStop'],
            'trnxNo' => $trnxNo,
            'stat' => $status,
            'onoff' => $onoff,
            'penalty' => $msg['penalty'],
            'fine' => $msg['fine_status'],
            'debitAccount' => $msg['accountCrId'],
            'creditAccount' => $msg['accountCrId'],
            'extTrnxType' => $msg['field_61'],
            'customerName' => $msg['customer'],
            'customerId' => $msg['customer_id'],
            'currency' => $currency,
            'rate' => $rate,
            'paid_amount' => $paid_amount
        );
        try {
            $result = $this->db_insert($sql,$parameters);
            $status = true;

            return $status;
        } catch (PDOException $ex) {
            $this->messageCode = $ex->errorInfo[1];
            $this->sqlErrorMsg = __LINE__ . '-' . $ex->getMessage();
            return false;
        }
        return false;
    }

    public function insert_summary_logs(Request $request)
    {
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        $output = $this->verify_message_source($this->msg['field_58'], null);
        $operator = $output[0]->id;

        try {

            $sql = "INSERT INTO `device_summary_receipts`
                    (
                    `operator_id`,
                    `device_imei`,
                    `total_amount`,
                    `total_tickets`,
                    `transaction_type_id`,
                    `train_id`,
                    `summary_date_time`
                    )
                    VALUES
                    (:operator_name,
                    :device_imei,
                    :amount_collected,
                    :total_tickets,
                    :trans_type,
                    :train_number,
                    :date_time
                    )";


            $parameters = array(
                'operator_name' => $operator,
                'device_imei' => $this->msg['field_68'],
                'amount_collected' => $this->msg['field_4'],
                'total_tickets' => $this->msg['total_tickets'],
                'trans_type' => $this->msg['tran_type'],
                'train_number' => $this->msg['train_number'],
                'date_time' => $this->msg['date_time'],
            );

            $status = $this->db_insert($sql, $parameters);

            $this->msg['field_39'] = '00';
            $this->msg['MTI'] = '0930';
            $this->msg['field_37'] = '90111';

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }catch (Exception $e){
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }
        //print_r(json_encode($parameters));
        return $this->msg;

    }


    public function online_card_transaction(Request $request, $type){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            $isFirstTime = false;
            $output = $this->verify_message_source($this->msg['field_58'], null);
            $operator = $output[0]->id;
            $this->msg['MTI'] = "0210";

            // $this->msg['tag_id']=Security::decrypt($this->msg['tag_id'], ENC_KEY);
            if ($this->checkTrainClassAvailability($this->msg['train_id'], $this->msg['class_id'])) {
                if ($output != null && $output['0']->status == 1) {
                    $customerDetails = $this->getCustomerAccounts($this->msg['tag_id']);
                    $defAccount = null;
                    $AutomotoraAccount = null;
                    $employeeAccount = null;
                    $customerExtraAccount = null;
                    $employeeExtraAccount = null;
                    foreach ($customerDetails->original as $detail) {
                        $this->msg['MTI'] = $detail['card_id'];



                        if($detail['account_packege_type'] == 28){
                            $this->msg['AutomotoraAccount'] = "AutomotoraAccount22";
                            $AutomotoraAccount = $detail;
                        }
                        if ($detail['account_packege_type'] == 20) {
                            $defAccount = $detail;

                        } elseif ($detail['card_ownership'] != 1 && $detail['accounts_usage_type'] != 'E') {
                            $customerExtraAccount = $detail;
                        } elseif ($detail['card_ownership'] == 1 && $detail['accounts_usage_type'] == 'E') {
                            $employeeAccount = $detail;
                        } elseif ($detail['card_ownership'] == 1 && $detail['accounts_usage_type'] == 'C') {
                            $employeeExtraAccount = $detail;
                            $this->msg['count'] = count($customerDetails);
                            $this->msg['employeeExtraAccount'] = $employeeExtraAccount;
                        }
                    }
                    if (!empty($customerDetails)) {
                        //                        card is active
                        // if($this->msg['location_name'] == "automotora"){
                        //     $type = 3;
                        // }else{
                        //     $type = 2; //Debit Customer
                        // }
                        $nature = 2; //Payment
                        $mode = 2; //Card
                        $source = 1;
                        $net = "Online";



                        if (isset($this->msg['operator_location'])) {
                            $onOff = $this->msg['operator_location'];
                        }else{
                            $onOff = 2;
                        }
                        $transactionNumber = $this->msg['field_7'];

                        if (!isset($this->msg['zone_id'])) {
                            $this->msg['zone_id'] = 0;
                        }
                        if (!isset($msg['penalty'], $msg['fine_status'])) {
                            $msg['penalty'] = 0;
                            $msg['fine_status'] = 1;
                        }
                        $this->msg['latitude'] = '0.0';
                        $this->msg['longitude'] = '0.0';

                        if ($employeeAccount == null && $defAccount != null) {

                            //                        normal customer
                            if ($defAccount['special_category_id'] == $this->msg['category']) {
                                $this->msg['category'] = 'Equal';
                                $this->msg['category'] = $defAccount['special_category_id'];
                                if($customerExtraAccount != null){

                                    if ( $customerExtraAccount['cfm_class_id']==$this->msg['class_id']
                                        // &&                             // $customerExtraAccount['line_id']==$this->msg['line']
                                        // && $customerExtraAccount['category_ID']==$this->msg['category']
                                        && $customerExtraAccount['zone_id'] >= $this->msg['zone_id'] && $this->msg['location_name'] != "automotora" ) {
                                        $this->msg['Nondefault'] = $defAccount['special_category_id'].'Nondefault'.$this->msg['category'];
                                        $account = $customerExtraAccount['account_number'];
                                        $cardNumber = $customerExtraAccount['card_number'];
                                        $discountRate = $customerExtraAccount['package_discount_percent'];
                                        $currentPackageBalance = $customerExtraAccount['account_balance'];
                                        $intPackageType = $customerExtraAccount['account_packege_type'];
                                        $discountedAmount = ($this->msg['field_4'] - ($this->msg['field_4'] * $discountRate));
                                        if (($discountedAmount <= $currentPackageBalance) && ($discountedAmount > 0)) {
                                            $this->msg['field_4'] = sprintf('%.2f', $discountedAmount, 2);
                                            $receiptNumber = $this->generate_daily_receipt();
                                            $this->msg['field_37'] = $receiptNumber;

                                            if ($receiptNumber != null) {
                                                $this->accountCrId = $this->CFMCAR1;
                                                $this->accountDrId = $customerExtraAccount['id'];
                                                if ($this->recordCustomerTicketBuyTransaction($this->msg, $type, $nature, $mode, $operator, $receiptNumber, $source, $net, $account, $cardNumber, 0, $transactionNumber, $onOff)) //Record  payments
                                                {
                                                    // if ($this->checkIfPassengerIsAllowedToRepay($this->msg['tag_id'], $this->msg['train_id'], $customerExtraAccount['id'], $operator)) {
                                                    //debit customer account specific package

                                                    if ($this->debitCustomerAccountPackage($this->msg, $customerExtraAccount['card_id'], $intPackageType)) {
                                                        //$this->updateTransactionStatus($operator, $this->msg['field_7'] , 0);
                                                        $this->updateOtherSystemAccounts($this->accountCrId, (1 * $this->msg['field_4']));
                                                        $this->msg['transaction_number'] = $receiptNumber;
                                                        $this->msg['customer'] = $customerExtraAccount['mask_card'];
                                                        $this->msg['card_number'] = $customerExtraAccount['mask_card'];
                                                        $this->msg['account_number'] = $customerExtraAccount['acc_num'];
                                                        $this->msg['balance'] = number_format((float) $currentPackageBalance - $discountedAmount, 2, ',', ' ');
                                                        $this->msg['second_balance'] = number_format((float) $this->getSecondBalance($customerExtraAccount['acc_num'],$this->msg['tag_id'])['acc_balance'], 2, ',', ' ');
                                                        $this->msg['discount_rate'] = ($discountRate * 100) . '%';
                                                        $this->msg['field_39'] = '00';
                                                        $this->msg['package_code'] = $customerExtraAccount['txt_package_code'];
                                                    } else {
                                                        $this->msg['field_39'] = '05';
                                                        $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                    }


                                                } else {
                                                    if ($this->messageCode == '1062') {
                                                        $this->msg['field_39'] = '94';
                                                        $this->msg['message'] = 'Duplicate record in transaction, not saved';
                                                    } else {
                                                        $this->msg['field_39'] = '05';
                                                        $this->msg['message'] = 'General error, not saved';
                                                        $this->msg['error_message'] = $this->sqlErrorMsg;
                                                        $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                    }
                                                }
                                            } else {
                                                $this->msg['field_39'] = '96';
                                                $this->msg['message'] = 'Failed to generate receipt number';
                                            }
                                        } else if ($discountedAmount < 0) {
                                            $this->msg['field_39'] = '13';
                                            $this->msg['message'] = 'Amount format error, less than Zero, not saved';
                                        } else {
                                            // $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);
                                            if ($this->msg['category']=='8') {
                                                // code...
                                                // debitUserMTCDefAccount
                                                $this->debitUserMTCDefAccount($defAccount, $operator, $transactionNumber);

                                            }else{
                                                $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);

                                            }
                                        }
                                    }} else if($this->msg['location_name'] == "automotora" && ($this->checkIfPassengerHasUseTwoTripsForSpecialPakage($AutomotoraAccount['account_number'],$AutomotoraAccount['card_number']) < 2)){

                                    $this->msg['automotoraPackage'] = $AutomotoraAccount['acc_balance'];
                                    $this->msg['acc_num_'] = $AutomotoraAccount['acc_num'];
                                    $this->msg['card_number_'] = $AutomotoraAccount['card_number'];
                                    $this->msg['testautomotora'] = $AutomotoraAccount['testautomotora'];
                                    $account = $AutomotoraAccount['acc_num'];
                                    $cardNumber = $AutomotoraAccount['card_number'];
                                    $discountRate = $AutomotoraAccount['dec_package_discount_percent'];
                                    $currentPackageBalance = $AutomotoraAccount['acc_balance'];
                                    $intPackageType = $AutomotoraAccount['account_packege_type'];
                                    $discountedAmount = ($this->msg['field_4'] - ($this->msg['field_4'] * $discountRate));
                                    if (($discountedAmount <= $currentPackageBalance) && ($discountedAmount > 0)) {
                                        $this->msg['field_4'] = sprintf('%.2f', $discountedAmount, 2);
                                        $receiptNumber = $this->generate_daily_receipt();
                                        $this->msg['field_37'] = $receiptNumber;

                                        if ($receiptNumber != null) {
                                            $this->accountCrId = $this->CFMCAR1;
                                            $this->accountDrId = $AutomotoraAccount['id'];
                                            if ($this->recordCustomerTicketBuyTransaction($this->msg, $type, $nature, $mode, $operator, $receiptNumber, $source, $net, $account, $cardNumber, 0, $transactionNumber, $onOff)) //Record  payments
                                            {
                                                if ($this->checkIfPassengerIsAllowedToRepay($this->msg['tag_id'], $this->msg['train_id'], $AutomotoraAccount['id'], $operator)) {
                                                    //debit customer account specific package

                                                    if ($this->debitCustomerAccountPackage($this->msg, $AutomotoraAccount['card_id'], $intPackageType)) {
                                                        $this->updateOtherSystemAccounts($this->accountCrId, (1 * $this->msg['field_4']));
                                                        $this->msg['transaction_number'] = $receiptNumber;
                                                        $this->msg['customer'] = $AutomotoraAccount['mask_card'];
                                                        $this->msg['card_number'] = $AutomotoraAccount['mask_card'];
                                                        $this->msg['account_number'] = $AutomotoraAccount['acc_num'];
                                                        $this->msg['balance'] = number_format((float) $currentPackageBalance - $discountedAmount, 2, ',', ' ');
                                                        $this->msg['second_balance'] = number_format((float) $this->getSecondBalance($AutomotoraAccount['acc_num'],$this->msg['tag_id'])['acc_balance'], 2, ',', ' ');
                                                        $this->msg['discount_rate'] = ($discountRate * 100) . '%';
                                                        $this->msg['field_39'] = '00';
                                                        $this->msg['package_code'] = $AutomotoraAccount['txt_package_code'];
                                                    } else {
                                                        $this->msg['field_39'] = '05';
                                                        $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                    }

                                                } else {
                                                    $this->msg['field_39'] = '65';
                                                    $this->msg['message'] = 'Exceed pay limit';
                                                    $this->updateTransactionStatus($operator, $this->msg['field_7'], 65);
                                                }
                                            } else {
                                                if ($this->messageCode == '1062') {
                                                    $this->msg['field_39'] = '94';
                                                    $this->msg['message'] = 'Duplicate record in transaction, not saved';
                                                } else {
                                                    $this->msg['field_39'] = '05';
                                                    $this->msg['message'] = 'General error, not saved';
                                                    $this->msg['error_message'] = $this->sqlErrorMsg;
                                                    $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                }
                                            }
                                        } else {
                                            $this->msg['field_39'] = '96';
                                            $this->msg['message'] = 'Failed to generate receipt number';
                                        }
                                    } else if ($discountedAmount < 0) {
                                        $this->msg['field_39'] = '13';
                                        $this->msg['message'] = 'Amount format error, less than Zero, not saved';
                                    } else {

                                        if ($this->msg['category']=='8') {
                                            // code...
                                            // debitUserMTCDefAccount
                                            $this->debitUserMTCDefAccount($defAccount, $operator, $transactionNumber);

                                        }else{
                                            $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);

                                        }
                                    }

                                } else {
                                    $this->msg['DEFAULT'] = 'DEFAULT';
//                            customer has only one account (DEFAULT)

                                    if ($this->msg['category']=='8') {
                                        $this->debitUserMTCDefAccount($defAccount, $operator, $transactionNumber);

                                    }else{

                                        $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);

                                    }
                                }
                            } else {
                                $this->msg['field_39'] = '12';
                                $this->msg['message'] = __LINE__ . ':Verifique a categoria, nort correto!';
                            }

                        } else if ($employeeAccount != null && $defAccount != null) {
                            //Employee card
                            if($this->msg['location_name'] == "automotora"){
                                $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);
                            }else{
                                $trainType = 2;
                                $this->processCompanyCardPayment($defAccount, $employeeAccount, $employeeExtraAccount, $type, $nature, $mode, $operator, $source, $net, $transactionNumber, $onOff, $trainType);
                            }
                        } else {
                            $this->msg['test2'] = 'test9';
                            if ($employeeAccount != null) {
                                $isFirstTime = true;
                                if ($this->setCustomerAccount($employeeAccount['customer_id'], $employeeAccount['card_id'], $operator, 6) == 1) {
                                    $this->buyTicket();
                                } else {
                                    $this->msg['field_39'] = '25';
                                    $this->msg['message'] = 'user type does not exist';
                                }
                            }else{
                                $this->msg['field_39'] = '15';
                                $this->msg['message'] = 'Cannot perform this operation';

                            }
                        }
                    } else {
                        $this->msg['field_39'] = '25';
                        $this->msg['message'] = 'Customer is invalid';
                    }
                } else {
                    $this->msg['field_39'] = '15';
                    $this->msg['message'] = 'Cannot perform this operation';
                }
            } else {
                $this->msg['field_39'] = '79';
                $this->msg['message'] = 'This class not allowed on this train';
            }
            if (!$isFirstTime) {
                $responseObject->message = "";
                $responseObject->response_code = \HttpResponseCode::SUCCESS;
                $transactions = null;
                $responseObject->transactions = $transactions;
                $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                $dataPayload->data = $encryptedResponse;
                return response()->json($dataPayload);
            }

        }
        catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }

    public function online_mobile_transaction(Request $request, $type){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {

            $isFirstTime = false;
            $jsonMessage = json_encode([
                'custom_message' => 'Request:',
                'data' => $request->data // Example data from the request
            ]);
            $this->logger->log($jsonMessage);
            $this->msg = $request;

            $output = $this->verify_message_source($this->msg['operator'], null);
            // return $output  ;
            $operator = $output[0]->id;

            $this->msg['field_61'] = "9012";
            $this->msg['line'] = "3";
            $this->msg['zone_id'] = "1";
            $this->msg['field_68'] = $this->msg['device_identifier'];
            $this->msg['field_69'] = $this->msg['device_identifier'];
            $this->msg['MTI'] = "0210";
            $this->msg['quantity'] = "1";
            $this->msg['category'] = "1";

            if ($this->checkTrainClassAvailability($this->msg['train_id'], $this->msg['carriage_class_id'])) {
                if ($output != null && $output['0']->status == 1) {

                    $tag_id = $this->get_tag_id($this->msg['card_number']);

                    if($tag_id  != null){
                        $customerDetails = $this->getCustomerAccounts($tag_id[0]->tag_id);
                        $defAccount = null;
                        $AutomotoraAccount = null;
                        $employeeAccount = null;
                        $customerExtraAccount = null;
                        $employeeExtraAccount = null;
                        foreach ($customerDetails->original as $detail) {
                            $this->msg['MTI'] = $detail['card_id'];



                            if($detail['account_packege_type'] == 28){
                                $this->msg['AutomotoraAccount'] = "AutomotoraAccount22";
                                $AutomotoraAccount = $detail;
                            }
                            if ($detail['account_packege_type'] == 20) {
                                $defAccount = $detail;

                            } elseif ($detail['card_ownership'] != 1 && $detail['accounts_usage_type'] != 'E') {
                                $customerExtraAccount = $detail;
                            } elseif ($detail['card_ownership'] == 1 && $detail['accounts_usage_type'] == 'E') {
                                $employeeAccount = $detail;
                            } elseif ($detail['card_ownership'] == 1 && $detail['accounts_usage_type'] == 'C') {
                                $employeeExtraAccount = $detail;
                                $this->msg['count'] = count($customerDetails);
                                $this->msg['employeeExtraAccount'] = $employeeExtraAccount;
                            }
                        }
                        if (!empty($customerDetails)) {

                            $nature = 2; //Payment
                            $mode = 2; //Card
                            $source = 1;
                            $net = "Online";



                            if (isset($this->msg['operator_location'])) {
                                $onOff = $this->msg['operator_location'];
                            }else{
                                $onOff = 2;
                            }
                            $transactionNumber = $this->msg['field_7'];

                            if (!isset($this->msg['zone_id'])) {
                                $this->msg['zone_id'] = 0;
                            }
                            if (!isset($msg['penalty'], $msg['fine_status'])) {
                                $msg['penalty'] = 0;
                                $msg['fine_status'] = 1;
                            }
                            $this->msg['latitude'] = '0.0';
                            $this->msg['longitude'] = '0.0';

                            if ($employeeAccount == null && $defAccount != null) {

                                //                        normal customer
                                if ($defAccount['special_category_id'] == $this->msg['category']) {
                                    $this->msg['category'] = 'Equal';
                                    $this->msg['category'] = $defAccount['special_category_id'];
                                    if($customerExtraAccount != null){

                                        if ( $customerExtraAccount['cfm_class_id']==$this->msg['class_id']
                                            && $customerExtraAccount['zone_id'] >= $this->msg['zone_id'] && $this->msg['location_name'] != "automotora" ) {
                                            $this->msg['Nondefault'] = $defAccount['special_category_id'].'Nondefault'.$this->msg['category'];
                                            $account = $customerExtraAccount['account_number'];
                                            $cardNumber = $customerExtraAccount['card_number'];
                                            $discountRate = $customerExtraAccount['package_discount_percent'];
                                            $currentPackageBalance = $customerExtraAccount['account_balance'];
                                            $intPackageType = $customerExtraAccount['account_packege_type'];
                                            $discountedAmount = ($this->msg['field_4'] - ($this->msg['field_4'] * $discountRate));
                                            if (($discountedAmount <= $currentPackageBalance) && ($discountedAmount > 0)) {
                                                $this->msg['field_4'] = sprintf('%.2f', $discountedAmount, 2);
                                                $receiptNumber = $this->generate_daily_receipt();
                                                $this->msg['field_37'] = $receiptNumber;

                                                if ($receiptNumber != null) {
                                                    $this->accountCrId = $this->CFMCAR1;
                                                    $this->accountDrId = $customerExtraAccount['id'];
                                                    if ($this->recordCustomerTicketBuyTransaction($this->msg, $type, $nature, $mode, $operator, $receiptNumber, $source, $net, $account, $cardNumber, 0, $transactionNumber, $onOff)) //Record  payments
                                                    {

                                                        if ($this->debitCustomerAccountPackage($this->msg, $customerExtraAccount['card_id'], $intPackageType)) {
                                                            $this->updateOtherSystemAccounts($this->accountCrId, (1 * $this->msg['field_4']));
                                                            $this->msg['transaction_number'] = $receiptNumber;
                                                            $this->msg['customer'] = $customerExtraAccount['mask_card'];
                                                            $this->msg['card_number'] = $customerExtraAccount['mask_card'];
                                                            $this->msg['account_number'] = $customerExtraAccount['acc_num'];
                                                            $this->msg['balance'] = number_format((float) $currentPackageBalance - $discountedAmount, 2, ',', ' ');
                                                            $this->msg['second_balance'] = number_format((float) $this->getSecondBalance($customerExtraAccount['acc_num'],$this->msg['tag_id'])['acc_balance'], 2, ',', ' ');
                                                            $this->msg['discount_rate'] = ($discountRate * 100) . '%';
                                                            $this->msg['field_39'] = '00';
                                                            $this->msg['package_code'] = $customerExtraAccount['txt_package_code'];
                                                        } else {
                                                            $this->msg['field_39'] = '05';
                                                            $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                        }


                                                    } else {
                                                        if ($this->messageCode == '1062') {
                                                            $this->msg['field_39'] = '94';
                                                            $this->msg['message'] = 'Duplicate record in transaction, not saved';
                                                        } else {
                                                            $this->msg['field_39'] = '05';
                                                            $this->msg['message'] = 'General error, not saved';
                                                            $this->msg['error_message'] = $this->sqlErrorMsg;
                                                            $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                        }
                                                    }
                                                } else {
                                                    $this->msg['field_39'] = '96';
                                                    $this->msg['message'] = 'Failed to generate receipt number';
                                                }
                                            } else if ($discountedAmount < 0) {
                                                $this->msg['field_39'] = '13';
                                                $this->msg['message'] = 'Amount format error, less than Zero, not saved';
                                            } else {
                                                if ($this->msg['category']=='8') {
                                                    // code...
                                                    $this->debitUserMTCDefAccount($defAccount, $operator, $transactionNumber);

                                                }else{
                                                    $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);

                                                }
                                            }
                                        }} else if($this->msg['location_name'] == "automotora" && ($this->checkIfPassengerHasUseTwoTripsForSpecialPakage($AutomotoraAccount['account_number'],$AutomotoraAccount['card_number']) < 2)){

                                        $this->msg['automotoraPackage'] = $AutomotoraAccount['acc_balance'];
                                        $this->msg['acc_num_'] = $AutomotoraAccount['acc_num'];
                                        $this->msg['card_number_'] = $AutomotoraAccount['card_number'];
                                        $this->msg['testautomotora'] = $AutomotoraAccount['testautomotora'];
                                        $account = $AutomotoraAccount['acc_num'];
                                        $cardNumber = $AutomotoraAccount['card_number'];
                                        $discountRate = $AutomotoraAccount['dec_package_discount_percent'];
                                        $currentPackageBalance = $AutomotoraAccount['acc_balance'];
                                        $intPackageType = $AutomotoraAccount['account_packege_type'];
                                        $discountedAmount = ($this->msg['field_4'] - ($this->msg['field_4'] * $discountRate));
                                        if (($discountedAmount <= $currentPackageBalance) && ($discountedAmount > 0)) {
                                            $this->msg['field_4'] = sprintf('%.2f', $discountedAmount, 2);
                                            $receiptNumber = $this->generate_daily_receipt();
                                            $this->msg['field_37'] = $receiptNumber;

                                            if ($receiptNumber != null) {
                                                $this->accountCrId = $this->CFMCAR1;
                                                $this->accountDrId = $AutomotoraAccount['id'];
                                                if ($this->recordCustomerTicketBuyTransaction($this->msg, $type, $nature, $mode, $operator, $receiptNumber, $source, $net, $account, $cardNumber, 0, $transactionNumber, $onOff)) //Record  payments
                                                {
                                                    if ($this->checkIfPassengerIsAllowedToRepay($this->msg['tag_id'], $this->msg['train_id'], $AutomotoraAccount['id'], $operator)) {
                                                        //debit customer account specific package

                                                        if ($this->debitCustomerAccountPackage($this->msg, $AutomotoraAccount['card_id'], $intPackageType)) {
                                                            $this->updateOtherSystemAccounts($this->accountCrId, (1 * $this->msg['field_4']));
                                                            $this->msg['transaction_number'] = $receiptNumber;
                                                            $this->msg['customer'] = $AutomotoraAccount['mask_card'];
                                                            $this->msg['card_number'] = $AutomotoraAccount['mask_card'];
                                                            $this->msg['account_number'] = $AutomotoraAccount['acc_num'];
                                                            $this->msg['balance'] = number_format((float) $currentPackageBalance - $discountedAmount, 2, ',', ' ');
                                                            $this->msg['second_balance'] = number_format((float) $this->getSecondBalance($AutomotoraAccount['acc_num'],$this->msg['tag_id'])['acc_balance'], 2, ',', ' ');
                                                            $this->msg['discount_rate'] = ($discountRate * 100) . '%';
                                                            $this->msg['field_39'] = '00';
                                                            $this->msg['package_code'] = $AutomotoraAccount['txt_package_code'];
                                                        } else {
                                                            $this->msg['field_39'] = '05';
                                                            $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                        }

                                                    } else {
                                                        $this->msg['field_39'] = '65';
                                                        $this->msg['message'] = 'Exceed pay limit';
                                                        $this->updateTransactionStatus($operator, $this->msg['field_7'], 65);
                                                    }
                                                } else {
                                                    if ($this->messageCode == '1062') {
                                                        $this->msg['field_39'] = '94';
                                                        $this->msg['message'] = 'Duplicate record in transaction, not saved';
                                                    } else {
                                                        $this->msg['field_39'] = '05';
                                                        $this->msg['message'] = 'General error, not saved';
                                                        $this->msg['error_message'] = $this->sqlErrorMsg;
                                                        $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                                                    }
                                                }
                                            } else {
                                                $this->msg['field_39'] = '96';
                                                $this->msg['message'] = 'Failed to generate receipt number';
                                            }
                                        } else if ($discountedAmount < 0) {
                                            $this->msg['field_39'] = '13';
                                            $this->msg['message'] = 'Amount format error, less than Zero, not saved';
                                        } else {

                                            if ($this->msg['category']=='8') {
                                                // code...
                                                $this->debitUserMTCDefAccount($defAccount, $operator, $transactionNumber);

                                            }else{
                                                $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);

                                            }
                                        }

                                    } else {
                                        $this->msg['DEFAULT'] = 'DEFAULT';
                                        $this->msg['status'] = 'success';
                                        $this->msg['message'] = 'Success';
                                        $this->msg['code'] = '200';
                                        $this->msg['data'] = ['message' => "Pagamento efectuado com sucesso"];

                                        if ($this->msg['category']=='8') {
                                            // code...
                                            $this->debitUserMTCDefAccount($defAccount, $operator, $transactionNumber);

                                        }else{
                                            $this->debitUserDefaultAccount();

                                        }
                                    }
                                } else {
                                    $this->msg['field_39'] = '12';
                                    $this->msg['message'] = __LINE__ . ':Verifique a categoria, nort correto!';
                                }

                            } else if ($employeeAccount != null && $defAccount != null) {
                                //Employee card
                                if($this->msg['location_name'] == "automotora"){
                                    $this->debitUserDefAccount($defAccount, $operator, $transactionNumber);
                                }else{
                                    $trainType = 2;
                                    $this->processCompanyCardPayment($defAccount, $employeeAccount, $employeeExtraAccount, $type, $nature, $mode, $operator, $source, $net, $transactionNumber, $onOff, $trainType);
                                }
                            } else {
                                $this->msg['test2'] = 'test9';
                                if ($employeeAccount != null) {
                                    $isFirstTime = true;
                                    if ($this->setCustomerAccount($employeeAccount['customer_id'], $employeeAccount['card_id'], $operator, 6) == 1) {
                                        $this->buyTicket();
                                    } else {
                                        $this->msg['field_39'] = '25';
                                        $this->msg['message'] = 'user type does not exist';
                                    }
                                }else{
                                    $this->msg['field_39'] = '15';
                                    $this->msg['message'] = 'Cannot perform this operation';

                                }
                            }
                        } else {
                            $this->msg['field_39'] = '25';
                            $this->msg['message'] = 'Customer is invalid';
                        }
                    }else{
                        $this->msg['field_39'] = '15';
                        $this->msg['message'] = 'Card Number does not exist';
                    }
                } else {
                    $this->msg['field_39'] = '15';
                    $this->msg['message'] = 'Cannot perform this operation';
                }
            } else {
                $this->msg['field_39'] = '79';
                $this->msg['message'] = 'This class not allowed on this train';
            }
            // $this->msg['tag_id']=Security::encrypt($this->msg['tag_id'], ENC_KEY);

            $jsonMessage = json_encode([
                'custom_message' => 'Response:',
                'data' => $this->msg // Example data from the request
            ]);
            if (!$isFirstTime) {
                $responseObject->message = "";
                $responseObject->response_code = \HttpResponseCode::SUCCESS;
                $responseObject->msg = $this->msg;
                $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                $dataPayload->data = $encryptedResponse;
                return response()->json($dataPayload);

            }

        }
        catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }

    public function debitCustomerAccountPackage($msg, $tagCardId, $intPackageType)
    {
        $sql = "UPDATE `customer_accounts`
                      SET `account_balance` = `account_balance` - :charge,
                      `trips_number_balance` = `trips_number_balance` + 1 ,
                      last_update= CURRENT_TIMESTAMP
                      WHERE `id`=:tagCardId
                      AND `status`='A'
                      AND `customer_account_package_type`=:accountPackageType";
        try {
            $totalAmount = $msg['field_4'] + $msg['penalty'];
            $data = [ 'charge'=>$totalAmount, 'accountPackageType'=> $intPackageType,'tagCardId'=> $tagCardId ];
            $result = $this->db_update($sql,$data);
            return $result;
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $status = 0;
        }
        return $status;
    }


    public function updateTransactionStatus($operator, $transactionReceipt, $trxStatus)
    {

        $sql = "UPDATE `ticket_transactions`
          SET `trnx_Status`=:transactionStatus
          WHERE `signature`=:transactionReceipt";
        $status = true;
        try {

            $data = [ 'transactionStatus'=>$trxStatus, 'transactionReceipt'=> $transactionReceipt ];
            $result = $this->db_update($sql,$data);

            if ($result) {
                $status = 1;
            } else {
                $status = 0;
            }

        } catch (Exception $e) {
            return false;
        }
        return $status;
    }
    public function debitUserDefaultAccount(){
        return "test";
    }

    public function debitUserDefAccount($defAccount, $operator, $transactionNumber)
    {

        $type = 2; //Debit Customer
        $nature = 2; //Payment
        $mode = 2; //Card
        $source = 1;
        $net = "Online";
        if (isset($this->msg['operator_location'])) {
            $onOff = $this->msg['operator_location'];
        } else {
            $onOff = 2;
        }
        if (isset($this->msg['MTI'])) {
            if ($this->msg['MTI']=='0220') {
                $net = "Offline";
            } else {
                $net = "Online";
            }
        }

        //check if default package has money
        $account = $defAccount['account_number'];
        $currentPackageBalance = $defAccount['account_balance'];
        $intPackageType = $defAccount['account_packege_type'];
        $this->accountDrId = $defAccount['id'];
        $this->accountCrId = $this->CFMCAR1;
        $this->msg['accountDrId'] = $this->accountDrId;
        $this->msg['accountCrId'] = $this->accountCrId;
        //check the card balance and decline if not enough.
        if ($currentPackageBalance >= $this->msg['field_4'] || $net =='Offline') {
            $this->msg['field_4'] = sprintf('%.2f', $this->msg['field_4'], 2);
            $receiptNumber = $this->generate_Daily_Receipt();
            $this->msg['field_37'] = $receiptNumber;

            if ($receiptNumber != null) {
                if ($this->recordCustomerTicketBuyTransaction($this->msg, $type, $nature, $mode, $operator, $receiptNumber, $source, $net, $account, $defAccount['card_number'], 0, $transactionNumber, $onOff)) //Record  payments
                {

                    //debit customer account specific package
                    if ($this->debitCustomerAccountPackage($this->msg, $defAccount['card_id'], $intPackageType)) {
                        //$this->updateTransactionStatus($operator, $this->msg['field_7'], 0);
                        $this->updateOtherSystemAccounts($this->accountCrId, (1 * $this->msg['field_4']));
                        $this->msg['transaction_number'] = $receiptNumber;
                        $this->msg['field_39'] = '00';
                        $this->msg['customer'] = $defAccount['fullname'];
                        $this->msg['package_name'] = 'Pacote Padrão';
                        $this->msg['balance'] = $currentPackageBalance - $this->msg['field_4'];
                        $this->msg['card_number'] = $defAccount['mask_card'];
                        $this->msg['package_code'] = $defAccount['package_code'];
                    } else {
                        $this->msg['field_39'] = '05';
                        $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                        $this->msg['message'] = 'failed to update';
                    }

                } else {
                    if ($this->messageCode == '1062') {
                        $this->msg['field_39'] = '94';
                        $this->msg['message'] = 'Duplicate record in transaction, not saved';
                    } else {
                        $this->msg['field_39'] = '05';
                        $this->msg['message_'] = $this->sqlErrorMsg;
                        $this->msg['message'] = 'General error, not saved';
                        $this->updateTransactionStatus($operator, $this->msg['field_7'], 05);
                    }
                }
            } else {
                $this->msg['field_39'] = '96';
                $this->msg['message'] = 'Failed to generate receipt number';
            }
        } else {
            $this->msg['field_39'] = '51';
            $this->msg['balance'] = number_format($currentPackageBalance, 2, ',', ' ') . ' MT';
            $this->msg['message'] = 'No enough balance';
        }
    }

    public function updateOtherSystemAccounts($accountId, $topupAmount)
    {
        $sql = "UPDATE `customer_accounts` SET `account_balance` = (`account_balance` + :topupAmount),last_update= CURRENT_TIMESTAMP WHERE id=:accountId";
        try {

            $data = [ 'topupAmount'=>$topupAmount, 'accountId'=> $accountId ];
            $result = $this->db_update($sql,$data);
            if ($result) {
                $status = 1;
            } else {
                $status = 0;
            }
        } catch (Exception $e) {
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $status = 0;
        }

        return $status;
    }


    public function online_transaction(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $msg = null;

        $this->msg = $request->data;
        $id =  $request->data['id'];
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $this->msg['MTI'] = "0210";

            $output = $this->verify_message_source($this->msg['field_58'], null);
            $operator = $output[0]->id;
            $operatorName = $output[0]->full_name;

            $onoff = null;
            if (isset($this->msg['operator_location'])) {
                $onoff = $this->msg['operator_location'];
            }
            if ($onoff == null) {
                $onoff = 2;
            }

            $net = "Online";
            $this->msg['field_4'] = sprintf('%.2f', $this->msg['field_4'], 2);
            $amount = $this->msg['field_4'];
            $type = 1;
            $nature = 2;
            $mode = 1;
            $source = 1;
            $receiptNumber = '';
            $trnx_No = $this->msg['field_7'];

            if (!isset($this->msg['zone_id'])) {
                $this->msg['zone_id'] = 0;
            }


            if (isset($this->msg['longitude'], $this->msg['latitude '])) {
                $this->msg['latitude'] = $this->msg['latitude'];
                $this->msg['longitude'] = $this->msg['longitude'];
            } else {
                $this->msg['latitude'] = '0.0';
                $this->msg['longitude'] = '0.1';
            }
            if (!isset($msg['penalty'], $msg['fine_status'])) {
                $msg['penalty'] = 0;
                $msg['fine_status'] = 1;
            }
            if ($operator != null) {
                $receiptNumber = $this->generate_daily_receipt();
                $this->msg['field_37'] = $receiptNumber;
                if ($receiptNumber != null) {
                    //Record Payment
                    //$this->msg['field_39']='00';
                    $this->msg['field_4'] = "$amount";
                } else {
                    $this->msg['field_39'] = '96';
                }
            } else {
                $this->msg['field_39'] = '15';
            }

            //Cash Payment

            //Check if its employee with ID //No tag Use
            if ($this->msg['category'] == 6 && !empty($this->msg['employee_id'])) {

            } else if ($this->msg['category'] == 6 && $this->msg['field_4'] == '0') {
                $this->msg['field_39'] = '12';
                $this->msg['Message'] = 'Invalid transaction';
            } else if ($this->msg['category'] == 6) {
                $this->msg['field_39'] = '12';
                $this->msg['Message'] = 'Invalid transaction';
            } else {
                $type = 2;
                $nature = 2;
                $mode = 1;
                // if($this->msg['location_name'] == "automotora"){
                //     $type = 3;
                // }
                if ($this->record_temporary_payment_message($this->msg, $type, $nature, $mode, $operator, $receiptNumber, $source, $net, null, null, 0, $trnx_No, $onoff)) //Record cash payments
                {
                    $this->msg['field_39'] = '00';
                    $this->msg['Message'] = 'Success';

                } else {
                    if ($this->msg['messageCode'] == '1062') {
                        $this->msg['field_39'] = '94';
                    } else {
                        $this->msg['field_39'] = '05';
                        $this->msg['Message'] = 'Error';
                    }
                }
            }



            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);




        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }
    public function reversal_transactions(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $msg = null;
            $this->msg = [];
            $jsonMessage = json_encode([
                'custom_message' => 'Request:',
                'data' => $request // Example data from the request
            ]);
            $this->logger->log($jsonMessage);
            foreach ($request->all() as $key => $value) {
                $this->msg[$key]= $value;
                $this->msg[$key]['MTI'] = "0430";

                $output = $this->verify_message_source($this->msg[$key]['field_58'], null);
                $operator = $output[0]->id;
                $operatorName = $output[0]->full_name;
                $field_7 = $this->msg[$key]['field_7'];
                $output = $this->check_transaction_existance($operator,$field_7);
                if(empty($output)){
                    $this->msg[$key]['field_39'] = "25";
                }else{
                    $this->msg[$key]['field_39'] = "99";
                }
            }
            $jsonMessage = json_encode([
                'custom_message' => 'Response:',
                'data' => $this->msg // Example data from the request
            ]);
            $this->logger->log($jsonMessage);
            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }


    public function offline_transaction(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $msg = null;
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {

            $this->msg = [];
            $jsonMessage = json_encode([
                'custom_message' => 'Request:',
                'data' => $request->data // Example data from the request
            ]);
            $this->logger->log($jsonMessage);
            foreach ($request->all() as $key => $value) {
                $this->msg[$key]= $value;
                $this->msg[$key]['MTI'] = "0210";

                $output = $this->verify_message_source($this->msg[$key]['field_58'], null);
                $operator = $output[0]->id;
                $operatorName = $output[0]->full_name;

                $onoff = null;
                if (isset($this->msg[$key]['operator_location'])) {
                    $onoff = $this->msg[$key]['operator_location'];
                }
                if ($onoff == null) {
                    $onoff = 2;
                }

                $net = "Online";
                $this->msg[$key]['field_4'] = sprintf('%.2f', $this->msg[$key]['field_4'], 2);
                $amount = $this->msg[$key]['field_4'];
                $type = 1;
                $nature = 2;
                $mode = 1;
                $source = 1;
                $receiptNumber = '';
                $trnx_No = $this->msg[$key]['field_7'];

                if (!isset($this->msg[$key]['zone_id'])) {
                    $this->msg[$key]['zone_id'] = 0;
                }


                if (isset($this->msg[$key]['longitude'], $this->msg[$key]['latitude '])) {
                    $this->msg[$key]['latitude'] = $this->msg[$key]['latitude'];
                    $this->msg[$key]['longitude'] = $this->msg[$key]['longitude'];
                } else {
                    $this->msg[$key]['latitude'] = '0.0';
                    $this->msg[$key]['longitude'] = '0.1';
                }
                if (!isset($msg['penalty'], $msg['fine_status'])) {
                    $msg['penalty'] = 0;
                    $msg['fine_status'] = 1;
                }
                if ($operator != null) {
                    $receiptNumber = $this->generate_daily_receipt();
                    $this->msg[$key]['field_37'] = $receiptNumber;
                    if ($receiptNumber != null) {
                        //Record Payment
                        $this->msg[$key]['field_4'] = "$amount";
                    } else {
                        $this->msg[$key]['field_39'] = '96';
                    }
                } else {
                    $this->msg[$key]['field_39'] = '15';
                }


                $type = 2;
                $nature = 2;
                $mode = 1;

                if ($this->record_temporary_payment_message($this->msg[$key], $type, $nature, $mode, $operator, $receiptNumber, $source, $net, null, null, 0, $trnx_No, $onoff)) //Record cash payments
                {
                    $this->msg[$key]['field_39'] = '00';
                    $this->msg[$key]['Message'] = 'Success';

                } else {
                    if ($this->msg[$key]['messageCode'] == '1062') {
                        $this->msg[$key]['field_39'] = '94';
                    } else {
                        $this->msg[$key]['field_39'] = '05';
                        $this->msg[$key]['Message'] = 'Error';
                    }
                }

            }
            $jsonMessage = json_encode([
                'custom_message' => 'Response:',
                'data' => $this->msg // Example data from the request
            ]);
            $this->logger->log($jsonMessage);
            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);


        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }

    public function summary_verification(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $username = $this->msg['field_42'];
            $pin = $this->msg['field_52'];
            $sql = 'SELECT  id FROM `operators`
            WHERE `password`=:pin AND `username`=:username';


            $data = ['pin' => $pin, 'username' => $username];
            $result = $this->db_select($sql, $data);
            if (!empty($result[0])) {
                $this->msg['field_39'] = '00';
            } else {
                $this->msg['field_39'] = '91';
            }

            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }catch (Exception $e){
            $this->exceptionErrorMsg = __LINE__ . '-' . $e->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }

    }

    public function get_info_detail($trainID)
    {
        $sql = 'SELECT `train_Number`,directions.name,line_Code FROM `trains`
          INNER JOIN train_routes ON trains.route_ID=train_routes.id
          INNER JOIN train_lines ON train_lines.id=train_routes.train_line_ID
          INNER JOIN directions ON directions.ID=train_routes.train_direction_id
          WHERE trains.id=:trainID';
        $data = [ 'trainID' => $trainID];
        $result = $this->db_select($sql,$data);
        return $result;
    }

    public function get_tag_id($card_number)
    {
        $sql = 'SELECT `tag_id` FROM `cards`
          WHERE card_number=:card_number';
        $data = [ 'card_number' => $card_number];
        $result = $this->db_select($sql,$data);
        return $result;
    }

    public function getZoneStationID($zoneID, $postion, $trainID)
    {
        $direction = $this->get_info_detail($trainID);
        if (!empty($direction)) {
            $direction = $direction[0]->id;
        }

        if ($direction == 1) { // Asending
            $sql = 'SELECT MIN(`id`) AS `stationID` FROM `train_stations` WHERE `zone_st`=:zoneID';

            $data = [ 'zoneID' => $zoneID];
            $result = $this->db_select($sql,$data);

            return $result[0]->station_ID;
        } else {
            $sql = 'SELECT MAX(`id`) AS `stationID` FROM `train_stations` WHERE `zone_st`=:zoneID';

            $data = [ 'zoneID' => $zoneID];
            $result = $this->db_select($sql,$data);

            return $result[0]->station_ID;
        }
    }


    public function record_temporary_payment_message($msg, $type, $nature, $mode, $operator, $receiptNumber, $source, $net, $account, $card, $status, $trnxNo, $onoff)
    {

        if (!isset($msg['penalty']) || $msg['penalty']=='' ) {
            $msg['penalty'] = 0;
        }

        if (!isset($msg['fine_status']) || $msg['fine_status']==0 ) {
            $msg['fine_status'] = '1';
        }

        if (!isset($msg['fromStop'])) {
            if (isset($msg['zone_id'])) {
                $msg['fromStop'] = $this->getZoneStationID($msg['zone_id'], 'S', $msg['train_id']);
            }
        } else {
            if (empty($msg['fromStop'])) {
                if (isset($msg['zone_id'])) {
                    if ($msg['zone_id'] == '1' || $msg['zone_id'] == '2' || $msg['zone_id'] == '3') {
                        $msg['fromStop'] = $this->getZoneStationID($msg['zone_id'], 'S', $msg['train_id']);
                    }

                }
            }
        }
        if (!isset($msg['toStop'])) {
            if (isset($msg['zone_id'])) {
                $msg['toStop'] = $this->getZoneStationID($msg['zone_id'], 'E', $msg['train_id']);
            }
        } else {
            if (empty($msg['toStop'])) {
                if (isset($msg['zone_id'])) {
                    if ($msg['zone_id'] == '1' || $msg['zone_id'] == '2' || $msg['zone_id'] == '3') {
                        $msg['toStop'] = $this->getZoneStationID($msg['zone_id'], 'E', $msg['train_id']);
                    }

                }
            }
        }

        $froStationId = $this->get_station_id($msg['field_68']); //POS

        if (!empty($froStationId)) {
            if ($froStationId[0]->device_type == 'P') {
                if ($froStationId[0]->station_id > 0) {
                    if ($froStationId[0]->station_id != $msg['fromStop']) {
                        $msg['fromStop'] = $froStationId[0]->station_id;
                    }
                    if ($onoff == 1) {
                        $onoff = 2;
                    }
                    if ($onoff == 0) {
                        if ($this->msg['field_61'] == '9009') {
                            $onoff = 2;
                            $msg['fromStop'] = $froStationId[0]->station_id;
                        }
                    }
                } else {
                    //check if its maputo or caben B and do exception
                    if ($onoff == 2) {
                        $chkResult = $this->check_station_if_estacao($msg['fromStop']);
                        if (!empty($froStationId[0]->type)) {
                            if (trim($froStationId[0]->type) != 'Estacao' && trim($froStationId[0]->type) != 'estacao' && strcmp('Esta', trim($chkResult)) === false && strcmp('esta', trim($chkResult)) === false) {
                                if ($msg['fromStop'] != '2') {
                                    $onoff = 1;
                                }
                            }
                        } else {
                            $chkResult = $this->check_station_if_estacao($msg['fromStop']);
                            if (!empty($chkResult)) {
                                if (trim($chkResult) != 'Estacao' && trim($chkResult) != 'estacao' && strcmp('Esta', trim($chkResult)) === false && strcmp('esta', trim($chkResult)) === false) {
                                    if ($msg['fromStop'] != '2') {
                                        $onoff = 1;
                                    }
                                }
                            }
                        }
                    }
                    //for topup
                    if ($onoff == 0) {
                        if ($this->msg['field_61'] == '9009') {
                            $onoff = 1;
                        }
                    }
                }
            } else {

                if ($onoff == 0) {
                    if ($this->msg['field_61'] == '9009') {
                        $onoff = 2;
                    }
                    $msg['fromStop'] = $froStationId[0]->station_id;
                }
            }
        }

        $this->accountDrId = 0;
        $this->accountCrId = 0;
        if ($mode == 1) { //Cash Payment
            $this->accountDrId = $this->get_account_type_id($operator, "O", $this->OPERATOR_CASH_ACCOUNT_TYPE);
            $this->accountCrId = $this->CFMCAS0; //$this->get_account_type_id($this->CFMCAS0,"I");
        } else {
            if ($this->msg['field_61'] == '9006') { //buy Ticket payment cash
                if (isset($msg['employee_id']) && !empty($msg['employee_id'])) {
                    $this->accountDrId = $this->get_account_type_id($account, "E", null);
                    $this->accountCrId = $this->CFMEMP4; //$this->get_account_type_id("CFMGL","I");
                } else {
                    $this->accountDrId = $this->get_account_type_id($account, "C", null);
                    $this->accountCrId = $this->CFMCAS0;
                }

            } else if ($msg['field_61'] == '9007') {
                if (isset($msg['employee_id']) && !empty($msg['employee_id'])) {
                    $this->accountDrId = $this->get_account_type_id($account, "E", null);
                    $this->accountCrId = $this->CFMCAR1;
                } else {
                    $this->accountDrId = $this->get_account_type_id($account, "C", null);
                    if ($this->accountDrId == '0' || empty($this->accountDrId)) {
                        $this->accountDrId = $this->get_account_type_id($account, "X", null);
                    }
                    $this->accountCrId = $this->CFMCAR1;
                }
            } else if ($msg['field_61'] == '9008') { //Paymet emplyee ID
                if (isset($msg['employee_id']) && !empty($msg['employee_id'])) {
                    $this->accountDrId = $this->get_account_type_id($account, "E", null);
                    $this->accountCrId = $this->CFMEMP4;
                } else {
                    $this->accountDrId = $this->get_account_type_id($account, "C", null);
                    $this->accountCrId = $this->CFMCAR1;
                }

            } else if ($msg['field_61'] == '9009') { //Topup
                if (isset($msg['employee_id']) && !empty($msg['employee_id'])) {
                    $this->accountCrId = $this->get_account_type_id($account, "E", null);
                } else {
                    $this->accountCrId = $this->get_account_type_id($account, "C", null);
                }

                $this->accountDrId = $this->get_account_type_id($operator, "O", $this->OPERATOR_TOPUP_ACCOUNT_TYPE);
            }
        }




        $date=date("Y-m-d h:i:s");
        $deviceID=0;
        if(isset($msg['field_68'])){
            $device_id = $this->get_device_ID($msg['field_68']);
            $deviceID = $device_id;
        }
        $status = false;
        $status = "0";

        $currency = null;
        if (isset($msg['currency'])) {
            $currency = $msg['currency'];
        }
        if ($currency == null) {
            $currency = 1;
        }

        $rate = null;
        if (isset($msg['rate'])) {
            $rate = $msg['rate'];
        }
        if ($rate == null) {
            $rate = 1;
        }
        $paid_amount = null;
        if (isset($msg['paid_amount'])) {
            $paid_amount = $msg['paid_amount'];
        }
        if ($paid_amount == null) {
            $paid_amount = round($msg['field_4']);
        }

        $sql = 'INSERT INTO `ticket_transactions`
				(
					`trnx_Date`,
					`trnx_Time`,
					`trnx_Number`,
					`trnx_Type`,
					`trnx_Nature`,
					`trnx_Mode`,
					`acc_Number`,
					`card_Number`,
					`trnx_Amount`,
					`device_Number`,
					`operator_ID`,
					`trnx_Receipt`,
					`trnx_Source`,
					`signature`,
					`class_ID`,
					`Train_ID`,
					`seat_No`,
					`station_From`,
					`station_To`,
					`longitude`,
					`latitude`,
					`category_ID`,
					`net_Status`,
					`zone_ID`,
					`trnx_Quantity`,
					`trnx_Status`,
					`on_Off`,
					`fine_Amount`,
					`fine_Status`,
					`int_debit_account_id`,
					`int_credit_account_id`,
					`extended_trnx_type`,
					`customer_name`,
					`customer_id`,
                    `currency`,
                    `rate`,
                    `paid_amount`
				)
				VALUES
				(
					:tranxdt,
					:tranxtime,
					:trnxNo,
					:type,
					:nature,
					:mode,
					:acc,
					:card,
					:amount,
					:device,
					:operator,
					:receipt,
					:source,
					:field_7,
					:class,
					:train,
					:seat,
					:station1,
					:station2,
					:long,
					:lat,
					:cate,
					:net,
					:zone,
					:qnty,
					:stat,
					:onoff,
					:penalty,
					:fine,
					:debitAccount,
					:creditAccount,
					:extTrnxType,
					:customerName,
					:customerId,
                    :currency,
                    :rate,
                    :paid_amount
				)';

        $msg['field_4'] = round($msg['field_4']);

        if (!isset($msg['customer'])) {
            $msg['customer'] = "";
        }
        if (!isset($msg['customer_id'])) {
            $msg['customer_id'] = "";
        }
        if (!isset($msg['seat']) || $msg['seat']=="") {
            $msg['seat'] = "0";
        }

        $accountCrId = '0';
        $parameters = array(
            'field_7' => $msg['field_7'] . $operator . $deviceID,
            'tranxdt' => $msg['field_7'],
            'tranxtime' => $msg['field_7'],
            'amount' => $msg['field_4'],
            'type' => $type,
            'nature' => $nature,
            'mode' => $mode,
            'acc' => $account,
            'card' => $card,
            'device' => $msg['field_68'],
            'operator' => $operator,
            'receipt' => $receiptNumber,
            'source' => $source,
            'train' => $msg['train_id'],
            'class' => $msg['class_id'],
            'seat' => $msg['seat'],
            'long' => $msg['longitude'],
            'lat' => $msg['latitude'],
            'cate' => $msg['category'],
            'net' => $net,
            'zone' => $msg['zone_id'],
            'qnty' => $msg['quantity'],
            'station1' => $msg['fromStop'],
            'station2' => $msg['toStop'],
            'trnxNo' => $trnxNo,
            'stat' => $status,
            'onoff' => $onoff,
            'penalty' => $msg['penalty'],
            'fine' => $msg['fine_status'],
            'debitAccount' => $accountCrId,
            'creditAccount' => $accountCrId,
            'extTrnxType' => $msg['field_61'],
            'customerName' => $msg['customer'],
            'customerId' => $msg['customer_id'],
            'currency' => $currency,
            'rate' => $rate,
            'paid_amount' => $paid_amount
        );
        // $status = $this->execute_query_transaction_post($sql, $parameters);
        try {
            $result = $this->db_select($sql,$parameters);
            $status = true;

        } catch (Exception $e) {
            $this->msg['error_Message'] = $e->getMessage();
            $status = false;

        }
        return $status;
    }


    public function get_device_ID($imei)
    {

        $sql = 'SELECT id FROM `device_details`
         WHERE `device_imei`=:imei AND activation_status="A"';

        $data = [ 'imei' => $imei];
        $result = $this->db_select($sql,$data);

        return $result[0]->id;
    }


    public function get_account_type_id($accountOrId, $mode, $type)
    {

        $sql = "SELECT id FROM `customer_accounts` ";
        if (!isset($type)) {
            if ($mode == OPERATOR_MODE) {  //Operator
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				 AND `customer_id`=:accountNumberOrId LIMIT 1";
            } else if ($mode == CUSTOMER_MODE) { //Customer
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `account_number`=:accountNumberOrId	LIMIT 1";
            } else if ($mode == INTERNAL_MODE) { //Internal GL
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `account_number`=:accountNumberOrId	LIMIT 1";
            } else if ($mode == EMPLOYEES_MODE) { //Employess
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `account_number`=:accountNumberOrId	LIMIT 1";
            } else if ($mode == EXTERNAL_ORGANIZATION_MODE) {  //Extenal Organization
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `customer_id`=:accountNumberOrId	LIMIT 1";
            }
        } else {
            if ($mode == OPERATOR_MODE) {
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `customer_id`=:accountNumberOrId AND customer_account_package_type=:accountType LIMIT 1";
            } else if ($mode == CUSTOMER_MODE) {
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `account_number`=:accountNumberOrId AND customer_account_package_type=:accountType	LIMIT 1";
            } else if ($mode == INTERNAL_MODE) {
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `account_number`=:accountNumberOrId AND customer_account_package_type=:accountType	LIMIT 1";
            } else if ($mode == EMPLOYEES_MODE) {
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `account_number`=:accountNumberOrId AND customer_account_package_type=:accountType	LIMIT 1";
            } else if ($mode == EXTERNAL_ORGANIZATION_MODE) {  //External Organization
                $sql = $sql . "	WHERE `accounts_usage_type`=:usageType
				AND `customer_id`=:accountNumberOrId	LIMIT 1";
            }
        }

        try {
            if (isset($type)) {
                $data = ['usageType' => $mode, 'accountNumberOrId' => $accountOrId, 'accountType' => $type];
            } else {
                $data = ['usageType' => $mode, 'accountNumberOrId' => $accountOrId];
            }
            $result = $this->db_select($sql, $data);
            if (!empty($result)) {
                return $result[0]->id;
            }
            return 0;
        } catch (\Exception $e) {
            Log::channel('pos')->error(json_encode($this->errorPayload($e)));
        }
        return 0;
    }


    public function check_station_if_estacao($fromStation)
    {
        $sql = "SELECT IFNULL(`s`.`station_Type_ERP`,'') AS `type`
		FROM  `train_stations` AS `s`
		WHERE `s`.`id` =:fromStation";
        $data = [ 'fromStation' => $fromStation];
        $result = $this->db_select($sql,$data);
        // $result = $this->fetch_query_result($sql, array('fromStation' => $fromStation), false);
        if (empty($result) || !isset($result)) {
            return null;
        }
        return $result[0]->type;
    }

    public function get_station_id($deviceIMEI)
    {
        $sql = "SELECT `d`.`station_id`,`d`.`device_type`,
		`d`.`On_Off`,IFNULL(`s`.`station_Name`,'') AS `station_name`,
		IFNULL(`s`.`station_Type_ERP`,'') AS `type`
		FROM `device_details` AS `d` LEFT JOIN `train_stations` AS `s`
		ON `d`.`station_ID`=`s`.`id`
		WHERE `d`.`device_imei`=:device_imei";

        $data = [ 'device_imei' => $deviceIMEI];
        $result = $this->db_select($sql,$data);

        return $result;
    }

    public function check_if_employee_allowed_to_travel_now($employeeNumber, $trainId, $isZoneTrain)
    {
        $status = false;

        $sql = "SELECT   IFNULL(IF((`trnx_Date` IS NULL OR `trnx_Time` IS NULL),DATE_ADD(NOW(), INTERVAL -10 DAY),MAX(CAST(CONCAT(`trnx_Date`, ' ', `trnx_Time`) AS DATETIME))),DATE_ADD(NOW(), INTERVAL -10 DAY)) AS `lastTransaction`
          FROM `card_customers` AS `C`
              INNER JOIN `tbl_customer_account` AS `A`  ON `A`.`cust_id` = `C`.`id`
              INNER JOIN `tbl_customer_account_package_type` AS `P` ON `A`.`customer_account_package_type` = `P`.`id`
              INNER JOIN `tbl_transactions` AS `T` ON `A`.`acc_num` = `T`.`acc_Number`
          WHERE
              `A`.`acc_status` = 'A'
              AND `C`.`emp_ID`=:employeeId
              AND `T`.`category_ID`= 6
              AND `T`.`train_id`=:trainId
          LIMIT 1";

        //$this->log_event('Sql travel_now', $sql);
        try {
            $result = $this->db->prepare($sql);
            $result->bindParam(':employeeId', $employeeNumber);
            $result->bindParam(':trainId', $trainId);
            $result->execute();
            $result = $result->fetch();
            if (!empty($result[0])) {
                $trainLastTrxDateTime = $this->get_train_last_transaction($trainId);
                $dateObj = new DateTime($result[0]);
                $dataObj = $dateObj->diff(new DateTime($trainLastTrxDateTime));
                if ($isZoneTrain) {
                    if ($dataObj->h < $this->ZONA_TRAIN_TIME_LIMIT_HRS) {
                        return false;
                    }

                } else {
                    if ($dataObj->h < $this->NORMAL_TRAIN_TIME_LIMIT_HRS) {
                        return false;
                    }
                }

            }
            return true;
        } catch (Exception $e) {
        }
        return $status;
    }


    public function operator_transaction_scanning(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $ticket_number = $request->data['ticket_number'];
            $trnx_time = $request->data['trnx_time'];
            $station_id = $request->data['station_id'];
            $operator_id = $request->data['operator_id'];



            if(!empty($station_id)){
                $transactionsDetails = DB::table('ticket_transactions')
                    ->where('signature',$ticket_number)
                    ->select('station_from','station_to')
                    ->first();
                if($transactionsDetails) {

                    if($transactionsDetails->station_from > $transactionsDetails->station_to){
                        if(($station_id >= $transactionsDetails->station_to) && ($station_id <= $transactionsDetails->station_from)){
                            DB::table('ticket_transactions')
                                ->where('signature', $ticket_number)
                                ->update(['validation_status' => '1','validator' => $operator_id]);
                            $responseObject->message = "Invalid Transactions";
                            $responseObject->response_code = \HttpResponseCode::SUCCESS;
                            $responseObject->code = '200';
                            $responseObject->trnx_status = '00';
                            $responseObject->status = 'success';
                            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                            $dataPayload->data = $encryptedResponse;
                            return response()->json($dataPayload);
                        }else{
                            DB::table('ticket_transactions')
                                ->where('signature', $ticket_number)
                                ->update(['validation_status' => '2','validator' => $operator_id]);
                            $responseObject->message = "Invalid Transactions";
                            $responseObject->response_code = \HttpResponseCode::BAD_REQUEST;
                            $responseObject->code = '200';
                            $responseObject->trnx_status = '99';
                            $responseObject->status = 'Failed';
                            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                            $dataPayload->data = $encryptedResponse;
                            return response()->json($dataPayload);

                        }
                    }else{
                        if(($station_id >= $transactionsDetails->station_from) && ($station_id <= $transactionsDetails->station_to)){
                            DB::table('ticket_transactions')
                                ->where('signature', $ticket_number)
                                ->update(['validation_status' => '1','validator' => $operator_id]);
                            $responseObject->message = "Valid Transactions";
                            $responseObject->response_code = \HttpResponseCode::SUCCESS;
                            $responseObject->code = '200';
                            $responseObject->trnx_status = '99';
                            $responseObject->status = 'success';
                            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                            $dataPayload->data = $encryptedResponse;
                            return response()->json($dataPayload);

                        }else{
                            DB::table('ticket_transactions')
                                ->where('signature', $ticket_number)
                                ->update(['validation_status' => '2','validator' => $operator_id]);
                            $responseObject->message = "invalid Transactions";
                            $responseObject->response_code = \HttpResponseCode::SUCCESS;
                            $responseObject->code = '200';
                            $responseObject->trnx_status = '99';
                            $responseObject->status = 'Success';
                            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                            $dataPayload->data = $encryptedResponse;
                            return response()->json($dataPayload);
                        }
                    }
                }else{
                    $responseObject->message = "invalid Transactions";
                    $responseObject->response_code = \HttpResponseCode::SUCCESS;
                    $responseObject->code = '200';
                    $responseObject->trnx_status = '99';
                    $responseObject->status = 'Success';
                    $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                    $dataPayload->data = $encryptedResponse;
                    return response()->json($dataPayload);
                }}else{
                $transactionsDetails = DB::table('ticket_transactions')
                    ->where('signature',$ticket_number)
                    ->select('station_from','station_to')
                    ->get();
                if($transactionsDetails) {
                    DB::table('ticket_transactions')
                        ->where('signature', $ticket_number)
                        ->update(['validation_status' => '1','validator' => $operator_id]);
                    $responseObject->message = "Valid Transactions";
                    $responseObject->response_code = \HttpResponseCode::SUCCESS;
                    $responseObject->code = '200';
                    $responseObject->trnx_status = '00';
                    $responseObject->status = 'Success';
                    $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                    $dataPayload->data = $encryptedResponse;
                    return response()->json($dataPayload);
                }else{
                    $responseObject->message = "Invalid Transactions";
                    $responseObject->response_code = \HttpResponseCode::SUCCESS;
                    $responseObject->code = '200';
                    $responseObject->trnx_status = '99';
                    $responseObject->status = 'Failed';
                    $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
                    $dataPayload->data = $encryptedResponse;
                    return response()->json($dataPayload);

                }

            }



        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }

    public function normal_transaction(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);

        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $trnx_date = $request->data['trnx_date'];
            $trnx_time = $request->data['trnx_time'];
            $trnx_number = $request->data['trnx_number'];
            $trnx_type = $request->data['trnx_type'];
            $trnx_Nature = $request->data['trnx_Nature'];
            $trnx_mode = $request->data['trnx_mode'];
            $acc_number = $request->data['acc_number'];
            $card_number = $request->data['card_number'];
            $trnx_amount = $request->data['trnx_amount'];
            $fine_amount = $request->data['fine_amount'];
            $fine_status = $request->data['fine_status'];
            $device_number =$request->data['device_number'];
            $operator_id = $request->data['operator_id'];
            $trnx_status = $request->data['trnx_status'];
            $trnx_receipt =$request->data['trnx_receipt'];
            $trnx_source =$request->data['trnx_source'];
            $reference_number =$request->data['reference_number'];
            $signature = $request->data['signature'];
            $zone_id = $request->data['zone_id'];
            $class_id =$request->data['class_id'];
            $train_id = $request->data['train_id'];
            $category_id = $request->data['category_id'];
            $seat_no = $request->data['seat_no'];
            $station_from = $request->data['station_from'];
            $station_to = $request->data['station_to'];
            $net_status = $request->data['net_status'];
            $trnx_quality = $request->data['trnx_quality'];
            $on_off =$request->data['on_off'];
            $extended_trnx_type = $request->data['extended_trnx_type'];
            $customer_name = $request->data['customer_name'];
            $tag_id = $request->data['tag_id'];



            DB::beginTransaction();


            $transaction = new TicketTransaction();
            $transaction->trnx_date = $trnx_date;
            $transaction->trnx_time = $trnx_time ;
            $transaction->trnx_number = $trnx_number;
            $transaction->category_id =  $category_id;
            $transaction->trnx_amount =  $trnx_amount;
            $transaction->class_id = $class_id;
            $transaction->station_from = $station_from;
            $transaction->station_to = $station_to;
            $transaction->zone_id = $zone_id;
            $transaction->train_id =  $train_id;
            $transaction->operator_id = $operator_id;
            $transaction->trnx_source = $trnx_source;
            $transaction->signature = $signature;
            $transaction->device_number =  $device_number;
            $transaction->trnx_receipt = $trnx_receipt;
            $transaction->extended_trnx_type = $extended_trnx_type;
            $transaction->save();
            DB::commit();
            $responseObject->message = "Successfully transaction Created";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->code = '200';
            $responseObject->trnx_status = '00';
            $responseObject->status = 'success';
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);



        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }


    public function operator_summary(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $msg = null;
        $this->msg = $request->data;
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;

        try {
            $this->msg['MTI'] = "0630";
            $dateTime = now();
            $params = $this->get_app_parameters($this->msg['field_68'], $this->msg['field_69']);
            $operator = $this->verify_message_source($this->msg['field_42'], null);
            $deviceID = $params[0]->id;
            $operatorID = $operator[0]->id;


            $this->msg['field_39'] = '00';


            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->msg = $this->msg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }
    }



    public function automotora_prices(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            $operatorCategories = DB::table('tbl_price_table_automotora')->get();


            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->operatorCategories = $operatorCategories;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        }
    }

    public function normal_prices(Request $request){
        $request->validate([
            'data' =>'required',
            'key' => 'required'
        ]);
        $pwKey = $request->key;
        $dataPayload = (object)null;
        $responseObject = (object) null;
        try {
            $normalprices = DB::table('tbl_price_table')->get();


            $responseObject->message = "";
            $responseObject->response_code = \HttpResponseCode::SUCCESS;
            $responseObject->normalprices = $normalprices;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $this->exceptionErrorMsg = __LINE__ . '-' . $th->getMessage();
            $responseObject->message =  $this->exceptionErrorMsg;
            $responseObject->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $responseObject->msg =  $this->exceptionErrorMsg;
            $encryptedResponse = EncryptionHelper::encrypt(json_encode($responseObject), $pwKey);
            $dataPayload->data = $encryptedResponse;
            return response()->json($dataPayload);

        }
    }

    public function deviceLogout(Request $request){
        validator([
            'data' => 'required'
        ]);
        $logoutResponse = (object) null;
        $privateKey = null;
        try {
            $headers = getallheaders();
            $stringEncryptedByteAndroidId = $headers['Encrypted-Android-Id'] ?? null;
            $jsonEncryptedAndroidId = json_decode($stringEncryptedByteAndroidId);
            FacadesLog::info("Payload", ["data" => $request->data]);
            FacadesLog::info("Header", ["encryptedByteAndroidId" => $jsonEncryptedAndroidId->data]);
            if (!$jsonEncryptedAndroidId) {
                return response()->json(['error' => "AndroidId Not Provided"]);
            }
            $decryptedAByteAndroidId = AsymmetricEncryption::decryptAsymmetric($jsonEncryptedAndroidId->data);
            FacadesLog::info('AndroidId', ["decryptedAByteAndroidId" => $decryptedAByteAndroidId]);
            $keys = DB::table('keys')
                ->select('key')
                ->where(['android_id' => $decryptedAByteAndroidId])->first();
            if (!$keys) {
                return response()->json(['error' => "No Key Found"]);
            }
            $privateKey = $keys->key;
            $oldToken = JWTAuth::getToken();
            $oldTokenString = (string) $oldToken;
            // Invalidate the token
            FacadesLog::info("oldToken", ["oldToken" => $oldTokenString]);
            JWTAuth::invalidate($oldTokenString);
            $logoutResponse->message = "Logout Successfully";
            $logoutResponse->response_code = \HttpResponseCode::SUCCESS;
            FacadesLog::info("privateKey", ["privateKey" => $privateKey]);
            $res = EncryptionHelper::encrypt(json_encode($logoutResponse), $privateKey);
            FacadesLog::info("LOGOUT", ["Response" => $logoutResponse]);
            $last = (object) null;
            $last->data = $res;
            return response()->json($last);
        } catch (\Exception $e) {
            FacadesLog::error("LOGOUT", ["ERROR" => $e]);
            $logoutResponse->message = "Logout Failed";
            $logoutResponse->response_code = \HttpResponseCode::INTERNAL_SERVER_ERROR;
            $res = EncryptionHelper::encrypt(json_encode($logoutResponse), $privateKey);
            $last = (object) null;
            $last->data = $res;
            return response()->json($last);
        }
    }




}
