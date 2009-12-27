<?php
/**
 * This file houses the database classes for Pilaster.
 * Document DB is the main data storage mechanism for Pilaster. 
 * This class provides a single controller for a backend document
 * storage engine.
 * 
 * <b>Classes</b>
 * 
 * DocumentDB is the main class that you should use.
 * 
 * AbstractDB, now part of Pilaster, was originally introduced in the 
 * first version of Sinciput ({@link http://aleph-null.tv}). It was then
 * ported to Python, and then to PHP5. This version is a stand-alone package
 * that now makes use of the Zend Framework -- primarily for its Lucene
 * implementation. It is an extension of DocumentDB that supports several 
 * deprecated methods.
 * 
 * (Plans to port Pilaster to Java resulted in an even more robust document
 * database called Rhizome {@link http://sinciput.etl.luc.edu}.)
 * 
 * DocumentDBLuceneDriver provides low-level implementations of functions used
 * by the other clases in the DB package. You should never use this class directly,
 * as it is volatile and likely to change as we figure out better ways of doing 
 * things.
 * 
 * 
 * <b>terminology</b>
 * * Metadata: Attributes attached to a document to provide information 
 *    about the document.
 * * Data or Body: The contents of the document, often in the form of 
 *    a text file or a marked-up text file.
 * * Extension: An arbitrary datastructured, serialized to XML, that 
 *    contains additional information to be added to the document.
 * 
 * @package Pilaster
 * @subpackage DB
 * @license GPL 3
 */

/**
 * The Lucene implementation.
 */
require_once "Zend/Search/Lucene.php";
/**
 * The core document object.
 */
require_once "PilasterPHP/PilasterDocument.php";
    
/**
 * DocumentDB is a generic document database.
 * 
 * The purpose of DocumentDB is to provide a generic controller for access
 * to a document database. The document database stores documents,
 * together with metadata and extensions, in a way that makes lookup
 * based on arbitrary metadata quick and efficient.
 * 
 * Here's an example of typical usage:
 * <code>
 * $db = new DocumentDB('MyIndex');
 * 
 * $db->addDocument(SomePilasterDocument);
 * 
 * $document = $db->getDocument("MyDocumentID");
 * 
 * $attrs = array('name' => 'To Do", 'type' => 'list');
 * $listOfDocuments = $db->fetchDocumentList( $attrs );
 * </code>
 * 
 * <b>Drivers</b>
 * This first version of PilasterPHP uses a single driver -- the Lucene driver --
 * to provide a database backend. Future versions should facilitate different
 * drivers, like -- perhaps -- BDB or Xapian driver.
 * 
 * @package Pilaster
 * @subpackage DB
 */
class DocumentDB {
    
    /**
     * The driver implementation.
     * @var
     */
    protected $driver;

    /**
     * Create a new repository and return a DocumentDB object.
     * @param string $repositoryName 
     *  The name of the repository.
     * @param string $basePath
     *  The path to the repository.
     * @return DocumentDB 
     *  The new document database.
     */
    static function createDocumentDB($repositoryName, $basePath = './') {
        DocumentDBLuceneDriver::createRepository($repositoryName, $basePath);
        return new DocmentDB($repository, $basePath);
    }
    
    /**
     * Check to see if the given repository exists.
     *
     * @param string $repositoryName
     *  The name of the repository.
     * @param string $basePath
     *  The path to the repository.
     * @return boolean
     *  TRUE if the repository exists, false otherwise.
     */
    static function hasDocumentDB($repositoryName, $basePath = './') {
        return DocumentDBLuceneDriver::hasDocumentDB($repositoryName, $basePath);
    }
    
    /**
     * Create a new DocumentDB.
     * 
     * This opens a connection to the repository and prepares it for queries.
     * 
     * 
     * @param string $repository 
     *   Name of the repository.
     * @param string $base_path
     *   Path where repository is located on disk.
     */
    function __construct($repository, $base_path = './') {
        $this->driver = new DocumentDBLuceneDriver($repository, $base_path);
    }
    
