<?php

namespace app\Enums;

class Constants
{
    CONST DATA_TYPE_INTEGER = 'integer';
    CONST DATA_TYPE_STRING  = 'string';
    CONST DATA_TYPE_DOUBLE  = 'double';
    CONST DATA_TYPE_NULL    = 'null';
    CONST DATA_TYPE_ARRAY   = 'array';
    CONST DATA_TYPE_BOOL    = 'bool';
    CONST DATA_TYPE_INT     = 'int';
    CONST DATA_TYPE_FLOAT   = 'float';
    
    // login type
    const LOGIN_TYPE_BACKOFFICE = 'BACKOFFICE';
    const LOGIN_TYPE_IOS = 'IOS';
    const LOGIN_TYPE_POS = 'POS';
    const LOGIN_TYPE_EMAIL = 'EMAIL';
    const LOGIN_TYPE_ANDROID = 'ANDROID';
    const LOGIN_TYPE_MPOS  = 'MPOS';
    const LOGIN_TYPE_KIOSK = 'KIOSK';
    const LOGIN_TYPE_CUSTOMER_MENU = 'CUSTOMER_MENU';
    const LOGIN_TYPE_BUTLER_CLIENT = 'BUTLER_CLIENT';
    const LOGIN_TYPE_BUTLER_USER = 'BUTLER_USER';
    const LOGIN_TYPE_APP_MANAGER = 'APP_MANAGER';
    const LOGIN_TYPE_KDS = 'KDS';

    // logout
    const POS_LOGOUT_SUCCESS = 'POS_LOGOUT_SUCCESS';
    const POS_LOGOUT = 'POS_LOGOUT';

    const TYPE_ERECEIPT = "EReceipt";
    const NF_COUNTRY = ["FR"];
    const TYPE_ERECEIPT_DEFAULT = "Default";

    const EB_ROLE_DRIVER = 'DRIVER';
    const EB_ROLE_HOUSE_KEEPER = 'HOUSE_KEEPER';
    const EB_ROLE_BUTLER_ADMIN = 'BUTLER_ADMIN';

    // Licenses
    const BUTLER_PRO_LICENSE_CODE    = 'E_BUTLER_PRO';
    const BUTLER_WEB_LICENSE_CODE    = 'E_BUTLER_WEB';
    const APP_LICENSE_POS_EXTRA_DEVICE = 'POS_EXTRA_DEVICE';
    const POS_LICENSE_CODE           = 'POS';
    const APP_MANAGER_LICENSE_CODE   = 'APP_MANAGER';
    const CUSTOMER_MENU_LICENSE_CODE = 'CUSTOMER_MENU';
    const LICENSE_CODE_POS = 'POS';
    const LICENSE_CODE_POS_RETAIL = 'POS_RETAIL';
    const LICENSE_CODE_MPOS = 'MPOS';
    const LICENSE_CODE_KDS = 'KDS';
    const LICENSE_CODE_POS_LAUNDRY = 'POS_LAUNDRY';
    const LICENSE_CODE_POS_OMNICHANNEL = 'OMNICHANNEL';
    const LICENSE_CODE_POS_LOYALTY = 'LOYALTY';
    

    const SINGLE_LICENSE = [
        "POS",
        "CUSTOMER_MENU",
        "BOOKING_WIDGET",
        "KIOSK",
        "KDS",
        "E_BUTLER_WEB",
        "INVENTORY",
        "HUMAN_RESOURCE",
        "LOYALTY",
        "POS_LAUNDRY"
    ];
    const MULTIPLE_LICENSE = [
        "MPOS",
        "POS_EXTRA_DEVICE",
        "APP_MANAGER",
        "E_BUTLER_PRO"
    ];

