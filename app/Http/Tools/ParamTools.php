<?php

namespace App\Http\Tools;

use App\Exceptions\POSException;
use App\Services\SVCategory;
use App\Services\SVDatabase;
use App\Services\SVDish;
use App\Services\SVModule;
use App\Services\Sync\SVSynchronizeToSuperAdmin;
use App\Services\Sync\SyncTools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Schema;
use mysqli;

class ParamTools {

    private static $ignore_fields = ["_method", "_token", "profile"];

    CONST CACH_ITEMS = "report-sale-item";
    CONST CURRENT_STOCK = 0;
    CONST JOURNAL = 1;
    CONST STOCK_MANAGEMENT_TABS = ['current_stock','journal'];

    CONST CATEGORIES = 0;
    CONST GROUP = 1;
    CONST CURRENT_STOCK_GROUP_BY = [
        'categories',
        // 'group'
    ];

    CONST FILTER = 1;
    CONST MODEL = 2;
    CONST CREATION = 3;
    CONST CUSTOM_REPORT_TABS = ['categories', 'filter', 'report_model', 'report_creation'];

    CONST PROCESSING_FLOW = [
        'create_categories',
        'choose_filter',
        'create_report_model',
        'create_your_report'
    ];

    CONST SITTING = 0;
    CONST INV_USAGE = 1;
    CONST POS_USAGE = 2;
    CONST AVG_USAGE_BY_MONTH = 3;
    CONST AVG_SALE_BY_WEEK  = 4;
    CONST AVG_MAGIN_BY_WEEK = 5;
    CONST TOTAL_SOLD = 6;
    CONST AVG_RECEIVED_QTY_BY_MONTH = 7;

    CONST FILTERS = [
        'Sitting in inventory',
        'Inventory Usage in a period',
        'POS Usage in a period',
        'Average Usage by month',
        'Average Sale by week',
        'Average Margin by week',
        'Total Sold',
        'Average Received quantity By month'
    ];

    public static function fetchDatabases()
    {
        return array(
            'db_auth' => config('app.db_auth'),
            'db_acc'  => config('database.connections.mysql.database')
        );
    }

    /**
     * PREFIX DATA TO SAVE LIKE FOLDER
     *
    */
    public static function getNameDBByGid($gid)
    {
        // We need split symbol (-) to symbol(_) coz mysql can't create with symbol (-)
        return config('app.db_prefix')."_".str_replace("-",'_', $gid);
    }

    /**
     * DROP DATABSE ALL WITH STORE PROCEDURE
     *
    */
    public static function dropAllDBByStoreProcedure()
    {
        $st_pro = "CREATE PROCEDURE kill_db()
        BEGIN
            DECLARE done INT DEFAULT FALSE;
            DECLARE dbname VARCHAR(255);
            DECLARE cur CURSOR FOR SELECT schema_name
              FROM information_schema.schemata
             WHERE schema_name LIKE '".config('app.db_prefix')."\_%' ESCAPE '\\\\'
             ORDER BY schema_name;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

            OPEN cur;

            read_loop: LOOP
                FETCH cur INTO dbname;

                IF done THEN
                  LEAVE read_loop;
                END IF;

                SET @query = CONCAT('DROP DATABASE ',dbname);
                PREPARE stmt FROM @query;
                EXECUTE stmt;
            END LOOP;
        END;";
        $queryUpdateDBNameToAuth = "UPDATE account SET `db_name` = '".config('app.db_auth')."'";
        $conn = DB::connection('authconnect');
        $conn->unprepared('DROP PROCEDURE IF EXISTS kill_db');
        $conn->unprepared($st_pro);
        $conn->select('CALL kill_db');
        $conn->unprepared($queryUpdateDBNameToAuth);
    }

    public static function reconnectDB($db, $connection = 'mysql')
    {
        // RECONNECT DATABASE
        Config::set('database.connections.'.$connection.'.database', $db);

        DB::purge($connection);
        // Log::info("Switch Connection db_name: ". $db);
    }

