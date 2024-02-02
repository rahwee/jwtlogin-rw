<?php

use Carbon\Carbon;
use App\Models\Account;
use App\Enums\Constants;
use App\Services\SVMeta;
use App\Models\CashRegister;
use App\Services\SVDatabase;
use Akaunting\Money\Currency;
use Illuminate\Http\Response;
use App\Http\Tools\DateHelper;
use App\Http\Tools\ParamTools;
use App\Exceptions\POSException;
use App\Http\Tools\RabbitmqTool;
use App\Services\SVFavoriteReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Exports\Backoffice\Reports\ReportManager;
use App\Models\History;
use App\Models\Payment;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Ixudra\Curl\Facades\Curl;

define('NO_IMG', 'megadin/imgs/not-available.jpg');
define('NO_LOGO', '/images/logo.png');
define('CUSTOM_COLOR_CATEGORY', '80CED7');
define("BOOKING_REST_NO_IMG", "https://is3-ssl.mzstatic.com/image/thumb/Purple124/v4/bb/b2/b3/bbb2b38b-f3b1-5e87-b5fe-cac67155218f/source/512x512bb.jpg");
define("LOGO_YOUDING", '/megadin/imgs/Youding_logo.png');
define("FORMAT_DATE_TIME_CASHOUT", "m/d/y g:i A");
define('NO_PROFILE_IMG', 'https://www.gravatar.com/avatar?d=mm');
define('COUNTRY_SUPPORT_NF525', ["FR", "GY", "MQ", "GP", "PM"]);


function hasLogin()
{
    $user = auth()->user();
    return $user != null;
}

function is_superadmin()
{
    $user = auth()->user();
    if (!$user) {
        return false;
    }
    return $user->account->is_default;
    // $user_role = $user->role?strtolower($user->role->name):'';

    // return ($user_role == "superadmin" && $user->account->is_default);
}

function is_admin()
{
    // $user = auth()->user();
    // if (!$user) {
    //     return false;
    // }
    // $is_admin = $user->role?$user->role->is_admin:false;

    // return ($is_admin && !$user->account->is_default);
    return !is_superadmin();
}

function user_admin()
{
    $user = auth()->user();
    if (!$user) {
        return false;
    }
    $is_admin = $user->role ? $user->role->is_admin : false;

    return ($is_admin && !$user->account->is_default);
}

function is_youding_default()
{
    $user = auth()->user();
    if (!$user) return false;

    $is_admin = $user->role ? $user->role->is_admin : false;

    return ($is_admin && $user->account->is_youding_default);
}

function is_supplier()
{
    $user = auth()->user();
    if (!$user) {
        return false;
    }

    $role = $user->role;
    if ($role->name == 'Supplier') {
        return true;
    }
    return false;
}

function get_sort_date()
{
    $date = [
        'TODAY',
        'YESTERDAY',
        'CURRENT_WEEK',
        'LAST_WEEK',
        'CURRENT_MONTH',
        'LAST_MONTH',
        'CURRENT_YEAR',
        'LAST_YEAR',
        'CUSTOM',
    ];

    return $date;
}

function myLogInfo($text, $params, $api = false)
{
    $var = $api ? 'API' : 'Back Office';
    Log::info("*** $text  $var ***");
    if (!empty($params))
        Log::info($params);
    Log::info("*** $text $var ***");
}

if (!function_exists('get_record_by_date'))
{
    function get_record_by_date($date, $field)
    {
        $q = '';
        switch ($date) {
            case "TODAY":
                $q = "DATE($field)=DATE(NOW())";
                break;
            case "YESTERDAY":
                $q = "DATE($field) = DATE(NOW() - INTERVAL 1 DAY)";
                break;
            case "CURRENT_WEEK":
                $q = "YEARWEEK($field, 7) = YEARWEEK(NOW(), 7) AND YEAR($field) = YEAR(NOW())";
                break;
            case "LAST_WEEK":
                $q = "YEARWEEK($field, 7) = YEARWEEK(NOW() - INTERVAL 1 WEEK, 7)";
                break;
            case "CURRENT_MONTH":
                $q = "MONTH($field) = MONTH(NOW()) AND YEAR($field) = YEAR(NOW())";
                break;
            case "LAST_MONTH":
                $q = "MONTH($field) = MONTH(NOW() - INTERVAL 1 MONTH)";
                break;
            case "CURRENT_YEAR":
                $q = "YEAR($field) = YEAR(NOW())";
                break;
            case "LAST_YEAR":
                $q = "YEAR($field) = YEAR(NOW() - INTERVAL 1 YEAR)";
                break;
        }
        return $q;
    }
}


if (!function_exists('str2dateRange'))
{
    function str2dateRange($str_date)
    {
        $ret      = [];
        $now      = date('Y-m-d');
        $str_date = strtoupper($str_date);

        switch ($str_date) {
            case 'YESTERDAY': 
                $ret = [
                    'start' => date('Y-m-d', strtotime($now . ' -1 days')),
                    'end'   => date('Y-m-d', strtotime($now . ' -1 days')),
                ];
                break;
            case 'CURRENT_WEEK': 
                $monday                              = strtotime("last monday");
                $monday                              = date('w', $monday) == date('w') ? $monday + 7 * 86400 : $monday;
                $sunday                              = strtotime(date("Y-m-d", $monday) . " +6 days");
                $this_week_sd                        = date("Y-m-d", $monday);
                $this_week_ed                        = date("Y-m-d", $sunday);
                if($this_week_ed >= $now) $this_week_ed = $now;
                $ret                                 = [
                    'start' => $this_week_sd,
                    'end'   => $this_week_ed,
                ];
                break;
            case 'LAST_WEEK': 
                $monday       = strtotime("last monday");
                $monday       = date('W', $monday) == date('W') ? $monday - 7 * 86400 : $monday;
                $sunday       = strtotime(date("Y-m-d", $monday) . " +6 days");
                $this_week_sd = date("Y-m-d", $monday);
                $this_week_ed = date("Y-m-d", $sunday);
                $ret          = [
                    'start' => $this_week_sd,
                    'end'   => $this_week_ed,
                ];
                break;
            case 'CURRENT_MONTH': 
                $ret = [
                    'start' => date('Y-m-d', strtotime(date("Y") . "-" . date("m") . "-01")),
                    'end'   => $now                                                            // the old date("Y-m-t"),
                ];
                break;
            case 'LAST_MONTH': 
                $current_month = date('m');
                $current_year  = date('Y');
                if ($current_month == 1) {
                    $current_month = 12;
                    $current_year--;
                } else {
                    $current_month--;
                }
                if ($current_month < 10) {
                    $current_month = "0$current_month";
                }
                $first_of_month = $current_year . "-" . $current_month . "-01";
                $end_of_month   = date("Y-m-t", strtotime($current_year . "-" . $current_month . "-01"));
                $ret            = [
                    'start' => $first_of_month,
                    'end'   => $end_of_month,
                ];
                break;
            case 'CURRENT_YEAR': 
                $ret = [
                    'start' => date('Y') . "-01-01",
                    'end'   => $now                   // the old date("Y") . "-12-31",
                ];
                break;
            case 'LAST_YEAR': 
                $ret = [
                    'start' => (date('Y') - 1) . "-01-01",
                    'end'   => (date("Y") - 1) . "-12-31",
                ];
                break;
            case 'ALL': 
                $ret = [
                    'start' => null,
                    'end'   => null,
                ];
                break;
            default: 
                $ret = [
                    'start' => $now,
                    'end'   => $now,
                ];
                break;
        }
        return $ret;
    }
}

function getIntervals($start, $end)
{
    $dtStart = date_create($start);
    $dtEnd = date_create($end);
    $nbDays = date_diff($dtStart, $dtEnd)->days;
    $interval = 1;
    $intervalType = 'day';
    $intervals = [];
    if ($nbDays >= 60) {
        $interval = 31;
        $intervalType = 'month';
        $dtStart = date_create(date('Y-m-d', strtotime('first day of this month', strtotime($start))));
    } else if ($nbDays >= 14) {
        $interval = 7;
        $intervalType = 'week';
        $dtStart = date_create(date('Y-m-d', strtotime('last monday', strtotime($start))));
    } else if($nbDays === 0){
        $dtEnd = date_create($end.date("23:59:59"));
        while ($dtStart <= $dtEnd) {
            $intStart = date_format($dtStart, 'Y-m-d H:0:0');
            $intEnd = date_format($dtStart, 'Y-m-d H:59:59');
            $label = date_format($dtStart, 'M-d H A');
            date_add($dtStart, date_interval_create_from_date_string($interval . " hours"));
            $paramsDate = [
                "start_date"    => Carbon::parse($intStart)->format('Y-m-d H:i:s'),
                "end_date"      => Carbon::parse($intEnd)->format('Y-m-d H:i:s'),
                "label"         => $label
            ];
            $intervals [] = $paramsDate;
        }
        return [
            "intervals"     => $intervals,
            "intervalType"  => $intervalType
        ];
    }

    while ($dtStart <= $dtEnd) {
        $intStart = date_format($dtStart, 'Y-m-d');
        date_add($dtStart, date_interval_create_from_date_string($interval . " days"));
        if ($interval == 31) { // adjust start date for months
            $dtStart = date_create(date('Y-m-d', strtotime('first day of this month', strtotime(date_format($dtStart, 'Y-m-d')))));
        }

        if ($intervalType == "day") {
            $label = date('M-d', strtotime('-1 day', strtotime(date_format($dtStart, 'Y-m-d'))));
        }elseif ($intervalType == "week") {
            $label = "Week ".date('W', strtotime('-1 day', strtotime(date_format($dtStart, 'Y-m-d'))));
        }elseif ($intervalType == "month") {
            $label = date('M', strtotime('-1 day', strtotime(date_format($dtStart, 'Y-m-d'))));
        }

        $intEnd = date('Y-m-d', strtotime('-1 day', strtotime(date_format($dtStart, 'Y-m-d'))));

        $paramsDate = [
            "start_date"    => Carbon::parse($intStart)->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            "end_date"      => Carbon::parse($intEnd)->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            "label"         => $label
        ];

        $intervals[] = $paramsDate;
    }

    return [
        "intervals"     => $intervals,
        "intervalType"  => $intervalType
    ];
}

// Transform hours like "1:45 AM" into the total number of minutes, "105".
function hoursToMinutes($hours_base)
{
    $hours = 0;
    $minutes = 0;
    $hours_base = str_replace(' ', '', $hours_base);
    $ampm = substr($hours_base, -2, 2);
    $hours_base = str_replace($ampm, '', $hours_base);

    if (strpos($hours_base, ':') !== false) {
        // Split hours and minutes.
        list($hours, $minutes) = explode(':', $hours_base);
    }
    if ((strtoupper($ampm) == "PM") && $hours < 12) {
        $hours += 12;
    } elseif (strtoupper($ampm) == "AM" && $hours == 12) {
        $hours = 0;
    }

    return (int) $hours * 60 + (int) $minutes;
}

function format_time($t, $f = ':') // t = seconds, f = separator
{
    return sprintf("%02d%s%02d%s%02d", floor($t / 3600), $f, ($t / 60) % 60, $f, $t % 60);
}

// Transform minutes like "105" into hours like "1:45". 24hours
function minutesToHours($minutes)
{
    $hours = (int) ($minutes / 60);
    $minutes -= $hours * 60;

    return sprintf("%d:%02.0f", $hours, $minutes);
}

if ( !function_exists('minutesToHoursWithSecond') )
{
    function minutesToHoursWithSecond($minutes)
    {
        $hours = (int) ($minutes / 60);
        $minutes -= $hours * 60;

        return sprintf("%02d:%02d:00", $hours, $minutes);
    }
}


