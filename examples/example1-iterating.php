<?php


require '../src/Coseva/Csv.php';

use Coseva\Csv;

$csv = Csv::getInstance('example1.csv');
$csv->fetchColumns();

$csv->filter(function ($row) { return array_map('intval', $row); });
$csv->filter('3a', function ($cell) { return $cell . ' bananas'; });
$csv->filter('4a', 'number_format', 2, ',', '.');

echo $csv->toJSON();