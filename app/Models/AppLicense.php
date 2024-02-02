<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppLicense extends BaseModel
{
    protected $table = "app_license";
    protected $fillable = [
        "name", "price", "global_id",
        "version", "license_code", "license_type"
    ];

    public function accounts()
    {
        return $this->belongsToMany(Account::class, "acc_license", "license_id", "account_id")
            ->wherePivotNull("deleted_at")->withPivot("price", "expire_date", "contact_id", "enable", "auto_renew");
    }

    public function venues()
    {
        return $this->belongsToMany(Account::class, "acc_license", "license_id", "venue_id")
            ->wherePivotNull("deleted_at")->withPivot("price", "expire_date", "contact_id", "enable", "auto_renew");
    }
}