    // booking
    const BOOKING_STATUS_CANCELLED      = 'CANCELLED';
    const BOOKING_STATUS_CONFIRMED      = 'CONFIRMED';
    const BOOKING_STATUS_NOSHOW         = 'NO_SHOW';
    const BOOKING_STATUS_ARRIVED        = 'ARRIVED';
    const BOOKING_TYPE_BOOKING          = 'BOOKING';
    const BOOKING_TYPE_WAITING          = 'WAITING_QUEUE';
    const BOOKING_VALUE_MAIL_NOTE_TITLE = 'MAIL_NOTE_TITLE';
    const BOOKING_VALUE_MAIL_NOTE_TEXT  = 'MAIL_NOTE_TEXT';

    const PRINTER_TYPE_DISH = 'DISH';
    const PRINTER_TYPE_BILL = 'BILL';

    //printer model
    const PRINTER_MODEL_ZIWELL = 'ZIWELL';
    const PRINTER_MODEL_START  = 'START';

    // order
    const META_KEY_ORDER_ORIGIN = 'ORDER_ORIGIN';
    const ORDER_ORIGIN_TABLE_MANAGEMENT = 'TABLE_MANAGEMENT';
    const ORDER_ORIGIN_RETAIL = 'RETAIL';
    const ORDER_ORIGIN_KIOSK = 'KIOSK';
    const ORDER_ORIGIN_CUSTOMER_MENU_DINEIN = 'CUSTOMER_MENU_DINEIN';
    const ORDER_ORIGIN_CUSTOMER_MENU_DELIVERY = 'CUSTOMER_MENU_DELIVERY';
    const ORDER_ORIGIN = 'ORDER_ORIGIN';
    const ORDER_ORIGIN_LAUNDRY = 'LAUNDRY';
    const ORDER_ORIGIN_NHAM24 = 'NHAM24';

    const ORDER_TYPE_DINE_IN = 'DINE_IN';
    const ORDER_TYPE_TAKEAWAY = 'TAKE_AWAY';
    const ORDER_TYPE_DELIVERY = 'DELIVERY';

    const ORDER_STATUS_NEW = 'NEW';
    const ORDER_STATUS_ORDERED  = 'ORDERED';
    const ORDER_STATUS_SENT = 'SENT';
    const ORDER_STATUS_CANCELLED = 'CANCELLED';
    const ORDER_STATUS_CALLED = 'CALLED';
    const ORDER_STATUS_PENDING = 'PENDING';
    const ORDER_STATUS_DONE = 'DONE';
    const ORDER_STATUS_DISPATCH = 'DISPATCH';
    

    // Table Enum
    const TABLE_TYPE_NONE = 'NONE';
    const TABLE_TYPE_TAKEAWAY = 'TAKEAWAY';
    const TABLE_TYPE_DELIVERY = 'DELIVERY';
    const TABLE_TYPE_QUICK_ORDER = 'QUICK_ORDER';

    // discount
    const DISCOUNT_TYPE_PERCENT = 'PERCENT';
    const DISCOUNT_TYPE_AMOUNT = 'AMOUNT';

    // bill
    const BILL_STATUS_NEW = 'NEW';
    const BILL_STATUS_PAID = 'PAID';
    const BILL_EVENT_CREATE = 'CREATE';
    const BILL_EVENT_PAY = 'PAY';
    const BILL_EVENT_RECALL = 'RECALL';
    const BILL_TYPE_RECEIPT = 'RECEIPT';
    const BILL_TYPE_NOTE = 'NOTE';

    // dish
    const DISH_TYPE_DISH = 'DISH';
    const DISH_TYPE_FORMULA = 'FORMULA';

    // dish stock
    const STATUS_OUT_OF_STOCK = "OUT_OF_STOCK";
    const STATUS_IN_STOCK     = "IN_STOCK";

    // dish option
    const DISH_OPTION_COMMENT = 'COMMENT';
    const DISH_CUSTOM_NAME = '@custom';

    // Account Enum
    const ACCOUNT_SUPERADMIN = 'superadmin';
    const ACCOUNT_QUEUE      = 'queue';