function minutesToHoursSign($minutes)
{
    $sign = $minutes < 0 ? "-" : "";
    $minutes = $sign == "-" ? explode($sign, $minutes)[1] : $minutes;
    $hours = (int) ($minutes / 60);
    $minutes -= $hours * 60;
    if ($hours == 0 && $minutes != 0) return $sign . sprintf("%02.0fmin", $minutes);
    else if ($hours != 0 && $minutes == 0) return $sign . sprintf("%dh", $hours);
    else if ($hours == 0 && $minutes == 0) return $sign;
    return $sign . sprintf("%dh%02.0f", $hours, $minutes);
}

#hourMinute2Minutes('01:30'); // returns 90
function hourMinute2Minutes($strHourMinute)
{
    $from = date('Y-m-d 00:00:00');
    $to = date('Y-m-d ' . $strHourMinute . ':00');
    $diff = strtotime($to) - strtotime($from);
    $minutes = $diff / 60;
    return (int) $minutes;
}

function getNameRest($global_id)
{
    return \App\Models\Localize::where(['obj_gid' => $global_id, 'locale' => App::getLocale(), 'column' => 'name'])->pluck('value')->first();
}

function minutesToHoursFormated($minutes)
{
    $hours = (int) ($minutes / 60);
    $minutes -= $hours * 60;
    return printf("%d:%02.0f", $hours, $minutes);
}
function minutesTo12HoursFormated($minutes)
{
    $hours = (int) ($minutes / 60);
    $minutes -= $hours * 60;
    if ($hours > 24) {
        $hours -= 24;
    }
    $sh = "AM";
    if ($hours >= 12) {
        $sh = "PM";
        if ($hours == 24) {
            $sh = "AM";
        }

        if ($hours >= 13) {
            $hours -= 12;
        }
    } elseif ($hours == 0) {
        $hours = 12;
    }
    return sprintf("%d:%02.0f %s", $hours, $minutes, $sh);
}

function sub_string($string, $len = 150)
{
    if (strlen($string) > $len) {
        $string = substr($string, 0, $len) . "...";
    }

    return $string;
}

function get_sort_links($field_name = '')
{
    $sort_query = array('sort' => $field_name, 'order' => 'asc');
    $query_str = array_merge(request()->all(), $sort_query);

    if (request('order') == 'asc') {
        $query_str['order'] = 'desc';
    } else {
        $query_str['order'] = 'asc';
    }
    if ($field_name != request('sort')) {
        $query_str['order'] = 'asc';
    }

    return '?' . http_build_query($query_str);
}

function get_sort_icon($field_name = '')
{
    $icon = 'sort';
    if (request('sort') == $field_name) {
        $sort = request('order');
        $icon = "sort-alpha-$sort";
    }
    return $icon;
}

function append_build_query($fields = [])
{
    $query_str = array_merge($_GET, $fields);
    return empty($query_str) ? '' : ('?' . http_build_query($query_str));
}
function toDateTime($datetime)
{
    $date = date_create($datetime);
    return date_format($date, 'Y-m-d H:i:s');
}

if (!function_exists("get_default_value")) {
    function get_default_value($key = null)
    {
        $path = storage_path() . "/json/default-data.json";
        $json = json_decode(file_get_contents($path), true);
        if ($key === null) {
            return $json;
        }
        return isset($json[$key]) ? $json[$key] : null;
    }
}

if (!function_exists("get_default_action")) {
    function get_default_action()
    {
        $path = storage_path() . "/json/actions.json";
        return json_decode(file_get_contents($path), true);
    }
}

function setMapReplaceGidToId(&$arr, $key, $val)
{
    $arr[$key] = $val;
}
function setReplaceGidToId(&$data, $arr)
{
    foreach ($data as $k => $v) {
        foreach ($v as $kf => $vf) {
            $valReplace = $arr[$kf];
            $data[$k][$kf] = $valReplace[$vf];
        }
    }
}

if (!function_exists('getRestaurants'))
{
    function getRestaurants($db)
    {
        $restaurants = DB::table('account')
            ->join('meta', 'account.account_type', 'meta.id')
            ->select('account.global_id', 'account.parent_id', 'account.id', 'account.name', 'account.db_name', 'account.timezone')
            ->where('account.db_name', $db)
            ->where('meta.key', SVMeta::KEYS[SVMeta::KEY_GROUP_TYPE])
            ->where('meta.value', SVMeta::GROUP_TYPES[SVMeta::GROUP_TYPE_RESTAURANT])
            ->whereNull('account.deleted_at')
            ->get();
        return $restaurants;
    }
}


function get_active_menu($menus = '')
{
    $active = session('active_menu', '');
    $menus = explode("|", $menus);
    return in_array($active, $menus) ? "active" : "";
}

function to_user_timezone($datetime, $tz = 'UTC')
{
    $tz   = Auth::check() && isset(auth()->user()->timezone) ? auth()->user()->timezone : $tz;
    $date = new DateTime($datetime);
    $date->setTimezone(new DateTimeZone($tz));
    return $date->format("Y-m-d");
}

function to_database_timezone($datetime)
{
    $tz = auth()->user()->timezone ? auth()->user()->timezone : 'UTC';
    $date = new DateTime($datetime, new DateTimeZone($tz));
    $date->setTimezone(new DateTimeZone('UTC'));
    return $date->format("Y-m-d H:i:s");
}
function convertRestTimezoneToUTC($datetime, $tz = 'Asia/Shanghai')
{
    $date = new DateTime($datetime, new DateTimeZone($tz));
    $date->setTimezone(new DateTimeZone('UTC'));
    return $date->format("Y-m-d H:i:s");
}

function convertUTC2RestTimezone($datetime, $tz = 'Asia/Shanghai')
{
    $date = new DateTime($datetime);
    $date->setTimezone(new DateTimeZone($tz));
    return $date->format("Y-m-d H:i:s");
}

function getCurrencies()
{
    return auth()->user()->account
        ->currencies()
        ->orderBy('is_default', 'DESC')
        ->get();
}

function getCurrency()
{
    $keyCacheCurrencyRestaurant = 'ACCOUNT-CURRENCY-' . auth()->user()->restaurant_gid;

    if ($currency = Cache::get($keyCacheCurrencyRestaurant))
        return $currency;

    $account  = Account::where("global_id", auth()->user()->restaurant_gid)->first();
    $currency = $account && $account->currency_code ?  $account->currency_code : "USD";
    Cache::forever($keyCacheCurrencyRestaurant, $currency);
    return $currency;
}

function getCurrencyObject($code = null)
{
    $currency = $code ?? getCurrency();
    return (new \Akaunting\Money\Currency($currency));
}
function getCurrencySign($code = null)
{
    $currency = $code ?? getCurrency();
    $symbol = (new \Akaunting\Money\Currency($currency))->getSymbol();
    session(['currencySign' => $symbol]);
    return $symbol;
}
function getSubunit($code = null)
{
    $currencyCode = $code ?? getCurrency();
    return (new \Akaunting\Money\Currency($currencyCode))->getSubunit();
}
// convert money amount into cents for storing into db
function convertAmountToCents($amount, $currency = null)
{
    $currencyCode = $currency ?? getCurrency();
    $subunit = (new \Akaunting\Money\Currency($currencyCode))->getSubunit();
    $amount = parseAmountFromString($amount);
    return $amount * $subunit;
}
// convert cents to money for displaying in page (without currency sign)
function convertCentsToAmount($cents, $currency = null, $convert = false)
{
    $currencyCode = $currency ?? getCurrency();
    return money($cents, $currencyCode, $convert)->getValue();
}
function parseAmountFromString($amount)
{
    $currencyCode = getCurrency();
    $currency = (new \Akaunting\Money\Currency($currencyCode));
    if (!is_string($amount)) {
        return $amount;
    }
    $thousandsSeparator = $currency->getThousandsSeparator();
    $decimalMark = $currency->getDecimalMark();
    $amount = str_replace($currency->getSymbol(), '', $amount);
    $amount = preg_replace('/[^0-9\\' . $thousandsSeparator . '\\' . $decimalMark . '\-\+]/', '', $amount);
    $amount = str_replace($currency->getThousandsSeparator(), '', $amount);
    $amount = str_replace($currency->getDecimalMark(), '.', $amount);
    if (preg_match('/^([\-\+])?\d+$/', $amount)) {
        $amount = (int) $amount;
    } elseif (preg_match('/^([\-\+])?\d+\.\d+$/', $amount)) {
        $amount = (float) $amount;
    }
    return $amount;
}

function minutesToHoursWithShift($minutes)
{
    $hours = (int) ($minutes / 60);
    $minutes -= $hours * 60;
    if ($hours > 24) {
        $hours -= 24;
    }

    $sh = "AM";
    if ($hours >= 12) {
        $sh = "PM";
        if ($hours == 24) {
            $sh = "AM";
        }

        if ($hours >= 13) {
            $hours -= 12;
        }
    } elseif ($hours == 0) {
        $hours = 12;
    }
    return sprintf("%d:%02.0f%s", $hours, $minutes, $sh);
}
function minutesToHoursShort($minutes)
{
    $hours = (int) ($minutes / 60);
    $minutes -= $hours * 60;

    return $minutes > 0 ? sprintf("%d:%02.0f", $hours, $minutes)
        : $hours;
}

function minuteToHour12($minutes)
{
    $hours = (int) ($minutes / 60);
    $minutes -= $hours * 60;
    if ($hours > 24) {
        $hours -= 24;
    }

    return sprintf("%d:%02.0f", $hours, $minutes);
}


function removeAttachment($att_id)
{
    $att = \App\Models\Attachment::find($att_id);
    if ($att) {
        unlink(public_path($att->file));
        $att->delete();
    }
}
function getTabActive($tab, $default = 'dish')
{
    return (request('tab') ?? $default) == $tab ? 'active' : '';
}
function getTabContentActive($tab, $default = 'dish')
{
    return (request('tab') ?? $default) == $tab ? ' in active' : '';
}

function getColors()
{
    return [
        "80CED7",
        "FAE3E3",
        "ED8989",
        "F8C7CB",
        "B6D7B9",
        "FE938C",
        "9AD1D4",
        "6D98BA",
        "C6DDF0",
        "F4FAFF",
        "D6EDFF",
        "E8AEB7",
        "B8E1FF",
        "80CFA9",
        "A9FFF7",
        "AEECEF",
        "DA667B",
        "B2EDC5",
        "DDFFF7",
        "93E1D8",
    ];
}

function getScheduleColor()
{
    return [
        "16cbf6",
        "ffc057",
        "36cbb0",
        "fa97c0",
        "89d3d0",
    ];
}
function getShortName($string)
{
    $ignore = ['le', 'a', 'an', 'the', 'of', 'on', 'in'];
    $words = explode(" ", $string);
    $letters = "";
    foreach ($words as $value) {
        if (in_array(strtolower($value), $ignore)) {
            continue;
        }

        $letters .= mb_substr($value, 0, 1, 'utf-8');
    }
    return $letters;
}
function percent(Float $total, Float $value)
{
    if ($total <= 0)
        return 0;

    return round($value * 100 / $total, 2);
}
function prepare_array_ids($items)
{
    $ret = [];
    foreach ($items as $row) {
        $ret[] = $row->id;
    }
    return $ret;
}
function calculatePercent($old, $new)
{
    // old = 100%
    $delta = $new - $old;
    if ($old == 0) {
        if ($new == 0) {
            return 0;
        }
        return 100;
    }
    return (int) ($delta * 100 / $old);
}
function buildValueText($change)
{
    $image = "<i class='fa fa-caret-up text-success'></i>";
    if ($change < 0) {
        $image = "<i class='fa fa-caret-down text-danger'></i>";
    } else if ($change == 0) {
        $image = "=";
    }
    return $image;
}
function buildChangeText($change)
{
    $status = "increase";
    if ($change < 0) {
        $status = "decrease";
    } elseif ($change == 0) {
        $status = "constant";
    }
    return $status;
}
function minusOrPlus($change)
{
    $status = '+ ';
    if ($change < 0) {
        $status = '- ';
    } elseif ($change == 0) {
        $status = "";
    }
    return $status;
}
function thousandsCurrencyFormat($num)
{
    if ($num > 1000) {
        $x = round($num);
        $x_number_format = number_format($x);
        $x_array = explode(',', $x_number_format);
        $x_parts = array('k', 'm', 'b', 't');
        $x_count_parts = count($x_array) - 1;
        $x_display = $x;
        $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
        $x_display .= $x_parts[$x_count_parts - 1];

        return $x_display;
    }
    return $num;
}

