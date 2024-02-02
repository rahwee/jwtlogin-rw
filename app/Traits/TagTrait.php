<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait TagTrait
{

     /**
     * Get where tag
     * */
    function getWhereTag($table_name, $id, $prefix = null)
    {
        $type          = $this->getTypeByTblName($table_name);
        $table_name    = empty($prefix) ? $table_name : $prefix;
        $str_global_id = empty($type) ? '' : implode(",", $this->getSVTagRelation()->getGlobalIdItemByTag($type, $id));
        return empty($str_global_id) ? " AND $table_name.global_id IN('NO_RESULT')":" AND $table_name.global_id IN($str_global_id)";
    }

    function updateTagToNULL($table_name) {
        $account_id = auth()->user()->account_id;
        DB::update("UPDATE $table_name
                            SET tag = CASE
                                        WHEN tag = '' THEN NULL
                                        /* CHECK LAST STRING IS EMPTY AFTER SPLIT , TO REMOVE ,. Example: davit, => davit */
                                        WHEN SUBSTRING( tag , LENGTH(tag) - LOCATE(',', REVERSE(tag)) + 2 , LENGTH(tag) ) = '' THEN REPLACE(tag, ',', '')
                                        ELSE tag
                                    END
                    WHERE account_id=$account_id");
    }
     /**
     * Update table column tag
     * */
    function updateTagInTable($key_type, $str_id, $new_tag, $old_tag)
    {
        $table_name = $this->getTableNameByType($key_type);


        if ($this->action == "DELETE")
        {
            // dd('delete');
            // *** Remove Tag ***
            $this->getSVTagRelation()->deleteByTag($old_tag, $key_type);
            return true; // BREAK POINT TO STOP CODE BELOW
        }
        // GET ITEMS GLOBAL ID OF STRING ID
        $globalIds  = empty($table_name) ? [] : $this->getGlobalIdByTableName($table_name, $str_id);
        // *** STILL WORK ONLY UPDATE AND CREATE ***
        if ($this->action == "UPDATE")
        {
            // REMOVE TAG NOT IN ITEM
             $this->getSVTagRelation()->removeItemsNotIn($old_tag, $globalIds, $key_type);
        }

        // CREATE TAG WITH ITEMS

        $this->getSVTagRelation()->insertMultiple($new_tag, $globalIds,$key_type);
    }

    function paramsDish($label) {
        return  [
            "dish",
            $this->getSVQueryLocalize()->index('dish'),
            " AND dish.virtual=0 AND dish.deleted_at IS NULL AND isCustom = 0",
            $label
        ];
    }

    function paramsCategory($label) {
        return [
            "category",
            $this->getSVQueryLocalize()->index('category'),
            " AND category.deleted_at IS NULL AND category.is_default = 0",
            $label
        ];
    }

    function paramsCustomer($label) {
        return [
            "contact",
            "CONCAT(contact.firstname, IF(contact.lastname IS NULL, '', ' '), contact.lastname)",
            " AND contact.deleted_at IS NULL",
            $label
        ];
    }

    function paramsMarketingGroup($label) {
        return [
            "group_compaign",
            "group_compaign.name",
            " AND group_compaign.deleted_at IS NULL",
            $label
        ];
    }

    function paramsDiscount($label) {
        $sql_localize = $this->getSVQueryLocalize()->index('discount');
        $subunit = getSubunit();
        $currency_sign = getCurrencySign();
        $sql_2digit = "CAST(discount.amount/$subunit as decimal(10,2))";
        $sql_name_loyalty = "CONCAT('(Offer: ', (IF(discount.type = 'AMOUNT', CONCAT('$currency_sign', $sql_2digit), CONCAT('%',discount.percent))), ')')";
        $sql_raw = "IF(discount.discount_type = 'LOYALTY', CONCAT($sql_localize,' ',$sql_name_loyalty), $sql_localize)";
        return  [
            "discount",
            $sql_raw,
            " AND discount.deleted_at IS NULL",
            $label
        ];
    }

    function paramsTable($label)
    {
        $this->joinTable = " ";
        $this->joinTable .= ",rest_space rs, account acc";
        $this->joinTable .= " ";
        $localize_rest_space = $this->getSVQueryLocalize()->index('rest_space', "rs");
        $localize_rest = $this->getSVQueryLocalize()->index('account', "acc");
        $whereClause = " AND rest_table.deleted_at IS NULL AND rs.deleted_at IS NULL AND acc.deleted_at IS NULL".
                        " AND rest_table.space_id = rs.id AND rs.account_id = acc.id".
                        " AND acc.parent_id IS NULL".
                        " AND rest_table.decoration = 0";
        return [
            "rest_table",
            "CONCAT(IFNULL($localize_rest, acc.name),'|',IFNULL($localize_rest_space, rs.name), '|', IFNULL(rest_table.name,rest_table.number))",
            $whereClause,
            $label
        ];
    }

    function paramsBooking($label)
    {
        $this->joinTable = " ";
        $this->joinTable .= ",contact c";
        $this->joinTable .= " ";
        return [
            "booking",
            "CONCAT(booking.id, '|', (IF(c.lastname IS NULL,c.firstname,CONCAT(c.firstname, ' ', c.lastname))))",
            " AND booking.deleted_at IS NULL AND `type`='BOOKING' AND booking.customer_id = c.id AND c.deleted_at IS NULL",
            $label
        ];
    }
    function paramsBookingRequirement($label) {
        $this->joinTable = " ";
        $this->joinTable .= ",contact c";
        $this->joinTable .= " ";
        return [
            "booking",
            "CONCAT(booking.id, '|', (IF(c.lastname IS NULL,c.firstname,CONCAT(c.firstname, ' ', c.lastname))))",
            " AND booking.deleted_at IS NULL AND `type`='BOOKING' AND booking.customer_id = c.id AND c.deleted_at IS NULL",
            $label
        ];
    }
    function paramsBookingOccasion($label)
    {
        $this->joinTable = " ";
        $this->joinTable .= ",contact c";
        $this->joinTable .= " ";
        return [
            "booking",
            "CONCAT(booking.id, '|', (IF(c.lastname IS NULL,c.firstname,CONCAT(c.firstname, ' ', c.lastname))))",
            " AND booking.deleted_at IS NULL AND `type`='BOOKING' AND booking.customer_id = c.id AND c.deleted_at IS NULL",
            $label
        ];
    }
}