    /**
     * Checks to see if this $element is a known metadata type. If a metadata
     * type is known, then when a piece of metadata of that type is digested,
     * then it will be added to the metadata index files.
     *
     * @param string $element
     *   The name of metadatum.
     * @return boolean 
     * TRUE if the given $element is a metadata type.
     */
    function isMetadataType($element) {
        $a = $this->getMetadataTypes();
        foreach($a as $aa) {
            if($aa == $element) return true;
        }
        return false;
    }


    /**
     * Gets an array of metadata types.
     * 
     * @return array 
     *   Array of string metadata types.
     */
    function getMetadataTypes() {
        return $this->driver->getMetadataNames();
    }


    /**
     * Return a list of documents that have the metadatum with the given value.
     * 
     * (The name of the function is slightly misleading.)
     * 
     * This will search the index for $element and return the document
     * IDs that have the value $value for element $element.
     * @param string $element indexed element
     * @param string $value value to search for
     * @returns array of matching docIDs
     */
    function getMetadataByValue($element, $value) {
        $this->driver->searchByTerm($element, $value);
    }

    /**
     * Given an element (e.g. title) and a document ID, returns the value for
     * that element/docID combination (e.g. the title string).
     * 
     * Use this if you just want a very particular piece of content from a document.
     * Because of lazy field loading, this method is more efficient than retrieving 
     * and entire document.
     * 
     * @deprecated Use {@link AbstractDB::getMetadatumByDocumentID()
     * @param string $element indexed element
     * @param string $docID document ID
     * @return string value
     */
    function getMetadataByDocumentID($element, $docID) {
        $this->getMetadatumByDocumentID($element, $docID);
    }
    
    /**
     * Given an element (e.g. title) and a document ID, returns the value for
     * that element/docID combination (e.g. the title string).
     * 
     * Use this if you just want a very particular piece of content from a document.
     * Because of lazy field loading, this method is more efficient than retrieving 
     * and entire document.
     * 
     * 
     * @param string $element indexed element
     * @param string $docID document ID
     * @return string value
     */
    function getMetadatumByDocumentID($element, $docID) {
        return $this->driver->getMetadatumByDocumentID($element, $docID);
    }

    /**
     * returns the table as an associative array.
     * 
     * If $id_array is set (and longer than 0), then only values that exist in
     * the array will be returned.
     *
     * @param string $element name of metadata element to search for
     * @param array $id_array (optional) array of doc ids.
     */
    function getMetadataByType($element, $id_array=null) {
        $r = array();
        if(!emtpy($id_array)) {
            foreach($id_array as $id) {
                $r[] = $this->getMetadatumByDocumentID($element, $id);
            }
        } else {
            //$ids = $this->driver->getDocumentIDsByMetadataName($element);
            $r = $this->driver->getDocumentIDsAndValuesByMetadataName($element);
        }
        
        return $r;
    }

    function addDocument($name, PilasterDocument $contents) {
        if($contents->getDocId() !== $name) $contents->setDocId($name);
        
        $this->driver->replace($contents);
    }

    function replaceDocument($name, PilasterDocument $contents) {
        $this->addDocument($name, $contents);
    }

    /**
     * Retrieve a document by document name (docID).
     * @access public
     * @param string $name document ID
     * @return string containing the document contents.
     */
    function getDocument($documentID) {
        return $this->driver->getDocument($documentID);
    }
    
    /**
     * Gets all documents from the database.
     * Use this with caution: You will need a lot of memory for large sites.
     */
    function getAllDocuments() {
        return $this->driver->getAllDocuments();
        
    }

    /**
     * Completely obliterate a document.
     * 
     * It is possible that an external program could have inserted multiple 
     * documents with the same document ID. This deletion will delete ALL of 
     * them. In such a case, the return value may be greater than 1.
     * 
     * @param String $documentID
     * @return int Number of deleted documents.
     */
    function deleteDocument($documentID) {
        return $this->driver->delete($documentID);
    }
    
    /**
     * Delete everything from the database.
     * If $makeBackup is true, then the contents of the database will be exported 
     * into the $basePath first.
     * @param boolean $makeBackup
     * @return int Number of documents deleted.
     */
    function deleteAllDocuments($makeBackup = false) {
        if($makeBackup) $this->exportDocuments($this->basePath, true);
        return $this->driver->emptyDatabase();
    }