    public static function synTable($db, $acc_id = null)
    {
        $show = DB::select('SHOW TABLES');
        foreach ($show as $tableInfo)
        {
            $tableArr  = (array) $tableInfo;
            $nameKey   = (string) array_keys($tableArr)[0];
            $tableName = $tableArr[$nameKey];
            DB::statement("CREATE TABLE $db.$tableName LIKE ".config('app.db_auth').".$tableName;");
            if ($tableName == 'account')
                DB::statement("INSERT $db.$tableName SELECT * FROM ".config('app.db_auth').".$tableName WHERE id = $acc_id;");
            else
            {
                if(Schema::hasColumn($tableName, 'account_id'))
                    DB::statement("INSERT $db.$tableName SELECT * FROM ".config('app.db_auth').".$tableName WHERE account_id = $acc_id;");
                else
                    DB::statement("INSERT $db.$tableName SELECT * FROM ".config('app.db_auth').".$tableName;");
            }
        }
    }

    public static function computeOrder()
    {
        $db = config('app.db_auth');
        // compute the table dependencies so we can sync in order

        // find all tables
        $allTables = [];
        $conn   = ParamTools::getConnection($db);
        $tables = $conn->query("show tables");
        while($tableInfo = $tables->fetch_assoc())
        {
            $tableArr  = (array) $tableInfo;
            $nameKey   = (string) array_keys($tableArr)[0];
            $tableName = $tableArr[$nameKey];
            $allTables[] = $tableName;
        }




        // we use the foreign keys to know which table depends on which tables
        $fkeys = DB::select("SELECT table_name as table_name, column_name as column_name, referenced_table_name as referenced_table_name, referenced_column_name as referenced_column_name" .
            " FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE" .
            " WHERE referenced_table_name IS NOT NULL" .
            " AND TABLE_SCHEMA = '".$db."'".
            " AND referenced_table_name != 'account'" . // we can ignore dependencies on account
            " AND referenced_table_name != table_name"); // ignore self-references such as parent categories
        // build a map where we map the table name to the tables it depends on

        $depends = [];
        foreach ($fkeys as $fkey) {
            $keySrc = $fkey->table_name;
            if (!isset($depends[$keySrc])) {
                $depends[$keySrc] = [];
            }
            $depends[$keySrc][] = $fkey->referenced_table_name;
        }
        // compute level for each table
        $levels = [];
        $steps = 0;
        while (count($levels) < count($allTables)) {
            foreach ($allTables as $tableName) {
                $isOk = true;
                $level = 0;
                if (isset($depends[$tableName])) {
                    foreach ($depends[$tableName] as $other) {
                        if (!isset($levels[$other])) {
                            $isOk = false;
                            break;
                        }
                        $level = max($level, $levels[$other] + 1);
                    }
                }
                if ($isOk) {
                    $levels[$tableName] = $level;
                }
            }
            $steps++;
            if ($steps > 20) {
                // more than 20 steps => something went wrong, abort!
                break;
            }
        }

        // prepare the data to send to user
        asort($levels);

        return $levels;
    }