function resolveTimeShift($hour)
{
    $strtime = $hour . " PM";

    if ($hour < 12) {
        $strtime = $hour . " AM";
    }

    if ($hour > 12) {
        $strtime = ($hour - 12) . " PM";
    }

    if ($hour == 24) {
        $strtime = ($hour - 12) . " AM";
    }
    return $strtime;
}

function getFrom($goals, $input)
{
    $arr = collect($goals)->filter(function ($val) use ($input) {
        return $input >= $val;
    })->toArray();
    reset($arr);
    $first_key = key($arr);
    if (count($arr) && in_array($input, $arr))
        return $input;
    else if (count($arr) && !in_array($input, $arr))
        return $arr[$first_key] + 1;
    return 0;
}

function is_database_auth()
{
    $authDb      = config('app.db_auth');
    $dbConnected = DB::connection()->getDatabaseName();
    return $authDb == $dbConnected;
}

function tz_list()
{
    $zones_array = array();
    $timestamp = time();
    foreach (timezone_identifiers_list() as $key => $zone) {
        date_default_timezone_set($zone);
        $gmt = 'GMT' . date('P', $timestamp);
        $zones_array[$key]['zone'] = $zone;
        $zones_array[$key]['gmt'] = $gmt;
    }
    return $zones_array;
}
function listGmt()
{
    $ret = [
        "GMT+0",
        "GMT+1",
        "GMT+2",
        "GMT+3",
        "GMT+3:30",
        "GMT+4",
        "GMT+4:30",
        "GMT+5",
        "GMT+5:30",
        "GMT+5:45",
        "GMT+6",
        "GMT+6:30",
        "GMT+7",
        "GMT+8",
        "GMT+8:45",
        "GMT+9",
        "GMT+9:30",
        "GMT+10",
        "GMT+10:30",
        "GMT+11",
        "GMT+13",
        "GMT+12",
        "GMT+14",
        "GMT+13:45",
        "GMT-1",
        "GMT-2",
        "GMT-3",
        "GMT-3:30",
        "GMT-4",
        "GMT-5",
        "GMT-6",
        "GMT-7",
        "GMT-8",
        "GMT-9",
        "GMT-9:30",
        "GMT-10",
        "GMT-11",
    ];
    return $ret;
}
function code2Icon($code)
{
    switch ($code) {
        case 0:
            return 'wi-tornado';
        case 1:
            return 'wi-tornado';
        case 2:
            return 'wi-tornado';
        case 3:
            return 'wi-thunderstorm';
        case 4:
            return 'wi-thunderstorm';
        case 5:
            return 'wi-rain-mix';
        case 6:
            return 'wi-rain-mix';
        case 7:
            return 'wi-rain-mix';
        case 8:
            return 'wi-hail';
        case 9:
            return 'wi-sprinkle';
        case 10:
            return 'wi-hail';
        case 11:
            return 'wi-showers';
        case 12:
            return 'wi-showers';
        case 13:
            return 'wi-snow';
        case 14:
            return 'wi-snow';
        case 15:
            return 'wi-snow';
        case 16:
            return 'wi-snow';
        case 17:
            return 'wi-hail';
        case 18:
            return 'wi-hail';
        case 19:
            return 'wi-fog';
        case 20:
            return 'wi-fog';
        case 21:
            return 'wi-fog';
        case 22:
            return 'wi-fog';
        case 23:
            return 'wi-cloudy-gusts';
        case 24:
            return 'wi-cloudy-windy';
        case 25:
            return 'wi-thermometer-exterior';
        case 26:
            return 'wi-cloudy';
        case 27:
            return 'wi-night-cloudy';
        case 28:
            return 'wi-day-cloudy';
        case 29:
            return 'wi-night-cloudy';
        case 30:
            return 'wi-day-cloudy';
        case 31:
            return 'wi-night-clear';
        case 32:
            return 'wi-day-sunny';
        case 33:
            return 'wi-night-clear';
        case 34:
            return 'wi-day-sunny-overcast';
        case 35:
            return 'wi-rain-mix';
        case 36:
            return 'wi-day-sunny';
        case 37:
            return 'wi-thunderstorm';
        case 38:
            return 'wi-thunderstorm';
        case 39:
            return 'wi-thunderstorm';
        case 40:
            return 'wi-thunderstorm';
        case 41:
            return 'wi-snow';
        case 42:
            return 'wi-snow';
        case 43:
            return 'wi-snow';
        case 44:
            return 'wi-day-cloudy';
        case 45:
            return 'wi-storm-showers';
        case 46:
            return 'wi-snow';
        case 47:
            return 'wi-thunderstorm';
        case 3200:
            return 'wi-cloud';
        default:
            return '';
    }
}

function getMyFavoriteReports()
{
    $ret = [];
    $favorites = (new SVFavoriteReport)->getMyFavorites();
    foreach ($favorites as $row) {
        $ret[] = $row->report_name;
    }
    return $ret;
}
function mapReportRoute($report)
{
    $ret = [
        'ReportDashboard' => 'dashboard',
        'ReportRevenue' => 'revenue',
        'ReportAvgSales' => 'avgsales',
        'ReportDishRevenue' => 'dishrevenue',
        'ReportDishEfficiency' => 'dishefficiency',
        'ReportCategories' => 'categories',
        'ReportCancelledDishes' => 'cancelleddishes',
        'ReportPayment' => 'payments',
        'ReportRestaurant' => 'restaurants',
        'ReportCancelledBookings' => 'cancelledbookings',
        'ReportClients' => 'clients',
        'ReportStaffTopPerformance' => 'topperformers',
        'ReportStaffTopHolidays' => 'topholidays',
        'ReportStaffTurnover' => 'turnover',
        'ReportWeather' => 'weather',
        'ReportHoliday' => 'holidays',

        'RepSittingInventoryCtrl' => 'sittinginventory',
        'RepUsedInventoryCtrl' => 'usedinventory',
        'transferreport' => 'transferreport',
        'itempricetracker' => 'itempricetracker',
    ];
    return $ret[$report] ?? null;
}

function timeShift($time)
{
    $day_shift = "am";
    if ($time > 11 && $time <= 24) {
        $day_shift = "pm";
        if ($time == 24) {
            $day_shift = "am";
        }

        if ($time >= 13) {
            $time -= 12;
        }
    }
    $h = floor($time);
    $mn = ($time - $h) * 60;
    if ($mn == 0) {
        $mn = "00";
    }
    return $h . ":" . $mn . " " . __($day_shift);
}

function timeShift2($time)
{
    $day_shift = "am";
    if ($time > 11 && $time <= 24) {
        $day_shift = "pm";
        if ($time == 24) {
            $day_shift = "am";
        }

        if ($time >= 13) {
            $time -= 12;
        }
    }
    $h = $time;
    return $h . " " . __($day_shift);
}
function hex2rgba($color, $opacity = false)
{

    $default = 'rgb(0,0,0)';

    //Return default if no color provided
    if (empty($color)) {
        return $default;
    }

    //Sanitize $color if "#" is provided
    if ($color[0] == '#') {
        $color = substr($color, 1);
    }

    //Check if color has 6 or 3 characters and get values
    if (strlen($color) == 6) {
        $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
    } elseif (strlen($color) == 3) {
        $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
    } else {
        return $default;
    }

    //Convert hexadec to rgb
    $rgb = array_map('hexdec', $hex);

    //Check if opacity is set(rgba or rgb)
    if ($opacity) {
        if (abs($opacity) > 1) {
            $opacity = 1.0;
        }

        $output = 'rgba(' . implode(",", $rgb) . ',' . $opacity . ')';
    } else {
        $output = 'rgb(' . implode(",", $rgb) . ')';
    }

    //Return rgb(a) color string
    return $output;
}
function getStartOfWeekDate($date = null)
{
    return DateHelper::getStartOfWeekDate($date);
}
function getEndOfWeekDate($date = null)
{
    return DateHelper::getEndOfWeekDate($date);
}
function getShiftTotalHours($data = [])
{
    $ret = 0;
    foreach ($data as $row) {
        $ret += ($row->end - $row->start);
    }
    return minutesToHoursShort($ret);
}

// Color generator
function hslToRgb($h, $s, $l)
{
    #    var r, g, b;
    if ($s == 0) {
        $r = $g = $b = $l; // achromatic
    } else {
        if ($l < 0.5) {
            $q = $l * (1 + $s);
        } else {
            $q = $l + $s - $l * $s;
        }
        $p = 2 * $l - $q;
        $r = hue2rgb($p, $q, $h + 1 / 3);
        $g = hue2rgb($p, $q, $h);
        $b = hue2rgb($p, $q, $h - 1 / 3);
    }
    $return = array(floor($r * 255), floor($g * 255), floor($b * 255));
    return $return;
}

function hue2rgb($p, $q, $t)
{
    if ($t < 0) {
        $t++;
    }
    if ($t > 1) {
        $t--;
    }
    if ($t < 1 / 6) {
        return $p + ($q - $p) * 6 * $t;
    }
    if ($t < 1 / 2) {
        return $q;
    }
    if ($t < 2 / 3) {
        return $p + ($q - $p) * (2 / 3 - $t) * 6;
    }
    return $p;
}

function numberToColorHsl($i, $min, $max, $rev = true)
{
    $ratio = $i;
    if ($min > 0 || $max < 1) {
        if ($i < $min) {
            $ratio = 0;
        } elseif ($i > $max) {
            $ratio = 1;
        } else {
            $range = $max - $min;
            $ratio = ($i - $min) / $range;
        }
    }

    $ratio = $rev ? $ratio : 1 - $ratio;

    $hue = $ratio * 1.2 / 3.60;
    $rgb = hslToRgb($hue, 1, .5);
    return 'rgb(' . $rgb[0] . ',' . $rgb[1] . ',' . $rgb[2] . ')';
}
// End color generator

function getAlipayGatewayUrl($gateway)
{
    return "https://openapi.$gateway.com/gateway.do";
}

function array_to_obj($array, &$obj)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $obj->$key = new stdClass();
            array_to_obj($value, $obj->$key);
        } else {
            $obj->$key = $value;
        }
    }
    return $obj;
}

function arrayToObject($array)
{
    $object = new stdClass();
    return array_to_obj($array, $object);
}

if (!function_exists('sendMessagesToSocket'))
{
    function sendMessagesToSocket($msgs, $bindingKeys = array())
    {
        if (count($bindingKeys)) {
            return sendMessagesToRabbitMQ($bindingKeys, $msgs);
        }
    }
}

if (!function_exists('sendMessagesToRabbitMQ'))
{
    /**
     * Send message to rabbitmq
     *
     * @param $params [account_gid, routing_key] is required
     * @param $messages as array json
     *
     *
     * */
    function sendMessagesToRabbitMQ(array $params = array(), array $messages = [])
    {
        $ret = RabbitmqTool::sendMessages($params, $messages);
        return $ret;
    }
}