    /**
     * Export all documents.
     * 
     * Writes all of the XML files in the document repository into the
     * specified dir (or if none is specified, into the export dir set in
     * the settings.php file). If $createDir is true (default) it will 
     * create a subdirectory (using a timestamp) and then write the files
     * into that directory.
     * @param string $path Directory where docs should be exported.
     * @param $createDir If true, a subdirectory will be created in $path. 
     *  The directory is named by date stamp.
     * @return int Number of documents exported
     */
    function exportDocuments($path='', $createDir=true) {
        
        if($createDir) {
            $newdir = date('Ymd-His');
            if(!mkdir($path . $newdir)) {
              throw new Exception("Failed to create directory in $path");
            }
            $newpath = $path . $newdir;
        } else $newpath = $path;
        
        return $this->driver->exportDocs($newpath);
    }

    /**
     * Checks to see if the given document ID ($name) exists in the database.
     * @param string $name document ID
     * @return boolean true if the document exists, false otherwise.
     */
    function hasDocument($name) {
        return $this->driver->hasDocument($name);
    }
    
    /**
     * Search for documents that match all conditions.
     * 
     * <b>You are encouraged to use fetchDocumentList() instead.</b> This method 
     * is less efficient, and is likely to be deprecated in future releases.
     * 
     * Performs a search through the attributes, starting with the first 
     * element in the array and then testing the rest of the elements based
     * on the results of the first.
     *
     *  Example Array:
     * <code>
     * $elementArray = Array( 
     *   'topic'=>'System Use',
     *   'category'=>'FAQ', 
     *   'live'=>'true'
     * );
     * </code>
     *
     * This function is an _equality_ based function, and does not support any 
     * other comparisons.
     *
     * @param array $elementArray array of filtering criteria (see above).
     * @return array Array of docIDs that match all criteria. (may be empty array)
     */
    function narrowingSearch($elementArray) {
        
        $r = $this->driver->narrowingSearch($elementArray);
        $a = array();
        foreach($r as $doc) {
            $a[] = $doc->getDocID();
        }
    }
    
    /**
     * Retrieve a list of PilasterDocuments that match all elements in $elementArray.
     * 
     * This searches the repository for documents that contain metadata that match
     * each and every entry in $elementArray. That is, for a document to match, 
     * it must have ALL of the metadata listed in $metadataArray, and the values 
     * must match, too.
     * 
     * Example $elementArray:
     * <code>
     * array(
     *   'title' => 'The Moon',
     *   'subject' => 'General Astronomy',
     *   'media' => 'Books'
     * )
     * </code>
     * 
     * This filter will match any document that has the title "The Moon", the 
     * subject "General Astronomy", AND the media "Books".
     * 
     * <b>Compared to NarrowingSearch</b>
     * The narrowingSearch function is similar to this, but it only returns 
     * an array of document IDs, instead of the entire document. Unfortunately,
     * given the way Lucene works, we incur MORE over head in that method than
     * we do in this. So you are encouraged to use this method.
     * 
     * <b>Origin</b>
     * This method is derived from the fetchDocumentList() method in 
     * Rhizome's com.technosophos.repository.lucene.LuceneSearcher class. See
     * http://sinciput.googlecode.com for more.
     * 
     * @param array $elementArray Filter of metadata name=>value pairs.
     * @return array Array of PilasterDocument objects
     */
    function fetchDocumentList($elementArray) {
        return $this->driver->narrowingSearch($elementArray);
    }

    /**
     * Get the number of documents in the database.
     * @return int Number of documents in the database.
     */
    function countDocuments() {
        return $this->driver->countDocuments();
    }
    
    /* ZF gets angry if I do this.
    function __destruct() {
        try {
          unset($this->driver);
        } catch (Exception $e) {
          print "Caught Exception while Closing.";
        }
    }
    */
    function close() {
        $this->driver->close();
        unset($this->driver);
    }

}

/**
 * Exception for DocumentDB and AbstractDB errors.
 */
class AbstractDBException extends Exception {
    function __construct($msg, $code = 0) {
        parent::__construct($msg, $code);
    }
}

