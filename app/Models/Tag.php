<?php

namespace App\Models;

use App\Traits\GlobalVersionTraits;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use GlobalVersionTraits, SoftDeletes;

    public $timestamps = false;
    protected $dates = ['deleted_at'];
    protected $table = "tag";
    public $fillable = array(
        'version',
        'global_id',
        "account_id",
        "label",
        "description",
        "is_custom",
        "group_number",
        "color_code"
    );
    protected $cascadeDeletes = [
            // 'tableTags'
    ];
    // *** MAP CONST LIST TAG AND TABLE ARE THE SAME INDEX
    const LIST_TAG = ['DISH', 'TABLE',       'CATEGORY', 'CUSTOMER', 'MARKETING_GROUP', 'BOOKING', 'DISCOUNT','SPECIAL_REQUIREMENT','SPECIAL_OCCASION','TAKEOUT'];
    // const LIST_TAG = ['DISH', 'TABLE',       'CATEGORY', 'CUSTOMER', 'MARKETING_GROUP', 'BOOKING', 'DISCOUNT','SPECIAL_REQUIREMENT','SPECIAL_OCCASION'];

    const TABLES   = ["dish", "rest_table",  "category", "contact", "group_compaign",  "booking", "discount"];

    // public function tables(){
    //     return $this->belongsToMany('App\Models\RestTable', 'table_tag', 'tag_id', 'table_id')->withPivot('id');
    // }

    public function tableTags(){
        return $this->hasMany("App\Models\TableTag", "tag_id");
    }
    public function tagsMetaRel(){
        return $this->hasMany("App\Models\TagMetaRel","tag_id");
    }

    // Tag type restaurnat
    public function restaurant()
    {
        return $this->belongsTo(Account::class, "label", "global_id");
    }

    // Tag department assign to restaurants
    public function restaurants()
    {
        return $this->belongsToMany(Account::class, 'tag_relation', 'tag_id', 'obj_global_id', 'id', 'global_id')->whereNull('tag_relation.deleted_at');
    }

    // Localize department
    public function localizes(){
        return $this->hasMany('App\Models\Localize', 'obj_gid', 'global_id')->where('loc_table', 'tag');
    }
}
