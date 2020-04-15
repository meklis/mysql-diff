<?php


namespace Meklis\DbDiff;


class Printer
{
    protected $diffs = [];
    protected $comments = false;
    public function __construct()
    {

    }
    public function addDiffs($diffs) {
        $this->diffs = array_merge($this->diffs, $diffs);
        return $this;
    }
    public function enableComments() {
        $this->comments = true;
        return $this;
    }
    public function disableComments() {
        $this->comments = false;
        return $this;
    }
    protected function buildComment($diff) {
        $comment = "";
        switch ($diff['action']) {
            case 'add_column':
                $comment .= "Add column {$diff['column']} to {$diff['table']}";
                break;
            case 'modify_column':
                $comment .= "Modify column {$diff['table']}.{$diff['column']}";
                break;
            case 'drop_column':
                $comment .= "Drop column {$diff['table']}.{$diff['column']}";
                break;
            case 'create_table':
                $comment .= "Create table {$diff['table']}";
                break;
            case 'drop_table':
                $comment .= "Drop table {$diff['table']}";
                break;
            case 'drop_view':
                $comment .= "Drop view {$diff['table']}";
                break;
            case 'create_view':
                $comment .= "Create view {$diff['table']}";
                break;
            case 'drop_index':
                $comment .= "Drop index {$diff['name']}";
                break;
            case 'create_index':
                $comment .= "Create index {$diff['name']}";
                break;
            case 'drop_fk':
                $comment .= "Drop foreign key  {$diff['name']}";
                break;
            case 'create_fk':
                $comment .= "Create foreign key  {$diff['name']}";
                break;
        }
        return $comment;
    }
    public function getQueries() {
        $queries = [];
        foreach ($this->diffs as $diff) {
            $query = "";
            if($this->comments) {
                $query .= "/* {$this->buildComment($diff)} */\n";
                $query .= "{$diff['query']};";
            }
            $queries[] = $query;
        }
        return $queries;
    }
    public function getQueriesRaw() {
        return join("\n", $this->getQueries());
    }
}