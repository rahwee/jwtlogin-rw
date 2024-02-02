<?php
namespace App\Traits;

use App\Http\Tools\ParamTools;
use App\Models\RoleAccess;
use App\Permission;
use App\Role;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasPermissionsTrait {

    /**
     * Has permission on actions
     * */
    public function hasPermissionToAction($module, $action)
    {
        $that    = $this;
        $account = $this->account;
        // *** If admin approve all access ***
        if ($account->is_default || $that->role->is_admin)
            return true;
        if ($this->hasPermissionToModule($module)) {
            // *** Access on actions ***
            $this_action = $module->module_actions->firstWhere('code', $action->action);
            $role_access = $that->role->accesses->where('module_id', $module->id)->firstWhere('action_id', $this_action->id);

            return $role_access->access == 'FULL';
        }
        return false;
    }

    /**
     * Has permission on module
     * */
    public function hasPermissionToModule($module)
    {
        $that = $this;
        $account = $this->account;
        // *** If admin approve all access ***
        if ($account->is_default || $that->role->is_admin)
            return true;
        // \Log::info("module : " . json_encode($module->toArray()));
        $role_access = $that->role->accesses->where('module_id', $module->id)->firstWhere('action_id', NULL);
        if ( !$role_access ) {
            $hasFull     = $that->role->accesses->where('module_id', $module->id)->firstWhere('access', 'FULL');
            $role_access = RoleAccess::create(['account_id' => $that->account_id, 'role_id' => $that->role->id, 'module_id' => $module->id, 'action_id' => NULL, 'access' => $hasFull ? 'FULL' : 'NO']);
        }
        return $role_access->access == 'FULL';
    }

}
