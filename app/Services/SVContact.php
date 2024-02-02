<?php

namespace App\Services;

use App\Enums\MetaEnum;
use App\Models\Contact;



/**
 * Class SVContact
 * @package App\Services
 */
class SVContact extends BaseService
{
    public function getQuery()
    {
        return Contact::query();
    }

    public function getByGId($id)
    {
        $query = $this->getQuery()->where('global_id', $id);
        $query = $query->with(array(
            "tags" => function ($q) {
                $q->with(["restaurant" => function ($q) {
                    $q->with(["localizes"]);
                }]);
            }
        ))
            ->first();
           
        return $query;
    }

    public function getByEmail($email, string $type = MetaEnum::VALUE_CONTACT_TYPE_ACCOUNT_USER)
    {
        $table = $this->getTableName();
        return $this->getQuery()
            ->join('meta as m', 'm.id', $table . '.contact_type')
            ->where('m.key', MetaEnum::KEY_CONTACT_TYPE)
            ->where('m.value', $type)
            ->where($table . '.email', $email)
            ->select($table . '.*')
            ->first();
    }
}