/**
 * Driver to provide AbstractDB with a Lucene (Zend Search) backend.
 * 
 * This class provides a driver for the Zend Search backend. It stores documents
 * inside of a Lucene repository. While the entire document is stored in the
 * index, some parts of the document are indexed, meaning that they can be 
 * searched efficiently.
 * 
 * Note that this implementation differs from the default Rhizome Lucene backend
 * (which was in turn based on an earlier AbstractDB implementation). It differs
 * in that documents are stored IN THE INDEX, not as separate files on the 
 * file system. This makes backups slightly more difficult: you must actually
 * backup the entire Lucene repository. It also makes re-indexing more difficult,
 * since it involves first extracting the documents from the index, and then
 * re-indexing them.
 * 
 * Note that this implementation differs markedly from the old AbstractDB, which
 * operated not necessarily at the document level, but at the metadata level.
 * While that worked well for the PHP DBA implementation, it is not a good way 
 * to handle document-based databases. This implementation, in contrast, is
 * document-oriented.
 * 
 * @package AbstractDB
 * @subpackage LuceneDriver 
 * @author mbutcher
 * @todo Extract an interface.
 */
class DocumentDBLuceneDriver {
    
    const driver_version = "Zend Search (Lucene) Driver, v. 1.0";
    protected $repoName = null;
    protected $basePath = null;
    protected $db_params = array();
    protected $repo = null;
    
    /**
     * Create a new repository.
     * This will generate a new directory where the Lucene index files will
     * be stored.
     * @param string $repository_name Name of the repository. This will become a directory name.
     * @param string $base_path Path where the repository will be created.
     */
    static function createRepository($repository_name, $base_path = './') {
        if(!is_dir($base_path) || !is_writable($base_path)) {
            throw new AbstractDBException(
              "Cannot create repository. Base path is not a directory or is not writable."
            );
            //return false;
        }
        if(strrpos($base_path, '/') != strlen($base_path) - 1) {
            $base_path .= '/';
        }
        // Don't return this. We don't expose the low level objects here.
        /*return*/ Zend_Search_Lucene::create($base_path . $repository_name);
    }
    
    static function hasRepository($repository_name, $base_path = './') {
        
        if(!is_dir($base_path) || !is_writable($base_path)) return false;
        if(strrpos($base_path, '/') != strlen($base_path) - 1) {
            $base_path .= '/';
        }
        if(!is_dir($base_path) || !is_writable($base_path)) return false;
        return true;
    }
    
    function __construct($repository_name, $base_path = './') {
        $this->repoName = $repository_name;
        $this->basePath = $base_path;
        
        if(strrpos($base_path, '/') != strlen($base_path) - 1) {
            $base_path .= '/';
        }
        $lucene_path = $base_path . $repository_name;
        if(!is_dir($lucene_path) || !is_writable($lucene_path)) {
            throw new AbstractDBException(
              "Cannot open repository. It does not exist or is not writable."
            );
        }
        $this->repo = Zend_Search_Lucene::open($lucene_path);
        //$this->repo->optimize();
    }
    
    /**
     * @param string $version
     * @return void
     */
    function setDBParameter($name, $value) {
        $this->db_params[$name] = $value;
    }
    
    /**
     * Get the version information for this driver.
     * 
     * This information (for this driver) is not impacted by a call to 
     * setDBVersion().
     * 
     * @return string Version information.
     */
    function getDBVersion() {
        return driver_version;
    }
    
    function getDBParameters() {
        return $this->db_params;
    }
    
    function countDocuments() {
        // We use this instead of $this->repo-<count() because
        // we don't want deleted documents to be counted.
        return count($this->repo->getAllLuceneDocIDs());
    }
    
    /**
     * Insert a document into the repository.
     * <b>This will not check for duplicates!</b> It is possible, using this
     * method, to insert a duplicate into the database. Lucene will deal happily 
     * with duplicates, but AbstractDB makes no such assurances.</b>
     * 
     * Unless you know what you are doing, use replace() instead, as it can be
     * safely used to insert new documents.
     * 
     * @param PilasterDocument $document The document to insert.
     */
    function insert($document) {
        $ldoc = LuceneDocumentConverter::pilasterToLucene($document);
        $this->repo->addDocument($document);
        $this->repo->commit();
    }
    
