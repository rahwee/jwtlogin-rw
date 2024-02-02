<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Account;
use App\Models\Contact;
use App\Enums\Constants;
use App\Models\AppLicense;
use Illuminate\Http\Response;
use App\Http\Tools\ParamTools;
use App\Models\AccountLicense;
use App\Exceptions\POSException;
use App\Models\CommercialOfferAppLicense;
use App\Models\Device;
use App\Models\History;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\User;
use PHPUnit\TextUI\XmlConfiguration\Constant;

class SVAppLicense extends BaseService
{
    public function getWithPaginateFilter($params = [])
    {
        $txt_search  = ParamTools::get_value($params, 's');
        $sort        = ParamTools::get_value($params, 'sort', 'name');
        $order       = ParamTools::get_value($params, 'order', 'asc');
        $query = $this->getQuery()
            ->select(
                "global_id",
                DB::raw("CASE WHEN license_code = 'POS' THEN 'POS' ELSE name END AS name"),
                "license_code",
                "license_type",
                "price"
            );
        $query->orderBy($sort, $order);
        $query->when($txt_search, fn ($q) => $q->where("name", "LIKE", "%$txt_search%"));

        // exclude Omnichannel license 
        $query->where('license_code', '!=', Constants::LICENSE_CODE_POS_OMNICHANNEL);

        return $query->paginate();
    }
    public function getQuery()
    {
        return AppLicense::query();
    }
    public function getAccountLicense($acc_gid, $params)
    {

        $txt_search  = ParamTools::get_value($params, 's');
        $sort        = ParamTools::get_value($params, 'sort', 'name');
        $order       = ParamTools::get_value($params, 'order', 'asc');

        $sortable_columns = [
            "name" => "al.name",
            "price" => "ac.price",
            "expire_date" => "ac.expire_date"
        ];

        $query = DB::table("account AS a")
            ->select(
                "al.global_id",
                DB::raw("CASE WHEN al.license_code = 'POS' THEN 'POS' ELSE al.name END AS name"),
                "al.license_code",
                "venue.name as venue_name",
                "ac.auto_renew",
                "ac.enable",
                "ac.expire_date"
            )
            ->selectSub("SELECT CAST(SUM(
                    CASE
                        WHEN DATE(expire_date) >= DATE(CURRENT_DATE()) THEN (
                            CASE
                                WHEN discount_percent > 0 THEN (price - ((price * discount_percent) / 100))
                                ELSE price
                            END
                        )
                        ELSE 0
                    END
                ) AS FLOAT) FROM acc_license WHERE account_id = a.id AND license_id = al.id", "price")
            ->selectSub("SELECT COUNT(id) FROM acc_license WHERE account_id = a.id AND venue_id IS NOT NULL AND license_id = al.id AND expire_date >= CURRENT_DATE()", "active_license_count")
            ->selectSub("SELECT COUNT(id) FROM acc_license WHERE account_id = a.id  AND license_id = al.id AND venue_id IS NULL", "remaining_license_count")
            ->leftJoin("account AS venue", "venue.parent_id", "a.id")
            ->join("acc_license AS ac", "a.id", "ac.account_id")
            ->join("app_license AS al", function($q){
                $q->on("al.id", "ac.license_id")
                    ->groupBy("ac.license_id");
            })
            ->where("a.global_id", $acc_gid)
            ->where('al.license_code', '!=', Constants::LICENSE_CODE_POS_OMNICHANNEL)
            ->groupByRaw("a.global_id, name");

        if ($sort && in_array($sort, array_keys($sortable_columns))) {
            $col = $sortable_columns[$sort];
            $query->orderBy($col, $order);
        }

