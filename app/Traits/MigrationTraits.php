<?php
namespace App\Traits;

use Illuminate\Support\Facades\Schema;


trait MigrationTraits {
    /**
     * Check if a foreign key exists
     *
     * @param string $table
     * @param string $column
     * @param string $foreignTable
     * @param string $foreignColumn
     * @return boolean
     */
    public function hasForeign($table, $column, $foreignTable, $foreignColumn) 
    {
        // Get the foreign keys for the table
        $fkColumns = Schema::getConnection()
        ->getDoctrineSchemaManager()
        ->listTableForeignKeys($table);

        // Check if the foreign key exists
        foreach($fkColumns as $fkColumn) {            
            if($fkColumn->getLocalColumns()[0] == $column && $fkColumn->getForeignColumns()[0] == $foreignColumn && $fkColumn->getForeignTableName() == $foreignTable) {
                return true;
            }
        }
        
        return false;
    }
}
