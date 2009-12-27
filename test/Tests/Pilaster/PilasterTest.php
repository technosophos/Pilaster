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

define('DB_PATH', './test/db');
define('DB_NAME', 'pilaster_test_1');

class PilasterTest extends PHPUnit_Framework_TestCase {
  
  public function testPilasterCreateDB() {
    // Test that we have set up the testing environment correctly:
    $this->assertTrue(is_dir(DB_PATH) && is_writable(DB_PATH), 'DB_PATH should exist.');
    $this->assertFalse(is_dir(DB_PATH . '/' . DB_NAME), "DB_NAME should not already exist.");
    
    // Test that we can create a new database
    Pilaster::createDB(DB_NAME, DB_PATH);
    $this->assertTrue(is_dir(DB_PATH . '/' . DB_NAME), "DB_NAME should be created.");
    
  }
  
  public function testPilasterHasDB() {
    $this->assertFalse(is_dir(DB_PATH . '/' . DB_NAME), "DB_NAME_2 should not already exist.");
    
    // Test that we can create a new database
    Pilaster::createDB(DB_NAME, DB_PATH);
    $this->assertTrue(is_dir(DB_PATH . '/' . DB_NAME), "DB_NAME should be created.");
    
    $this->assertTrue(Pilaster::hasDB(DB_NAME, DB_PATH));
  }
  
  public function testSelectDB() {
    // Test that we can create a new database
    Pilaster::createDB(DB_NAME, DB_PATH);
    $this->assertTrue(is_dir(DB_PATH . '/' . DB_NAME), "DB_NAME should be created.");
    
    $db = Pilaster::selectDB(DB_NAME, DB_PATH);
    
    $this->assertTrue($db instanceof PilasterDB);
  }
  
  public function tearDown() {
    //$files = array('read.lock.file', 'segments.gen', 'segments_1', 'write.lock.file');
    
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