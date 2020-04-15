<?php


namespace Meklis\DbDiff\Mysql;



class Schema {
    protected $sql;
    protected $dbName;
    public function getConn() {
        return $this->sql;
    }
    public function __construct($host, $username, $passwd, $dbName)
    {
        $this->dbName = $dbName;
        $this->sql = new \mysqli($host, $username, $passwd, $dbName);
    }
    public function getTables() {
        return $this->getTablesView('TABLE');
    }
    public function getIndexes() {
        $result = [];
        $data = $this->sql->query("SELECT CONCAT(table_name,':', index_name) name,
       index_name,
       table_name, 
       if(non_unique = 1, 0, 1) uni, 
       CONCAT('`',column_name,'`') column_names, 
       index_type
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = '{$this->dbName}' and INDEX_NAME != 'primary' 
        ORDER BY 1, 5
        ");
        while ($d = $data->fetch_assoc()) {
            if(isset($result[$d['name']])) {
                $result[$d['name']]['column_names'] .= ",".$d['column_names'];
            } else {
                $result[$d['name']] = $d;
            }
        }
        return $result;
    }
    public function getViews() {
        return $this->getTablesView('VIEW');
    }
    public function getCreateCommand($type, $viewName) {
        return $this->sql->query("SHOW CREATE $type `$viewName`")->fetch_array()[1];
    }
    public function getConstraintFKs() {
        $data = $this->sql->query("select c.constraint_name, c.table_name, c.column_name, c.referenced_table_name, c.referenced_column_name, r.UPDATE_RULE update_rule, r.DELETE_RULE delete_rule 
            from information_schema.key_column_usage c 
            JOIN  information_schema.REFERENTIAL_CONSTRAINTS r on r.CONSTRAINT_NAME = c.constraint_name and r.TABLE_NAME = c.table_name 
            where c.constraint_schema = '{$this->dbName}'");
        $result = [];
        while ($d = $data->fetch_assoc()) {
            $result["{$d['table_name']}:{$d['constraint_name']}"] = $d;
        }
        return $result;
    }
    protected function getTablesView($type) {
        $tables = [];
        $data = $this->sql->query("SELECT table_name, table_type, `engine` FROM information_schema.`TABLES` WHERE TABLE_SCHEMA = '{$this->dbName}' and table_type like '%$type%'");
        while ($d = $data->fetch_assoc()) {
            $collumns = [];
            $collumns_fill = $this->sql->query("SHOW FULL COLUMNS FROM {$d['table_name']}");
            while ($c = $collumns_fill->fetch_assoc()) {
                $collumns[$c['Field']] = [
                    'name' => $c['Field'],
                    'type' => $c['Type'],
                    'collation' => $c['Collation'],
                    'not_null' => $c['Null'] == 'NO' ? true : false,
                    'primary_key' => $c['Key'] == 'PRI' ? true : false,
                    'default' => $c['Default'] !== '' ?  $c['Default'] : false,
                    'auto_increment' => $c['Extra'] === 'auto_increment' ?  true : false,
                ];
            }
            if($d['table_type'] == 'BASE TABLE') {
                $tables[$d['table_name']] = [
                    'type' => $d['table_type'],
                    'engine' => $d['engine'],
                    'name' => $d['table_name'],
                    'columns' => $collumns,
                    'create_query' => $this->getCreateCommand('TABLE', $d['table_name']),
                ];
            } else {
                $tables[$d['table_name']] = [
                    'type' => $d['table_type'],
                    'engine' => $d['engine'],
                    'name' => $d['table_name'],
                    'create_query' => $this->getCreateCommand('VIEW', $d['table_name']),
                ];
            }
        }
        return $tables;
    }

}