    /**
     * Replace an old version of this document with a new one.
     * 
     * If the document alredy exists in the repository, it will be replaced. If
     * it does not exist, the document is added anyway. 
     * 
     * Note that "replacing" in Lucene requires adding and removing. If a delete
     * suceeds but a write fails, this may result in a loss of data.
     * @todo Use LID to add transactional integrity here.
     * @param <type> $document
     * @see $this->delete()
     */
    function replace($document) {
        $ldoc = LuceneDocumentConverter::pilasterToLucene($document);
        
        $oldDoc = $this->get($document->getDocID());
        if($oldDoc) $this->delete($oldDoc->getDocID());
        
        //print "Adding document " . $document->getDocID();
        //var_dump($ldoc);
        $this->repo->addDocument($ldoc);
        $this->repo->commit();
    }
    
    /**
     * Delete the given document from the index.
     * 
     * <b>Details:</b>
     * Note that this will delete ANY document with the given ID. On the off
     * chance that there are multiple documents with the same ID (a possibility
     * in Lucene), this will delete them all.
     * 
     * @param String $documentID Document ID for document to delete.
     * @return int Number of matching documents deleted. This should be either 1 or 0.
     */
    function delete($documentID) {
        // Find document by document ID:
        $term = new Zend_Search_Lucene_Index_Term(
            $documentID,
            LuceneDocumentConverter::docid_field_name
        );
        
        $matches = $this->repo->termDocs($term);
        $c = 0;
        foreach($matches as $match) {
            $this->repo->delete($match);
            ++$c;
        }
        $this->repo->commit();
        return $c;
    }
    
    /**
     * Delete all documents in the database.
     * 
     * This removes all documents from the database. This operation cannot be
     * reversed.
     * 
     * @return boolean True if everything is deleted.
     */
    function emptyDatabase() {
        $lids = $this->getAllLuceneDocIDs();
        foreach($lids as $lid) {
            $this->repo->delete($lid);
        }
        $this->repo->commit();
    }
    
    /**
     * Check for the existence of a document with the given documentID.
     * 
     * This runs a search of the database and returns true if there is a document
     * (or possibly more than one) with the given ID.
     *
     * Note that this incurs the full overhead of a search. If you are planning
     * on, for instance, getting a document, it is cheaper to just use get() and
     * check the results than to call hasDocument() and then conditionally use
     * get(). (Cheaper unless Lucene is tuned to agressively cache.)
     * @param <type> $documentID
     * @return <type>
     */
    function hasDocument($documentID) {
        
        // Find document by document ID:
        $term = new Zend_Search_Lucene_Index_Term(
            $documentID,
            LuceneDocumentConverter::docid_field_name
        );
        
        // Get the Lucene doc ID
        $matches = $this->repo->termDocs($term);
        foreach($matches as $match) {
            if(!$this->repo->isDeleted($match)) return true;
        }
        return false;
    }
    
    /**
     * Given a document ID, get a document.
     * @param string $documentID
     * @return PilasterDocument The document, or false if the document could not be found.
     */
    function get($documentID) {
        
        //print "DocumentDB::get: Trying to get " . $documentID;
        // Find document by document ID:
        $term = new Zend_Search_Lucene_Index_Term(
            $documentID,
            LuceneDocumentConverter::docid_field_name
        );
        
        //var_dump($term);
        
        // Get the Lucene doc ID
        $matches = $this->repo->termDocs($term);
        //var_dump($matches);
        if(count($matches) == 0) {
            //print "DocumentDB::get: NO DOCUMENTS FOUND.";
            return false;
        }
        
        // Fetch the document from Lucene
        $lid = $matches[0];
        if($this->repo->isDeleted($lid)) {
            //print "DocumentDB::get: Requested document has been deleted.";
            return false;
        }
        $doc = $this->repo->getDocument($lid);
        
        // Convert Lucene doc to Pilaster doc and return.
        return LuceneDocumentConverter::luceneToPilaster($doc);
    }
    