if (!function_exists('isJson'))
{
    function isJson($string) {
        // Attempt to decode the string as JSON
        $decoded = json_decode($string);

        // Check if the decoding was successful and if the result is an object or an array
        return (json_last_error() === JSON_ERROR_NONE) && (is_object($decoded) || is_array($decoded));
    }
}

function sortData($arrData)
{
    sort($arrData);
    return $arrData;
}

function mapParams($p, $n = true, $o = false)
{
    // $p is params.
    // $n (false) is convert value to 0 when null.
    // $o (true) convert array to object.

    if (count($p) == 0) {
        return [];
    }

    $keys = array_keys($p);
    $list = [];

    for ($i = 0; $i < count($p[$keys[0]]); $i++) {
        $r = [];
        foreach ($keys as $k) {
            $v = $p[$k][$i];
            $r[$k] = $n ? $v : (is_null($v) ? 0 : $v);
        }
        $list[] = $o ? (object) $r : $r;
    }

    return $list;
}

function generateTextNumberWithZero($text, $code, $size, $number)
{
    return $text . '-' . $code . '-' . date('Y') . date('m') . "-" . str_pad($number, $size, "0", STR_PAD_LEFT);
}

function generateCode($table, $columnName, $text, $code_id, $size)
{

    $number = 1;
    $isHas = DB::table($table)->where($columnName, $code_id)->orderBy('id', 'desc')->first();
    if ($isHas) {
        $number = $number + $isHas->index;
        // DB::table($table)->where('restaurant_id',$code_id)
        // ->update(
        //     ['restaurant_id' => $code_id, 'index' => $number]
        // );

    }

    DB::table($table)->insert(
        [$columnName => $code_id, 'index' => $number]
    );

    return generateTextNumberWithZero($text, $code_id, $size, $number);
}

function getSql($query)
{
    $bindings = $query->getBindings();

    return preg_replace_callback('/\?/', function ($match) use (&$bindings, $query) {
        return $query->getConnection()->getPdo()->quote(array_shift($bindings));
    }, $query->toSql());
}
function genSkuCode()
{
    return 'P' . strtoupper(substr(uniqid(), 3, -1));
}

function convert12To24Hours($hours_base = '')
{
    $hours      = 0;
    $minutes    = 0;
    $hours_base = str_replace(' ', '', $hours_base);
    $ampm       = substr($hours_base, -2, 2);
    $hours_base = str_replace($ampm, '', $hours_base);

    if (strpos($hours_base, ':') !== false) {
        // Split hours and minutes.
        list($hours, $minutes) = explode(':', $hours_base);
    }
    if ((strtoupper($ampm) == "PM") && $hours < 12) {
        $hours += 12;
    } elseif (strtoupper($ampm) == "AM" && $hours == 12) {
        $hours = 0;
    }

    return "$hours:$minutes:00";
}


function getRestaurantTimeNow($restaurant_id)
{
    $now = DB::table('restaurant')
        ->where('id', $restaurant_id)
        ->select(DB::raw('CONVERT_TZ(NOW(), @@session.time_zone, timezone) as timestamp'))
        ->first();

    if ($now)
        return $now->timestamp;

    return date("Y-m-d H:i:s");
}

function formatNo($prefix, $code, $dateStr, $id)
{
    return $prefix . "-" . $code . "-" . date('Ym', strtotime($dateStr)) . "-" . str_pad($id, 5, '0', STR_PAD_LEFT);
}

function nameLang($lang_code)
{
    return strtolower(str_replace('-', '_', $lang_code)) . '_name';
}

function enterNameLang($lang_code)
{
    return __("enter_" . strtolower(str_replace('-', '_', $lang_code)) . "_name");
}

function dataTextMessage($lang_code)
{
    return __("the_" . strtolower(str_replace('-', '_', $lang_code)) . "_name_field_is_required");
}

function arrSortObjsByKey($key, $order = 'DESC')
{
    return function ($a, $b) use ($key, $order) {
        // Swap order if necessary
        if ($order == 'DESC') {
            list($a, $b) = array($b, $a);
        }
        // Check data type
        if (is_numeric($a[$key])) {
            return (int)$a[$key] - (int)$b[$key]; // compare numeric
        } else {
            return strnatcasecmp($a[$key], $b[$key]); // compare string
        }
    };
}


function arrChangeValueToUpperCase($arr)
{
    return array_keys(array_change_key_case(array_flip($arr), CASE_UPPER));
}

function get_full_date_between($date1, $date2)
{

    $diff = abs(strtotime($date2) - strtotime($date1));

    $years   = floor($diff / (365 * 60 * 60 * 24));
    $months  = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
    $days    = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));

    $hours   = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24) / (60 * 60));

    $minuts  = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24 - $hours * 60 * 60) / 60);

    $seconds = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24 - $hours * 60 * 60 - $minuts * 60));

    return [
        'years'   => $years,
        'months'  => $months,
        'days'    => $days,
        'hours'   => $hours,
        'minuts'  => $minuts,
        'seconds' => $seconds
    ];
}

function avgMonthByDay($date, $is_date2 = false)
{
    $day = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($date)), date('Y', strtotime($date)));
    $dayOfMonth = date('d', strtotime($date));
    // Calculate month we just plus 1 are the first day, if date 2 are not plus 1
    return (($day - $dayOfMonth) + ($is_date2 ? 0 : 1)) / $day;
}


function buildInputHidden($class_name, $attribute, $val)
{
    return "<input type='hidden' class='$class_name' name='$attribute' value='$val'>";
}

/*
    $img = 127.0.0.1/file/image.jpg
    $img = parse_url($img);
    return : /file/image.jpg
*/
function parseUrl($original, $flag = false)
{

    $p        = parse_url($original);

    if ($flag)
        return $p['path'];

    $original = public_path($p['path']);

    return $original;
}


function getVersion()
{
    return time();
}


function getValueFromLocalize($object, $column = 'name')
{
    if (!$object) return "NULLABLE";
    return isset($object->localizes) && $object->localizes->isEmpty() ? $object->{$column} : $object->localizes[0]->value;
}

function urlCouponVoucher($suffix)
{
    return config('app.url') . '/marketing/coupon-voucher/' . $suffix;
}


function getAllNamesCatDish($con_list, $cat_dishes)
{
    $items = $con_list->items->map(function ($itm) use ($cat_dishes) {
        if ($itm->dish_id) {
            foreach ($cat_dishes as $catDish) {
                if (count($catDish->dishes)) {
                    $dish = collect($catDish->dishes)->firstWhere("id", $itm->dish_id);
                    if ($dish) {
                        $itm->name = !count($dish->localizes) ? $dish->name : $dish->localizes[0]->value;
                        break;
                    }
                }
            }
        } else {
            $cat = collect($cat_dishes)->firstWhere('id', $itm->category_id);

            $itm->name = empty($cat) ? "" : (empty($cat->localizes) ? $cat->name : $cat->localizes[0]->value);
        }
        return $itm;
    });
    $allNames = $items->pluck('name')->toArray();
    $allNames = implode(", ", $allNames);
    return $allNames;
}

function getDescriptionTier($consequence_lists, $cat_dishes)
{
    $description = null;
    foreach ($consequence_lists as $con_index => $con_list) {
        $con_type        = $con_list->type;
        $con_key         = $con_list->key;
        $discount_amount = $con_list->discount_type == 'AMOUNT' ? money($con_list->discount_amount, getCurrency()) : $con_list->discount_amount . "%";
        $allNames        = getAllNamesCatDish($con_list, $cat_dishes);
        $detail = null;
        if ($con_type == 'BILL')
            $detail = "Get a $discount_amount on Bill";
        elseif ($con_key == 'Get a "Discount Amount " on Product/Category')
            $detail = "Get " . $discount_amount . " on " . ($con_type == 'DISH' ? 'product' : 'category') . "($allNames)";
        else
            $detail = "Get $con_list->unit of " . ($con_type == 'DISH' ? 'product' : 'category') . "($allNames) At $discount_amount";

        if ($con_index != count($consequence_lists) - 1)
            $detail .= " " . $con_list->operation;

        $description .= $detail . "<br/>";
    }
    return $description;
}


function getUuid()
{
    return \Faker\Provider\Uuid::uuid();
}


function calculateProfitMargin($amount, $costs)
{
    $profit = $amount - $costs;
    $margin = $amount ? ($profit / $amount) : 0;
    return $margin * 100;
}

function getAllRouteNames()
{
    $routes = app('router')->getRoutes();
    $arrays = (array) $routes;
    $all_route_names = $arrays[array_keys($arrays)[2]];
    return array_keys($all_route_names);
}

function getReplaceGidToId(array $gids, string $table)
{
    return DB::table($table)->whereIn('global_id', $gids)->pluck('id', 'global_id')->toArray();
}

if (!function_exists('concatStringDB')) {
    function concatStringDB($arr_obj, $delim = ", ")
    {
        $str = "";
        if (!count($arr_obj)) return "-";
        foreach ($arr_obj as $obj) {
            if (strlen($str) > 0) $str .= $delim;
            $str .= $obj->name;
        }
        return $str;
    }
}

if (!function_exists('getIsConnectionYoudingDb')) {
    function getIsConnectionYoudingDb($includeDb = array())
    {
        $dbName = DB::connection()->getDatabaseName();
        $prefix = config('app.db_prefix', 'youding');
        $arrDb = array_merge(array('db_default'), $includeDb);
        return strpos($dbName, $prefix . '_') === 0 || in_array($dbName, $arrDb);
    }
}

if (!function_exists('getObjectByGlobalId')) {
    function getObjectByGlobalId(string $table, string $gid)
    {
        return DB::table($table)->where('global_id', $gid)->first();
    }
}

if (!function_exists('getIdByGlobalId')) {
    function getIdByGlobalId($table_name, $global_id)
    {
        $object = DB::table($table_name)->where('global_id', $global_id)->select('id')->first();
        if (empty($object)) {
            throw new ModelNotFoundException("Global ID not found", 404);
        }
        return $object->id;
    }
}

if (!function_exists('getIdsByGlobalIds')) {
    function getIdsByGlobalIds($table_name, $global_ids)
    {
        $object = DB::table($table_name)->whereIn('global_id', $global_ids)->select('id')->get();
        if (empty($object)) {
            throw new ModelNotFoundException("Global IDs not found", 404);
        }

        return $object->pluck('id')->toArray();
    }
}


if (!function_exists('attachGlobalVersion')) {
    /**
     * This helper function is used to add the Global_id and version
     * when using Query Builder
     * 
     * @param array $params
     * @param ?string $table_name The table name that we would like to update by default empty
     * 
     * ```
     * $table_name = "dish_price."
     * 
     * ```
     * 
     * @return array
     */
    function attachGlobalVersion(array $params = [], string $table_name = '') : array
    {
        return array_merge($params, [
            $table_name.'version'   => DB::raw($table_name.'version+1'),
            $table_name.'global_id' => DB::raw('IFNULL('.$table_name.'global_id, "' . getUuid() . '")')
        ]);
    }
}

if (!function_exists('getSetupValue')) {
    /**
     * This helper function is used to setup value to one more thing
     */
    function getSetupValue(mixed $val, mixed $toSomething, mixed $default = null): mixed
    {
        return $val ? $toSomething : $default;
    }
}

if (!function_exists('getCurrentAccountId')) {
    /**
     * Get the evaluated view contents for the given view.
     */
    function getCurrentAccountId()
    {
        $account_gid = auth()->user()->restaurant_gid;
        $accounts    = getReplaceGidToId([$account_gid], (new \App\Services\SVRestaurant)->getTableName());
        return @$accounts[$account_gid];
    }
}

