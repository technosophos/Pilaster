<?php
/**
 * Tests for the Pilaster.
 *
 * @package Tests
 * @author M Butcher <matt@aleph-null.tv>
 */

/** */
require_once 'PHPUnit/Framework.php';
require_once 'src/Pilaster.php';

if (!defined('DB_PATH')) {
  define('DB_PATH', './test/db');
  define('DB_NAME', 'pilaster_test_1');
}


class PilasterDBLuceneDriverTest extends PHPUnit_Framework_TestCase {
  const DOCID = 'TEST_001';
  
  public $doc = array(
    'id' => self::DOCID,
    'title' => 'Test',
    'author' => 'mbutcher',
    'body' => 'This is a test',
    'keywords' => array('Test', 'Experiment', 'Pilaster'),
  );
  
  public function setUp() {
    Pilaster::createDB(DB_NAME, DB_PATH);
  }
  
  public function testInsert() {
    $db = Pilaster::selectDB(DB_NAME, DB_PATH);
    
    $db->insert($this->doc);
  }
  
  public function testCount() {
    
    $db = Pilaster::selectDB(DB_NAME, DB_PATH);
    
    $this->assertEquals(0, $db->count(), 'No documents to begin.');
    
    $db->insert($this->doc);
    $this->assertEquals(1, $db->count(), 'One total document.');
    
    $q = array('id' => self::DOCID);
    throw new Exception($db->count($q));
    $this->assertEquals(1, $db->count($q), 'One document with ID ' . self::DOCID);
    
    $q = array('id' => 'foo');
    $this->assertEquals(0, $db->count($q), 'No documents with ID foo');
    
  }
  
  public function testFind() {
    
  }
  
  public function testFindOne() {
    
  }
  
  public function testSave() {
    
  }
  
  public function testRemove() {
    
  }
  
  public function testClose() {
    
  }
  
  public function tearDown() {
    $path = DB_PATH . DIRECTORY_SEPARATOR . DB_NAME;
    if (is_dir($path)) {
      $files = scandir($path);
      foreach($files as $file) {
        $filename = $path . DIRECTORY_SEPARATOR . $file;
        if (is_file($filename)) unlink($filename);
      };
      rmdir($path);
    }
    
  } 
}
