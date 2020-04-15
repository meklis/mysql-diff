<?php


namespace Meklis\DbDiff\Mysql;


class DbSchemaBuildHelper {
    public static function getColumnDefinition($params) {
        $columnDefinition = " {$params['type']} ";
        if($params['not_null']) {
            $columnDefinition .= " NOT NULL";
        } else {
            $columnDefinition .= " NULL";
        }
        if($params['auto_increment']) {
            $columnDefinition .= " AUTO_INCREMENT";
        }
        if($params['collation']) {
            $columnDefinition .= " COLLATE {$params['collation']}";
        }
        if($params['default'] !== 0 && $params['default'] !== '' && $params['default'] !== false && $params['default'] !== null) {
            if($params['default'] !== 'CURRENT_TIMESTAMP') {
                $columnDefinition .= " DEFAULT '{$params['default']}'";
            } else {
                $columnDefinition .= " DEFAULT {$params['default']}";
            }
        }
        return $columnDefinition;
    }
}