<?php
/**
 * Coseva Csv.
 *
 * A friendly, object-oriented alternative for parsing and filtering Csv files
 * with PHP.
 *
 * @package Coseva
 * @subpackage Csv
 * @copyright 2013 Johnny Freeman
 */

namespace Coseva;

use \InvalidArgumentException;

/**
 * Csv.
 */
class Csv {

  /**
   * Storage for parsed Csv rows.
   *
   * @var array $_rows the rows found in the Csv resource
   */
  protected $_rows;

  /**
   * The columns for the rows.
   *
   * @var array $_columns
   */
  protected $_columns;

  /**
   * Storage for filter callbacks to be executed during the parsing stage.
   *
   * @var array $_filters filter callbacks
   */
  protected $_filters = array();

  /**
   * Whether Coseva should fetch column names.
   *
   * @var boolean $_fetchColumnNames
   */
  protected $_fetchColumnNames = false;

  /**
   * Whether Coseva fetched column names.
   *
   * @var boolean $_fetchedColumns
   */
  private $_fetchedColumns = false;

  /**
   * Whether or not to flush empty rows after filtering.
   *
   * @var bool $_flushOnAfterFilter
   */
  protected $_flushOnAfterFilter = false;

  /**
   * Whether or not to do garbage collection after parsing.
   *
   * @var bool $_garbageCollection
   */
  protected $_garbageCollection = true;

  /**
   * Whether the filters should stay after they have been applied.
   *
   * @var boolean $_persistentFilters
   */
  protected $_persistentFilters = false;

  /**
   * The file which holds the Csv.
   *
   * @var string $_sourceFile
   */
  protected $_sourceFile;

  /**
   * The format in which the Csv is stored.
   *
   * @todo add a method that can modify these format settings. The method in
   *   question has to be rather strict, since we want to be able to extract
   *   this array inside parse().
   * @var array $_format
   */
  protected $_format = array(
    'delimiter' => ',',
    'enclosure' => '"',
    'escape' => '\\'
  );

  /**
   * The threshold in bytes after which Coseva will automatically flush empty
   * rows after parsing and filtering.
   *
   * @const integer FLUSHTHRESHOLD
   */
  const FLUSHTHRESHOLD = 1e6;

  /**
   * An array of instances of Csv to prevent unnecessary parsing of Csv files.
   *
   * @var array $_instances A list of Csv instances, keyed by filename
   */
  private static $_instances = array();

  /**
   * Constructor for Csv.
   *
   * To read a Csv file, just pass the path to the .Csv file.
   *
   * @param string $filename The file to read. Should be readable
   * @param boolean $useIncludePath Whether to search through include_path
   * @param boolean $resolveFilename Whether to resolve the filename
   * @throws InvalidArgumentException when the given file could not be read
   * @return Csv $this
   */
  public function __construct(
    $filename, $useIncludePath = false, $resolveFilename = true
  ) {
    // Check if the given filename was readable.
    if ($resolveFilename
        && !self::_resolveFilename($filename, $useIncludePath)
    ) {
      throw new InvalidArgumentException(
        var_export($filename, true) . ' is not readable.'
      );
    }

    // Store the filename for later use.
    $this->_sourceFile = $filename;

    // Try to automatically determine the most optimal settings for this file.
    // First we clear the stat cache to have a better prediction.
    clearstatcache(false, $filename);

    $fsize = filesize($filename);
    $malloc = memory_get_usage();
    $mlimit = (int) ini_get('memory_limit');

    // We have memory to spare. Make use of that.
    if ($mlimit < 0 || $mlimit - $malloc > $fsize * 2) {
      $this->_garbageCollection = false;
    }

    // If the file is large, flush empty rows to improve filter speed.
    if ($fsize > self::FLUSHTHRESHOLD) $this->_flushOnAfterFilter = true;
  }

  /**
   * Get an instance of Csv, based on the filename.
   *
   * @param string $filename the Csv file to read. Should be readable.
   *   Filenames will be resolved. Symlinks will be followed.
   * @param boolean $useIncludePath whether Coseva should look inside the
   *   include path when searching for the source file.
   * @return Csv self::$_instances[$filename]
   */
  public static function getInstance(
    $filename, $useIncludePath = false, $resolveFilename = true
  ) {
    // Check if the given filename was readable.
    if ($resolveFilename
        && !self::_resolveFilename($filename, $useIncludePath)
    ) {
      throw new InvalidArgumentException(
        var_export($filename, true) . ' is not readable.'
      );
    }

    // Check if an instance exists. If not, create one.
    if (!isset(self::$_instances[$filename])) {
      // Collect the class name. This won't break when the class name changes.
      $class = __CLASS__;

      // Create a new instance of this class.
      self::$_instances[$filename] = new $class(
        $filename, $useIncludePath, false
      );
    }

    return self::$_instances[$filename];
  }