    // cash register
    const CASH_REGISTER_OPEN = 'OPEN';
    const CASH_REGISTER_CLOSE = 'CLOSE';

    // Payment
    const PAYMENT_STATUS_SUCCESS = 'SUCCESS';
    const PAYMENT_STATUS_PENDING = 'PENDING';
    const PAYMENT_CHANGE_PAYMENT = 'CHANGE_PAYMENT';

    // Pay Transation
    const TRANSATION_STATUS_SUCCESS = 'SUCCESS';

    // Meta Key & Value
    // Key
    const META_KEY_DISCOUNT_ROUNDING                  = "DISCOUNT_ROUNDING";
    const META_KEY_PAYMENT_PAYMENT_TYPE               = 'PAYMENT_TYPE';
    const META_KEY_CONTACT_TYPE                       = 'CONTACT_TYPE';
    const META_KEY_REGION_FIELD                       = 'REGION_FIELD';
    const META_KEY_LEGAL_INFO                         = 'LEGAL_INFO';
    const META_KEY_COMPLY_WITH                        = 'COMPLY_WITH';
    const META_KEY_BOOKING_WIDGET                     = 'BOOKING_WIDGET';
    const META_KEY_SYSTEM                             = 'SYSTEM';
    const META_KEY_SYSTEM_ACTION_DUMP_DB              = 'dump_db';
    const META_KEY_CUSTOMER_MENU_DELIVERY_OFFICE_NAME = 'CUSTOMER_MENU_DELIVERY_OFFICE_NAME';

    const META_VALUE_PAYMENT_PAYMENT_TYPE_ALIPAY        = 'ALIPAY';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_WECHAT        = 'WECHAT';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_KESS          = 'KESS';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_VISA_CARD     = 'VISA_CARD';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_MASTER_CARD   = 'MASTER_CARD';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_PAY_LATER     = 'PAY_LATER';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_ALREADY_MADE  = 'ALREADY_MADE';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_STRIPE        = 'STRIPE';
    const META_VALUE_PAYMENT_PAYMENT_TYPE_APPLE_PAY     = 'APPLE_PAY';

    // Value
    const META_VALUE_PAYMENT_PAYMENT_TYPE_CASH = 'CASH';
    const META_VALUE_PAYMENT_TYPE_APPLE_PAY = 'APPLE_PAY';

    const META_VALUE_TERM_CONDITION     = 'TERM_CONDITION';
    const META_VALUE_ADULT_RULE         = 'ADULT_RULE';
    const META_VALUE_KID_RULE           = 'KID_RULE';

    // CONTACT_TYPE
    const CONTACT_TYPE_CUSTOMER = "CUSTOMER";
    const CONTACT_TYPE_ACCOUNT_USER = "ACCOUNT_USER";
    const CONTACT_TYPE_GUEST = "GUEST";

    // Env Vue js
    const ENV_VUE_JS_DEVELOPMENT = 'development';

    // All my error response code
    const ERROR_WRONG_REQUEST   = 'WRONG_REQUEST';
    const ERROR_WRONG_PIN_CODE  = 'WRONG_PIN_CODE';
    const ERROR_DATA_NOT_FOUND  = 'DATA_NOT_FOUND';
    const ERROR_DATA_WRONG_VERSION = 'WRONG_VERSION';
    const ERROR_DATA_ALREADY_LOGGED = 'ALREADY_LOGGED';
    const ERROR_CODE_WRONG_AUTH_HEADER = "WRONG_AUTH_HEADER";
    const ERROR_CODE_GENERIC_ERROR = 'GENERIC_ERROR';
    const ERROR_TABLE_NOT_YET_OPEN = 'TABLE_NOT_YET_OPEN';
    const ERROR_CODE_NOT_FOUND = 'NOT_FOUND';
    const ERROR_CODE_SERVICE_CLOSE = 'SERVICE_CLOSE';
    const ERROR_CODE_SERVER_MAINTENANCE = 'SERVER_MAINTENANCE';
    const ERROR_CODE_LICENSE_EXPIRED = "LICENSE_EXPIRED";
    const ERROR_CODE_USER_DO_NOT_HAVE_LICENSE = "USER_DO_NOT_HAVE_LICENSE";