if (!function_exists('getSysTableId')) {
    /**
     * Get systable by table name
     * @param string $table The name of table
     * @return ?int
     * */
    function getSysTableId($table): ?int
    {
        $result = DB::table('sys_table')->where('name', $table)->select('id')->first();
        return $result ? $result->id : null;
    }
}

if (!function_exists('getForeignKeys')) {
    /**
     * Get foreign keys that link to that
     * @param string $dbName The dbName to switch connection
     * @param array $tables The table that we want to get foreign key
     * @return array
     * */
    function getForeignKeys(string $dbName, array $tables = []): array
    {
        // Version SQL have problem innodb sys or innodb simple
        $innoDBF      = 'INNODB_SYS_FOREIGN';
        $innoDBFC     = 'INNODB_SYS_FOREIGN_COLS';
        $query_innodb = "SHOW TABLES FROM INFORMATION_SCHEMA LIKE 'INNODB_SYS_FOREIGN'";
        // Your mysql is Innodb sys
        $isInnodbSys  = count(DB::select($query_innodb)) > 0;
        if (!$isInnodbSys) {
            $innoDBF  = 'INNODB_FOREIGN';
            $innoDBFC = 'INNODB_FOREIGN_COLS';
        }

        $queryTable = " AND 1";
        if (count($tables)) {
            $nums = count($tables) - 1;
            $queryTable = " AND ";
            foreach ($tables as $k => $v) {
                if ($k == 0 && $k == $nums) {
                    $queryTable .= "sysf.REF_NAME = '" . $dbName . "/$v'";
                } else if ($k == 0) {
                    $queryTable .= "(sysf.REF_NAME = '" . $dbName . "/$v'";
                } else if ($k == $nums) {
                    $queryTable .= " OR sysf.REF_NAME = '" . $dbName . "/$v')";
                } else {
                    $queryTable .= " OR sysf.REF_NAME = '" . $dbName . "/$v'";
                }
            }
        }

        return DB::SELECT("SELECT sysf.FOR_NAME as for_name," .
            "sysf.ID as constraint_name," .
            "sysf.REF_NAME as ref_name, fcol.FOR_COL_NAME as for_col_name," .
            "fcol.REF_COL_NAME as ref_col_name" .
            " FROM `information_schema`.`{$innoDBF}` AS sysf" .
            " INNER JOIN `information_schema`.`{$innoDBFC}` AS fcol ON sysf.ID = fcol.ID" .
            " WHERE sysf.id LIKE '" . $dbName . "%'" .
            " $queryTable");
    }
}

if (!function_exists('getPimAddressBuilder')) {
    function getPimAddressBuilder($table)
    {
        $tables = explode(',',$table);
        return  DB::table("pim_address")
            ->select(
                "address_rel.obj_global_id AS object_global_id",
                "pim_address.global_id",
                "pim_address.email",
                "pim_address.phone_number",
                "pim_address.address1",
                "pim_address.address2",
                "pim_address.city",
                "pim_address.country",
                "pim_address.zip_code"
            )
            ->join("address_rel", "address_rel.address_id", "pim_address.id")
            ->join("sys_table", "address_rel.sys_table_id", "sys_table.id")
            ->whereIn("sys_table.name", $tables);
    }
}