  /**
   * Resolve a given filename, keeping include paths in mind.
   *
   * Note: Because PHP's integer type is signed and many platforms use 32bit
   * integers, some filesystem functions may return unexpected results for
   * files which are larger than 2GB.
   *
   * @param string &$filename the file to resolve.
   * @param boolean $useIncludePath whether or not to use the PHP include path.
   *   If set to true, the PHP include path will be used to look for the given
   *   filename. Only if the filename is using a relative path.
   * @see http://php.net/manual/en/function.realpath.php
   * @return boolean true|false to indicate whether the resolving succeeded.
   */
  private static function _resolveFilename(&$filename, $useIncludePath = false) {
    $exists = file_exists($filename);

    // The given filename did not suffice. Let's do a deeper check.
    if (!$exists && $useIncludePath && substr($filename, 0, 1) !== '/') {
      // Gather the include paths.
      $paths = explode(':', get_include_path());

      // Walk through the include paths.
      foreach ($paths as $path) {
        // Check if the file exists within this path.
        $exists = realpath($path . '/' . $filename);

        // It didn't work. Move along.
        if (!$exists) continue;

        // It actually did work. Now overwrite my filename.
        $filename = $exists;
        $exists = true;
        break;
      }
    }

    return $exists && is_readable($filename);
  }

  /**
   * Fetch the first row of the Csv file as the column names.
   *
   * @param boolean $fetch whether to fetch them
   * @return Csv $this
   */
  public function fetchColumns($fetch = true) {
    $this->_fetchColumnNames = !!$fetch;
    return $this;
  }

  /**
   * Allows you to register any number of filters on a particular column or an
   * entire row.
   *
   * Any additional arguments will be passed along to the callback.
   *
   * @param integer|string $column Specific column
   * @param callable $callable Expects a scalar when applied on a column and an
   *   array if applied on the whole row
   * @throws InvalidArgumentException when no valid callable was given
   * @return Csv $this
   */
  public function filter($column, $callable = null) {
    // Set the defaults.
    $filter = array(
      'callable' => null,
      'column' => false,
      'args' => func_get_args()
    );

    // Gather the callable.
    $filter['callable'] = array_shift($filter['args']);

    // Check if we actually have a column or a callable.
    if (is_scalar($filter['callable'])) {
      // Determine if this is a numeric or textual column index.
      $filter['column'] = $this->_fetchedColumns && is_numeric($filter['callable'])
        ? $this->_columns[$filter['callable']]
        : $filter['callable'];

      $filter['callable'] = array_shift($filter['args']);
    }

    // Check the function arguments.
    if (!is_callable($filter['callable'])) {
      throw new InvalidArgumentException(
        'The $callable parameter must be callable'
      );
    }

    // Add the filter to the stack.
    array_push($this->_filters, $filter);

    return $this;
  }

  /**
   * Flush rows that have turned out empty, either after applying filters or
   * rows that simply have been empty in the source Csv from the get-go.
   *
   * @param boolean $onAfterFilter whether or not to trigger while parsing.
   *   Leave this blank to trigger a flush right now.
   * @return Csv $this
   */
  public function flushEmptyRows($onAfterFilter = null) {
    // Update the _flushOnAfterFilter flag and return.
    if (!empty($onAfterFilter)) {
      $this->_flushOnAfterFilter = (bool) $onAfterFilter;
      return $this;
    }

    // Parse the Csv.
    if (!isset($this->_rows)) $this->parse();

    // Walk through the rows.
    foreach ($this->_rows as $index => &$row) {
      $this->_flushEmptyRow($row, $index);
    }

    // Remove garbage.
    unset($row, $index);

    return $this;
  }

  /**
   * Flush a row if it's empty.
   *
   * @param mixed $row the row to flush
   * @param mixed $index the index of the row
   * @param bool $trim whether or not to trim the data.
   * @return void
   */
  private function _flushEmptyRow($row, $index, $trim = false) {
    // If the row is scalar, let's trim it first.
    if ($trim && is_scalar($row)) $row = trim($row);

    // Remove any rows that appear empty.
    if (empty($row)) unset($this->_rows[$index], $row, $index);
  }

