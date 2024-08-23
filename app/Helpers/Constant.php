<?php

class HttpResponseCode
{
    const SUCCESS = 200;
    const CREATED = 201;
    const NO_CONTENT = 204;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const INTERNAL_SERVER_ERROR = 500;
}

class ResponseMessages
{
    // General response messages
    const OPERATOR_ACCOUNT_CREATION_FAILED = 'Failed to create operator account';
    const OPERATOR_CREATED_SUCCESSFULLY = 'New Operator created successfully';
    const OPERATOR_CREATION_FAILED = 'Failed to create operator';
}


const VALIDATION_FAIL = "Validation failed";
const VALIDATION_ERROR = "Error";

const USERNAME_AND_PASSWORD_REQUIRED = "Username And Password Is Required";

// Common Responses
const LOGIN_SUCCESSFULLY = "Login successfully";
const SUCCESS_RESPONSE = "Success";
const WARNING_RESPONSE = "Warning";
const ERROR_RESPONSE = "Error";
const DATA_RETRIEVED = "Data retrieved Successfully";
const DATA_SAVED = "Data saved Successfully";
const DATA_UPLOADED = "Data upload Successfully";
const DATA_EXIST = "Data already exists";
const DATA_UPDATED = "Data updated Successfully";
const DATA_DELETED = "Data deleted Successfully";
const DATA_NOT_FOUND = "Data do not exist";
const SERVER_ERROR = "Internal Server Error";
const UNPROCESSABLE = "Unprocessable Entity";
const NOT_FOUND = "Not Found";
const FORBIDDEN = "Forbidden";
const UNAUTHENTICATED = "Unauthenticated";
const UNAUTHORIZED = "Unauthorized";
const BAD_REQUEST = "Bad Request";
const ACCESS_DENIED = "Access Denied";
const SOMETHING_WENT_WRONG = "Something went wrong, please contact admin";

const USER_NOT_EXIST = "User not exists";
const RELATED_DATA_ERROR = "This data has existing relationships with other records and cannot be deleted.";

const INVALID_DATA = 'Invalid data, please contact admin for support';
const UNEXPECTED_CASE = 'Unexpected case triggered, please contact admin for support';




const CREDITO = 'CR';
const DEBITO = 'DB';

//Train DIrection
const ASC = 'ASC';
const DESC = 'DESC';

const ACTIVE_STATUS = 'A';
const INACTIVE_STATUS = 'I';
const BLOCKED_STATUS = 'B';

const INT_ACTIVE = 1;
const INT_INCTIVE = 0;

//CFM CLASSES
const FIRST_CLASS = 'f1';
const SECOND_CLASS = 'f2';
const THIRD_CLASS = 'f3';

//ZONE
const FIRST_ZONE = 'zone1';
const SECOND_ZONE = 'zone2';
const THIRD_ZONE = 'zone3';


//TRANSACTION TYPES
const TRAIN_CASH_PAYMENT = "9006"; //used in transaction
const CARD_PAYMENT = "9007"; //used in transaction
const EMPLOYEE_ID_PAYMENT = "9008";
const TOP_UP_CARD = "9009"; //used in transaction
const REGISTRATION = "9010";
const CARGO = "9019";
const MOBILE_APP_TRANSACTIONS = "9102";
const ONLINE_CREDIT_TRANSACTIONS = "9103";
const ONLINE_PAYMENT = "9104";


//Cargo Customer Types
const INDIVIDUAL_CUSTOMER = '00';
const ORGANIZATION_CUSTOMER = '11';

// Cargo Service Type

const CARGO_SENDER = 'S';
const CARGO_RECEIVER = 'R';
const CARGO_SENDER_AND_RECEIVER = 'B';

//Cargo Customer Payment Type
const PREPAID = '00';
const POSTPAID = '11';


//Cargo Customer
const CUST_CONSTANT = 'CUST';

//Transaction status
const PAID = "00";
const CANCELLED = "02";
const REVERSED = "99";

//CHANNELS
const PORTAL = 'P';
const KIOSK = 'K';
const APP = 'A';

