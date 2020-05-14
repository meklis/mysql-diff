<?php


namespace Meklis\DbDiff\Mysql;


class SchemaDiff {
    /**
     * @var Schema
     */
    protected $src;
    /**
     * @var Schema
     */
    protected $dest;

    const PRINT_DEBUG = 2;
    const PRINT_NOTICE = 1;
    const PRINT_NONE = 0;

    protected $printLevel = 0;
    /**
     * SchemaDiff constructor.
     * @param Schema $src
     * @param Schema $dest
     */
    public function __construct(Schema $src, Schema $dest, $printLevel = 0)
    {
        $this->printLevel = $printLevel;
        $this->src = $src;
        $this->dest = $dest;
    }
    public function writeLog($level, $log) {
        if($this->printLevel >= self::PRINT_DEBUG) {
            echo $level. " - ". $log ."\n";
        } elseif ($this->printLevel == self::PRINT_NOTICE && $level=='NOTICE') {
            echo $level. " - ". $log ."\n";
        }
    }
    protected function buildCreateTableQuery($tableName, $columns) {
        $query = "CREATE TABLE `$tableName` (\n";
        $pk = "";
        foreach ($columns as $columnName => $columnDefinition) {
            if($columnDefinition['primary_key']) {
                $pk = $columnName;
            }
            $query .= "   `$columnName` ".DbSchemaBuildHelper::getColumnDefinition($columnDefinition) . ",\n";
        }
        if($pk) {
            $query .= "   PRIMARY KEY (`$pk`),\n";
        }
        $query = trim($query, ",\n")."\n";
        $query .= ")";
        return $query;
    }
    public function compareTables() {
        $src_tables = $this->src->getTables();
        $dest_tables = $this->dest->getTables();
        $result = [];
        foreach ($src_tables as $srcName => $srcData) {
            if(isset($dest_tables[$srcName])) {
                $this->writeLog("DEBUG", "Table $srcName is defined");
                //Проверка полей по src -> dest
                foreach ($src_tables[$srcName]['columns'] as $columnName => $columnData) {
                    $columnDefinition = DbSchemaBuildHelper::getColumnDefinition($columnData);
                    if(isset($dest_tables[$srcName]['columns'][$columnName])) {
                        //Проверка, что поле изменилось
                        if($dest_tables[$srcName]['columns'][$columnName] !== $columnData)  {
                            $result[] = [
                                'old_definition' => DbSchemaBuildHelper::getColumnDefinition($dest_tables[$srcName]['columns'][$columnName]),
                                'action' => 'modify_column',
                                'query' => "ALTER TABLE `$srcName` MODIFY COLUMN `$columnName` $columnDefinition",
                                'table' => $srcName,
                                'column' => $columnName,
                            ];
                            $this->writeLog("NOTICE", "Column {$srcName}.{$columnName} - definition is changed");
                        } else {
                            $this->writeLog("DEBUG", "Column {$srcName}.{$columnName} - not changed");
                        }
                    } else {
                        $result[] = [
                            'action' => 'add_column',
                            'query' => "ALTER TABLE `$srcName` ADD COLUMN `$columnName` $columnDefinition",
                            'table' => $srcName,
                            'column' => $columnName,
                        ];
                        $this->writeLog("NOTICE", "Column {$srcName}.{$columnName} - not exist in destination");
                    }
                }
                //Проверка полей dest -> src
                foreach ($dest_tables[$srcName]['columns'] as $columnName => $columnData) {
                    if(!isset($src_tables[$srcName]['columns'][$columnName])) {
                        $result[] = [
                            'action' => 'drop_column',
                            'query' => "ALTER TABLE `$srcName` DROP COLUMN `$columnName`",
                            'table' => $srcName,
                            'column' => $columnName,
                        ];
                        $this->writeLog("NOTICE", "Column {$srcName}.{$columnName} - not exist in src, must drop");
                    }
                }
            } else {
                $result[] = [
                    'action' => 'create_table',
                    'query' => $this->buildCreateTableQuery($srcName, $srcData['columns']),
                    'table' => $srcName,
                ];
            }
        }
        foreach ($dest_tables as $tbl_name => $tbl_data) {
            if(!isset($src_tables[$tbl_name])) {
                $result[] = [
                    'action' => 'drop_table',
                    'query' => "DROP TABLE `$tbl_name`",
                    'table' => $tbl_name,
                ];
            }
        }
        return $result;
    }
    public function compareViews() {
        $src = $this->src->getViews();
        $dest = $this->dest->getViews();
        $result = [];
        foreach ($src as $name=>$definition) {
            if(isset($dest[$name]) && $dest[$name]['create_query'] === $definition['create_query']) {
                $this->writeLog("DEBUG", "View $name is defined and not changed");
            } elseif (isset($dest[$name])) {
                $this->writeLog("NOTICE", "View $name is defined but changed");
                $result[] = [
                    'action' => 'drop_view',
                    'query' => "DROP VIEW `$name`",
                    'name' => $name,
                ];
                $result[] = [
                    'action' => 'create_view',
                    'query' => "{$definition['create_query']}",
                    'name' => $name,
                ];
            } else {
                $result[] =  [
                    'action' => 'create_view',
                    'query' => "{$definition['create_query']}",
                    'name' => $name,
                ];
            }
        }
        foreach ($dest as $name=>$def) {
            if(!isset($src[$name])) {
                $result[] = [
                    'action' => 'drop_view',
                    'query' => "DROP VIEW `$name`",
                    'name' => $name,
                ];
            }
        }
        return $result;
    }
    public function compareIndexes() {
        $src = $this->src->getIndexes();
        $dest = $this->dest->getIndexes();
        $result = [];
        foreach ($src as $key=>$def) {
            if(!isset($def['table_name'])) {
                $this->writeLog("DEBUG", "Index $key not have table in src store");
                continue;
            }
            if(isset($dest[$key]) && $dest[$key] === $def) {
                $this->writeLog("DEBUG", "Index $key is defined and not changed");
            } elseif (isset($dest[$key])) {
                $this->writeLog("NOTICE", "Index $key is defined but changed");
                $unique = $def['uni'] ? " UNIQUE " : "";
                $result[] = [
                    'action' => 'drop_index',
                    'query' => "DROP INDEX  {$def['index_name']} ON {$def['table_name']}",
                    'name' => $key,
                    'debug' => [
                        'src' => $def,
                        'dest' => $dest[$key],
                    ]
                ];
                $result[] = [
                    'action' => 'create_index',
                    'query' => "CREATE $unique INDEX {$def['index_name']} ON {$def['table_name']} ({$def['column_names']})",
                    'name' => $key,
                    'debug' => [
                        'src' => $def,
                        'dest' => $dest[$key],
                    ]
                ];
            } else {
                $unique = $def['uni'] ? " UNIQUE " : "";
                $result[] = [
                    'action' => 'create_index',
                    'query' => "CREATE $unique INDEX {$def['index_name']} ON {$def['table_name']} ({$def['column_names']})",
                    'name' => $key,
                ];
            }
        }
        foreach ($dest as $key=>$def) {
            if(!isset($def['table_name'])) {
                throw new \Exception("TABLE NOT FOUND IN DEST");
            }
            if(!isset($src[$key])) {
                $result[] = [
                    'action' => 'drop_index',
                    'query' => "DROP INDEX  {$def['index_name']} ON {$def['table_name']}",
                    'name' => $key,
                ];
            }
        }
        return $result;
    }
    function compareConstraintFKs() {
        $src = $this->src->getConstraintFKs();
        $dest = $this->dest->getConstraintFKs();
        $result = [];
        foreach ($src as $key => $def) {
            if(isset($dest[$key]) && $dest[$key] === $def) {
                $this->writeLog("DEBUG", "FK $key exist and not updated");
            } elseif (isset($dest[$key])) {
                $this->writeLog("NOTICE", "FK $key is changed");
                $result[] = [
                    'action' => 'drop_fk',
                    'query' => "ALTER TABLE `{$def['table_name']}` DROP FOREIGN KEY `{$def['constraint_name']}`",
                    'name' => $key,
                    'debug' => [
                        'src' => $def,
                        'dest' => $dest[$key],
                    ]
                ];
                $result[] = [
                    'action' => 'create_fk',
                    'query' => "ALTER TABLE `{$def['table_name']}`
ADD CONSTRAINT  `{$def['constraint_name']}` FOREIGN KEY(`{$def['column_name']}`)
REFERENCES `{$def['referenced_table_name']}` (`{$def['referenced_column_name']}`)
ON DELETE {$def['delete_rule']}
ON UPDATE  {$def['update_rule']} ",
                    'name' => $key,
                ];
            } else {
                $result[] = [
                    'action' => 'create_fk',
                    'query' => "ALTER TABLE `{$def['table_name']}`
ADD CONSTRAINT  `{$def['constraint_name']}` FOREIGN KEY(`{$def['column_name']}`)
REFERENCES `{$def['referenced_table_name']}` (`{$def['referenced_column_name']}`)
ON DELETE {$def['delete_rule']}
ON UPDATE  {$def['update_rule']} ",
                    'name' => $key,
                ];
            }
        }
        foreach ($dest as $key => $def) {
            if(!isset($src[$key])) {
                $this->writeLog("NOTICE", "FK $key doesn't exists");
                $result[] = [
                    'action' => 'drop_fk',
                    'query' => "ALTER TABLE `{$def['table_name']}` DROP FOREIGN KEY `{$def['constraint_name']}`",
                    'name' => $key,
                ];
            }
        }
        return $result;
    }
}