    const TOKEN_TYPE_BEARER = 'bearer';
    const TOKEN_TYPE_REFRESH = 'refresh';
    const TOKEN_TYPE_SWITCH = 'switch';
    const TOKEN_TYPE_EXCHANGE_TOKEN = 'EXCHANGE_TOKEN';
    const REFRESH_TOKEN_NOT_EXIST = 'REFRESH_TOKEN_NOT_EXIST';
    const INVALID_REFRESH_TOKEN = 'INVALID_REFRESH_TOKEN';
    const REFRESH_TOKEN_READY_REFRESH = 'REFRESH_TOKEN_READY_REFRESH';

    // Group type
    const GROUP_TYPE_DEPARTMENT = 'DEPARTMENT';
    const GROUP_TYPE_RESTAURANT = 'RESTAURANT';
    const GROUP_TYPE_MARKETTING_LIST = 'MARKETTING_LIST';
    const GROUP_TYPE_ACCOUNT = 'ACCOUNT';

    // IMAGE TYPE
    const IMAGE_TYPE_ICON = 'ICON';

    // CUSTOMER_STATS key and value
    // key
    const CUSTOMER_STATS_KEY = 'CUSTOMER_STATS';
    // value
    const CUSTOMER_STATS_VALUE_FAV_TAG      = 'FAV_TAG';
    const CUSTOMER_STATS_VALUE_FAV_DISH     = 'FAV_DISH';
    const CUSTOMER_STATS_VALUE_BEST_MATE    = 'BEST_MATE';


    const TRANSLATE_COLUMNS = ["name", "value"];

    // Action Account
    const ACTION_STATUS_CREATE_ACCOUNT = 'CREATE_ACCOUNT';
    const ACTION_STATUS_CREATE_DB_ONLY = 'CREATE_DB_ONLY';

    // Butler
    const SESSION_STATUS_ACTIVE = "ACTIVE";
    const SESSION_STATUS_COMPLETED = "COMPLETED";

    const LICENSE_TYPE = ["SINGLE", "MULTIPLE"];
    const PLAN_TYPE = ["MONTHLY", "YEARLY"];

    // Device OS
    const DEVICE_OS_IOS = "IOS";
    const DEVICE_OS_ANDROID = "ANDROID";

    //APP_TYPE_KEY
    const LOGIN_KEY_APP_TYPE = "APP_TYPE";

    // App type device tokens
    const APP_TYPE_EBUTLER = 'EBUTLER';
    const APP_TYPE_APP_MANAGER = 'APP_MANAGER';

    //EB Resource Group
    const RESOURCE_GROUP_ALL = "ALL";
    const RESOURCE_GROUP_ORDER = "ORDER";
    const RESOURCE_GROUP_TRANSPORT = "TRANSPORT";
    const RESOURCE_GROUP_HOUSE_KEEPING = "HOUSE_KEEPING";
    const RESOURCE_GROUP_BOOKING = "BOOKING";
    const RESOURCE_GROUP_ROOM = "ROOM";
    const RESOURCE_GROUP_MINI_BAR = "MINI_BAR";

    //================ E-Butler ===================
    //EB Group Type Constant
    const RESOURCE_GROUP = "RESOURCE_GROUP";
    const DATA_GROUP = "DATA_GROUP";
    const DATA_GROUP_POS = "POS";
    const DATA_GROUP_BUTLER = "BUTLER";
    const GROUP_TYPE = "GROUP_TYPE";
    const GROUP_TYPE_E_BUTLER = "E_BUTLER";
    const BUTLER_ROOM_TYPE = "ROOM_TYPE";
    const CONTACT_TYPE = "CONTACT_TYPE";
    const ACCOUNT_USER = "ACCOUNT_USER";
    // Data group value
    const VALUE_DATA_GROUP_POS = "POS";
    const VALUE_DATA_GROUP_BUTLER = "BUTLER";

