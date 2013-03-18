# Coseva
Coseva is meant to improve your programming logic when it comes to parsing and handling CSV.
It translates your CSV file in an object with an extensive API.

From filtering rows and columns to parsing back to CSV and other formats.

## Getting started

```php
<?php
// Require this once or add it to your autoloader.
require_once 'src/Coseva/Csv.php';

// Use the Csv class from the Coseva namespace.
use Coseva\Csv;

// Get an instance of the file you want to manipulate.
$csv = Csv::getInstance('lib/monthly_income.csv');
```

## Filtering and parsing

With Coseva you can easily filter and parse your data using it's smart filtering and parsing system.


### By column

A most common use for Coseva is to properly parse data in PHP native data types.

```
<?php
$csv->filter('Hits', 'intval');

// The parse method will be implicitely called when you try to get output, but
// there are no parsed rows available. This means you don't have to call the
// parse method if you only need it once. This is true for most scenarios.
$csv->parse();
```

How about we format a price into a proper string? We could do that by stacking filters.

```
<?php
$csv->filter('Price', 'number_format', 2, '.', ',');
$csv->filter('Price', function($price) { return $price . ' USD'; });
```

Ofcourse it would've been more efficient to combine those filters in one filter.

What about a more complex filter for parsing a date range?

```php
<?php
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
```

### By row

We could even do a bunch of things at once. Just leave out the column index.
It will even eat additional parameters like with `number_format` in the column example.

```php
<?php
$csv->filter(function(array $row) {
  // Cast the hits to an integer.
  $row['Hits'] += 0;

  // Transform the timestamp into a human readable time string.
  $row['Time'] = strftime('%c', $row['Time'] + 0);

  // Return the row.
  return $row;
});
```

### Flushing filters

If you're unhappy about the filters you applied to the Csv object, simply flush them.

- `$csv->flushFilters();`

Flushing the filters will be done each time you trigger `$csv->parse()` or an output method.

### Persistent filters

Since filters will be automatically flushed after filtering and perhaps you might want to use them all over again, one
can enable persistent filters. Remember, this should be only used in rare edge cases where data can be altered more than once by the same set of filters. The filtered data is what is stored and will be used when claiming output.

- enable  `$csv->persistentFilters();`
- disable `$csv->persistentFilters(false);`

And remember, all filters can be stacked.

## Getting output

Getting output out of Coseva is rather simple. The Csv class knows how to be cast to a string, so a simple `echo $csv;` will suffice in most cases.

### toCsv

Getting back parsed CSV can be done by casting the Csv object of calling the corresponding method.

- `echo $csv`
- `echo $csv->toCsv()`

### toJson

In case you want to serve the data to a web application through JSON, you can do so by calling `$csv->toJson();`.

- `echo $csv->toJson();`

### getRows

A great amount of scenarios expect you to retreive the CSV data as a native PHP array. Coseva obliges.

- `$csvArray = $csv->getRows();`

### Saving to file

When saving the parsed CSV to a file, just call `$csv->save('/path/to/file');`. Or, to simply store the CSV back into it's source file, call `$csv->save();`.

- `$csv->save('/path/to/file.csv');`
- `$csv->save();`

## Optimization

Because Coseva is rather badass, it will automatically detect the file size and available memory usable by your PHP process and then determine whether to automatically enable things like garbage collection and flushing empty rows.

Since the user might want to enable this manually, one can do so.

### Garbage collection

Garbage collection is meant to prevent memory leaks and clean up unused bits in memory.
One can trigger it manually:

- enable:  `$csv->collectGarbage();`
- disable: `$csv->collectGarbage(false);`

### Flushing empty rows.

Flushing empty rows will not only clean up your data, but can improve filter speed on huge data sets. Therefore, it is automatically triggered on files over 1MB.

- run now: `$csv->flushEmptyRows();`
- enable:  `$csv->flushEmptyRows(true);`
- disable: `$csv->flushEmptyRows(false);`

## Creating an archive/executable.

Since Coseva wants to be really badass, it has a packager built in, which allows you to create binary executables.
This way, you can store your data alongside a script that manipulates the data in realtime, allowing for presentations and maintenance scripts precompiled for certain environments, using specific CSV data.

To create an executable of your own:

`bin/packager /path/to/csv.csv /path/to/script.php [/optional/packagename.phar]`

Once that has finished, you can run your package directly using `./package.phar`.

## Todo

A todo list can be found [here](TODO.md).

## Credits

- This repo is a fork from [johnnyfreeman/coseva](https://github.com/johnnyfreeman/coseva).
- Google Trends data was gathered using [this link](http://www.google.com/trends/explore?hl=en#q=github).
- Main development done by [johmanx10](https://github.com/johmanx10)