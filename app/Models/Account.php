<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends BaseModel
{
    use HasFactory;
    public const TBL_NO_NEED_ACCOUNT_ID = [
        'sys_table',
        'meta',
        'mail_app_images',
        'events',
        'leads',
        'cases',
        'account',
        'failed_jobs',
        'jobs',
        "job_batches",
        'migrations',
        'module',
        'module_action',
        'rasp_history',
        'password_resets',
        'weather',
        'menu_pricebook',
        'dish',
        'category',
        'discount',
        'tax',
        'dish_option',
        'dish_cat',
        'formula_item',
        'dish_price_option',
        'option_value',
        'pim_address',
        'address_rel',
        'language',
        'printer'
    ];

    public const SUPERADMIN_IGNORE_COLUMN = array(
        'currency_code'
    );

    public $timestamps = false;
    protected $dates = ['deleted_at'];
    protected $table = 'account';

    protected $fillable = [
        'id',
        'version',
        'global_id',
        'parent_id',
        'name',
        'db_name',
        'db_username',
        'db_password',
        'is_default',
        'is_youding_default',
        'vat_number',
        'cashregister_activation_code',
        'ticket_z',
        'account_type',
        'rest_key',
        'training',
        'closed',
        'timezone',
        'payment_gateway',
        'weekly_par_level',
        'server_code',
        'server_url',
        'currency_code',
        'rounding_unit',
        'category'
    ];
    protected $cascadeDeletes = [
        // 'roles',
        'users',
        'restaurants',
        // 'expenseCategories',
        // 'inventory_categories',
        // 'suppliers',
        // 'picklists',
        // 'priorities',
        // 'histories_config',
        // 'reports',
        // 'customers',
        // 'report_catgories',
        // 'report_groups',
        // 'report_models',
        // 'menus',
        // 'localizes',
        'pim_address'
    ];
}