    //EB Action Type
    const EB_ACTION_TYPE = "EB_ACTION_TYPE";
    const EB_ACTION_TYPE_RESOURCE = "RESOURCE";
    const EB_ACTION_TYPE_BOOKING = "BOOKING";
    const EB_ACTION_TYPE_SERVICE = "SERVICE";
    const EB_ACTION_TYPE_WAKE_UP_CALL = "WAKE_UP_CALL";
    const EB_ACTION_TYPE_EXTRA_HOUSEKEEPING = "EXTRA_HOUSEKEEPING";
    const EB_ACTION_TYPE_LUGGAGE = "LUGGAGE";
    const EB_ACTION_TYPE_UMBRELLA = "UMBRELLA";

    //EB Task Status
    const EB_TASK_STATUS = "EB_TASK_STATUS";
    const EB_TASK_STATUS_PENDING = "PENDING";
    const EB_TASK_STATUS_ACCEPT = "ACCEPT";
    const EB_TASK_STATUS_REJECT = "REJECT";
    const EB_TASK_STATUS_CLOSE = "CLOSE";
    const EB_TASK_STATUS_CANCEL = "CANCEL";
    const EB_TASK_STATUS_OUT_OF_STOCK = "OUT_OF_STOCK";
    const EB_TASK_STATUS_RESTOCK = "RESTOCK";
    const EB_TASK_STATUS_ASSIGN = "ASSIGN";
    const EB_TASK_STATUS_REPLACE = "REPLACE";
    const EB_TASK_STATUS_CONFIRM = "CONFIRM";
    const EB_TASK_STATUS_PICKUP = "PICKUP";

    //EB Role
    const BUTLER_ROLE_DRIVER = "DRIVER";
    const BUTLER_ROLE_HOUSE_KEEPER = "HOUSE_KEEPER";
    const BUTLER_ROLE_ADMIN = "BUTLER_ADMIN";

    // Test Class
    const TEST_CLASS = 'TEST CLASS';

    //meta language KEY
    const LANGUAGE_KEY = 'LANGUAGE';

    //meta language VALUE
    const LANGUAGE_ENGLISH = 'en';
    const LANGUAGE_CHINESE = 'zh-Hans';
    const LANGUAGE_FRENCH  = 'fr';
    const LANGUAGE_KHMER   = 'km';

    //Redis key
    const CUSTOMER_STATS = 'CUSTOMER_STATS';

    // WIDGET_DESIGN_TYPE
    const WIDGET_DESIGN_TYPE_REST_RULE = "REST_RULE";

    const DES_PARAM_NAME_LOCALIZE = 'The localize to english "name" help use "name-en" instead';

    // TOKEN
    const TOKEN_EXPIRED = "TOKEN_EXPIRED";

    // MODULE
    const MODULE_HOUSECAR     = 'HOUSECAR';
    const MODULE_MINIBAR      = 'MINIBAR';
    const MODULE_HOUSEKEEPING = 'HOUSEKEEPING';
    const MODULE_ROOM         = 'ROOM';
    const MODULE_ORDER        = 'ORDER';
    const MODULE_DYNAMIC_MENU = 'DYNAMIC_MENU';
    const MODULE_RESTAURANT   = 'RESTAURANT';

    // SHORT URL TYPE
    const META_KEY_SHORT_URL = 'SHORT_URL';
    const SHORT_URL_TYPE_BILL = 'BILL';
    const SHORT_URL_TYPE_NOTIFY_URL = 'NOTIFY_URL';

    // Meta Parent
    const META_KEY_FROM_PARENT = 'FROM_PARENT';