const VALIDATION_APP = 'VA';
const POS = 'POS';

//News Status
const PUBLISHED = 'published';
const CREATED = 'created';
const UNPUBLISHED = 'unpublished';

//Permission Actions
const VIEW = 'V';
const CREATE = 'C';
const EDIT = 'E';
const DELETE = 'D';
const BLOCK = 'B';
const UNBLOCK = 'U';
const APPROVE = 'A';
const CANCEL = 'Y';
const EXECUTE = 'X';
const SUBMIT = 'S';
const ACTIVATE = 'T';
const DEACTIVATE = 'Z';
const ATTACH = 'AT';
const DEATTACH = 'DEA';
const PRINT_DOC = 'P';

const CONFIGURE_TRAIN = 'CT';
const GENERATE_SEAT_ACTION = 'GSA';

//Roles
const ADMIN = "ADM";
const FISCAL_OFFICER = 'FISC';
const FISCAL_MASTER = 'FISM';
const FINANCE_MANAGER = "FIN_MAN";
const FISCAL_VALIDATOR = "FISC_VAL";
const REVISER_MASTER = "REV_MAS";
const CONDUCTOR = "COND";
const OPERADOR = 'OPE';
const REVISOR = 'REV';
const SERVICO_DE_TRANSPORTE = 'STP';


//Operator Category

const CONDUCTOR_OPERATOR = 'COND_OP';
const INSPECTOR = 'INSP_OP';
const CARGO_MASTER = 'CARG_MAS';
const REPORTS_PRINTING = 'REPO_PR';
const CONDUCTOR_REPORTS = 'COND_REP';

const ALL_OPERATOR_CAT = 'ALL_OP';


// Modules
const DASHBOARD = 'dashboard';
const NOTIFICATION = 'notification';
const CARDS = 'cards';
const TRAIN_CLASS = 'train-class';
const COACHES = 'coaches';
const COACH_LAYOUTS = 'coach-layouts';
const COACH_MANUFACTURES = 'coach-manufactures';
const TRAIN_LAYOUTS = 'train-layouts';
const WAGON_CONFIGURATIONS = 'wagon-configurations';
const COLLECTIONS = 'collections';
const OPERATOR_COLLECTION = 'operator-collection';
const OPERATOR_ACCOUNTS = 'operator-accounts';
const CARGOS = 'cargos';
const CARGO_CUSTOMER = 'cargo-customer';
const CARGO_TRANSACTIONS = 'cargo-transactions';
const CARGO_CONFIGURATIONS = 'cargo-configurations';
const CARGO_PRICES = 'cargo-prices';
const CARGO_SUB_CATEGORIES = 'cargo-sub-categories';
const AUDIT_TRAILS = 'audit-trails';
const TRAIN_SCHEDULES = 'train-schedules';
const TODAY_TRAINS = 'today-trains';
const SYSTEM_USERS = 'system-users';
const MANAGE_ROLES = 'manage-roles';
const NEWS_UPDATES = 'news-updates';
const REPORT_MODULE = 'module-reports';
const CFM_INFO = 'cfm-information';

//Actor Types
const PORTAL_ACTOR = 0;
const POS_ACTOR = 1;

//Seat Type

const NORMAL_SEAT = 'NS';
const COMPARTMENT_SEAT = 'CPS';
const COMPARTMENT_SEAT_N_BED = 'CPSB';
const EMPTY_SEAT = 'EMT';

//Wagon Types
const PASSENGER = 'Passenger';
const CARGO_TYPE = 'Cargo';


//Report modules
const TRANSACTIONS_REPORTS = "RTX";
const CARDS_REPORTS = "RCA";
const LUGGAGE_REPORTS = "RLG";

const CUSTOMERS_REPORTS = "RCM";
const OPERATORS_REPORTS = "ROM";

//Report Codes
const TNX_SUMMARY_REPORT = "TSR";
const TNX_GENERAL_REPORT = "TGR";
const TNX_TO_FROM_STATION_REPORT = "TTF";
const TNX_INCENTIVE_REPORT = "TIR";
const TNX_PASSENGER_REPORT = "TPR";
const TNX_OPERATOR_REPORT = "TOR";
const TNX_ON_OFF_REPORT = "TOF";

