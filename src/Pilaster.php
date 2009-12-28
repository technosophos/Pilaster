<?php
/**
 * Provide database access to Pilaster.
 * @package Pilaster
 */

// Set include paths for Zend.
$base = dirname(__FILE__);
$paths = get_include_path();
set_include_path($paths . PATH_SEPARATOR . $base);




/**
 * Import a driver to provide base functionality.
 */
require_once 'Pilaster/PilasterLuceneDriver.php';
/**
 * Import a driver to provide CRUD and search.
 */
require_once 'Pilaster/PilasterDBLuceneDriver.php';

/**
 * Cheating autoloader for Zend.
 */
function pilaster_autoload($klass) {
  if (class_exists($klass) || interface_exists($klass) || strpos($klass, 'Zend') !== 0) 
    return;
  
  $path = str_replace('_', DIRECTORY_SEPARATOR, $klass) . '.php';
  
  require_once $path;
}
spl_autoload_register('pilaster_autoload');

/**
 * Core Zend Lucene library.
 */
require_once 'Zend/Search/Lucene.php';

/**
 * The main Pilaster document database.
 *
 * This roughly follows the pattern of the MongoDB driver. (Caveat: this 
 * implementation was written before the author ever used the MongoDB driver.)
 */
class Pilaster {
  
  public function __construct($options = array()) {}
  
  public function createDB($dbName, $path = './') {
    PilasterLuceneDriver::createRepository($dbName, $path);
  }
  public function hasDB($dbName, $path = './') {
    return PilasterLuceneDriver::hasRepository($dbName, $path);
  }
  public function selectDB($dbName, $path = './') {
    return new PilasterDBLuceneDriver($dbName, $path);
  }
}

/**
 * A queryable Pilaster object.
 *
 * This is the main tool for interacting with the Pilaster database. The API
 * roughly mirrors Mongo's API, though this has methods specific to Pilaster's behavior.
 *
 * Pilaster's native "format" is simply arrays. A pilaster document might look something
 * like this:
 * <?php
 * $doc = array(
 *   'id' => $some_unique_id, // Not required, but recommended.
 *   // All other attributes are made up. None are required.
 *   'title' => 'Some title',
 *   'date' => '10/23/2007',
 *   // An other attributes here...
 * )
 * ?>
 *
 * If present, a document's 'id' attribute will get special treatment. IDs are not 
 * automatically generated, though. So if you omit it, {@link has()} and {@link get()}
 * will simply not return any results.
 */
interface PilasterDB {
  
  public function __construct($dbName, $path = '');
    
  public function count($query = NULL);
  
  /**
   * Check whether a given document exists.
   *
   * Check whether there is an item in the index whose 'id' attribute matches
   * $docID.
   *
   * @param string $docID
   *   The document ID, a string. This is assumed to be unique.
   * @return boolean
   *   TRUE if the document is found, false otherwise.
   */
  public function has($docID);
  /**
   * Get a document, given the document's ID.
   *
   * Check whether there is an item in the index whose 'id' attribute matches
   * $docID. If one is found, return the document.
   *
   * @param string $docID
   *   The document ID, a string. This is assumed to be unique.
   * @return array
   *   The document, as an array.
   * @see PilasterDB This describes the returned array.
   */
  public function get($docID);
  
  public function find($query = NULL, $fields = NULL);
  public function findOne($query = NULL, $fields = NULL);
  
  public function save($data);
  public function remove($criteria, $justOne = FALSE);
  
  public function close();
  
  public function insert($data);
  // TODO: Should we implement these?
  /*
  public function update() {}
  */
}

