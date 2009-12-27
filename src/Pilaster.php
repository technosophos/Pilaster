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
 */
interface PilasterDB {
  
  public function __construct($dbName, $path = '');
    
  public function count($query = NULL);
  
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

