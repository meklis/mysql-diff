#!/usr/bin/php
<?php
use Meklis\DbDiff\Mysql\Schema;
use Meklis\DbDiff\Mysql\SchemaDiff;
use Meklis\DbDiff\Mysql\SchemaSync;
use Meklis\DbDiff\Printer;

require __DIR__ . '/../vendor/autoload.php';

// Define the cli options.
$cli = new \Garden\Cli\Cli();

$cli->description('Compare two mysql databases')
    ->opt('src:s', 'Source database. Example: login:password@127.0.0.1:3306/database_name', true)
    ->opt('dest:d', 'Destination database. Example: login:password@127.0.0.1:3306/database_name', true)
    ->opt('update:u', 'Update destination database', false, 'boolean');

// Parse and return cli args.
$args = $cli->parse($argv, true);

$parseDsn = function($line) {
    $resp = [];
    if(preg_match('/^((.*?):(.*?)@(.*):([0-9]{1,5})\/(.*)|(.*?):(.*?)@(.*)\/(.*))$/', $line, $m)) {
        if($m[5]) {
            $resp = [
              'host' => $m[4],
                'port' => $m[5],
                'user' => $m[2],
                'password' => $m[3],
                'database' => $m[6]
            ];
        } elseif ($m[7]) {
            $resp = [
              'host' => $m[9],
              'port' => 3306,
              'user' => $m[7],
                'password' => $m[8],
                'database' => $m[10],
            ];
        } else {
            return  false;
        }
    } else {
        return false;
    }
    return  $resp;
};

$s = $parseDsn($args->getOpt('src'));
$d = $parseDsn($args->getOpt('dest'));

if(!$s) {
    die("Incorrect source dsn\n");
}
if(!$d) {
    die("Incorrect destination dsn\n");
}

$src = new Schema($s['host'], $s['user'], $s['password'], $s['database'], $s['port'] );
$dest = new Schema($d['host'], $d['user'], $d['password'], $d['database'], $d['port']);

$comparator = new SchemaDiff($src, $dest, SchemaDiff::PRINT_NOTICE);
$printer = new Printer();
$printer->enableComments();
$sync = new SchemaSync($dest->getConn());

$diffs = [];
$diffs = array_merge($diffs, $comparator->compareTables());
$diffs = array_merge($diffs, $comparator->compareConstraintFKs());
$diffs = array_merge($diffs, $comparator->compareIndexes());
$diffs = array_merge($diffs, $comparator->compareViews());

if(!$diffs) {
    die("Diffs not found\n");
}

echo $printer->addDiffs($diffs)->getQueriesRaw() . "\n";

if($args->getOpt('update')) {
   echo "\nUpdating is enabled, pause 5 sec before start";
   for($i = 0; $i < 5; $i++) {
       sleep(1);
       echo '.';
   }
   echo "\nStart updating...\n";
   $resp = $sync->sync($diffs);
   print_r($resp);
}