<?php
/**
 * Provide an implementation of the {@link PilasterDB} interface.
 * 
 * @package Pilaster
 * @subpackage LuceneDriver 
 * @author mbutcher
 * @since 2.0
 */

/**
 * Provides an implementation of the {@link PilasterDB} interface.
 * 
 * This driver uses Lucene as a backend for Pilaster. It relies heavily
 * on the full driver in {@link PilasterLuceneDriver}, which contains (among 
 * other things) the full legacy Rhizome-style database.
 *
 * @package Pilaster
 * @subpackage LuceneDriver 
 * @author mbutcher
 * @since 2.0
 */
class PilasterDBLuceneDriver implements PilasterDB {
  
  /**
   * The database (from the driver).
   * @var
   */
  protected $db = NULL;
  
  public function __construct($dbName, $path = '') {
    $this->db = new PilasterLuceneDriver($dbName, $path);
  }
  
  /**
   * Count the number of documents.
   *
   * Performance note: If no query is passed, this is optimized to count documents.
   * Passing a query, however, is no more efficient than simply running {@link find()}
   * and then counting the results.
   *
   * @see PilasterDB
   */
  public function count($query = NULL) {
    if (!isset($query))
      return $this->db->countDocuments();
    /*
    elseif (count($query) == 1) {
      // Optimize using Lucene::docFreq()
    }
    */
    else {
      $res = $this->find($query);
      return count($res);
    }
  }
  
  public function find($query = NULL, $fields = NULL) {
    if (is_string($query)) {
      // Process this as a search query:
      //throw new Exception("Not implemented.");
      return $this->db->search($query);
    }
    elseif (is_array($query)) {
      return $this->db->narrowingSearch($query);
    }
    throw new Exception('Unknown search behavior.');
  }
  
  public function findOne($query = NULL, $fields = NULL) {
    $result = $this->find($query);
    if (count($result) > 0) {
      return $result[0];
    }
    return NULL;
  }
  
  public function save($data) {
    $this->db->replace($data);
  }
  public function remove($criteria, $justOne = FALSE) {
    $this->db->delete($criteria, $justOne);
  }
  
  public function close() {
    $this->db->close();
  }
  
  public function insert($data) {
    $this->db->insert($data);
  }
  // TODO: Should we implement these?
  /*
  public function update() {}
  */
}