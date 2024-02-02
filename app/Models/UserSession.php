<?php

namespace App\Models;

use App\Traits\GlobalVersionTraits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use GlobalVersionTraits, HasFactory;

    protected $table = 'user_session';
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
        'global_id',
        'version',
        'user_id',
        'app_type_id',
        'access_token',
        'refresh_token',
        'is_refresh',
        'exchange_token'
    ];

    public function contact() {
        return $this->belongsTo(Contact::class, 'user_id');
    }
}