if (!function_exists('getRemoveDataRelations')) {
    /**
     * Get remove data relations
     * @param string $dbName The dbName to switch connection
     * @param string $table The table we need to delete all data
     * @param array $fields The fields of table
     * @example array('field' => 'id', 'value' => 1)
     * @return void
     * */
    function getRemoveDataRelations(string $dbName, string $table, array $fields, bool $isMultiple = false): void
    {
        // Get id to delete items that foreign that
        if (Schema::hasTable($table))
        {
            $data = DB::table($table);
            $data = $isMultiple ? $data->where($fields)->get() : $data->where($fields['field'], $fields['value'])->get();
            if ($data) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                foreach ($data as $self) {
                    $id   = $self->id;
                    // Loop foreign key that link to its
                    $fs = getForeignKeys($dbName, array($table));
                    foreach ($fs as $v) {
                        $table_foreign = explode("/", $v->for_name)[1];
                        if (Schema::hasTable($table_foreign)) {
                            DB::table($table_foreign)->where($v->for_col_name, $id)->delete();
                        }
                    }
                }
                // Remove me
                $data = DB::table($table);
                $agr = $isMultiple ? [$fields] : [$fields['field'], $fields['value']];
                $data->where(...$agr)->delete();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
}

if (!function_exists('checkRestaurantLogin')) {
    function checkRestaurantLogin(string $rest_gid)
    {
        $restaurant = DB::select("SELECT a.db_name as db_name, a.global_id as rest_gid FROM account a" .
            " WHERE" .
            " a.global_id = '{$rest_gid}'" .
            " AND a.deleted_at IS NULL");
        if (!count($restaurant))
            throw new POSException('Invalid restaurant', 'WRONG_LOGIN_OR_PASSWORD');
        return $restaurant[0];
    }
}

if ( !function_exists('checkUserLoginOnOwner') )
{
    function checkUserLoginOnOwner($connection, $password_hash, $rest)
    {
        $querySel = 'u.id,' .
            'u.global_id,' .
            'u.last_access,' .
            'u.firstname,' .
            "u.lastname," .
            'u.device_id,' .
            'i.id as image_id,' .
            'i.url as image_url,' .
            'u.account_id,' .
            'ur.role_id,' .
            'u.api_token,' .
            'u.version';
        return $connection->select(
            "SELECT $querySel FROM contact as u" .
                " inner join account_contact ac on u.id = ac.contact_id" .
                " inner join account a on ac.account_id = a.id" .
                " inner join user_role as ur on ur.user_id = u.id" .
                " inner join role as r on ur.role_id = r.id" .
                " left join image as i on u.global_id = i.obj_global_id" .
                " where u.password_pos = '$password_hash'" .
                " and a.global_id = '$rest->rest_gid'" .
                " and u.deleted_at is null" .
                " and a.deleted_at is null" .
                " and ur.role_id is not null"
        );
    }
}

if ( !function_exists('getCurrentServiceCashRegister') )
{
    function getCurrentServiceCashRegister($restaurant_id)
    {
        $cashRegister = CashRegister::where('account_id', $restaurant_id)
            ->whereNull('deleted_at')
            ->orderBy("updated_at", "desc")
            ->first();
        return isset($cashRegister) && !isset($cashRegister->close_date) ? $cashRegister : null;
    }
}


if (!function_exists('getRandomCode')) {
    function getRandomCode()
    {
        return rand(1000, 9999);
    }
}

if (!function_exists('issetArrayValue')) {
    function issetArrayValue($data, $column, $default = NULL)
    {

        return isset($data[$column]) ? $data[$column] : $default;
    }
}

if (!function_exists('convertUTC2TimezoneByFormating')) {

    function convertUTC2TimezoneByFormating($datetime, $tz = 'Asia/Shanghai', $formating = 'Y-m-d H:i:s')
    {
        $date = new DateTime($datetime);
        $date->setTimezone(new DateTimeZone($tz));
        return $date->format($formating);
    }
}

if (!function_exists('formatMoneyNoCurrency')) {
    function formatMoneyNoCurrency($amount, $currency = 'USD')
    {
        return formatCurrency(money($amount, $currency)->getValue(), $currency);
    }
}

if (!function_exists('formatCurrency')) {
    function  formatCurrency($amount, $currency = null, $convert = false)
    {
        $currencyObj = getCurrencyObject($currency);
        $decimalMark = $currencyObj->getDecimalMark();
        $thousandsSeparator = $currencyObj->getThousandsSeparator();
        $precision   = $currencyObj->getPrecision();
        $ret = number_format((float)$amount, $precision, $decimalMark, $thousandsSeparator);
        return $ret;
    }
}

if (!function_exists('currencyFormat'))
{
    /**
     * Convert cents to money for displaying in page (without currency sign)
     * */
    function currencyFormat($cents, $currency = null, $convert = false)
    {
        $currencyCode = $currency ?? getCurrency();
        return money($cents, $currencyCode, $convert)->format();
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($amount, $currency = 'USD')
    {
        $ret = null;
        try {
            $ret = formatCurrency(money($amount, $currency)->getValue(), $currency)." ".$currency;
        } catch (\Throwable $th) {
            $ret = null;
        }
        return $ret;
    }
}

if (!function_exists('getColumnCurrencyRealityAndFormatValue'))
{
    /**
     * Get column format currency reality value
     * @param array|object $data The $data of values using references
     * @param array $columns The column that we need to format currency
     * @return void
     * */
    function getColumnCurrencyRealityAndFormatValue(array|object &$data, array $columns, array $gets = ['_reality', '_format'], bool $flagOriginal = true)
    {
        foreach ($columns as $col)
        {
            $flagNoSuffixFormat = false;
            $isArr = is_array($data);
            if ($isArr) {
                $data[$col]            = isset($data[$col]) ? $data[$col] : 0;
                if (!is_int($data[$col])) {
                    $data[$col] = (float) $data[$col];
                } else {
                    $data[$col] = (int) $data[$col];
                }
                if (in_array('_reality', $gets))
                $data[$col.'_reality'] = convertCentsToAmount($data[$col]);
                if (in_array('_format', $gets))
                $data[$col.'_format']  = formatCurrencyReport($data[$col]);

                if (in_array('_format', $gets) || in_array('_format',  array_keys($gets))) {
                    $valueFormat        = isset($gets['_format']) ? $gets['_format'] : [];
                    $flagNoSuffixFormat = ParamTools::get_value($valueFormat, 'no_suffix', false);
                    $data[$col.($flagNoSuffixFormat?'':'_format')]  = formatCurrencyReport($data[$col]);
                }

                if (!$flagOriginal)
                    unset($data[$col]);
            }
            else
            {
                $data->$col              = isset($data->$col) ? (int)$data->$col : 0;
                if (in_array('_reality', $gets))
                $data->{$col.'_reality'} = convertCentsToAmount($data->$col);
                if (in_array('_format', $gets) || in_array('_format',  array_keys($gets))) {
                    $valueFormat        = isset($gets['_format']) ? $gets['_format'] : [];
                    $flagNoSuffixFormat = ParamTools::get_value($valueFormat, 'no_suffix', false);
                    $data->{$col.($flagNoSuffixFormat?'':'_format')}  = formatCurrencyReport($data->$col);
                }

                if (!$flagOriginal && !$flagNoSuffixFormat)
                    unset($data->$col);
            }
        }
    }
}

if (!function_exists('formatCurrencyReport'))
{
    /**
     * Convert cents to money for displaying in page (without currency sign)
     * */
    function formatCurrencyReport($cents, $currency = null, $convert = false)
    {
        $currencyCode = $currency ?? getCurrency();
        return money($cents, $currencyCode, $convert)->format();
    }
}

if (!function_exists('getUpdatePromotionAmountPercent')) {
    function getUpdatePromotionAmountPercent(&$params)
    {
        $type = $params['type'] ?? 'AMOUNT';

        if ( $params['amount'] && $type == 'AMOUNT')
        {
            $params['amount']  = convertAmountToCents($params['amount']);
            $params['percent'] = 0;
        }
        else
        {
            $params['percent'] = $params['amount'];
            $params['amount'] = 0;
        }
    }
}

/**
 * Get is enabled nf525 by account(resturant)
 * */
if (!function_exists('isEnabledNF525')) {
    function isEnabledNF525($account)
    {
        if (!isset($account->global_id))
            throw new POSException('Account not existing to check NF525', 'NOT_FOUND', [], 400);

        $criteria = array(
            array('key', Constants::META_KEY_COMPLY_WITH),
            array('value', 'NF525'),
            array('modifier', $account->global_id),
            array('deleted_at', NULL),
            array('active', TRUE),
        );

        return DB::table('meta')
            ->selectRaw(1)
            ->whereRaw(TRUE)
            ->where($criteria)
            ->first() != NULL;
    }
}

if (!function_exists('getRestCurrency'))
{
    /**
     * Get currency
     * @return mixed The currency code Ex: 'USD'
     * */
    function getRestCurrency($restId): object
    {
        $currency     = DB::table('account')->where('id', $restId)->first();
        $currencyCode = @$currency->currency_code ? $currency->currency_code : 'USD';
        $currecyObj = (new \Akaunting\Money\Currency($currencyCode));
        return (object) [
            "code" => $currencyCode,
            "currencyObj" => $currecyObj->toArray()[$currencyCode]
        ];
    }
}

if (!function_exists('formatAmountCurrency'))
{
    /**
     * Format amount currency
     *
     * when you convert a floating-point number to an integer in PHP, the decimal portion is truncated (removed)
     * and the resulting integer represents the whole number part of the original floating-point value.
     *
     * @param integer $amount
     * @param string $currency
     * @param string $keyType. Can be use to reverse or get only value
     * */
    function formatAmountCurrency($amount, $currency, $keyType='')
    {
        $amount = is_float($amount) ? intval(round($amount, 2)) : $amount;

        $currency = getCurrencyObject($currency)->toArray()[$currency];

        if(strpos($keyType, 'reverse') > -1) {
            return $amount * (int) $currency['subunit'];
        }

        $price = (int) $amount / (int) $currency['subunit'];
        $value = number_format($price, (int) $currency['precision'], $currency['decimal_mark'], $currency['thousands_separator']);

        if(strpos($keyType, 'value') > -1) {
            return $value;
        }

        return $currency['prefix'] . $value . $currency['suffix'];
    }
}

if (!function_exists('formatCurrencyCentsToAmount'))
{
    function formatCurrencyCentsToAmount(array &$generalReturn, $currency)
    {
        $filteredArray = filterArrayByKeyCompare($generalReturn, 'amount');

        foreach ($filteredArray as $k => $v)
        {
            $generalReturn[$k."_format"] = formatMoney($v, $currency);
        }
    }
}
if (!function_exists('filterArrayByKeyCompare'))
{
    function filterArrayByKeyCompare($generalReturn, $keyCompare = null)
    {
        $filteredKeys = array_filter(array_keys($generalReturn), function ($key) use($keyCompare) {
            return strpos($key, $keyCompare) !== false;
        });
        return array_intersect_key($generalReturn, array_flip($filteredKeys));
    }
}

if ( !function_exists('getUserLastConnection') )
{
    // Get user session last access
    function getUserLastConnection($user)
    {
        $user_session = \App\Models\UserSession::where('user_id', $user->id)
                    ->orderBy('id', 'DESC')
                    ->first();
        return $user_session ? $user_session->created_at : null;
    }
}

if ( !function_exists('setUpDeviceToken') )
{
    /**
     * Setup device token for contact(user)
     * @param User $user required The user of table contact
     * @param array $requestBodyDevices required The param to save
     * @param boolean $clearDeviceToken To clear all old device token
     * @return void
     * */
    function setUpDeviceToken($user, array $requestBodyDevices, $clearDeviceToken = false): void
    {
        $requestBodyDevices['last_access'] = Arr::get($requestBodyDevices, 'last_access', DB::raw("CURRENT_TIMESTAMP"));
        $requestBodyDevices['account_id']  = $user->account_id;

        $app_type = Arr::get($requestBodyDevices, 'app_type', Constants::APP_TYPE_EBUTLER);

        // check if set to delete device token - for e-butler only
        if ($clearDeviceToken) $user->devices()->forceDelete();

        $user->devices()->updateOrCreate(array(
            'app_type' => $app_type,
            'device_name' => Arr::get($requestBodyDevices, 'device_name')
        ), $requestBodyDevices);
    }
}



if ( !function_exists('getRulePaginate') )
{
    /**
     * Get Rule Pagination
     * @return array
     * */
    function getRulePaginate() : array
    {
        return [
            'sort'  => 'nullable|string',
            'order' => 'nullable|string',
            'size'  => 'nullable|integer',
            'page'  => 'nullable|integer',
            's'     => 'nullable|string'
        ];
    }
}

if ( !function_exists('getDescriptionRulePaginate') )
{
    /**
     * Get Rule Description Pagination
     * @return array
     * */
    function getDescriptionRulePaginate() : array
    {
        return array(
            'sort' => getDescriptionParam('In order to sort data','id'),
            'order' => getDescriptionParam('In order to order data', 'desc'),
            'size' => getDescriptionParam('In order to limit data',10),
            'page' => getDescriptionParam('In order to current page data', 1),
            's' => getDescriptionParam('In order to search data'),
        );
    }
}


if (!function_exists('getDescriptionParam'))
{
    /**
     * Get description param
     *
     * @param string $des The desciption about your field
     * @param mixed $example The example to show data
     *
     * @return array
     * */
    function getDescriptionParam(string $des, $example = 'No-example') : array
    {
        return array(
            'description' => $des,
            'example'     => $example
        );
    }
}

if (!function_exists('isDateNoExistingHours'))
{
    function isDateNoExistingHours($date)
    {
        return strlen(trim($date)) == 10;
    }
}

if (!function_exists('getQueryOrigin'))
{
    function getQueryOrigin($query, $alias_meal = 'm', $origins = [])
    {
        $query->join('meta as origin', 'origin.id', $alias_meal.'.origin_id');
        return count($origins) ? $query->whereIn('origin.value', $origins) : $query;
    }
}

if (!function_exists('getOrigin'))
{
    function getOrigin($origin) {
        return DB::table('meta')
        ->where('key', Constants::META_KEY_ORDER_ORIGIN)
        ->whereNull('deleted_at')
        ->where('value', $origin)
        ->first();
    }
}


if (!function_exists('getMappingHourly'))
{
    /**
     * Get hourly summery for ticket Z
     *
     * @param mixed $cashRegisters The array list of the date
     * @param datetime $cashRegisters.*.start_date The start of the date
     * @param datetime $cashRegisters.*.close_date The start of the close_date
     * @param datetime $cashRegisters.*.current_datetime The start of the current_datetime
     *
     * @return ?array Example:
     * start => 01:00
     * end   => 01:59
     *
     * start => 02:00
     * end   => 02:59
     * */
    function getHourlySummery($cashRegisters)
    {
        $hours = array();
        foreach ($cashRegisters as $cashregister)
        {
            $hourStart = (int)date('H',strtotime($cashregister->start_date));

            $endDate = !$cashregister->close_date ? $cashregister->current_datetime : $cashregister->close_date;
            $hourEnd = (int)date('H',strtotime($endDate));

            // Filter over date we need to show 24h
            $isDateFilterOver = date('Y-m-d', strtotime($endDate)) > date('Y-m-d', strtotime($cashregister->start_date));
            if ( $isDateFilterOver ) {
                $hourEnd   = 23;
                $hourStart = 0;
            }

            $step = $hourEnd + 1;
            while($hourStart < $step) {
                $include = array(
                    'start' => $hourStart.":00",
                    'end'   => $hourStart.":59"
                );
                if (!in_array($include, $hours))
                {
                    $hours[] = $include;
                }
                $hourStart += 1;
            }
        }
        return $hours;
    }
}

if (!function_exists('getListingCurrencyRoundingUnit'))
{
    function getListingCurrencyRoundingUnit()
    {
        $path                   = storage_path() . "/json/currency.json";
        $roundingUnitCurrencies = json_decode(file_get_contents($path), true);
        return $roundingUnitCurrencies;
    }
}

if (!function_exists('concatStringToCapitalize'))
{
    function getConcatStringToCapitalize(string $str, string $delimitor = ' ')
    {
        $fn = null;
        foreach (explode($delimitor, $str)  as $v) {
            $fn .= ucfirst($v);
        }
    }
}

if (!function_exists('formatPercentage'))
{
    function formatPercentage($number)
    {
        $roundedNumber = round($number, 2);
        $percentage = (string)$roundedNumber . "%";
        return $percentage;
    }
}

if (!function_exists('getMetaRows'))
{
    function getMetaRows($accounts = [])
    {
        $rows = array();

        if (empty($accounts)) {
            $accounts = DB::table('account')->whereNotNull('parent_id')->pluck('global_id')->toArray();
        }

        foreach ($accounts as $value)
        {
            # Payment Type
            foreach ((new \App\Services\SVSelf())->getJsonMetaPayment() as $paymentType)
            {
                $paymentType['value']    = json_encode($paymentType['value']);
                $paymentType['modifier'] = $value;
                $rows[]                  = attachGlobalVersion($paymentType);
            }

            array_push($rows,
                attachGlobalVersion([
                    'key'       => "OMNICHANNEL_YELLOW_TIMER",
                    'value'     => 10,
                    'is_custom' => 0,
                    'modifier'  => $value,
                    'active'    => 1
                ]),attachGlobalVersion([
                    'key'       => "OMNICHANNEL_RED_TIMER",
                    'value'     => 20,
                    'is_custom' => 0,
                    'modifier'  => $value,
                    'active'    => 1
                ]));
        }

        return $rows;
    }
}

if (!function_exists('getFunctionName'))
{
    /**
     * Get function name
     * @param string $type The type of service
     * @return ?string
     * */
    function getFunctionName(string $type) : ?string
    {
        $fn = null;
        $convertTypes = explode("_", $type);
        foreach ($convertTypes as $k => $v) {
            $v = strtolower($v);
            $fn .= ($k > 0 ? ucfirst($v) : $v);
        }
        return $fn;
    }
}

if (!function_exists('getResponseNameReport'))
{
    /**
     * Get respone name data of report
     * @param string $type The type of service
     * @return string
     * */
    function getResponseNameReport(string $type) : string
    {
        return str_replace('get_', '', strtolower($type));
    }
}

if (!function_exists('getConvertArrayToQueryIn'))
{
    /**
     * Get merge array to query in
     * @param array $arr The array that we are set condion sql IN
     * @return string Ex: [1,2] => '1','2'
     * */
    function getConvertArrayToQueryIn(array $arr) : string
    {
        $ret = "";
        foreach ($arr as $value) {
            if (strlen($ret) > 0) {
                $ret .= ",";
            }
            $ret .= "'".$value."'";
        }
        return $ret;
    }
}

if (!function_exists('getQryImageUrl'))
{
    function getQryImageUrl(string $as = "image_url", string $alisImage = "I")
    {
        $as = $as ? " as ".$as:$as;
        $asset = config('app.media_url');
        return "(CASE WHEN $alisImage.id IS NULL THEN NULL ELSE CONCAT('$asset', $alisImage.url) END)".$as;
    }
}

if(!function_exists('getSqlQueryRevenueRefundEditPayment')) {
    /**
     * Get query refund and edit payment
     * */
    function getSqlQueryRevenueRefundEditPayment(string $alias = "revenue_incl_tax", string $column = 'unit_price_exclude_vat', string $columnOrder = "order_dish", bool $isExcludeVat = false)
    {
        // Calculate tax amount for each paid by qty (tax_amount have multiple by total qty)
        $sqlSumAmount = $isExcludeVat ? "":"+($columnOrder.tax_amount/paid_by.quantity)";

        $sqlSumAmount = "($columnOrder.$column". $sqlSumAmount .")";

        // Calculate real price just multiple by qty
        $sqlSumAmount .= "*paid_by.quantity";

        return "CAST(SUM(
            IF(paid_by.id IS NULL, $columnOrder.total_price_include_vat,
            IF(bill.amount > 0, $sqlSumAmount, 0) - IF(bill.amount < 0, $sqlSumAmount, 0)
            )
        ) AS SIGNED)". ($alias ? ' AS '.$alias:'');
    }
}

if (!function_exists('criteriaDeletedAtIsNull'))
{
    function criteriaDeletedAtIsNull(Array &$columnSetup, Array $columnTable)
    {
        foreach ($columnTable as $v)
            $columnSetup[] = [$v.".deleted_at", NULL];
    }
}

if(!function_exists('getSqlQueryQuantityRefundEditPayment')) {
    /**
     * Get query refund and edit payment
     * */
    function getSqlQueryQuantityRefundEditPayment(string $alias = "quantity") {
        return "CAST(SUM(
            IF(paid_by.id IS NULL, order_dish.quantity,
            IF(bill.amount > 0, paid_by.quantity, 0) - IF(bill.amount < 0, paid_by.quantity, 0)
            )
        ) AS UNSIGNED)". ($alias ? ' AS '.$alias:'');
    }
}