        $query->when($txt_search, function ($q, $txt_search) {
            $q->where(function ($q) use ($txt_search) {
                $q->orWhere("ac.price", "LIKE", "%$txt_search%");
                $q->orWhere("al.name", "LIKE", "%$txt_search%");
            });
        });
        return $query->get();
    }

    public function getLicenseQuery($account_id,$license_code,$pim_address)
    {
        $username = "CONCAT(c.firstname, ' ', c.lastname)";

        # Select main is lite version or not
        $mainContactLiteVersion = DB::table('contact')
        ->where('is_main_contact', 1)
        ->whereNull('deleted_at')
        ->where(function($query) {
            $query
            ->where('contact.account_id', DB::raw('a.id'))
            ->orWhere('contact.account_id', DB::raw('venue.id'));
        })
        ->select('is_lite_version')
        ->limit(1);

        # Query as SQL
        $sqlLiteVersionMainContact = getSql($mainContactLiteVersion);

        $venueQuery = DB::table('account')
        ->select('account.global_id','account.id','account.parent_id','account.name')
        ->selectRaw('CONCAT(contact.firstname, \' \', contact.lastname) as user_name, contact.email as user_email')
        ->leftJoin('account_contact','account.id','account_contact.account_id')
        ->leftJoin('contact','contact.id','account_contact.contact_id')
        ->where('account.parent_id',$account_id)
        ->whereNull('account.deleted_at')
        ->groupBy('account.id');

        $query = DB::table("account AS a")
        ->select(
            "venue.name as venue_name",
            "venue.global_id as restaurant_id",
            "c.global_id as contact_id",
            "pim_address.email as venue_email",
            "al.name as license_name",
            "al.license_code as license_code",
            "acl.license_id as app_license_id",
            "acl.global_id as acc_license_id",
            "acl.price",
            "acl.auto_renew",
            "acl.enable as active",
            "acl.start_date",
            "acl.expire_date",
            DB::raw("COALESCE(c.is_lite_version, (". $sqlLiteVersionMainContact .")) is_lite_version"),
            "c.email as user_email",
        )
        ->selectRaw("IF(pim_address.email IS NULL, c.email, pim_address.email) as venue_email")
        //->selectRaw("IF(c.email IS NULL, pim_address.email, c.email) as user_email")
        // ->selectRaw("venue.user_email")
        ->selectRaw("IF($username != '',$username, venue.user_name) as user_name")
        ->joinSub($venueQuery,'venue',"venue.parent_id", "a.id")
        // ->join("account AS venue", "venue.parent_id", "a.id")
        ->join("account AS tenant", "tenant.id", "a.id")
        ->join("acc_license AS acl", "venue.id", "acl.venue_id")
        ->join("app_license AS al", "acl.license_id", "al.id")
        ->leftJoinSub($pim_address, 'pim_address', function ($join) {
            $join->on('venue.global_id', '=', 'pim_address.object_global_id')
                ->orOn('a.global_id', '=', 'pim_address.object_global_id');
        })
        ->leftJoin("contact AS c", "c.id", "acl.contact_id")
        ->whereNull("acl.deleted_at")
        ->where("acl.account_id", $account_id)
        ->where("al.license_code", $license_code)
        ->groupBy('acl.id');
        
        return $query;
    }

    public function getPOSExtraDeviceQuery($account_id){
        $query =  DB::table("acc_license as acl")
        ->select(
            "acl.global_id as acc_license_id",
            "acc.id as account_id",
            "c.email as user_email",
            "venue.name as venue_name",
            "venue.global_id as restaurant_id",
            "c.global_id as contact_id",
            "al.name as license_name",
            "al.license_code as license_code",
            "acl.price",
            "acl.auto_renew",
            "acl.enable as active",
            "acl.expire_date",
            "acl.in_use",
            "device.name as device_name",
            "device.code as device_id",
        )
        ->join("app_license as al", "acl.license_id", "al.id")
        ->join("account as acc", "acc.id", "acl.account_id")
        ->leftJoin("account AS venue", "venue.id", "acl.venue_id")
        ->leftJoin("contact AS c", "c.id", "acl.contact_id")
        ->leftJoin("device", "device.id", "acl.device_id")
        ->whereNull("acl.deleted_at")
        ->where("acl.account_id", $account_id)
        ->where("al.license_code", Constants::APP_LICENSE_POS_EXTRA_DEVICE);
        return $query;
    }

    public function getLicenseByClient($account_id, $params)
    {

        $txt_search  = ParamTools::get_value($params, 's');
        $sort        = ParamTools::get_value($params, 'sort', 'id');
        $order       = ParamTools::get_value($params, 'order', 'desc');
        $license_code = ParamTools::get_value($params, 'license_code');
        $start_date = ParamTools::get_value($params, 'start_date');
        $end_date = ParamTools::get_value($params, 'end_date');

        $sortable_columns = [
            "name" => "al.name",
            "id" => "acl.id",
            "price" => "acl.price",
            "start_date" => "acl.start_date",
            "expire_date" => "acl.expire_date"
        ];
        $pim_address = getPimAddressBuilder("account");
        $account_id = getIdByGlobalId("account", $account_id);

        $query = $this->getLicenseQuery($account_id,$license_code,$pim_address);
        if (in_array($license_code, [Constants::LICENSE_CODE_POS,Constants::LICENSE_CODE_POS_RETAIL])) {
            # Omni Channel
            $omniChannelLicenseId = AppLicense::where('license_code', Constants::LICENSE_CODE_POS_OMNICHANNEL)->first()->id;
            $query->selectSub("IF(COALESCE((SELECT COUNT(id)
                            FROM acc_license
                            WHERE account_id = a.id AND venue_id = venue.id AND license_id = ".$omniChannelLicenseId." AND acc_license.deleted_at IS NULL AND enable = 1) ,
                             0) > 0, 1, 0)", "is_omnichannel");
        }

        if ($license_code == Constants::APP_LICENSE_POS_EXTRA_DEVICE )
        {
            $query =  $this->getPOSExtraDeviceQuery($account_id);
            $app_license_pos_extra = $query->get();

            if (count($app_license_pos_extra)) {
                $app_license_pos = $this->getLicenseQuery($app_license_pos_extra[0]->account_id,Constants::POS_LICENSE_CODE,$pim_address)->first();
                // $app_license_pos_extra[0]->user_email = $app_license_pos->venue_email;
                $app_license_pos_extra[0]->venue_name = $app_license_pos->venue_name;
            }

            return $app_license_pos_extra;
        }

        // exclude omnichannel license
        $query->where('al.license_code', '!=', Constants::LICENSE_CODE_POS_OMNICHANNEL);

        if ($sort && in_array($sort, array_keys($sortable_columns))) {
            $col = $sortable_columns[$sort];
            $query->orderBy($col, $order);
        }

        if ($start_date && $end_date) {
            $query->where("acl.updated_at", "<>", $start_date)
                ->where("acl.expire_date", ">=", $end_date);
        }

        $query->when($txt_search, function ($q, $txt_search) {
            $q->where(function ($q) use ($txt_search) {
                $q->orWhere("ac.price", "LIKE", "%$txt_search%");
                $q->orWhere("al.name", "LIKE", "%$txt_search%");
            });
        });

        return $query->get();
    }

    public function registerLicense($data, $isAdd = false)
    {
        $account = Account::where("global_id", $data["account_id"])->first();

        if (!$account) {
            throw new ModelNotFoundException("Account Global ID not found", 404);
        }

        try {
            $acc_license_data = [];
            foreach ($data["licenses"] as $item) 
            {
                # Initially field before assign
                $item['discount_percent'] = 0;

                /* Initially assign license to account, if the venue_id given, we assign it to venue */
                $item["account_id"] = $account->id;
                if ($account->parent_id) {
                    $item["account_id"] = $account->parent_id;
                    $item["venue_id"] = $account->id;
                }

                $license = DB::table("app_license")
                    ->select("id", "global_id", "license_type")
                    ->where("global_id", $item["license_id"])
                    ->first();

                if (empty($license)) {
                    throw new ModelNotFoundException("License Global ID not found", 404);
                }

                $item["license_id"] = $license->id;
                $isMultipleLicense = $license->license_type == "MULTIPLE";

                /* Case 1 : Multiple License, we assign it by contact_id (E Butler Pro, App Manager, POS Extra Device) */
                if ($isMultipleLicense && isset($item["contact_id"]) && !empty(isset($item["contact_id"]))) {
                    $item["contact_id"] = $this->getIdByGlobalId("contact", $item["contact_id"]);
                }

                /* Case 3 :  Assign Multiple License to different venues */
                if (!$isMultipleLicense && isset($item["venue_id"]) && !empty(isset($item["venue_id"]))) {
                    $item["venue_id"] = $this->getIdByGlobalId("account", $item["venue_id"]);
                }

                # Check start and end date if exist
                if (isset($item['start_date']) && isset($item['end_date'])) {
                    $item["start_date"]     = Carbon::parse($item['start_date'])->format('Y-m-d H:i:s');
                    $item["expire_date"]    = Carbon::parse($item['end_date'])->format('Y-m-d 23:59:59');
                    unset($item["end_date"]);
                }

                # Check commercial offer if exist
                if (isset($item['commercial_offer_id'])) {
                    $commercial_offer_id    = getIdByGlobalId('commercial_offer', $item['commercial_offer_id']);
                    $commercial_discount    = CommercialOfferAppLicense::where([
                                                'commercial_offer_id'   => $commercial_offer_id,
                                                'app_license_id'        => $item['license_id']
                                            ])->first()->discount_percent;

                    $item['commercial_offer_id']    = $commercial_offer_id;
                    $item['discount_percent']       = $commercial_discount;
                }

                if (isset($item["quantity"])) {

                    $value = $item;
                    for ($i = 0; $i < $item["quantity"]; $i++) {
                        $value["global_id"] = getUuid();
                        unset($value["quantity"]);
                        $acc_license_data[] = $value;
                    }

                }
                else 
                {

                    $acc_license = AccountLicense::where([
                            "account_id"    => $item["account_id"],
                            "license_id"    => $item["license_id"]
                        ])
                        ->whereNull([
                            "venue_id",
                            "contact_id"
                        ])->first();

                    if ($acc_license) {
                        $acc_license->update(["venue_id" => $item["venue_id"]]);
                    }else {
                        throw new POSException("License is over limit!", Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_FORBIDDEN);
                    }

                }

            }

            # Insert account licenses by quantity which is for assignment
            DB::table("acc_license")->insert($acc_license_data);

            # Create History
            if($isAdd){
                $accLicenseGIds = collect($acc_license_data)->pluck('global_id')->toArray();
                DB::table("acc_license")->whereIn('global_id', $accLicenseGIds)
                    ->get()->each(fn($row) => createHistory('acc_license', $row->id, 'CREATE', $row));
            }

        } catch (\Throwable $th) {
            throw $th;
            throw new POSException("Can not register License", Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_FORBIDDEN);
        }
    }

    public function registerUserLicense($params)
    {
        $data = $params["licenses"];
        try {
            foreach ($data as $item) {
                $venue = Account::where("global_id", $item["venue_id"])->first();
                $carbon_function = $item["plan_type"] == 'MONTHLY' ? 'addMonth' : 'addYear';
                $item["license_id"] = $this->getIdByGlobalId("app_license", $item["license_id"]);
                $item["contact_id"] = $this->getIdByGlobalId("contact", $item["contact_id"]);
                $item["venue_id"] = $venue->id;
                $item["account_id"] = $venue->parent_id;
                $item["expire_date"] = Carbon::now()->{$carbon_function}();

                $existedLicense = DB::table('acc_license')->where([
                    ['account_id', $item['account_id']],
                    ['venue_id', $item['venue_id']],
                    ['license_id', $item['license_id']],
                    ['contact_id', $item['contact_id']],
                    ['deleted_at', null]
                ])->first();

                if ($existedLicense) throw new POSException("User's license is already exited.", "USER'S_LICENSE_EXISTED", [], Response::HTTP_BAD_REQUEST);

                $accLicense = DB::table("acc_license")
                    ->where('account_id', $item['account_id'])
                    ->where('license_id', $item['license_id'])
                    ->whereNull('contact_id')
                    ->whereNull('venue_id')
                    ->first();

                # Keep price the same
                $item['price'] = $accLicense->price;

                DB::table('acc_license')
                ->where('global_id', $accLicense->global_id)
                ->update($item);
 
            }
        } catch (\Throwable $th) {
            throw new POSException("Can not register Licenese", Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_FORBIDDEN);
        }
    }

    public function registerVenueLicense($data)
    {

        $account_id = getIdByGlobalId("account", $data['account_gid']);
        $venue_ids = $data['venue_ids'];
        $license_id =getIdByGlobalId('app_license', $data['license_gid'] );

        try {

            $remaining_licenses = DB::table('acc_license')
                ->where('account_id', $account_id)
                ->where('license_id', $license_id)
                ->whereNull('venue_id')
                ->whereNull('deleted_at')
                ->orderBy('expire_date', 'desc')
                ->get();

            if ($remaining_licenses->count() < count($venue_ids)) {
                throw new POSException("Not enough license", "NOT_ENOUGH_LICENSE", [], Response::HTTP_BAD_REQUEST);
            }

            for ($i = 0; $i < count($venue_ids); $i++) {

                $venue_license = DB::table('acc_license')
                    ->where('account_id', $account_id)
                    ->where('license_id', $license_id)
                    ->where('venue_id', getIdByGlobalId("account", $venue_ids[$i]))
                    ->whereNull('deleted_at')
                    ->first();

                if (!$venue_license) {
                    DB::table('acc_license')->where('id', $remaining_licenses[$i]->id)->update([
                        'venue_id' => getIdByGlobalId("account", $venue_ids[$i]),
                    ]);
                }

            }

        } catch (\Throwable $th) {
            throw $th;
            throw new POSException("Can not register Licenese", Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_FORBIDDEN);
        }

    }

    public function renewLicense($params)
    {
        /*  
            TODO : Comemercialization 
            TODO : History of renew license, should be separated from acc_license table 
            TODO : Renew from the old record in acc_license table for temporary, until we have a new table for history of renew license
        */

        $commercialOfferGid = ParamTools::get_value($params, 'commercial_offer_gid');
        $renewFrom          = ParamTools::get_value($params, 'renew_from');
        $renewTo            = ParamTools::get_value($params, 'renew_to');
        $price              = ParamTools::get_value($params, 'price');
        $acc_license_id     = ParamTools::get_value($params, 'acc_license_id');
        $renewFrom          = Carbon::parse($renewFrom)->setTime(00, 00, 00)->format('Y-m-d H:i:s');
        $renewTo            = Carbon::parse($renewTo)->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        // $commercialOfferId = $commercialOfferGid ? getIdByGlobalId('commercial_offer', $commercialOfferGid) : null;
        $license = AccountLicense::where([['global_id', $acc_license_id]])->first();

        if (!$license) {
            throw new POSException("License not found", Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_FORBIDDEN);
        }

        $today      = Carbon::now()->format('Y-m-d H:i:s');
        $expireDate = $renewTo ? Carbon::parse($renewTo)->format('Y-m-d H:i:s') : $today;
 
        if ($expireDate < $today) {
            throw new POSException("Renew date must be greater than today", Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_FORBIDDEN);
        }

        $commercialOffer = DB::table('commercial_offer AS co')
                                ->select('co.id', 'coap.discount_percent')
                                ->join('commercial_offer_app_license AS coap', 'coap.commercial_offer_id', 'co.id')
                                ->join('app_license', 'app_license.id', 'coap.app_license_id')
                                ->join('acc_license', 'acc_license.license_id', 'app_license.id')
                                ->where('acc_license.id', $license->id)
                                ->where('co.global_id', $commercialOfferGid)
                                ->whereNull('co.deleted_at')
                                ->whereNull('coap.deleted_at')
                                ->whereNull('app_license.deleted_at')
                                ->whereNull('acc_license.deleted_at')
                                ->first();

        $licenseHistory = clone $license;
        if (date('Y-m-d', strtotime($renewFrom." -1 days")) == date('Y-m-d', strtotime($license->expire_date))) {
            $license->expire_date         = $expireDate;
            $license->commercial_offer_id = $commercialOffer ? $commercialOffer->id : null;
            $license->discount_percent    = $commercialOffer ? $commercialOffer->discount_percent : null;
            $license->price               = $price;
            $license->save();
        }else {
            DB::table('acc_license')->insert(attachGlobalVersion([
                'account_id'          => $license->account_id,
                'venue_id'            => $license->venue_id,
                'license_id'          => $license->license_id,
                'price'               => $price,
                'contact_id'          => $license->contact_id,
                'commercial_offer_id' => $commercialOffer ? $commercialOffer->id : null,
                'discount_percent'    => $commercialOffer ? $commercialOffer->discount_percent : null,
                'device_id'           => $license->device_id,
                'start_date'          => $renewFrom,
                'expire_date'         => $expireDate,
                'enable'              => $license->enable,
                'in_use'              => $license->in_use,
                'auto_renew'          => $license->auto_renew,
                'plan_type'           => $license->plan_type
            ]));
        }

        if($license->AppLicense->license_code === Constants::LICENSE_CODE_POS)
        {
            $omnichannelLicense = AccountLicense::select('acc_license.*')
                                ->join('app_license', 'app_license.id', 'acc_license.license_id')
                                ->where('acc_license.venue_id', $license->venue_id)
                                ->where('app_license.license_code', Constants::LICENSE_CODE_POS_OMNICHANNEL)->first();
            
            $omnichannelLicense->start_date = $renewFrom;
            $omnichannelLicense->expire_date = $expireDate;
            $omnichannelLicense->save();
        }

        # create history
        createHistory('acc_license', $licenseHistory->id, 'RENEW', $licenseHistory);

        return $license;
    }

    public function autoRenewLicense()
    {
        AccountLicense::whereRaw('enable = 1 AND auto_renew = 1 AND DATE(expire_date) = CURDATE()')->update(['expire_date' => DB::raw('DATE_ADD(expire_date, INTERVAL 1 MONTH)')]);
    }

    public static function validateButlerLicense(string $user_gid): void
    {
        $butler_license_code = Constants::BUTLER_PRO_LICENSE_CODE;
        $license_code = Constants::LICENSE_CODE_POS;
        $app_license = DB::table("app_license")->select("name")->where("license_code", $license_code)->first();
        $query =  DB::table("acc_license as acl")
            ->join("app_license as ap", "acl.license_id", "ap.id")
            ->join("account as acc", "acc.id", "acl.account_id")
            ->join("contact as c", "c.id", "acl.contact_id")
            ->whereNull("acl.deleted_at")
            ->where("c.global_id", $user_gid)
            ->where("acc.license_active", 1)
            ->where("acl.enable", 1)
            ->orderBy("acl.id")
            ->where("ap.license_code", $butler_license_code);
        $count = $query->count();
        $isExpired = $query
            ->where("acl.expire_date", ">", Carbon::now("UTC"))
            ->count();

        if (!$count) {
            throw new POSException("User do not have license access to $app_license?->name", "INVALID_LICENSE", [], Response::HTTP_UNAUTHORIZED);
        }

        if (!$isExpired) {
            throw new POSException("License expired : $app_license?->name ", "INVALID_LICENSE", [], Response::HTTP_UNAUTHORIZED);
        }
    }

    public static function validatePOSLicense(string $venue_gid, string $user_gid, array $device): void
    {
        ParamTools::reconnectDB(config('app.db_auth'));
        $license_code = Constants::LICENSE_CODE_POS;
        $venue        = Account::where('global_id', $venue_gid)->first();
        $parent_id    = $venue->parent_id;

        $accountLicenses = self::getAccountLicenseQuery($parent_id, $license_code)
                                ->where('acl.venue_id', $venue->id)
                                ->get();

        $accountLicenses = !$accountLicenses->isEmpty() ? $accountLicenses : self::getAccountLicenseQuery($parent_id, Constants::LICENSE_CODE_POS_RETAIL)->where('acl.venue_id', $venue->id)->get();

        if($accountLicenses->isEmpty())
        {
            $app_license = self::getAppLicense($license_code);
            throw new POSException("User do not have license access to $app_license?->name", Constants::ERROR_CODE_USER_DO_NOT_HAVE_LICENSE, [], Response::HTTP_UNAUTHORIZED);
        }

        $isPOSLicenseExpired = $accountLicenses->where("expire_date", ">", Carbon::now("UTC"))->first();

        if(!$isPOSLicenseExpired)
        {
            $app_license = self::getAppLicense($license_code);
            throw new POSException("License expired : $app_license?->name ", Constants::ERROR_CODE_LICENSE_EXPIRED, [], Response::HTTP_UNAUTHORIZED);
        }

        $accountLicenses = $accountLicenses->where("expire_date", ">", Carbon::now("UTC"));

        if(count($accountLicenses->whereNotNull('device_id')->where('in_use', 1)))
        {
            $deviceId = @DB::table('device')->where('code', $device['device'])->first()->id;
            $accountLicense = $accountLicenses->where('device_id', $deviceId)->first();
        }

        if(!isset($accountLicense))
        {
            $accountLicense = $accountLicenses->where("in_use", 0)->first();
        }

        // If POS does not have any license that is not in use, we will check if user have license access to POS Extra Device
        if(!$accountLicense)
        {
            $accountLicense = self::getAccountLicenseQuery($parent_id, Constants::APP_LICENSE_POS_EXTRA_DEVICE)
                                    ->where('acl.venue_id', null)
                                    ->where("acl.in_use", 0)
                                    ->where("acl.expire_date", ">", Carbon::now("UTC"))
                                    ->first();

            if(!$accountLicense)
            {
                $app_license = self::getAppLicense($license_code);
                throw new POSException("User do not have license access to $app_license?->name", Constants::ERROR_CODE_USER_DO_NOT_HAVE_LICENSE, [], Response::HTTP_UNAUTHORIZED);
            }
        }

        $contact_id = getIdByGlobalId("contact", $user_gid);

        $device = Device::updateOrCreate([
                "code" => $device["device"],
            ],[
                "code"       => $device["device"],
                "name"       => @$device["device_name"],
                "version"    => $device["version"],
                "account_id" => $accountLicense->account_id,
            ]
        );

        // if the deice is not used by any account
        # Problem: device-id not stable after user re build the app
        self::setInUseDeviceOnLicense($device, $venue, $contact_id,  $accountLicense->acc_license_id);
    }

    /**
     * Update in-use for POS License
     * 
     * @param mixed $device to set acc_license
     * @param ?mixed $venue account as restaurant
     * @param ?int $contact_id as contact id to set acc_license
     * @param ?string $accLicenseId it's optional for when user try logout
     * 
     * @return mixed
     * */ 
    public static function setInUseDeviceOnLicense(mixed $device, mixed $venue = null, int $contact_id = null, string $accLicenseId = null) : mixed
    {
        $accLicense = AccountLicense::where("device_id", $device->id)
                    ->join('app_license', 'app_license.id', 'acc_license.license_id')
                    ->select('acc_license.id', 'app_license.license_code')
                    ->first();
        
        if (!$accLicense && !$venue)
            return false;

        # Declare a variable for check logging out or in
        $isLoggedOut = $accLicense && !$venue;

        # Get license code to update venue or not
        $licenseCode = !isset($accLicense->license_code) ? self::getLicenseCodeByAccountLicenseId($accLicenseId) : $accLicense->license_code;

        # Should be able to update venue_id or not in table acc_license, declare a variable for update
        $updateVenueIdParams = $licenseCode === Constants::APP_LICENSE_POS_EXTRA_DEVICE ? ['venue_id' => $isLoggedOut ? null : $venue->id] : [];

        # Update acc_license
        DB::table('acc_license')
        ->when(!$accLicense, function($query) use($accLicenseId) {
            $query->where("id", $accLicenseId);
        })
        ->when($accLicense, function($query) use($accLicense) {
            $query->where("id", $accLicense->id);
        })
        ->update(array_merge([
            "in_use"     => $isLoggedOut ? 0 : 1,
            "contact_id" => $isLoggedOut ? null : $contact_id,
            "device_id"  => $isLoggedOut ? null : $device->id
        ], $updateVenueIdParams));
        return true;
    }

    /**
     * Get license code by account license id
     * 
     * @param int $accLicenseId The id acc_license
     * 
     * @return string POS, POS_EXTRA_DEVICE, etc.
     * */ 
    public static function getLicenseCodeByAccountLicenseId(int $accLicenseId) : string
    {
        return AccountLicense::where("acc_license.id", $accLicenseId)
        ->join('app_license', 'app_license.id', 'acc_license.license_id')
        ->select('app_license.license_code')
        ->first()->license_code;
    }

    public static function validateKDSLicense($account_id = NULL, string $license_code, $venue_id)
    {
        ParamTools::reconnectDB(config('app.db_auth'));
        $venue     = Account::where('id', $venue_id)->first();
        $parent_id = $venue->parent_id;

        $query = DB::table("acc_license AS acl")
            ->select(
                "apl.license_type",
                "apl.license_code",
                "acl.expire_date",
                "c.global_id",
                "apl.name",
                "acc.global_id AS account_global_id",
                "acc.id AS account_id",
                "acc.license_active",
                "acl.enable"
            )
            ->join("app_license AS apl", "acl.license_id", "apl.id")
            ->join("account AS acc", "acl.account_id", "acc.id")
            ->leftJoin("account AS venue", "acl.venue_id", "venue.id")
            ->leftJoin("contact AS c", "acl.contact_id", "c.id")
            ->whereNull("acl.deleted_at")
            ->where("acc.id", $parent_id)
            ->where("apl.license_code", $license_code)
            ->where("acc.license_active", 1)
            ->where("acl.enable", true);
            
        if ( $license_code === Constants::LICENSE_CODE_KDS && $venue_id ) {
            $query->where('acl.venue_id', $venue_id );
        }

        $license = $query->first();

        if (!$license) {
            $app_license = DB::table("app_license")->select("name")->where("license_code", $license_code)->first();
            throw new POSException("User do not have license access to $app_license?->name", "INVALID_LICENSE", [], Response::HTTP_UNAUTHORIZED);
        }
        if ($license->expire_date < Carbon::now("UTC")) {
            throw new POSException("License expired : $license->name ", "INVALID_LICENSE", [], Response::HTTP_UNAUTHORIZED);
        }
    }

    public static function validateLicense($account_id, string $license_code, $rest_gid = null, $contact_gid = false)
    {
        ParamTools::reconnectDB(config('app.db_auth'));
        $query = DB::table("acc_license AS acl")
            ->select(
                "apl.license_type",
                "apl.license_code",
                "acl.expire_date",
                "c.global_id",
                "apl.name",
                "acc.global_id AS account_global_id",
                "acc.id AS account_id",
                "acc.license_active",
                "acl.enable"
            )
            ->join("app_license AS apl", "acl.license_id", "apl.id")
            ->join("account AS acc", "acl.account_id", "acc.id")
            ->leftJoin("account AS venue", "acl.venue_id", "venue.id")
            ->leftJoin("contact AS c", "acl.contact_id", "c.id")
            ->whereNull("acl.deleted_at")
            ->where("apl.license_code", $license_code)
            ->where("acc.license_active", 1)
            ->where("acl.enable", true)
            ->orderBy("acl.expire_date", "DESC");

        // CASE POS-Login : when we have restaurant global_id
        if ($rest_gid) {
            $query->where("venue.global_id", $rest_gid);
        }
        // CASE E-Butler-Login : when we do not have restaurant global_id
        else {
            $query->where("acc.id", $account_id);
        }

        $query->when($contact_gid, function ($q) use ($contact_gid) {
            $q->where("c.global_id", $contact_gid);
        });

        $license = $query->orderByDesc("acl.expire_date")->first();

        if (!$license) {
            $app_license = DB::table("app_license")->select("name")->where("license_code", $license_code)->first();
            throw new POSException("User do not have license access to $app_license?->name", "INVALID_LICENSE", [], Response::HTTP_UNAUTHORIZED);
        }
        if ($license->expire_date < Carbon::now("UTC")) {
            throw new POSException("License expired : $license->name ", "INVALID_LICENSE", [], Response::HTTP_UNAUTHORIZED);
        }
    }

    public static function terminateLicense($acl_license_gid)
    {
        $acc_license  = AccountLicense::where("global_id", $acl_license_gid)
                        ->with('appLicense')
                        ->first();
        
        $deviceId = Contact::find($acc_license->contact_id)->device_id;
        $rest_gid = Account::find($acc_license->venue_id)->global_id;
        
        if (!$acc_license) {
            throw new POSException("Invalid license.", "INVALID_LICENSE", [], Response::HTTP_UNAUTHORIZED);
        }

        $isPOSExtraDevice = $acc_license->appLicense->license_code === Constants::APP_LICENSE_POS_EXTRA_DEVICE;

        $updateAccLicenseParams = $isPOSExtraDevice ? ["venue_id" => null, "contact_id" => null] : ["contact_id" => null];

        $acc_license->update(array_merge([
            "in_use"    => 0,
            "device_id" => NULL,
        ], $updateAccLicenseParams));

        $queueName = Constants::LOGIN_TYPE_POS.".".$deviceId;
        $message[$queueName] = json_encode([
            'action' => Constants::POS_LOGOUT_SUCCESS
        ]);
        
        sendMessagesToSocket($message, array(
            'account_gid' => $rest_gid,
            'type'        => Constants::LOGIN_TYPE_POS,
            'routing_key' => Constants::POS_LOGOUT,
            'is_directly' => true
        ));

    }

    public static function toggleExtraDeviceLicense($license_gid)
    {
        $id = getIdByGlobalId("acc_license", $license_gid);
        $license =  DB::table('acc_license')->join("app_license as ap", "acc_license.license_id", "ap.id")
                    ->where("ap.license_code", Constants::APP_LICENSE_POS_EXTRA_DEVICE )
                    ->where("acc_license.id", $id)
                    ->whereNull("acc_license.deleted_at")
                    ->first();

        if (!$license) {
            throw new POSException("License not found.", "LICENSE_NOT_FOUND", [], Response::HTTP_UNAUTHORIZED);
        }

        AccountLicense::where("id", $id)->update(["enable" => !$license->enable, "auto_renew" => !$license->enable]);
    }

    /**
     * Get venue licenses
     * */ 
    public static function getVenueLicenses()
    {
        $connectionClientDB = DB::connection()->getDatabaseName();
        ParamTools::reconnectDB(config('app.db_auth'));
        # Mapping is_omnichannel to restaurant
        $venueLicenses = DB::table('acc_license')
                        ->select(
                            'account.global_id',
                            'app_license.license_code',
                        )
                        ->join('app_license', 'acc_license.license_id', 'app_license.id')
                        ->join('account', 'account.id', 'acc_license.venue_id')
                        ->whereNull('acc_license.deleted_at')
                        ->whereNull('app_license.deleted_at')
                        ->whereNull('account.deleted_at')
                        ->where('acc_license.enable', true)
                        ->whereNotNull('acc_license.venue_id')
                        ->whereIn('app_license.license_code', [
                            Constants::LICENSE_CODE_POS_OMNICHANNEL,
                            Constants::LICENSE_CODE_KDS,
                            Constants::LICENSE_CODE_POS_LAUNDRY
                        ])
                        ->where("acc_license.expire_date", ">", Carbon::now("UTC"))
                        ->where('account.db_name', $connectionClientDB)
                        ->whereNotNull('account.parent_id')
                        ->get();
                        
        ParamTools::reconnectDB($connectionClientDB);
        return $venueLicenses;
    }
    
    public static function toggleLiteVersionPOS($params)
    {
        $contact = Contact::where('global_id', $params['user_gid'])
        ->first();
        $contact->is_lite_version = !$contact->is_lite_version;
        $contact->save();
    }

    /**
     * Get Base Query License
     * 
     * @param int $parent_id The tenant id account
     * @param ?array $licenses The licenses
     * 
     * @return mixed
     * */ 
    protected static function getBaseQueryLicense(int $parent_id, array $licenses = []) : mixed
    {
        return DB::query()
                ->from('acc_license')
                ->join('account','acc_license.account_id','=','account.id')
                ->join('app_license','acc_license.license_id','=','app_license.id')
                ->where('acc_license.account_id', $parent_id)
                ->where("acc_license.expire_date", ">", Carbon::now("UTC"))
                ->whereNull(['acc_license.deleted_at', 'app_license.deleted_at'])
                ->when(count($licenses)>0, fn($q) => $q->whereIn('app_license.license_code', $licenses));
    }


    public static function validatePOSLaundryLicense($restaurant) : bool
    {
        $POSLaundryLicenseCount  = self::getBaseQueryLicense($restaurant->parent_id, [
                        Constants::LICENSE_CODE_POS_LAUNDRY
                    ])
                    ->where('acc_license.venue_id', $restaurant->id)
                    ->count();
        return $POSLaundryLicenseCount > 0;
    }

    public static function validatePOSLoyaltyLicense($restaurant) : bool
    {
        $POSLoyaltyLicenseCount  = self::getBaseQueryLicense($restaurant->parent_id, [
                        Constants::LICENSE_CODE_POS_LOYALTY
                    ])
                    ->count();
        return $POSLoyaltyLicenseCount > 0;
    }


    public function registerOmnichannelPOS($params)
    {
        $restaurantId   = $params["restaurant_id"];
        $account        = Account::where("global_id", $restaurantId)->first();
        $PosLicenseCode = Constants::LICENSE_CODE_POS;

        $parent_id = $account->parent_id;
        if (!$parent_id) {
            throw new POSException("Sorry your tenant not found", Constants::ERROR_DATA_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }
        # Make sure parent account not link to other id
        $parentAccount = Account::whereId($parent_id)->select('id', 'parent_id')->first();
        if ($parentAccount && $parentAccount->parent_id !== null) {
            throw new POSException("Sorry your restaurant have issue link to wrong tenant", Constants::ERROR_WRONG_REQUEST, Response::HTTP_BAD_REQUEST);
        }

        $queryLicense =  DB::table('acc_license')
        ->select('acc_license.id', 'acc_license.expire_date','app_license.license_code as license_code')
        ->join('account','acc_license.account_id','=','account.id')
        ->join('app_license','acc_license.license_id','=','app_license.id')
        ->where('acc_license.account_id', $account->parent_id)
        ->where('acc_license.venue_id', $account->id)
        ->where("acc_license.expire_date", ">", Carbon::now("UTC"))
        ->whereNull("acc_license.deleted_at")
        ->whereNull("app_license.deleted_at")
        ->whereNull("account.deleted_at");

        $data = $queryLicense->whereIn('app_license.license_code', [Constants::LICENSE_CODE_POS,Constants::LICENSE_CODE_POS_OMNICHANNEL,Constants::LICENSE_CODE_POS_RETAIL])->get();
        
        $posLicense = $data->where('license_code', $PosLicenseCode);
        
        $posLicense = $posLicense->isEmpty() ? $data->where('license_code', Constants::LICENSE_CODE_POS_RETAIL) : $posLicense;

        if (!$posLicense->isEmpty()) {
            $expireDate = $posLicense->first()->expire_date;
        }
        
        # Get app license id Omnichannel
        $omniChannelId = AppLicense::where('license_code', Constants::LICENSE_CODE_POS_OMNICHANNEL)->first()->id;

        DB::table('acc_license')
        ->where([
            'account_id'  => $account->parent_id,
            'venue_id'    => $account->id,
            'license_id'  => $omniChannelId,
        ])
        ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        
        DB::table('acc_license')
        ->updateOrInsert([
            'account_id'  => $account->parent_id,
            'venue_id'    => $account->id,
            'license_id'  => $omniChannelId,
        ], attachGlobalVersion([
            'enable'      => DB::raw('!enable'),
            'expire_date' => $expireDate ?? DB::raw('expire_date'),
            'deleted_at'  => NULL
        ]));
    }

    public static function getPOSFeatureLicenses($restaurant)
    {
        $features = DB::table('acc_license')
                    ->join('account','acc_license.account_id','=','account.id')
                    ->join('app_license','acc_license.license_id','=','app_license.id')
                    ->where('acc_license.account_id', $restaurant->parent_id)
                    ->whereIn('app_license.license_code', [
                        Constants::POS_LICENSE_CODE, 
                        Constants::LICENSE_CODE_POS_OMNICHANNEL, 
                        Constants::LICENSE_CODE_KDS,
                        Constants::LICENSE_CODE_POS_RETAIL
                    ])
                    ->where('acc_license.venue_id', $restaurant->id)
                    ->where('acc_license.enable', 1)
                    ->where('acc_license.expire_date', '>', Carbon::now("UTC"))
                    ->whereNull('acc_license.deleted_at')
                    ->whereNull('app_license.deleted_at')
                    ->select('app_license.license_code')
                    ->get()
                    ->toArray();
        
        return [
            'is_omni_channel' => in_array(Constants::LICENSE_CODE_POS_OMNICHANNEL, array_column($features, 'license_code')),
            'is_kds'          => in_array(Constants::LICENSE_CODE_KDS, array_column($features, 'license_code')),
            'is_pos'          => in_array(Constants::POS_LICENSE_CODE, array_column($features, 'license_code')),
            'is_pos_retail'   => in_array(Constants::LICENSE_CODE_POS_RETAIL, array_column($features, 'license_code')),
        ];
    }

    public function getAppLicenseWithCommitmentPeriod($appLicenseGid = null)
    {
        $query = $this->getQuery()->whereNull("deleted_at");

        if ($appLicenseGid) {
            $query->where('global_id', $appLicenseGid);
        }

        $separateColumn = ",";
        return $query->select(
                    'global_id',
                    DB::raw("CASE WHEN license_code = 'POS' THEN 'POS' ELSE name END AS name"),
                    DB::raw("(COALESCE(
                                (SELECT
                                    CONCAT('[',
                                        GROUP_CONCAT('{',
                                            '\"global_id\":', '\"', global_id, '\"',
                                            '$separateColumn',
                                            '\"monthly_commitment_period\":', '\"', monthly_commitment_period, '\"',
                                            '$separateColumn',
                                            '\"price\":', '\"', CAST((price / 100) AS FLOAT), '\"',
                                        '}'),
                                    ']')
                                FROM license_commitment_period WHERE app_license_id = app_license.id AND deleted_at IS NULL),
                            NULL)) AS commitment_period")
                );
    }

    public function getLicensePriceWithPaginateFilter(array $params = [])
    {
        $txt_search     = ParamTools::get_value($params, 's');
        $order          = ParamTools::get_value($params, 'order', 'desc');
        $sort           = ParamTools::get_value($params, 'sort', 'name');
        $limit          = ParamTools::get_value($params, 'size', 10);

        # Get query
        $query = $this->getAppLicenseWithCommitmentPeriod()->orderBy($sort, $order);

        if ($txt_search) {
            $query->where('app_license.name', 'LIKE', '%'.$txt_search.'%');
        }

        return $this->getLimit($query, $limit);
    }

    public function showLicensePrice($gid)
    {
        # Get query
        $query = $this->getAppLicenseWithCommitmentPeriod($gid);
        return $query->get();
    }

    public function updateLicensePrice(array $params = [], $gid)
    {
        $commitmentPeriod   = ParamTools::get_value($params, 'commitment_period');
        $appLicense         = $this->getByGId($gid);

        # Create History
        createHistory('app_license', $appLicense->id, 'UPDATE', $this->getAppLicenseWithCommitmentPeriod($gid)->first());

        # Upsert relational with license commitment period
        $this->upsertRelationalLicenseCommitmentPeriod($appLicense, $commitmentPeriod);

        # Get query
        $query = $this->getAppLicenseWithCommitmentPeriod($gid);
        return $query->get();
    }

    public function deleteLicensePrice($gid)
    {
        $appLicense = $this->getByGId($gid);
        $this->upsertRelationalLicenseCommitmentPeriod($appLicense, [], true);
    }

    public function upsertRelationalLicenseCommitmentPeriod($appLicense, $commitmentPeriod = [], $isDelete = false)
    {
        if ($isDelete) {
            DB::table('license_commitment_period')->where('app_license_id', $appLicense->id)->delete();
        }else {
            # Get current commitment period to compare with coming
            $currentCommitmentPeriodGid = DB::table('license_commitment_period')->where('app_license_id', $appLicense->id)->pluck('global_id')->toArray();
            foreach ($commitmentPeriod as &$value) 
            {
                if( $value['monthly_commitment_period'] === Constants::LICENSE_COMMITMENT_PERIOD_15DAYS_TRIAL
                    || $value['monthly_commitment_period'] === Constants::LICENSE_COMMITMENT_PERIOD_1MONTH_TRIAL
                )
                {
                    $value['price'] = 0;
                }

                $value['price'] = ($value['price'] * 100);
                if (isset($value['global_id']) && $currentCommitmentPeriodGid) {
                    # Unset coming commitment period from current
                    unset($currentCommitmentPeriodGid[array_search($value['global_id'], $currentCommitmentPeriodGid)]);
                    DB::table('license_commitment_period')->where('global_id', $value['global_id'])->update($value);
                }else {
                    DB::table('license_commitment_period')->insert(attachGlobalVersion(array_merge($value,array(
                        'app_license_id' => $appLicense->id
                    ))));
                }
            }
            
            /**
             * After unset coming commitment period from current
             * Delete the remaining from current case those remaining were remove by frontend
             */
            if ($currentCommitmentPeriodGid) {
                DB::table('license_commitment_period')->whereIn('global_id', $currentCommitmentPeriodGid)->delete();
            }
        }
    }

    public function getLicenseHistory($license_gid)
    {
        $histories = DB::table('history AS h')
                    ->join('acc_license AS al', 'al.id', 'h.history_id')
                    ->join('account AS a', 'a.id', 'al.venue_id')
                    ->leftJoin('contact AS c', 'c.id', 'al.contact_id')
                    ->leftJoin('commercial_offer AS co', 'co.id', DB::raw("JSON_VALUE(h.values, '$.commercial_offer_id')"))
                    ->select(
                        'a.name AS venue_name',
                        'c.email'
                    )
                    ->selectRaw(
                        "JSON_VALUE(h.values, '$.price') AS price,
                        CONCAT_WS(' ', c.firstname, c.lastname) AS username,
                        CASE 
                            WHEN co.type = 'PROMOTION' THEN co.name
                            ELSE 'None'
                        END AS promotion,
                        CASE 
                            WHEN co.type = 'PACKAGE' THEN co.name
                            ELSE 'None'
                        END AS package,
                        CONCAT(DATE(JSON_VALUE(h.values, '$.start_date')), ' - ', DATE(JSON_VALUE(h.values, '$.expire_date'))) AS date,
                        DATE(JSON_VALUE(h.values, '$.expire_date')) AS expire_date,
                        DATE(JSON_VALUE(h.values, '$.start_date')) AS start_date,
                        JSON_VALUE(h.values, '$.updated_at') AS updated_at,
                        false AS active"
                    )
                    ->where('al.global_id', $license_gid)
                    ->orderBy('updated_at', 'DESC')
                    ->get();

        $currentDate = Carbon::now("UTC");
        $activeLicense = $histories->where('expire_date', '>=', $currentDate)->where('start_date', '<=', $currentDate)->first();
        if($activeLicense) $activeLicense->active = 1;

        foreach($histories as &$history)
        {
            unset($history->expire_date);
            unset($history->start_date);
            unset($history->updated_at);
        }

        return $histories;
    }

    public function checkLicense($license_codes, $restaurantGid)
    {
        $restaurant = DB::table('account')
                        ->select('id', 'parent_id')
                        ->where('global_id', $restaurantGid)
                        ->first();

        foreach($license_codes AS $license_code)
        {
            SVAppLicense::validateLicense($restaurant->parent_id, $license_code, $restaurantGid);
        }
    }

    private static function getAccountLicenseQuery($accountParentId, $licenseCode)
    {
        return DB::table("acc_license as acl")
                ->select("acl.id as acc_license_id", "acc.id as account_id", "acl.expire_date", "acl.in_use", "acl.device_id")
                ->join("app_license as ap", "acl.license_id", "ap.id")
                ->join("account as acc", "acc.id", "acl.account_id")
                ->where("acc.id", $accountParentId)
                ->where("acl.enable", 1)
                ->where("acc.license_active", 1)
                ->where("ap.license_code", $licenseCode)
                ->whereNull("acl.deleted_at")
                ->whereNull("acc.deleted_at")
                ->whereNull("ap.deleted_at");
    }

    private static function getAppLicense($license_code)
    {
        return DB::table("app_license")
                    ->select("name")
                    ->where("license_code", $license_code)
                    ->first();
    }
}
