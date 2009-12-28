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
    
    $db->insert(array('id' => 'AnotherDoc'));
    $this->assertEquals(2, $db->count(), 'Two total documents.');
    
    $q = array('id' => self::DOCID);
    $this->assertEquals(1, $db->count($q), 'One document with ID ' . self::DOCID);
    
    $q = array('id' => 'foo');
    
    $this->assertEquals(0, $db->count($q), 'No documents with ID foo');
    
    $q = array('title' => 'Test');
    $this->assertEquals(1, $db->count($q), 'One document with title "Test"');
    
    /*
    $doc = array('title' => 'The quick brown fox jumped over the lazy dog.');
    for ($i = 0; $i < 1000; ++$i) {
      $doc['id'] = $i;
      $db->insert($doc);
    }
    
    $this->assertEquals(1002, $db->count(), 'Checking across a larger index.');
    */
    $db->close();
  }
  
  public function testFindWithArray() {
    $db = Pilaster::selectDB(DB_NAME, DB_PATH);
    
    $db->insert($this->doc);
    
    // Search by ID.
    $q = array('id' => self::DOCID);
    $res = $db->find($q);
    $this->assertEquals(1, count($res), 'One search result found.');
    $this->assertEquals('Test', $res[0]['title'], 'Title is "Test"');
    
    // Search for title
    $q = array('title' => $this->doc['title']);
    $res = $db->find($q);
    $this->assertEquals(1, count($res), 'One search results found for title.');
    
    $db->insert(array('id' => 'SecondDoc', 'title' => $this->doc['title']));
    $res = $db->find($q);
    $this->assertEquals(2, count($res), 'One search results found for title.');
    
    $q['id'] = 'SecondDoc'; // Title and second doc.
    $res = $db->find($q);
    $this->assertEquals(1, count($res), 'Only second doc.');
    
  }
  
  public function testFindWithString() {
    
    $db = Pilaster::selectDB(DB_NAME, DB_PATH);
    
    $db->insert($this->doc);
    $db->insert(array('id' => 'SecondDoc', 'title' => $this->doc['title']));

    // Search for keywords
    //$q = array('keywords' => 'Test');
    $q = 'keywords:Test';
    $res = $db->find($q);
    $this->assertEquals(1, count($res), 'One search results found for keyword Test.');
    $this->assertEquals($this->doc['title'], $res[0]['title']);
    
    // Insert a new doc, then search again.
    $db->insert(array('id' => 'AnotherDoc', 'keywords' => array('Test', 'Stinky cheese')));
    $res = $db->find($q);
    //throw new Exception(print_r($res, TRUE));
    $this->assertEquals(2, count($res), 'Two search results found for keyword Test.');
    
    $q = 'keywords:"Stinky cheese"';
    $res = $db->find($q);
    //throw new Exception(print_r($res, TRUE));
    $this->assertEquals('AnotherDoc', $res[0]['id'], 'One stinky cheese.');
    
    $q = 'keywords:AnotherDoc';
    $res = $db->find($q);
    //throw new Exception(print_r($res, TRUE));
    $this->assertEquals(0, count($res), 'Not matches on other attributes.');
    
    $q = '+keywords:Stinky';
    $res = $db->find($q);
    //throw new Exception(print_r($res, TRUE));
    $this->assertEquals(1, count($res), 'Require stinky.');
    
    $q = '-keywords:Stinky +keywords:Test';
    $res = $db->find($q);
    //throw new Exception(print_r($res, TRUE));
    $this->assertEquals(1, count($res), 'Require not stinky, but test.');
    $this->assertFalse(in_array('Stinky cheese', $res[0]['keywords']));
    
    //$q = '+title:"Test"';
    $q = 'title:Tes*';
    //$q = array('title' => 'Test');
    $res = $db->find($q);
    //throw new Exception(print_r($res, TRUE));
    $this->assertEquals(2, count($res), 'Query string against title field.');
    
    $id = $res[0]['id'];
    $ids = array('AnotherDoc', 'TEST_001');
    $this->assertTrue(in_array($id, $ids), "$id is one of the known IDs.");
    
  }
  
  public function testFindOne() {
    $db = Pilaster::selectDB(DB_NAME, DB_PATH);
    
    $db->insert($this->doc);
    $db->insert(array('id' => 'AnotherDoc'));
    
    $q = array('id' => self::DOCID);
    $res = $db->findOne($q);
    
    
    $this->assertEquals('Test', $res['title'], 'Title is "Test"');
    $this->assertEquals(3, count($res['keywords']), 'Three keywords');
    $this->assertEquals($this->doc['body'], $res['body'], 'Body matches');
  }
  
  public function testSave() {
    $db = Pilaster::selectDB(DB_NAME, DB_PATH);
    $db->save($this->doc);
    
    // Search by ID.
    $q = array('id' => self::DOCID);
    $res = $db->find($q);
    $this->assertEquals(1, count($res), 'One search result found.');
    
    $this->doc['foo'] = 'bar';
    $db->save($this->doc);
    
    // Search by ID.
    $q = array('id' => self::DOCID);
    $res = $db->find($q);
    $this->assertEquals(1, count($res), 'One search result found after update.');
    $this->assertEquals('bar', $res[0]['foo'], 'Added property found.');
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