if (!function_exists('getLimitPage'))
{
    function getLimitPage($page, $perpage)
    {
        return " LIMIT ". ($page == 1 ? 0 : ($page - 1) * $perpage ). ",". $perpage;
    }
}
if (!function_exists('getFiltersByChannel'))
{
    function getFiltersByChannel(&$saleData, $filters) {
        if (isset($filters) && !empty($filters)) {
            $saleData->whereIn('meal_type', $filters);
        }
    }
}

if (!function_exists('findParentAccount')) {
    function findParentAccount($account)
    {
        if ($account && !is_null($account->parent_id) && $account->id !== $account->parent_id) {
            $account = DB::table('account')->select(['id', 'parent_id', 'license_active'])->where('id', $account->parent_id)->first();
            $account = findParentAccount($account);
        }
        return $account;
    }
}

if (!function_exists('blockedUserValidation')) {
    function blockedUserValidation($user)
    {
        /**
         * Check and deny blocked users (blocked venue)
         * Note: account.is_default equal 1 is superadmin and we will not check about the blocking
         *
         */

        $account =  DB::table('contact')
            ->select(['account.id', 'account.parent_id', 'account.license_active'])
            ->leftJoin('account_contact', 'account_contact.contact_id', 'contact.id')
            ->leftJoin('account', 'account.id', 'account_contact.account_id')
            ->where('contact.global_id', $user->global_id)->first();
        $account = findParentAccount($account);

        // $system_databases = [
        //     config('database.connections.' . config('database.default') . ".database"),
        //     config('dynamic.database.db_kpi')
        // ];
        // if (
        //     $account && $account->license_active &&
        //     !in_array($account->db_name, $system_databases)) {
        //     throw new POSException('This tenant is blocked.', "BLOCKED_TENANT", [], Response::HTTP_BAD_REQUEST);
        // }

        if ($account && $account->license_active === 0) {
            throw new POSException('This tenant is blocked.', "BLOCKED_TENANT", [], Response::HTTP_BAD_REQUEST);
        }
    }
}

if (!function_exists('blockedVenueValidation')) {
    function blockedVenueValidation($restaurant_gid)
    {
        $blocked = DB::table('account')
            ->select('license_active')
            ->where('global_id', $restaurant_gid)
            ->where('license_active', 0)
            ->first();
        if ($blocked) {
            throw new POSException('This venue is blocked.', "BLOCKED_VENUE", [], Response::HTTP_BAD_REQUEST);
        }
    }
}

if (!function_exists('toSql')) {
    function toSql($query)
    {
        // Debug SQL Query

        $rawSql = $query->toSql();

        $dataBinding = $query->getBindings();

        $rawSql = str_replace("?", "'%s'", $rawSql);

        $rawSql = vsprintf($rawSql, $dataBinding);

        return $rawSql;
    }
}


if (!function_exists('debugQuery'))
{
    function debugQuery($debug_query)
    {
        $rawSql = $debug_query->toSql();
        $dataBinding = $debug_query->getBindings();
        $rawSql = str_replace("?", "%s", $rawSql);

        // Use call_user_func_array to pass the bindings as separate arguments
        $rawSql = vsprintf($rawSql, array_map(function ($binding) {
            return is_numeric($binding) ? $binding : "'$binding'";
        }, $dataBinding));

        die($rawSql);
    }
}


if (!function_exists('getQueryLocalize'))
{
    function getQueryLocalize(string $table)
    {
        return DB::table('localize')
        ->where('localize.locale', 'en')
        ->where('localize.loc_table', $table)
        ->where('localize.column', 'name');
    }
}

if (!function_exists('getQueryImage'))
{
    function getQueryImage(string $table)
    {
        $sysTableId = getSysTableId($table);
        return DB::table('image')
        ->where('sys_table_id', $sysTableId)
        ->whereNull('deleted_at')
        ->select('global_id', 'type', 'width', 'height', 'url', 'is_default', 'obj_global_id');
    }
}

if (!function_exists('getFormatCurrencyInListAndTotal'))
{
    function getFormatCurrencyInListAndTotal(&$data, $formatColumns, $callback = null, $callbackItems = null)
    {
		# Mapping total
		$total              = new stdClass();
        // Check if a callback function is provided and it is callable
        if (is_callable($callback)) {
            $total = $callback($total);
        }
		$formatTotalColumns = array();
		foreach ($formatColumns as $column)
		{
			$totalColumn           = 'total_'.$column;
			$total->{$totalColumn} = $data['records']->sum($column);
			$formatTotalColumns[]  = $totalColumn;
		}

		# Format total currency
		getColumnCurrencyRealityAndFormatValue(
			$total, $formatTotalColumns, [
				'_format' => [
					'no_suffix' => true
				]
			], false);

		# Mapping with currency formatter
		$data['records']->map(function($result) use($formatColumns, $callbackItems) {
			getColumnCurrencyRealityAndFormatValue(
				$result, $formatColumns, [
					'_format' => [
						'no_suffix' => true
					]
				], false);
            # Calback item, Check if a callback function is provided and it is callable
            if (is_callable($callbackItems)) {
                $result = $callbackItems($result);
            }
			return $result;
		});

		# Footer total
		$data['total'] = $total;
    }
}

if (!function_exists('getQuerySelectJsonArrayList'))
{
    /**
     * Get a SELECT query that retrieves JSON data as an array list.
     *
     * This function generates a SELECT query for retrieving data as an array list,
     * using the provided data mapping for column names and their properties.
     *
     * @param array  $dataMappingKeyValue An associative array that maps column names to their properties.
     * Example:
     * ```
     * $dataMappingKeyValue = array(
     *     'order_gid' => array(
     *         'value' => 'order_dish.global_id'
     *     ),
     *     'quantity' => array(
     *         'value' => 'order_dish.quantity',
     *         'key'   => 'INTEGER'
     *     ),
     *     'pricebooks' => array(
     *         'value' => 'order_dish.pricebooks',
     *         'key'   => 'JSON'
     *     )
     * );
     * ```
     * @param string $alias              The alias to use for the table.
     * Example:
     * ```
     * $alias = 'options';
     * ```
     * @param string $orderByQuery       The ORDER BY query for sorting results (optional).
     * Example:
     * ```
     * $orderByQuery= 'ORDER BY key_value.num ASC';
     * ```
     *
     * @return Expression
     * */
    function getQuerySelectJsonArrayList(array $dataMappingKeyValue, string $alias, string $orderByQuery = '') : Expression
    {
        $IGNORE_CONCAT_STRING = [
            Constants::QUERY_IGNORE_INTEGER => 1,
            Constants::QUERY_IGNORE_JSON    => 1
        ];

        $separateColumn = ",";

        $valueJson = "'{',";
        $numberOfValue = count($dataMappingKeyValue);
        $i = 0;
        foreach ($dataMappingKeyValue as $key => $map)
        {
            $i++;
            $val             = $map['value'];
            $ignoreString    = isset($map['key']) ? $IGNORE_CONCAT_STRING[$map['key']] : false;
            $queryValueJson  = $ignoreString ? "'\"{$key}\":', {$val}" : "'\"{$key}\":', '\"', {$val}, '\"'" ;
            $valueJson      .= $queryValueJson;

            if ($numberOfValue > $i)
                $valueJson .= ",'$separateColumn',";
        }
        # Value json close json
        $valueJson .= ",'}'";
        $valueJson = "CONCAT(
            '[',
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        $valueJson
                    ) $orderByQuery
                ),
            ']'
        ) $alias";

        return DB::raw($valueJson);
    }
}

if (!function_exists('downloadCsv')) {
    function downloadCsv(string $title, array $params, $data)
        {
            $reportManager = new ReportManager($title);
            $ret           = $reportManager->getService($title, $params, $data);
            $fileName      = 'reports/'.$title.'.csv';
            Excel::store($ret, $fileName, 'public');
            $url = Storage::url($fileName);
            return config('app.url').$url;
        }
}

if (!function_exists('convertDefaultCurrencyToForeign'))
{
    function convertDefaultCurrencyToForeign(object $currency, $amount)
    {
        return getRoundingPrice($amount / ($currency->foreign_currecy_value / $currency->base_currency_value), getRoundingUnit());
    }
}

if (!function_exists('convertForeignCurrencyToDefault'))
{
    function convertForeignCurrencyToDefault(object $currency, $amount)
    {
        return getRoundingPrice($amount * ($currency->foreign_currecy_value / $currency->base_currency_value), getRoundingUnit());
    }
}

if (!function_exists('getRoundingUnit'))
{
    function getRoundingUnit($restaurant_gid = NULL)
    {
        $rest_gid = $restaurant_gid ?: ( auth()->check() ? auth()->user()->restaurant_gid : NULL );
        return DB::table('account')->where('global_id', $rest_gid)->select('rounding_unit')->first()?->rounding_unit ?? 0.01;
    }
}

if (!function_exists('getRoundingPrice'))
{
    function getRoundingPrice($cents, $rounding_unit = 0.01)
    {
        $price = $cents/100;
        // Calculate with ceil and to real price
        return (round($price/$rounding_unit)*$rounding_unit)*100;
    }
}

/**
 * @param account_id: can be account id or global_id
 */
function getAccountId($account_id)
{
    if (intval($account_id) > 0) return $account_id;
    $rest = DB::table('account')->where('global_id', $account_id)->select('id')->first();
    if (!$rest) return null;

    return $rest->id;
}

if (!function_exists('getNextOrderNumber')) {
    function getNextOrderNumber($account_id)
    {
        try {
            $accountId = getAccountId($account_id);
            $nextOrder = DB::select(
                "SELECT COALESCE(MAX(meal_number), 0)+1 as next_order FROM meal where account_id = ?
                AND deleted_at IS NULL",
                [$accountId]
            );

            return isset($nextOrder[0]) ? $nextOrder[0]->next_order : 1;
        } catch (\Exception $ex) {
            Log::error("get order number fail: $ex");
            return null;
        }
    }
}
if(!function_exists('curl_request')){
    function curl_request($api,$headers = [],$type = 'post',$params = []){

        Log::channel('curl')->info(json_encode(['api'=>$api,'v'=>$headers,'type'=>$type = 'post','params'=>$params]));

		$curl = Curl::to($api);

        if(isset($headers['bearer'])){
            $curl->withBearer($headers['bearer']);
            unset($headers['bearer']);
        }

        if(count($headers)){
            $curl->withHeaders($headers);
        }

		$curl->withData( $params )
        ->asJson( true );
        return $curl->{$type}();
    }
}

