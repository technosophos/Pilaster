<?php
/**
 * Provide database access to Pilaster.
 * @package Pilaster
 */

/**
 * Import a driver to provide base functionality.
 */
require_once 'Pilaster/PilasterLuceneDriver.php';
/**
 * Import a driver to provide CRUD and search.
 */
require_once 'Pilaster/PilasterDBLuceneDriver.php';

/**
 * The main Pilaster document database.
 *
 * This roughly follows the pattern of the MongoDB driver. (Caveat: this 
 * implementation was written before the author ever used the MongoDB driver.)
 */
class Pilaster {
  
  public function __construct($options = array()) {}
  
  public function createDB($dbName, $path = './') {
    PilasetrLuceneDriver::createRepository($dbName, $path);
  }
  public function hasDB($dbName, $path = './') {
    return PilasetrLuceneDriver::hasRepository($dbName, $path);
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
  
  public function insert();
  // TODO: Should we implement these?
  /*
  public function update() {}
  */
}

