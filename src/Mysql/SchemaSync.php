<?php


namespace Meklis\DbDiff\Mysql;


class SchemaSync
{
        protected $cancelOnError = false;
        protected $sql;
        function __construct(\mysqli $connection)
        {
            $this->sql = $connection;
        }
        public function cancelOnError(bool $setCancel) {
            $this->cancelOnError = $setCancel;
            return $this;
        }
        public function sync($diffs) {
            $response = [];
            foreach ($diffs as $diff) {
                if($this->sql->query($diff['query'])) {
                    $response[] = [
                        'query' =>$diff['query'],
                        'status' => 'success',
                    ];
                } else {
                    $response[] = [
                        'query' =>$diff['query'],
                        'status' => 'fail',
                        'error' => $this->sql->error,
                        'errorno' => $this->sql->errno,
                    ];
                    if($this->cancelOnError) {
                        break;
                    }
                }
            }
            return $response;
        }

}