    public static function syncDataAccount($acc_id = null)
    {
        $levels = ParamTools::computeOrder();
        $tables = array_keys($levels);
        $syncSchemaAlready = true;
        $auth_db  = config('app.db_auth');
        $conn     = ParamTools::getConnection($auth_db);
        $acc      = $conn->query("SELECT global_id FROM account WHERE id = $acc_id")->fetch_assoc();

        if (!$acc) {
            throw new POSException('Contact developer wrong database or account', 'WRONG_PARAM');
        }
        $db       = ParamTools::getNameDBByGid($acc['global_id']);
        // Query Account With Sub Account
        $queryAccWithSubAcc = "SELECT id FROM ".$auth_db.".account WHERE id = $acc_id OR parent_id = $acc_id";
        $queryStr = "SET FOREIGN_KEY_CHECKS = 0;UPDATE ".$auth_db.".account SET db_name='$db' WHERE id IN($queryAccWithSubAcc);";
        $tableNoAccId = [
            // "sys_table","meta"
        ];
        $tableNoSee   = ['super_acc_relation'];//["contact"];
        // $noInsert = [
        //     'migrations',
        //     'module',
        //     'module_action',
        //     'world_cities',
        //     'world_countries'
        // ];
        $noInsert = array_merge($tableNoSee, $tableNoAccId);
        foreach ($tables as $tableName)
        {
            if ( in_array($tableName, $noInsert) )
                continue;

            // CHECK TABLE HAVE CONTRAINT
            $sqlCheckConstriant = "SELECT * FROM information_schema.key_column_usage WHERE TABLE_NAME = '$tableName' AND referenced_table_name IS NOT NULL AND TABLE_SCHEMA = '".$auth_db."' AND referenced_column_name = 'id';";
            $querySqlChkConstraint   = $conn->query($sqlCheckConstriant);
            // EXISTING ACCOUNT ID
            if( $querySqlChkConstraint->num_rows > 0 )
            {
                $sqlCheckConstriantDis = "SELECT * FROM information_schema.key_column_usage WHERE TABLE_NAME = '$tableName' AND referenced_table_name IS NOT NULL AND TABLE_SCHEMA = '$db' AND referenced_column_name = 'id';";
                $querySqlChkConstraintDis   = $conn->query($sqlCheckConstriantDis);
                if( $querySqlChkConstraintDis->num_rows == 0 )
                {
                    Log::info("Error Sync Data BO : ".$sqlCheckConstriantDis);
                    $syncSchemaAlready = false;
                    break;
                }
            }
            // Delete data before insert
            $queryStr .= "DELETE FROM $db.$tableName;";
            if ($tableName == 'account')
            {
                $queryAccount =  "INSERT $db.$tableName SELECT * FROM ".$auth_db.".$tableName WHERE id IN($queryAccWithSubAcc);".
                            "UPDATE $db.$tableName SET $db.$tableName.`db_name`= '$db' WHERE $db.$tableName.id IN($queryAccWithSubAcc);";
                $queryStr .= $queryAccount;
            }
            else
            {
                if ($tableName == 'image')
                {
                    $queryStr .="INSERT $db.$tableName SELECT * FROM ".$auth_db.".$tableName WHERE account_id IN($queryAccWithSubAcc) OR account_id IS NULL;";
                }
                else {
                    $checkColumn = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".$auth_db."' AND TABLE_NAME = '$tableName' AND COLUMN_NAME = 'account_id';";
                    $columnAcc   = $conn->query($checkColumn);
                    // EXISTING ACCOUNT ID
                    if( $columnAcc->num_rows > 0 )
                    {
                        $queryStr .= "INSERT $db.$tableName SELECT * FROM ".$auth_db.".$tableName WHERE account_id IN($queryAccWithSubAcc);";
                    }
                    else
                    {
                        $queryStr .= "INSERT $db.$tableName SELECT * FROM ".$auth_db.".$tableName;";
                    }
                }
            }
        }
        $conn->close();
        if ($syncSchemaAlready)
        {
            self::runQuery($queryStr);
        }
        return $syncSchemaAlready;
    }

    public static function runQuery($query) {
        $conn  = ParamTools::getConnection();
        $query .= "SET FOREIGN_KEY_CHECKS = 1";
        $queryArr = explode(";", $query);
        foreach ($queryArr as $sql)
        {
            if(!empty($sql))
                $conn->query($sql);
        }
        $conn->close();
    }

    public static function specificTable($queryStr = null, $db = null, $isSpecific = false, $acc_id = null, $tbl = 'user_role')
    {
        DB::beginTransaction();
        try {
            DB::statement("SET FOREIGN_KEY_CHECKS=0;");
            if ($isSpecific)
            {
                $queryStr = "INSERT $db.$tbl SELECT * FROM ".config('app.db_auth').".$tbl WHERE account_id = $acc_id;";
                DB::unprepared($queryStr);
            }
            else {
                DB::unprepared($queryStr);
                ParamTools::syncTableNoSeem(['user_role'], $db, $acc_id);
            }
            DB::statement("SET FOREIGN_KEY_CHECKS=1;");
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
        }
    }

    public static function syncTableNoSeem($arrTbls, $db, $acc_id)
    {
        $sql = '';
        foreach ($arrTbls as $tbl)
        {
            $sql .= "INSERT $db.$tbl SELECT * FROM ".config('app.db_auth').".$tbl WHERE account_id = $acc_id;";
        }
        DB::unprepared($sql);
    }

    public static function getConnection($dbname = null)
    {
        $dynamic   = (object)config('dynamic.database');
        $DBIUser   = $dynamic->dn_db_old_server_user;
        $DBIPass   = $dynamic->dn_db_old_server_password;
        $NewUser   = $dynamic->dn_db_new_server_user;
        $NewPass   = $dynamic->dn_db_new_server_password;
        $oldServer = $dynamic->dn_db_old_server;
        $newServer = $dynamic->dn_db_new_server;
        $olddb     = $dynamic->dn_db_clone;
        return new mysqli($oldServer, $DBIUser, $DBIPass, $dbname);
    }

