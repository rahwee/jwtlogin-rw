<?php
namespace App\Traits;

use Illuminate\Support\Facades\Log;
trait GlobalVersionTraits
{
    /**
     * Model Event
     *
     * This traits is use to generate UUID to columns global_id
     * and increase the version when record updated
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model)
        {
            // Before grenerate global id check field nullable or not
            if(in_array('global_id', $model->fillable) && !$model->global_id)
            {
                $model->global_id = \Faker\Provider\Uuid::uuid();
            }
            if(in_array('version', $model->fillable) && !$model->version) {
                $model->version   = 1;
            }
        });

        static::updating(function ($model) {
            if(in_array('version', $model->fillable) && is_integer($model->version)) {
                $model->version++;
            }
            if(in_array('global_id', $model->fillable) && !$model->global_id) {
                $model->global_id = \Faker\Provider\Uuid::uuid();
            }
        });

        static::deleting(function($model) {
            // Log::info("Deleting table [".$model->table."] id [".$model->id."] :");
            // if($model->forceDeleting) {
            //     //do in case of force delete
            //     Log::info("Force Delete : ". json_encode($model));
            // } else {
            //     //do in case of soft delete
            //     Log::info("Soft Delete :". json_encode($model));
            // }
        });
    }
}
