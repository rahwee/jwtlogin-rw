<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use App\Traits\GlobalVersionTraits;
use App\Traits\HasPermissionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Contact extends Authenticatable
{
    use GlobalVersionTraits, HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    use HasPermissionsTrait;

    protected $table = 'contact';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    public $timestamps = false;
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "global_id",
        "version",
        "firstname",
        "lastname",
        "account_id",
        "supplier_id",
        "contact_type",
        "fake_age",
        "gender",
        "dob",
        "id_card",
        "language",
        "email",
        "password",
        "password_pos",
        "last_access",
        "api_token",
        'app_manager_token',
        "device_id",
        "otp_verify_code",
        "remember_token",
        "tag",
        "timezone",
        "is_raspberry",
        "receive_notify",
        "sup_role",
        "isReceive",
        "leave_date",
        "entry_date",
        "color_code",
        "is_main_contact",
        "limit_unpaid_amount",
        "is_lite_version",
        "has_unpaid_amount",
        "pos_id"
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'password_pos', 'remember_token', 'api_token', 'app_manager_token'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Tags can be "resturant" coz 1 contact can assign to resturant but 1 rest have only tag
     * BelongsToMany join with another field
     * `tag`.`id` = `tag_relation`.`tag_id` where `contact`.`global_id` = `tag_relation`.`obj_global_id`
     * */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'tag_relation', "obj_global_id", "tag_id", "global_id", "id")->whereNull("tag_relation.deleted_at");
    }
}