    public static function buildDB($global_id)
    {
        $db = ParamTools::getNameDBByGid($global_id);
        SVDatabase::createDBByAccount($db);
        $account = DB::table('account')
        ->where('global_id', $global_id)
        ->first();
        DB::table('account')
        ->where('id', $account->id)
        ->orWhere("parent_id", $account->id)
        ->update(['db_name' => $db]);
        return true;
    }

    public static function testdb()
    {
        $conn = ParamTools::getConnection(config('app.db_auth'));
        $tables = $conn->query("show tables");
        if ($tables->num_rows > 0)
        {
            // output data of each row
            while($tableInfo = $tables->fetch_assoc())
            {
                $tableArr  = (array) $tableInfo;
                $nameKey   = (string) array_keys($tableArr)[0];
                $tableName = $tableArr[$nameKey];
                $conn->query("CREATE TABLE dbtest.$tableName SELECT * FROM ".config('app.db_auth').".$tableName;");
            }
        }
        $conn->close();
    }

    public static function getAllTrigger()
    {
        return DB::select("select * from information_schema.triggers where trigger_schema = '".config('app.db_auth')."'");
    }

    public static function dbExist($db)
    {
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  ?";
        $db = DB::select($query, [$db]);
        return !empty($db);
    }

    public static function repair_params(array $params) {
        $ret = [];
        foreach ($params as $key => $value) {
            if (in_array($key, ParamTools::$ignore_fields)) {
                continue;
            }
            $ret[$key] = $value;
        }
        return $ret;
    }

    public static function get_value(array $params, $key, $default = null) {
        return isset($params[$key]) && !empty($params[$key]) ? $params[$key] : $default;
    }

    public static function getTimezone($timezone = null) {
        if ($timezone == null || $timezone == "") {
            $timezone = auth()->user()->timezone;
        }
        if (substr($timezone, 0, 3) == 'GMT') {
            // converts GMT+7 to +7:00
            // and GMT-4 to -4:00
            return substr($timezone, 3, 2).":00";
        }
        return $timezone;
    }