  /**
   * This method will convert the Csv to an array and will run all registered
   * filters against it.
   *
   * @return Csv $this
   */
  public function parse() {
    if (!isset($this->_rows)) {
      $fh = fopen($this->_sourceFile, 'r', false);

      $this->_rows = array();
      $key = 0;

      // Gather the format settings.
      extract($this->_format);

      // Fetch the first row to determine the columns.
      $row = fgetCsv($fh, 0, $delimiter, $enclosure, $escape);

      // See if we want actual column names or simple column indices.
      if ($this->_fetchColumnNames) {
        $this->_columns = array_values($row);
        $this->_fetchedColumns = true;
      } else {
        $this->_columns = array_keys($row);

        // Apparently we shouldn't have fetched the first row yet, although we
        // needed it to determine the column indices.
        // We should rewind.
        rewind($fh);
      }

      // Fetch the rows.
      while ($row = fgetCsv($fh, 0, $delimiter, $enclosure, $escape)) {
        // Apply any filters.
        $this->_rows[$key] = $this->_applyFilters(
          $this->_fetchedColumns
            ? array_combine($this->_columns, array_values($row))
            : $row
        );

        // Flush empty rows.
        if ($this->_flushOnAfterFilter) $this->_flushEmptyRow($row, $key, true);

        // Increment the key.
        $key++;
      }

      // Flush the filters.
      $this->flushFilters();

      // We won't need the file anymore.
      fclose($fh);
      unset($fh);
    } elseif (empty($this->_filters)) {
      // Nothing to do here.
      // We return now to avoid triggering garbage collection.
      return $this;
    }

    if (!empty($this->_filters)) {
      // We explicitely divide the strategies here, since checking this
      // after applying filters on every row makes for a double iteration
      // through $this->flushEmptyRows().
      // We therefore do this while iterating, but array_map cannot supply
      // us with a proper index and therefore the flush would be delayed.
      if ($this->_flushOnAfterFilter) {
        foreach ($this->_rows as $index => &$row) {
          // Apply the filters.
          $row = $this->_applyFilters($row);

          // Flush it if it's empty.
          $this->_flushEmptyRow($row, $index);
        }
      } else {
        // Apply our filters.
        $this->_rows = array_map(
          array($this, '_applyFilters'),
          $this->_rows
        );
      }

      // Flush the filters.
      $this->flushFilters();
    }

    // Do some garbage collection to free memory of garbage we won't use.
    // @see http://php.net/manual/en/function.gc-collect-cycles.php
    if ($this->_garbageCollection) gc_collect_cycles();

    return $this;
  }

  /**
   * Whether or not to use garbage collection after parsing.
   *
   * @param bool $collect
   * @return Csv $this
   */
  public function collectGarbage($collect = true) {
    $this->_garbageCollection = (bool) $collect;
    return $this;
  }

  /**
   * Flushes all active filters.
   *
   * @return Csv $this
   */
  public function flushFilters() {
    if (!$this->_persistentFilters) $this->_filters = array();
    return $this;
  }

  /**
   * Let filters persist after being applied.
   *
   * @param boolean $persistent
   * @return Csv $this
   */
  public function persistentFilters($persistent = true) {
    $this->_persistentFilters = !!$persistent;
    return $this;
  }

  /**
   * Apply filters to the given row.
   *
   * @param  array $row
   * @return array $row
   */
  public function _applyFilters(array $row) {
    if (!empty($this->_filters)) {
      // Run filters in the same order they were registered.
      foreach ($this->_filters as &$filter) {
        $callable =& $filter['callable'];
        $column =& $filter['column'];
        $arguments =& $filter['args'];

        // Apply to the entire row.
        if ($column === false) {
          $row = call_user_func_array(
            $callable,
            array_merge(
              array($row),
              $arguments
            )
          );
        } else {
          $row[$column] = call_user_func_array(
            $callable,
            array_merge(
              array($row[$column]),
              $arguments
            )
          );
        }
      }

      // Unset references.
      unset($filter, $callable, $column, $arguments);
    }

    return $row;
  }

  /**
   * Save the current CSV to a file.
   *
   * @param $filename
   * @throws InvalidArgumentException when the given filename was not writable
   * @return CSV $this
   */
  public function save($filename = null) {
    // Store the CSV in the source file.
    if (empty($filename)) $filename = $this->_sourceFile;

    if (!is_writable($filename)) throw new InvalidArgumentException(
      'Could not write to file ' . var_export($filename, true)
    );

    // Save the data.
    file_put_contents($filename, (string) $this);

    return $csv;
  }

  /**
   * Use this to get the entire Csv in JSON format.
   *
   * @return string JSON encoded string
   */
  public function toJSON() {
    if (!isset($this->_rows)) $this->parse();
    return json_encode($this->_rows);
  }

  /**
   * Transform the currently parsed rows back to valid CSV.
   *
   * @return string $csv
   */
  private function toCsv() {
    if (!isset($this->_rows)) $this->parse();

    $csv = $this->_rows;

    // Add the columns back in as the first row.
    if ($this->_fetchedColumns) array_unshift($csv, $this->_columns);

    // Flatten the rows to CSV strings and implode thow whole of it.
    $csv = implode(PHP_EOL, array_map(array($this, '_arrayToCsv'), $csv));

    return $csv;
  }

  /**
   * Transform an array to a CSV row.
   *
   * @return string $csv
   */
  private function _arrayToCsv(array $fields) {
    // Gather the CSV format settings.
    extract($this->_format);

    $delimiter_esc = preg_quote($delimiter, '/');
    $enclosure_esc = preg_quote($enclosure, '/');
    $csv = array();

    foreach ($fields as $field) {
      // Enclose fields containing $delimiter, $enclosure or whitespace
      $csv[] = preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field)
        ? $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field)
          . $enclosure
        : $field;
    }

    $csv = implode($delimiter, $csv);

    return $csv;
  }

  /**
   * Returns the parsed Csv as a string.
   *
   * @return string $this->toCsv() parsed and filtered rows as Csv
   */
  public function __toString() {
    return $this->toCsv();
  }

}