    /**
     * Alias of get().
     * This returns a PilasterDocument for the given document ID.
     * @see AbstractDBLuceneDriver::get()
     * @param string $documentID
     * @return PilasterDocument Document with given ID, or false if no document was found.
     */
    function getDocument($documentID) {
        return $this->get($documentID);
    }
    
    /**
     * Return all of the document IDs that have the given metadatum name.
     * 
     * This will give you a list of all documents that use the given metadatum
     * name to store values.
     * @param string $name
     * @return array Array of document ID strings.
     */
    function getDocumentIDsByMetadataName($name) {
        $ids = $this->getAllLuceneDocIDs();
        $r = array();
        foreach($ids as $id) {
            
            $d = $this->repo->getDocument($id);
            $a = $this->repo->getFieldNames();
            if(in_array($name, $a)) {
              $r[] = $d->getFieldValue(LuceneDocumentConverter::docid_field_name);
            }
        }
        return $r;
    }
    
    /**
     * This returns an associative array of docIDs and their values for the 
     * given metadatum name.
     * @param <type> $name
     * @return <type>
     */
    function getDocumentIDsAndValuesByMetadataName($name) {
        $ids = $this->getAllLuceneDocIDs();
        $r = array();
        foreach($ids as $id) {
            
            $d = $this->repo->getDocument($id);
            $a = $this->repo->getFieldNames();
            if(in_array($name, $a)) {
              $r[$d->getFieldValue(LuceneDocumentConverter::docid_field_name)] =
                $d->getFieldValue($name);
            }
        }
        return $r;
    }
    
    /**
     * Get all documents in the repository.
     * @return array Array of PilasterDocuments.
     */
    function getAllDocuments() {
        $lids = $this->getAllLuceneDocIDs();
        $a = array();
        foreach($lids as $lid) {
            $a[] = LuceneDocumentConverter::luceneToPilaster($this->repo->getDocument($lid));
        }
        return $a;
    }
      
    /**
     * Search for metadata and return any document that matches.
     * 
     * @param string $name Metadata name.
     * @param string $value Metadata value.
     * @return array Array of {@link PilasterDocument} objects.
     */
    function searchByMetadata($name, $value = '') {
        // Find document by document ID:
        $term = new Zend_Search_Lucene_Index_Term(
            $value,
            $name
        );
        
        // Get the Lucene doc ID
        $matches = $this->repo->termDocs($term);
        
        $docList = array();
        if (count($matches) > 0) {
            foreach ($matches as $match) {
                $doc = $this->repo->getDocument($match);
                $docList[] = LuceneDocumentConverter::luceneToPilaster($doc);
            }
        }
        
        return $docList;
    }
    
    /**
     * Get all of the metadata names in this repository.
     */
    function getMetadataNames() {
        $a = $this->repo->getFieldNames();
        $b = array();
        // filter internal attributes.
        foreach($a as $aa) {
            if(strpos($aa, '__') !== 0) 
              $b[] = $aa;
            //else print "Driver::getMetadataNames: " . $aa;
        }
        unset($a);
        return $b;
    }
    
    /**
     * Get the value of just one single metadatum for one particular document.
     * This will return empty if either the doc doesn't exist or the MD has no
     * associated value.
     * @param String $mdName Name of the metadatum to get.
     * @param string $documentID Document ID to search
     * @return String value of the given metadatum for the given document.
     */
    function getMetadatumByDocumentID($mdName, $documentID) {
        // Find document by document ID:
        $term = new Zend_Search_Lucene_Index_Term(
            $documentID,
            LuceneDocumentConverter::docid_field_name
        );
        
        // Get the Lucene doc ID
        $matches = $this->repo->termDocs($term);
        if(count($matches) == 0) 
          return '';
        
        // Fetch the document from Lucene
        $lid = $matches[0];
        $doc = $this->repo->getDocument($lid);
        
        // Get the MD value:
        return $doc->getFieldValue($mdName);
    }
    
    /**
     * Perform a search of the DocumentDB.
     * <b>Warning:</b> This is likely to change!
     * @param string $query
     * @return array Array of Hit objects.
     */
    function search($query) {
        $results = $this->repo->find($query);
        
        // What should this return?
        
        return $results;
    }
    
