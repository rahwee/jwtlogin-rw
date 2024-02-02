<?php

namespace App\Models;

use App\Traits\GlobalVersionTraits;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

abstract class BaseModel extends Model
{
    use GlobalVersionTraits, SoftDeletes;

    // override soft deleting trait method on the model, base model
    // or new trait - whatever suits you
    protected function runSoftDelete()
    {
        $query = $this->newQuery()->where($this->getKeyName(), $this->getKey());

        $this->{$this->getDeletedAtColumn()} = $time = $this->freshTimestamp();

        $query->update(array(
            $this->getDeletedAtColumn() => $this->fromDateTime($time),
            'version' => DB::raw('`version`+1')
        ));
    }
}
