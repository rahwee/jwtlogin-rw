<?php

namespace App\Services\Jwt;

use App\Enums\Constants;
use App\Exceptions\POSException;
use App\Http\Resources\RestaurantResource;
use App\Http\Tools\ParamTools;
use App\Models\Contact;
use App\Services\SVContact;
use App\Services\SVRestaurant;
use App\Services\Sync\SyncTools;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmailLogin implements IJWTContract
{
    public function getRules()
    {
        return array(
            'credential.email'        => 'required|email',
            'credential.password'     => 'required|string'
        );
    }

    /**
     * @override
     */
    function authenticateAndGetToken($data, $authManager)
    {
        $email    = $data['credential']['email'];
        $password = $data['credential']['password'];

        $user = (new SVContact())->getByEmail($email);
        if (!isset($user) || !Hash::check($password, $user->password)) {
            throw new POSException('Incorrect email or password', "WRONG_PASSWORD", [], Response::HTTP_BAD_REQUEST);
        }

        /**
         * Blocked Venue validation
         */
        blockedUserValidation($user);

        // Change db name to child db
        ParamTools::reconnectDB($user->account->db_name);

        // Reload user
        $user = Contact::whereGlobalId($user->global_id)->first();

        $restaurants = $user->account->is_default ? (new SVRestaurant)->myRestGetAll()
            : (new SVContact)->getRestaurants($user);
        
        # Get license laundry each venue
        if (!$user->account->is_default)
        {
            $venueGlobalIds = $restaurants->pluck('global_id')->toArray();
            ParamTools::reconnectDB(config('app.db_auth'));
            $licenseLaundry = DB::table('acc_license')
            ->select(
                'account.global_id',
                'app_license.license_code'
            )
            ->join('app_license', 'acc_license.license_id', 'app_license.id')
            ->join('account', 'acc_license.venue_id', 'account.id')
            ->whereNull(['acc_license.deleted_at', 'app_license.deleted_at', 'account.deleted_at'])
            ->where('app_license.license_code', Constants::LICENSE_CODE_POS_LAUNDRY)
            ->where("acc_license.expire_date", ">", Carbon::now("UTC"))
            ->where('acc_license.enable', true)
            ->whereIn('account.global_id', $venueGlobalIds)
            ->get()
            ->pluck('license_code', 'global_id')
            ->toArray();
            
            foreach ($restaurants as $venue) 
            {
                $venue->licenses = [
                    'is_pos_laundry' => isset($licenseLaundry[$venue->global_id]) && $licenseLaundry[$venue->global_id] == Constants::LICENSE_CODE_POS_LAUNDRY
                ];
                $venue->isLicense = true;
            }

            ParamTools::reconnectDB($user->account->db_name);
            $restaurants = RestaurantResource::collection($restaurants);
        }

        // The restaurant display one user have access
        return [
            "restaurants" => $restaurants
        ];
    }

    function generateToken(JWTManager $authManager, $authType, $user, $extra = [])
    {
        return;
    }

    /**
     * @override
     * 
     * Logout
     * 
     * */ 
    function logout(array $params) : mixed
    {
        // Code ...
        return '';
    }
    
    public function getRefreshToken(JWTManager $authManager, $data){}
}