    /**
     * Get a list of documents that match the given filter.
     * The $narrower is an associative array of metadata=>value pairs that a 
     * document must match before being included in the result set. Think of
     * this as a list AND-evaluations.
     * 
     * @param array $narrower Associative array of metadata name=>value pairs.
     * @return array List of PilasterDocument objects that match the $narrower.
     */
    function narrowingSearch($narrower) {
        
        if(empty($narrower) || !is_array($narrower)) return false; // short-circuit if narrower is empty.
        
        // This implementation roughly follows the Rhizome implementation
        // (see com.technosophos.rhizome.repository.lucene.LuceneSearcher)
        // But the Zend API is smaller than the Lucene API, so we have 
        // used "higher level" API calls, and skipped the lazy loading,
        // which Zend either does not support, or supports quietly.
        
        $docs = $this->getAllLuceneDocIDs();
        //echo "Got all Lucene doc IDs: " . count($docs);
        
        // Instead of getting all docs, let's shift the first value off and 
        // try to get the termDocs for it:
        /*
         * This unfortunately only works for UnTokenized fields (like Keyword)
        list($k, $v) = each($narrower);
        $term = new Zend_Search_Lucene_Index_Term($v, $k);
        
        var_dump($term);
        
        $docs = $this->repo->termDocs($term);
        
        
        
        // Done with that key.
        unset($narrower[$k]);
        */
        
        $matches = array();
        foreach($docs as $lid) {
            $doc = $this->repo->getDocument($lid);
            //echo "Got the document...";
            if(empty($narrower) || $this->checkANDFieldMatches($doc, $narrower)) {
                $matches[] = LuceneDocumentConverter::luceneToPilaster($doc);
            }
        }
        
        return $matches;
    }
    
    /**
     * Evaluates a document to see whether it contains metadata that matches all
     * of the entries in $toMatch.
     * 
     * Any mismatch results in a return of FALSE.
     * @param Zend_Search_Lucene_Document $candidate Document to evaluate.
     * @param array $toMatch Associative array of Name/Value (Field/Value) pairs. 
     * The document must match every pair before this will return TRUE.
     * @return boolean True if the document contains matches for everything in $toMatch.
     */
    private function checkANDFieldMatches(Zend_Search_Lucene_Document $candidate, $toMatch) {
        $isMatch = true;
        foreach($toMatch as $name => $value) {
            try {
                $field = $candidate->getField($name);
                if(!isset($field) || $field->value != $value) {
                    $isMatch = false;
                }
            } catch (Zend_Search_Lucene_Exception $e) {
                // Term does not exist for document. For some reason this
                // case throws an exception, even though it is not abnormal
                // for a document to NOT have a specific term.
                $isMatch = false;
            }
        }
        return $isMatch;
    }
    
    /**
     * Retrieve a list of all Lucene Document IDs.
     * 
     * This returns a list of all document IDs that exist in Lucene and are not
     * marked as deleted. (Note that Lucene may mark documents as deleted, but
     * the documents may remain until a reindexing occurs).
     * 
     * Note that this returns LUCENE IDs, not Pilaster IDs. These are good only
     * for dealing with the Lucene engine.
     * @return array Array of document IDs.
     */
    private function getAllLuceneDocIDs() {
        $docID = 0;
        $maxDoc = $this->repo->maxDoc(); // One greater than largest doc.
        $totalWritten = 0;
        $r = array();
        while ($docID < $maxDoc) {
            //print "AbstractDBLuceneDriver::getAllLuceneDocIDs: DocID: $docID<br/>";
            // Early on, this was causing problems. I think those are resolved.
            if(!$this->repo->isDeleted($docID)) {
                $r[] = $docID;
            }
            ++$docID;
        }
        return $r;
    }
    