    const KESS_QR_RECEIPT = 'KESS_QR_RECEIPT';
    const KESS_CREDIT_CARD = 'KESS_CREDIT_CARD';
    const META_KEY_KESS_PAYMENT_TYPE = 'KESS_PAYMENT_TYPE';

    // E-Butler Hide/Show services
    const EB_MODULE_ORDER       = "EB_MODULE_ORDER";
    const EB_MODULE_BOOKING     = "EB_MODULE_BOOKING";
    const EB_MODULE_TRANSPORT   = "EB_MODULE_TRANSPORT";
    const EB_MODULE_HOUSEKEEPING= "EB_MODULE_HOUSEKEEPING";
    const EB_MODULE_ACTIVITY    = "EB_MODULE_ACTIVITY";

    // Event Log
    const EVENT_LOG_TABLE_OPEN = "TABLE_OPEN";

    const KESS_IMAGE_URL_VISA_MASTER      = 'images/icons/payment/visa-master-card.png';
    const KESS_IMAGE_URL_ALIPAY           = 'images/icons/payment/AliPay.jpg';
    const KESS_IMAGE_URL_KESSKH           = 'images/icons/payment/kess.png';
    const KESS_IMAGE_URL_ABAAKHPP         = 'images/icons/payment/ABA.png';
    const KESS_IMAGE_URL_ACLBKHPP         = 'images/icons/payment/ACLEDA.png';
    const KESS_IMAGE_URL_SBPLKHPP         = 'images/icons/payment/sathapana.png';
    const KESS_IMAGE_URL_WECHAT           = 'images/icons/payment/WeChat.png';
    const KESS_IMAGE_URL_WING             = 'images/icons/payment/wing.png';
    const KESS_IMAGE_URL_KHQR             = 'images/icons/payment/bakong.png';
    const KESS_IMAGE_URL_KESS_QR_RECEIPT  = 'images/icons/payment/kess_qr_on_receipt.png';
    const KESS_IMAGE_URL_KESS_CREDIT_CARD = 'images/icons/payment/credit_card.png';
    const KESS_IMAGE_URL_PPCBKHPP         = 'images/icons/payment/PPC.png';

    const MANUAL_DISCOUNT = 'Manual Discount';

    // Meta Omnichannel timers 
    const OMNICHANNEL_YELLOW_TIMER = "OMNICHANNEL_YELLOW_TIMER";
    const OMNICHANNEL_RED_TIMER = "OMNICHANNEL_RED_TIMER";

    const TOP_CUSTOMER_DETAIL = "TOP_CUSTOMER_DETAIL";
    // Meal
    const MEAL_TYPE_DINE_IN   = 'DINE_IN';
    const MEAL_TYPE_TAKE_AWAY = 'TAKE_AWAY';
    const MEAL_TYPE_DELIVERY  = 'DELIVERY';

    /**
     * Setting
     * */
    const SETTING_START_OF_DAY = "START_OF_DAY";
    const IS_DISCOUNT = "IS_DISCOUNT";

    /**
     * Commercial type
     */
    const COMMERCIAL_PACKAGE = "PACKAGE";
    const COMMERCIAL_PROMOTION = "PROMOTION";

    CONST REPORT_FILTER_PRODUCT_DISCOUNT = 'PRODUCT_DISCOUNT';

    # Constant Query ignore concat string json array value
    const QUERY_IGNORE_INTEGER = 'INTEGER';
    const QUERY_IGNORE_JSON    = 'JSON';

    # Discount
    const DISCOUNT_APPLY_TO_BILL = 'BILL';

    const CUSTOM_TAX_NAME = "@customtax";
    /**
     * License commitment period
     */
    CONST LICENSE_COMMITMENT_PERIOD_15DAYS_TRIAL = '15days_trial';
    CONST LICENSE_COMMITMENT_PERIOD_1MONTH_TRIAL = '1month_trial';

    const SOURCE_LOG = 'BackOffice API';

    const CATEGORY_RESTAURANT_PRICEBOOK = "restaurant_pricebooks";
}
