<?php
/**
 * Simple filtering example.
 *
 * @package Coseva
 * @subpackage Examples
 */

// Load in the library.
require '../src/Coseva/Csv.php';

// Use the Csv class from the Coseva namespace.
use Coseva\Csv;

// Get an instance of the file we want to use.
$csv = Csv::getInstance('../lib/google_trends_github-200401-201304.csv');

// Fetch the column names. This will use the first row as columns.
// If you don't fetch columns, they will maintain a numerical index.
$csv->fetchColumns();

// Parse the weeks.
$csv->filter('Week', function($week) {
  // List the start and the end of the week.
  list($start, $end) = explode(' - ', $week);

  // Cast the dates as a DateTime object.
  $start = new DateTime($start);
  $end = new DateTime($end);

  // Change the output around.
  return $start->format('Y') . ' week ' . $start->format('W') . ', '
    . $start->format('F jS') . ' - ' . $end->format('F jS');
});

// Parse the github interest.
$csv->filter('github', 'intval');

// We could have made that into a decimal number, with a comma separator.
// $csv->filter('github', 'number_format', 2, ',', '.');

// It also always was an option to do the whole row at once.
// $csv->filter(function(array $row) { $row['github'] += 0; return $row; });

// Oh right, we want to remove empty rows from our results.
$csv->flushEmptyRows(true);

// $csv->parse();

// Now return the CSV. Output methods will automatically call for the parse
// method.
echo $csv;