if (!function_exists('getNextBillNumber')) {
    function getNextBillNumber($billType, $account_id)
    {
        try {
            $accountId = getAccountId($account_id);
            $nextBill = DB::select(
                "
                SELECT COALESCE(MAX(bill_number), 0)+1 as next_bill FROM bill where type = ? and account_id = ?
                AND deleted_at IS NULL",
                [$billType, $accountId]
            );

            return isset($nextBill[0]) ? $nextBill[0]->next_bill : 1;
        } catch (\Exception $ex) {
            Log::error("get bill number fail: $ex");
            return null;
        }
    }
}

if (!function_exists('time2Minutes')) {
    function time2Minutes($strHourMinute)
    {
        $from = date('Y-m-d 00:00:00');
        $to = date('Y-m-d ' . $strHourMinute);
        $diff = strtotime($to) - strtotime($from);
        $minutes = $diff / 60;
        return (int) $minutes;
    }
}

if (!function_exists('getQuerySelectJsonArrayList'))
{
    /**
     * Get a SELECT query that retrieves JSON data as an array list.
     *
     * This function generates a SELECT query for retrieving data as an array list,
     * using the provided data mapping for column names and their properties.
     *
     * @param array  $dataMappingKeyValue An associative array that maps column names to their properties.
     * Example:
     * ```
     * $dataMappingKeyValue = array(
     *     'order_gid' => array(
     *         'value' => 'order_dish.global_id'
     *     ),
     *     'quantity' => array(
     *         'value' => 'order_dish.quantity',
     *         'key'   => 'INTEGER'
     *     ),
     *     'pricebooks' => array(
     *         'value' => 'order_dish.pricebooks',
     *         'key'   => 'JSON'
     *     )
     * );
     * ```
     * @param string $alias              The alias to use for the table.
     * Example:
     * ```
     * $alias = 'options';
     * ```
     * @param string $orderByQuery       The ORDER BY query for sorting results (optional).
     * Example:
     * ```
     * $orderByQuery= 'ORDER BY key_value.num ASC';
     * ```
     *
     * @return Expression
     * */
    function getQuerySelectJsonArrayList(array $dataMappingKeyValue, string $alias, string $orderByQuery = '') : Expression
    {
        $IGNORE_CONCAT_STRING = [
            Constants::QUERY_IGNORE_INTEGER => 1,
            Constants::QUERY_IGNORE_JSON    => 1
        ];

        $separateColumn = ",";

        $valueJson = "'{',";
        $numberOfValue = count($dataMappingKeyValue);
        $i = 0;
        foreach ($dataMappingKeyValue as $key => $map)
        {
            $i++;
            $val             = $map['value'];
            $ignoreString    = isset($map['key']) ? $IGNORE_CONCAT_STRING[$map['key']] : false;
            $queryValueJson  = $ignoreString ? "'\"{$key}\":', {$val}" : "'\"{$key}\":', '\"', {$val}, '\"'" ;
            $valueJson      .= $queryValueJson;

            if ($numberOfValue > $i)
                $valueJson .= ",'$separateColumn',";
        }
        # Value json close json
        $valueJson .= ",'}'";
        $valueJson = "CONCAT(
            '[',
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        $valueJson
                    ) $orderByQuery
                ),
            ']'
        ) $alias";

        return DB::raw($valueJson);
    }
}
if (!function_exists('getVenueDbByExternalKey')) {
    function getVenueDbByExternalKey($venueId){
        $dbs = getAllEtlDb();
        $query = "";
        $count = count($dbs);
        for($i = 0; $i < $count;$i++){
            $query .= "SELECT '{$dbs[$i]->db_name}' as `db_name`, external_ref.obj_global_id as `global_id` FROM {$dbs[$i]->db_name}.external_ref WHERE external_key = '{$venueId}' ";
            if($i < ($count - 1 )){
                $query .= " UNION ";
            }
        }
        $result = DB::select($query);
        if(is_array($result) && count($result)) $result = $result [0];
        return (object)$result;
    }
}
if (!function_exists('getAllEtlDb')) {
    function getAllEtlDb()
    {
        $query = "SELECT DISTINCT TABLE_SCHEMA as db_name FROM INFORMATION_SCHEMA.COLUMNS  WHERE TABLE_NAME = 'external_ref';";
        return DB::select($query);
    }
}

if (!function_exists('getVenueDbByExternalKey')) {
    function getVenueDbByExternalKey($venueId){
        $dbs = getAllEtlDb();
        $query = "";
        $count = count($dbs);
        for($i = 0; $i < $count;$i++){
            $query .= "SELECT '{$dbs[$i]->db_name}' as `db_name`, external_ref.obj_global_id as `global_id` FROM {$dbs[$i]->db_name}.external_ref WHERE external_key = '{$venueId}' ";
            if($i < ($count - 1 )){
                $query .= " UNION ";
            }
        }
        $result = DB::select($query);
        if(is_array($result) && count($result)) $result = $result [0];
        return (object)$result;
    }
}

if (!function_exists('isEtlEnabled')) {
    function isEtlEnabled($ob_glboal_id, $table_name)
    {
        if(!Schema::hasTable('external_ref')){
            return false;
        }
        switch($table_name){
            case 'product':
                $table_name = 'dish';
                break;
            case 'order':
                $table_name = 'meal';
                break;
            case 'venue':
                $table_name = 'account';
                break;
            default:
        }
        return getExternalRefQuery($table_name)->where('obj_global_id',$ob_glboal_id)->first();
    }
}

if (!function_exists('switchConnectionDatabaseEtl'))
{
    function switchConnectionDatabaseEtl(array $params) {
        if(request()->header('x-api-key') && !auth()->user()){
            if(isset($params['external_key'])){
                $db = getVenueDbByExternalKey($params['external_key']);
                ParamTools::reconnectDB($db->db_name);
                $params['rest_id'] = $db->global_id;
            }
            else if (isset($params['rest_id']))
            {
                $account = DB::table('account')
                ->select('db_name')
                ->where('global_id',$params['rest_id'])->first();
                ParamTools::reconnectDB($account->db_name);
            } else {
                throw new POSException("Sorry! We're required external_key(venue) or rest_id(uuid)", "NOT_FOUND", [], Response::HTTP_BAD_REQUEST);
            }
        }
    }
}
if (!function_exists('logReporter'))
{
    function logReporter(string $src, string $msg, array $ctx)
    {
        $log = array(
            'src' => $src,
            'msg' => $msg,
            'ctx' => $ctx
        );
        Log::info(json_encode($log));
    }
}

if (!function_exists('isEtlVenue')) {
    function isEtlVenue(){
        if(!Schema::hasTable('external_ref')){
            return false;
        }
        return true;
    }

}
function getTableLinkedToExternalRefQuery($table_name)
{
    if(!isEtlVenue()){
        return DB::table($table_name)->select($table_name.".*")->selectRaw('null as external_key');
    }
    return DB::table($table_name)
    ->select('external_ref.external_key',$table_name.'.*')
    ->leftJoinSub(getExternalRefQuery($table_name), 'external_ref', 'external_ref.obj_global_id', $table_name.'.global_id');
}

if(!function_exists('getExternalRefQuery')){
    function getExternalRefQuery($table_name, $params = []){
        $select = $params['select'] ?? ['external_ref.*'];
        $query = DB::table('sys_table')
        ->select($select)
        ->join('external_ref','external_ref.sys_table_id','=','sys_table.id',(isset($param['optional']) ? 'left' : 'inner' ));

        if(isset($params['joined'])){
            $query->leftJoin($table_name,$table_name.'.global_id','external_ref.obj_global_id');
        }

        $query->where('sys_table.name',$table_name);

        if(isset ($params['global_id'])){
            $query->where('external_ref.obj_global_id',$params['global_id']);
        }
        return $query;
    }
}
if (!function_exists('getStartAndEndService'))
{
    function getStartAndEndService(mixed $venue) : array
    {
        # Get current date service open
        $currentService = DB::table('cashregister')
        ->select('date', 'close_date')
        ->whereNull('cashregister.deleted_at')
        ->where('cashregister.account_id', $venue->id)
        ->whereDate('cashregister.date', now())
        ->orderBy('period_num', 'ASC')
        ->get();

        $lastServiceOpen = DB::table('cashregister')
            ->select('date', 'close_date')
            ->whereNull('cashregister.deleted_at')
            ->whereNull('close_date')
            ->where('cashregister.account_id', $venue->id)
            ->orderBy('period_num', 'DESC')
            ->first();

        if ($currentService->empty() && !$lastServiceOpen)
            throw new POSException("Sorry, we have not found service open", Constants::ERROR_CODE_NOT_FOUND, [], Response::HTTP_NOT_FOUND);
        else if ($currentService->empty() && $lastServiceOpen)
        {
            $start = convertUTC2RestTimezone($lastServiceOpen->date, $venue->timezone);
            $end   = convertUTC2RestTimezone(now(), $venue->timezone);
        }
        else if ($currentService && !$lastServiceOpen)
        {
            $countService = $currentService->count();
            $start        = convertUTC2RestTimezone($currentService[0]->date, $venue->timezone);
            $end          = convertUTC2RestTimezone($currentService[$countService-1]->close_date ?? now(), $venue->timezone);
        }

        return [$start, $end];
    }
}

if (!function_exists('getBoolean'))
{
    function getBoolean(mixed $value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('createHistory'))
{
    function createHistory(string $history_type, int $history_id, string $action, mixed $value)
    {
        History::create([
            'history_type' => $history_type,
            'history_id'   => $history_id,
            'action'       => $action,
            'values'       => json_encode($value)
        ]);
    }
}

if (!function_exists('checkTimeDifference'))
{
    function checkTimeDifference($datetime1, $datetime2, $thresholdMinutes = 5) {
        $carbonDatetime1 = Carbon::parse($datetime1);
        $carbonDatetime2 = Carbon::parse($datetime2);
    
        $timeDifference = $carbonDatetime1->diffInMinutes($carbonDatetime2);
    
        return $timeDifference <= $thresholdMinutes;
    }
}

if (!function_exists('getQuerySqlReplacePaymentMethod'))
{
    /**
     * Get Query Sql Replace Payment Method
     * 
     * @param string $fieldPaymentMethod The filed from payment method Example: payment.payment_method
     * 
     * @return string The return case function sql
     * */ 
    function getQuerySqlReplacePaymentMethod(string $fieldPaymentMethod) : string
    {
		$raw = '(CASE';
		foreach (Payment::$PAYMENTS as $paymentMethod) 
		{
            $raw .= " ";
			$raw .= "WHEN $fieldPaymentMethod = '".$paymentMethod."' THEN '". ucwords(strtolower(__($paymentMethod))) ."'";
			$raw .= " ";
		}
		$raw .= "ELSE $fieldPaymentMethod END)";
        return $raw;
    }
}

if (!function_exists('getAllVenuesDb')) {
    /**
     * Used to retrieve list of venue databases
     *
     *
     * @return array The list of venue databases
     * */
    function getAllVenuesDb()
    {
        $prefix         = str_replace(['_'],[''],config('app.db_prefix','youding'))."_";
        $ignore_dbs     = ['information_schema', config('app.db_auth')];
        $str_ignore_dbs = "'".implode("','",$ignore_dbs)."'";
        $query          = "SELECT DISTINCT TABLE_SCHEMA as db_name FROM INFORMATION_SCHEMA.COLUMNS  WHERE TABLE_SCHEMA LIKE '$prefix%' AND TABLE_SCHEMA NOT IN (".$str_ignore_dbs.")";
        return DB::select($query);
    }
}
if (!function_exists('convertAllObjectToArray')) {
    function convertAllObjectToArray(array $data)
    {
        return array_map(function ($object) {
            return $object instanceof Arrayable ? $object->toArray() : (array)$object;
        }, $data);
    }
}