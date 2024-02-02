<?php

namespace App\Services;

use App\Exceptions\POSException;
use App\Http\Tools\ParamTools;
use App\Models\Localize;
use App\Models\Account;
use App\Models\Currency;
use App\Enums\Constants;
use App\Enums\MetaEnum;
use App\Jobs\ProcessingSyncTableAdminSuperAdmin;
use App\Models\AccountContact;
use App\Models\AddressRel;
use App\Models\PimAddress;
use App\Models\SysTable;
use App\Services\Auth\JwtBuilder;
use App\Services\History\HistoryTrack;
use App\Services\Reports\SVReportRevenue;
use App\Services\Sync\SVSynchronizeToSuperAdmin;
use App\Services\Sync\SyncTools;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Webpatser\Uuid\Uuid;

abstract class BaseService
{
    protected function getQuery()
    {
        return null;
    }
       
    public function getTableName()
    {
        $query = $this->getQuery();
        return $query ? $query->getModel()->getTable() : null;
    }
}