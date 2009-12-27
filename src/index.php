<html>
<head>
<title>Testing Pilaster</title>
<style>
.err {
    color: red;
}
.info {
    color: green;
}
</style>
</head>
<body>
<?php
function msg($m, $failed = false) {
    $class = $failed ? 'err' : 'info';
    printf("<p class='%s'>%s</p>", $class, $m);
}    
    
set_include_path(get_include_path() . PATH_SEPARATOR . '/Users/mbutcher/Sites');
    
require_once 'PilasterPHP/DocumentDB.php';
require_once 'PilasterPHP/BasicPublishing.php';
require_once 'PilasterPHP/PilasterDocument.php';

msg("Requires are done. Now beginning tests...");
    
$path = './db';
$export = $path . '/test_exports_';
$dbName = 'test';

//DocumentDB::createDocumentDB($dbName, $path);
try {
  $db = new DocumentDB($dbName, $path);
  
  msg("Now emptying the database");
  $db->deleteAllDocuments();
} catch (Exception $e) {
    msg($e->getMessage());
    msg("Attempting to create a new repository...");
  
    $db = DocumentDB::createDocumentDB($dbName, $path);
    
    msg("New database created. Continuing.");
}

msg("DB opened");

$doc = new PilasterDocument();
$p = array(
  'title' => 'Test',
  'author' => 'M Butcher',
  'purpose' => 'Test application',
  'multi' => array('templateA','templateB','templateC'),
);
$doc->setMetadata($p);

$doc->setTemplates(array('1'=>'TestTemplate'), array('1' => 'TestTemplate', '2'=>'Other Templates'));
    
$content = 'This is the content.';
$doc->setContent($content);
msg("Document created.");

msg("Now inserting document:");
?>
<pre><?php print htmlentities($doc->toXML(true)); ?></pre>
<?

$db->replaceDocument($doc->getDocId(), $doc);

msg("Doc inserted.");

msg("Getting a list of all metadata types in the database:");
$types = $db->getMetadataTypes();
msg(sprintf("There are %d types in the index:", count($types)));
foreach($types as $t) {
    print $t . "<br/>";
}

msg("Getting all documents.");
$allDocs = $db->getAllDocuments();
msg(sprintf("Found %d documents.", count($allDocs)));
foreach($allDocs as $aDoc) {
    msg(sprintf("Doc ID: %s", $aDoc->getDocId()));
    //var_dump($aDoc);
}

msg("Making sure title exists.");
if($db->isMetadataType('title')) {
    msg("Found metadata 'title'");
} else {
    msg('Failed HasMetadataName: No "title" attribute.');
    $db->close();
    exit;
}

msg("Getting all documents whose title is 'Test'");
$titleDocs = $db->fetchDocumentList(array('title'=>'Test'));

msg(sprintf("Found %d documents.", count($titleDocs)));
foreach($titleDocs as $aDoc) {
    msg(sprintf("Doc ID: %s, Title is %s", $aDoc->getDocId(), $aDoc->getMetadatum('title')));
    //var_dump($aDoc);
}

msg("Getting all documents whose multi is 'templateB'");
$titleDocs = $db->fetchDocumentList(array('multi'=>'templateB'));

msg(sprintf("Found %d documents.", count($titleDocs)));
foreach($titleDocs as $aDoc) {
    msg(sprintf("Doc ID: %s, Title is %s", $aDoc->getDocId(), $aDoc->getMetadatum('title')));
    //var_dump($aDoc);
}

msg("Getting all documents whose foo is 'Foo'");
$titleDocs = $db->fetchDocumentList(array('foo'=>'Foo'));
assert(empty($titleDocs));

msg("Getting all documents whose title is 'Test' and whose author is 'M Butcher'");
$titleDocs = $db->fetchDocumentList(array('author' => 'M Butcher', 'title' => 'Test'));

msg(sprintf("Found %d documents.", count($titleDocs)));
foreach($titleDocs as $aDoc) {
    msg(sprintf("Doc ID: %s, Title is %s", $aDoc->getDocId(), $aDoc->getMetadatum('title')));
    //var_dump($aDoc);
}
    
msg("Checking to see if our document exists:");
if($db->hasDocument($doc->getDocId())) {
    msg("Document Exists.");
} else {
    msg(sprintf("Failed HasDocument test: Document %s not found.", $doc->getDocId()), true);
    $db->close();
    exit;
}
    
msg("Getting the document we created.");

$doc2 = $db->getDocument($doc->getDocId());
if(empty($doc2)) {
    msg(sprintf("Failed GetDocument test: Document %s not found.", $doc->getDocId()), true);
    $db->close();
    exit;
}
msg("The returned document:");
?>
<pre><?php print htmlentities($doc2->toXML(true)); ?></pre>
<?php
    
//msg("Exporting the database to $export");
//$db->exportDocuments($export, true);

//msg("Destroying the export in $export");
//unlink($export."/".$doc->getDocId());
//rmdir($export);
    
msg("Now attempting to delete the document.");
$numDeleted = $db->deleteDocument($doc->getDocId());
msg(sprintf("Deleted %d docs.", $numDeleted));

msg("Searching for the document we just deleted....");
assert(!$db->hasDocument($doc->getDocId()));
$doc3 = $db->getDocument($doc->getDocId());    
assert(empty($doc3));

msg("Now emptying the database");
$db->deleteAllDocuments();
    
$db->close();
//print_r($db->getAllDocuments());

msg("Test importing from XML");

$xml = '<?xml version="1.0"?>
<caryatid:doc xmlns:caryatid="http://aleph-null.tv/caryatid/document" name="20080622-1538-80.xml">
  <caryatid:metadata>
    <title type="string">Test</title>
    <author type="string">M Butcher</author>
    <purpose type="string">Test application</purpose>
  </caryatid:metadata>
  <caryatid:data format="cdata"><![CDATA[This is the content.]]></caryatid:data>
  <caryatid:templates/>
  <publishing module="BasicPublishing v.2.1" lastmod="1214170695">
    <user>mbutcher</user>
    <state>Checked Out</state>
  </publishing>
</caryatid:doc>
';
$newdoc = new PilasterDocument($xml);
?>
Orig:
<pre><?php print htmlentities($xml); ?></pre>
Post-parsing:
<pre><?php print htmlentities($newdoc->toXML(true)); ?></pre>
<?php
    
msg("Checking publishing status");
assert($newdoc->isLocked());
    
//print $db->getDocument($doc->getDocId())->toXML();
msg("All done.")
?>
</body>
</html>