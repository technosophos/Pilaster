<?php

$db_dir = './db';
$db_name = 'foo';

try {
  $db = new DocumentDB($db_name, $db_dir);
  $db->deleteAllDocuments();
} catch (Exception $e) {
  print $e->getMessage() . PHP_EOL;
  print "Attempting to create a new repository..." . PHP_EOL;
  $db = DocumentDB::createDocumentDB($dbName, $path);
  print "New database created." . PHP_EOL;
}

$doc = new PilasterDocument();
$p = array(
  'title' => 'Test',
  'author' => 'M Butcher',
  'purpose' => 'Test application',
  'multi' => array('templateA','templateB','templateC'),
);
$doc->setMetadata($p);