    /**
     * Export all documents to the path given.
     * 
     * Documents will be in XML.
     * @param string $path Path where files should be written. It is assumed
     * that the path is valid.
     * @return int Number of documents written.
     * @throws AbstractDBException if the path is unwritable.
     */
    function exportDocs($path) {
        if(!is_dir($path) || !is_writable($path)) {
            throw new AbstractDBException("Cannot export to $path: Unwritable or not a directory.");
        }
        
        if(strrpos($path, '/') !== strlen($path) - 1) {
            $path .= '/';
        }
        
        $docID = 0;
        
        $lids = $this->getAllLuceneDocIDs();
        
        //$maxDoc = $this->repo->maxDoc(); // One greater than largest doc.
        $totalWritten = 0;
        foreach ($lids as $lid) {
            if(!$this->repo->isDeleted($lid)) {
                $doc = $this->repo->getDocument($lid);
                if(!empty($doc)) {
                    $realDocID = $doc->getFieldValue(LuceneDocumentConverter::docid_field_name);
                    $pristineContents = $doc->getFieldValue(
                        LuceneDocumentConverter::pristine_field_name
                    );
                    $target = $path . $realDocID;
                    $r = file_put_contents($target, $pristineContents);
                    if ($r === false) 
                      $failedWrites[] = $realDocID;
                    else 
                      ++$totalWritten;
                      
                }
            }
        }
        
        if(!empty($failedWrites)) {
          throw new AbstractDBException(
            "Failed to export the following documents: " 
                . implode(', ', $failedWrites)
                . ". However, $totalWritten documents were exported to disk."
          );
        }
        
        return ++$totalWritten;
    }
    
    public function close() {
        $this->repo->commit();
        unset($this->repo);
    }
}

/**
 * Utility class to convert PilasterDocuments to Lucene documents and back again.
 * 
 * Lucene (aka Zend Search) needs to have documents prepared for insertion into
 * the index. The method of preparation includes storing data inside of a 
 * {@link Zend_Search_Lucene_Document} object. The methods in this class handle
 * converting back and forth between the PilasterDocument format and the 
 * Lucene Document format.
 * @package AbstractDB
 * @subpackage LuceneDriver
 */
class LuceneDocumentConverter {
    
    const content_field_name = '__content';
    const docid_field_name = '__docid';
    const pristine_field_name = '__pristine';
    
    const publishing_state_field_name = '__publishing_state';
    const publishing_owner_field_name = '__publishing_owner';
    
    /**
     * Convert a Pilaster Document to a Lucene Document.
     * A Lucene document is a pre-indexed Lucene-specific document description.
     * 
     * @param PilasterDocument $document The document to prepare for indexing.
     * @return Zend_Search_Lucene_Document Document prepared for indexing.
     */
    function pilasterToLucene(PilasterDocument $document) {
        $ldoc = new Zend_Search_Lucene_Document();
        $md = $document->getMetadataAssoc();
        foreach($md as $m => $v) {
            if(is_array($v)) {
              foreach($v as $vv) {
                $ldoc->addField(Zend_Search_Lucene_Field::Keyword($m, $vv));  
              }  
            } else {
              $ldoc->addField(Zend_Search_Lucene_Field::Text($m, $v));
            }
        }
        //$ldoc->addField(Zend_Search_Lucene_Field::Keyword('foo', 'Foo'));

        $ldoc->addField(Zend_Search_Lucene_Field::Keyword(LuceneDocumentConverter::docid_field_name, $document->getDocID()));
        $ldoc->addField(Zend_Search_Lucene_Field::Text(LuceneDocumentConverter::content_field_name, $document->getContent()));
        
        $pub = $document->getPublishing();
        /*
        $ldoc->addField(Zend_Search_Lucene_Field::Keyword(LuceneDocumentConverter::publishing_state_field_name, 
                $pub->getStatus()));
        $ldoc->addField(Zend_Search_Lucene_Field::Keyword(LuceneDocumentConverter::publishing_owner_field_name, 
                $pub->getOwner()));
         * 
         */
        
        // We are actually storing the entire document in the database.
        $ldoc->addField(Zend_Search_Lucene_Field::UnIndexed(
            LuceneDocumentConverter::pristine_field_name, 
            $document->toXML()
        ));
        //var_dump($ldoc);
        return $ldoc;
    }
    
    function luceneToPilaster(Zend_Search_Lucene_Document $lucene_document) {
       return new PilasterDocument($lucene_document->getFieldValue(LuceneDocumentConverter::pristine_field_name)); 
    }
}