    /**
     * unprepareTriggerVersionNtriggerGlobalId('account')
     * @param $tableName is name of table
     *
    */
    public static function unprepareTriggerVersionNtriggerGlobalId($tableName)
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `'.$tableName.'_uuid`');
        DB::unprepared('DROP TRIGGER IF EXISTS `before_update_'.$tableName.'`');
        DB::unprepared('CREATE TRIGGER `'.$tableName.'_uuid` BEFORE INSERT ON `'.$tableName.'` FOR EACH ROW
            BEGIN
                IF NEW.global_id IS NULL THEN
                    SET NEW.global_id=UUID();
                END IF;
            END;');
        DB::unprepared('CREATE TRIGGER `before_update_'.$tableName.'` BEFORE UPDATE ON `'.$tableName.'` FOR EACH ROW
            BEGIN
                IF NEW.version = OLD.version THEN
                    SET NEW.version = OLD.version + 1;
                ELSEIF NEW.version < OLD.version THEN
                    SET NEW.version = OLD.version;
                END IF;
            END
        ');
    }


    /**
     * Update multiple record
     * @param $values array : $data[id_table_1] = ['field_name_table' => value];
     *                        $data[id_table_2] = ['field_name_table' => value];
     * @param int $field (name column table) example: id or ....
     * @param string $table : table_name
     * update table
     * */
    public static function updateMultiple(array $values, string $table, string $field = 'id'): void
    {
        $ids = [];
        $params = [];
        $columnsGroups = [];
        $queryStart = "UPDATE {$table} SET";
        $columnsNames = array_keys(array_values($values)[0]);
        foreach ($columnsNames as $columnName) {
            $cases = [];
            $columnGroup = " " . $columnName . " = CASE $field ";
            foreach ($values as $setField => $newData) {
                $cases[] = "WHEN {$setField} then ?";
                $params[] = $newData[$columnName];
                $ids[$setField] = "0";
            }
            $cases = implode(' ', $cases);
            $columnsGroups[] = $columnGroup . "{$cases} END";
        }
        $ids = implode(',', array_keys($ids));
        $columnsGroups = implode(',', $columnsGroups);
        // $params[] = Carbon::now();// Current time zero
        // $queryEnd = ", updated_at = ? WHERE $field in ({$ids})";
        $queryEnd = " WHERE $field in ({$ids})";
        DB::update($queryStart . $columnsGroups . $queryEnd, $params);
    }

    /**
     * fetchProductCategories
     * @return object $data => [products,categories]
    */
    public static function fetchProductCategories()
    {
        $products   = (new SVDish())->getDishForCat(null);
        $categories = (new SVCategory())->getAllWithLocale(false);
        $data = new \stdClass();
        $data->products   = $products;
        $data->categories = $categories;
        return $data;
    }

    /**
     * Check Mail is sent success or not
     * @return bool false mail error, true mail sent
     * */
    public static function checkMail() {
        if( count(Mail::failures()) > 0 ) {
            foreach(Mail::failures() as $email_address) {
                //myLogInfo("Mail Error", $email_address);
            }
            return false;
        } else {
            //myLogInfo("Mail sent successfully", null);
            return true;
        }
    }

    /**
     * Check Mail is sent success or not
     * @return bool false mail error, true mail sent
     * */
    public static function is_mail_error() {
        return count(Mail::failures()) > 0;
    }

    public static function getNameReportSaleFilter($user = null) {
        return 'report-sale-filter/'.($user ? $user->id : auth()->user()->id);
    }

    public static function getNameReportSaleItem($user = null) {
        $user_id = ($user ? $user->id : auth()->user()->id);
        $filters = Cache::get(self::getNameReportSaleFilter());
        if ( !empty($filters) )
            return self::CACH_ITEMS.'/'. $user_id .'/'.$filters['restaurant_id'].'/'.$filters['groupBy'];
        return null;
    }

    public static function getClearCachItems($rest_global = null, $gb = null, $user = null) {
        $user_id = ($user ? $user->id : auth()->user()->id);
        return self::CACH_ITEMS.'/'. $user_id .'/'.$rest_global.'/'. $gb;
    }

    /**
     * Mode Filter Report Dashboard
     * */
    public static function getModeReportSale($user = null) {
        return 'report-sale-mode/'.($user ? $user->id : auth()->user()->id);
    }

    /**
     * Get Permission
     * */
    public static function getPermissions() {
        if (Cache::has('app_permissions')) {
            $permissions = Cache::get('app_permissions');
        }
        else{
            try{

                $permissions = (new SVModule())->getAll();
                Cache::forever('app_permissions', $permissions);

            } catch (\Illuminate\Database\QueryException $e) {
                $permissions = collect();
            }
        }

        return $permissions;
    }

    /**
     * Get all action must be check
     * */
    public static function fetchActions() {
        return get_default_action();
    }

    /**
     * List Foreign Kyes of table
    */
    public static function listTableForeignKeys($table)
    {
        $conn = Schema::getConnection()->getDoctrineSchemaManager();

        return array_map(function($key) {
            return $key->getName();
        }, $conn->listTableForeignKeys($table));
    }

    public static function getMetaData(string $nameKey, string $nameValue, $select = '*')
    {
        $result = DB::table('meta');

        if ( isset( $nameKey ) )
            $result->where([ 'key' => $nameKey ]);

        if ( isset( $nameValue ) )
            $result->where([ 'value' => $nameValue ]);
            
        $result->whereNull('deleted_at');

        $result->select($select);
        return $result->first();

     }

    /**
     * Function unsetMulti
     * @param $object type object or array
     * @param $arr element for unset example ['id', 'name']
    */
    public static function unsetMulti(&$object, $arr) {
        foreach ($arr as $v)
        {
            if(is_array($object) && isset($object[$v]))
                unset($object[$v]);
            else if(!is_array($object) && isset($object->{$v}))
                unset($object->{$v});
        }
    }
    public static function getWeek($date){
        $date_stamp = strtotime(date('Y-m-d', strtotime($date)));

         //check date is sunday or monday
        $stamp = date('l', $date_stamp);
        $timestamp = strtotime($date);
        //start week
        if(date('y-m-d', $timestamp) == 'Mon'){
            $week_start = date('Y-m-d', strtotime('This Monday', $date_stamp));
        }else{
            $week_start = date('Y-m-d', strtotime('Last Monday', $date_stamp));
        }
        //end week
        if($stamp == 'Sunday'){
            $week_end = date('Y-m-d', strtotime('this Sunday', $date));
        }else{
            $week_end = date('Y-m-d', strtotime('This Sunday', $date_stamp));
        }
        return array('start'=>$week_start, 'end'=>$week_end);

    }
    public static function createDateRangeArray($strDateFrom,$strDateTo)
    {
        // takes two dates formatted as YYYY-MM-DD and creates an
        // inclusive array of the dates between the from and to dates.

        // could test validity of dates here but I'm already doing
        // that in the main script

        $aryRange = [];

        $iDateFrom = mktime(1, 0, 0, substr($strDateFrom, 5, 2), substr($strDateFrom, 8, 2), substr($strDateFrom, 0, 4));
        $iDateTo = mktime(1, 0, 0, substr($strDateTo, 5, 2), substr($strDateTo, 8, 2), substr($strDateTo, 0, 4));

        if ($iDateTo >= $iDateFrom) {
            array_push($aryRange, date('Y-m-d', $iDateFrom)); // first entry
            while ($iDateFrom<$iDateTo) {
                $iDateFrom += 86400; // add 24 hours
                array_push($aryRange, date('Y-m-d', $iDateFrom));
            }
        }
        return $aryRange;
    }

    public static function getWeekAM($num,string $date,string $name){

        if($date == "weekly"){
            $currentDate = \Carbon\Carbon::now();
            $start = $currentDate->subDays($currentDate->dayOfWeek - $num)->subWeek()->format('Y-m-d');
            $end = $currentDate->endOfWeek()->format('Y-m-d');
           return $name == 'start' ? $start : $end;

        }else{
            $currentDate = \Carbon\Carbon::now();
            $start = $currentDate->startOfMonth()->subMonth()->format('Y-m-d');
            $end =$currentDate->endOfMonth()->format('Y-m-d');
            return $name == 'start' ? $start : $end;
        }
    }
    public static function getWeekAMCurrent($num,string $date,string $name){

        if($date == "weekly"){
            $currentDate = \Carbon\Carbon::now();
            $start = $currentDate->startOfWeek()->format('Y-m-d');
            $end = $currentDate->endOfWeek()->format('Y-m-d');
           return $name == 'start' ? $start : $end;

        }else{
            $currentDate = \Carbon\Carbon::now();
            $start = $currentDate->startOfMonth()->format('Y-m-d');
            $end =$currentDate->endOfMonth()->format('Y-m-d');
            return $name == 'start' ? $start : $end;
        }
    }

    /**
     * Get convert url params to array
     * 
     * @var string $urlParams The url params Example: kok=1&log=2
     * @var array $params The reference after converted Example: []
     * 
     * */ 
    public static function convertUrlParamToArray(string $urlParams, array &$params)
    {
        parse_str($urlParams, $params);
    }

    /**
     * Setup params reference
     * @param array $params The reference params
     * @param array $values The values for values params
     * @return void
     * */ 
    public static function getSetupReference(array &$params, array $values = []) : void
    {
        foreach ($values as $k => $v) 
        {
            $params[$k] = $params[$k] ?? $v;
        }
    }


    /**
     * Synchronize data across database
     * 
     * @param $table string which table that we would like to synchronize
     * @param $data array the list of data that we would like to synchronize
     * @param $database the source destination database that we would like to synchronize
     * 
     * @return void
     * */ 
    public static function syncData(string $table, $data, string $database) : void
    {
        $oldConnection = DB::connection()->getDatabaseName();
        $data          = SyncTools::remap($table, $data);
        foreach ($data as $value) {
            $val                = is_object($value) ? (array)$value : $value->toArray();
            $sync['table']      = $table;
            $sync['values']     = $val;
            $dataSync['sync'][] = $sync;
        }
        // Log::info("========== SYNC TABLE [". $table ."] =================");
        self::reconnectDB($database);
        try {
            $ret = (new SVSynchronizeToSuperAdmin)->process($dataSync);
            // Log::info("Done sync super admin [" . $table . "] : " . json_encode($ret));
            self::reconnectDB($oldConnection);
        } catch (\Throwable $th) {
            // Log::info("ERROR SYNC TO AUTH DB : ". $th->getMessage());
            self::reconnectDB($oldConnection);
        }
    }

}