//Report Parameters
const NORMAL_INPUT = "text";
const SELECT_INPUT = "select";
const MULTI_SELECT_INPUT = "multi_select";
const RADIO_INPUT = "radio";
const CHECKBOX_INPUT = "checkbox";
const DATE_INPUT = "date";
const DATETIME_INPUT = "datetime-local";
const TIME_INPUT = "time";
const INCENTIVE_PERCENTAGE_MODE = "PER";
const INCENTIVE_FLAT_MODE = "FLAT";

const ON_TRAIN = 1;
const OFF_TRAIN = 0;

//Report Statuses
const QUEUED = 'queued';
const RUNNING = 'running';
const COMPLETED = 'completed';
const FAILED = 'failed';

//Report File Types
const EXCEL = '001';
const PDF = '002';
const CSV = '003';
const TXT = '004';

//Incident Statuses
const PENDING_LOG = '001';
const RESOLVED_LOG = '002';

const CUSTOMER_INCIDENT_CODE = 'CIC';

const CRITICAL = '001';
const MEDIUM = '002';
const LOW = '003';

//Approval Processes

const PENDING_STEP = 'P';
const ON_PROGRESS_STEP = 'O';

const APPROVED_STEP = 'A';
const REJECTED_STEP = 'R';

//Approval Log Actions
const REJECTED_AND_RETURNED = 'RR';
const CANCEL_AND_FINISH = 'CF';
const APPROVED = 'A';
const APPROVED_AND_FINISHED = 'AF';
const ABORT = 'AB';

//Approval Processes
const OPERATOR_COLLECTIONS_APPROVAL_PROCESS = 'OCAP';


//Transcation Report Options
const OPERATOR_OPTION = 'OPO';
const STATION_OPTION = 'SON';
const TRAIN_OPTION = 'TON';

const MONDAY = 1;
const TUESDAY = 2;
const WEDNESDAY = 3;
const THURSDAY = 4;
const FRIDAY = 5;
const SATURDAY = 6;
const SUNDAY = 7;
const ALL_DAYS = 0;

const DURATION_24_HOURS = 24;
const DURATION_48_HOURS = 48;


//Card Status on card_block_action column
const ACTIVE_CARD = '00';
const BLOCKED_CARD = '42';
const ACTIVE_CARD_STATUS = 'A';
const BLOCKED_CARD_STATUS = 'B';
const INACTIVE_CARD_STATUS = 'I';

const USED_OTP = 'A';
const UNSED_OTP = 'I';



// System Event Constants
const TOO_MANY_LOGIN_ATTEMPTS = 'too-many-login-attempts';
const SEND_USER_CREDENTIALS = 'send-user-credentials';
const OTP_EVENT = 'otp';
const SEND_CUSTOMER_DEFAULT_PIN = 'customer-default-pin';
const RESET_PASSWORD = 'reset-password';


//Trend Of Amount

const UP_TREND = 1;
const BALANCED_TREND = 0;
const DOWN_TREND = 2;

//CARGO DEFAULT CATEGORIES
const NORMAL_CARGO_CATEGORY = 'NOR';
const SPECIAL_ONE_CARGO_CATEGORY = 'SPEC1';
const SPECIAL_TWO_CARGO_CATEGORY = 'SPEC2';

//Card Types
const EMPLOYEE_CARD = "EC";
const NORMAL_CARD = "NC";

//Identification
const BI = "BI";
const PASSAPORTE = "PASS";
const NUIT = "NUIT";
const DIRE = "DIRE";

// Pages

const ABOUT_US = 'about_us';
const CONTACT_US = 'contact_us';
const SERVICES_PAGE = 'services_page';

//Card
const CHANGE_STATUS = 'change_status';
const BLOCK_CARD = 'Successfully block card!.';
const ACTIVE_CARD_MESSAGE = 'Successfully active card!.';
const NOT_CHANGE_STATUS = 'Action type is not change status!.';

const HAS_TOO_MANY_ATTEMPTS = 60;
