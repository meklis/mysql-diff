#!/usr/bin/php
<?php
use Meklis\DbDiff\Mysql\Schema;
use Meklis\DbDiff\Mysql\SchemaDiff;
use Meklis\DbDiff\Mysql\SchemaSync;
use Meklis\DbDiff\Printer;

require __DIR__ . '/../vendor/autoload.php';

$src = new Schema("192.168.1.2", "meklis", "meklis", "service");
$dest = new Schema("192.168.1.2", "meklis", "meklis", "service2");

$comparator = new SchemaDiff($src, $dest, SchemaDiff::PRINT_NOTICE);
$printer = new Printer();
$printer->enableComments();
$sync = new SchemaSync($dest->getConn());

$diffs = [];
$diffs = array_merge($diffs, $comparator->compareViews());
$diffs = array_merge($diffs, $comparator->compareTables());
$diffs = array_merge($diffs, $comparator->compareConstraintFKs());
$diffs = array_merge($diffs, $comparator->compareIndexes());


echo $printer->addDiffs($diffs)->getQueriesRaw() . "\n";
echo "Start sync...\n";
$result = $sync->cancelOnError(false)->sync($diffs);

foreach ($result as $res) {
    if($res['status'] == 'success') {
        echo "Success - {$res['query']}\n";
    } else {
        echo "FAIL - {$res['error']} - {$res['query']}\n";
    }
}
