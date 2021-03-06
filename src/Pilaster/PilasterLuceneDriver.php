<?php
/**
 * Pilaster driver for Lucene backend.
 * @package Pilaster
 * @subpackage LuceneDriver
 */


/**
 * Provides a Lucene-based driver for Pilaster.
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
 * In version 2.0, this was renamed from DocumentDBLuceneDriver.
 * 
 * @package Pilaster
 * @subpackage LuceneDriver 
 * @author mbutcher
 * @todo Extract an interface.
 * @since 1.0
 */
class PilasterLuceneDriver {
    
    const driver_version = "Zend Search (Lucene) Driver, v. 2.0";
    const doc_id = 'id';
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
    
    /**
     * Check if the named repository exists.
     */
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
        // We use this instead of $this->repo->count() because
        // we don't want deleted documents to be counted.
        return count($this->getAllLuceneDocIDs());
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
     * @param array $document
     *  The document to insert.
     */
    function insert($document) {
        $ldoc = LuceneDocumentConverter::pilasterToLucene($document);
        /*
        $this->repo->setMergeFactor(15, 100);
        $this->repo->setMaxBufferedDocs(25);
        */
        $this->repo->addDocument($ldoc);
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
     * @param array $document
     * @see $this->delete()
     */
    function replace($document) {
      $ldoc = LuceneDocumentConverter::pilasterToLucene($document);
      
      $oldDoc = $this->get($document[self::doc_id]);
      if(!empty($oldDoc)) $this->deleteByID($document[self::doc_id]);
      
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
     * @param String $documentID 
     *  Document ID for document to delete.
     * @return int 
     *  Number of matching documents deleted. This should be either 1 or 0.
     */
    function deleteByID($documentID) {
        // Find document by document ID:
        $term = $this->createTerm(self::doc_id, $documentID);
        
        $matches = $this->repo->termDocs($term);
        $c = count($matches);
        $this->deleteByLuceneIDs($matches);
        return $c;
    }
    
    /**
     * Common deletion logic.
     * @param mixed $termDocs
     *   An integer ID or an array of integer IDs. These should be LUCENE IDs, not
     *   Pilaster IDs.
     */
    protected function deleteByLuceneIDs($termDocs) {
      if (is_array($termDocs)) {
        foreach($termDocs as $match) {
          $this->repo->delete($match);
        }
      }
      else {
        $this->repo->delete($termDocs);
      }
      $this->repo->commit();
    }
    
    /**
     * Delete all items that match the given search.
     *
     * @param $searchSpec
     *  A valid search spec (array or string) as explained in {@link narrowingSearch()}.
     * @return int
     *  The number of items deleted.
     */
    function delete ($searchSpec) {
      if (empty($searchSpec)) {
        throw new Exception('No search specification found. Cowardly refusing to delete entire repository.');
      }
      
      $results = $this->narrowingSearchGetDocs($searchSpec);
      $this->deleteByLuceneIDs($results);
      return count($results);
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
        $term = $this->createTerm(self::doc_id, $documentID);
        
        // Get the Lucene doc ID
        $matches = $this->repo->termDocs($term);
        foreach($matches as $match) {
            if(!$this->repo->isDeleted($match)) return true;
        }
        return false;
    }
    
    protected function createTerm($termID, $termValue) {
      $termID = '___' . $termID;
      return new Zend_Search_Lucene_Index_Term($termValue, $termID);
    }
    
    /**
     * Given a document ID, get a document.
     * @param string $documentID
     * @return array
     *  The document, or false if the document could not be found.
     */
    function get($documentID) {
        
        // Find document by document ID:
        $term = $this->createTerm(self::doc_id, $documentID);
        
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
              $r[] = $d->getFieldValue(self::doc_id);
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
              $r[$d->getFieldValue(self::doc_id)] = $d->getFieldValue($name);
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
     * Get all of the metadata names in this repository.
     */
    function getMetadataNames() {
        $a = $this->repo->getFieldNames();
        $b = array();
        // filter internal attributes.
        foreach($a as $aa) {
            if(strpos($aa, '__') !== 0) 
              $b[] = $aa;
        }
        unset($a);
        return $b;
    }
    
    /**
     * Perform a search of the DocumentDB.
     *
     * This funs a full text search using Lucene, and then returns only the 
     * document(s). It is considerably slower than {@link narrowingSearch()}
     * because it incurs the full overhead of Zend Search.
     *
     * @param string $query
     *   A valid query string. Any plain text search (along with all of Lucene's
     *   supported operators) can be used here.
     * @return array 
     *   Array of found objects.
     */
    function search($query) {
        $hits = $this->repo->find($query);
        $results = array();
        
        foreach ($hits as $hit) {
          $results[] = LuceneDocumentConverter::luceneToPilaster($hit->getDocument());
        }
        
        return $results;
    }
    
    /**
     * Get all of the (Lucene) document IDs that match a given narrower.
     *
     * Helper function for {@link narrowingSearch()}.
     *
     * @param array $narrower
     *  See the description in {@link narrowingSearch()}.
     * @return array
     *  The Term Docs array -- an array of Lucene document IDs.
     */
    protected function narrowingSearchGetDocs($narrower) {
      $docsFilter = new Zend_Search_Lucene_Index_DocsFilter();
      foreach ($narrower as $termId => $termValue) {
        // We use the ___ to separate term keywords from tokenized fields.
        $term = new Zend_Search_Lucene_Index_Term($termValue, '___' . $termId);
        $termDocs = $this->repo->termDocs($term, $docsFilter);
      }
      return $termDocs;
    }
    
    /**
     * Count the items that would be returned from a full narrowing search.
     *
     * Since this skips the retrieval of data, it is faster than a full
     * {@link narrowingSearch()}.
     *
     * @param array $narrower
     *  See the description in {@link narrowingSearch()}.
     * @return int
     *  Count of documents. An empty narrower will result in a return value of 0.
     */
    public function narrowingSearchCount($narrower) {
      if(empty($narrower) || !is_array($narrower)) return 0;
      
      $termDocs = $this->narrowingSearchGetDocs($narrower);
      return count($termDocs);
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
      // The point here is to make the search faster and more efficient than
      // the built-in find() method in Zend, which incurs the weight of the 
      // scoring system and other subsystems. This lib does not need scoring and 
      // weighting.
      // This can probably be more efficient.
        
        if(empty($narrower) || !is_array($narrower)) return false; // short-circuit if narrower is empty.
        
        $termDocs = $this->narrowingSearchGetDocs($narrower);
        
        $docs = array();
        foreach ($termDocs as $td) {
          // Deleted docs will still show in the index.
          if (!$this->repo->isDeleted($td)) {
            $docs[] = LuceneDocumentConverter::luceneToPilaster($this->repo->getDocument($td));
          }
        }
        return $docs;
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
            throw new Exception("Cannot export to $path: Unwritable or not a directory.");
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
          throw new Exception(
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
 * Utility class to convert Pilaster data structures to Lucene documents and back again.
 * 
 * Lucene (aka Zend Search) needs to have documents prepared for insertion into
 * the index. The method of preparation includes storing data inside of a 
 * {@link Zend_Search_Lucene_Document} object. The methods in this class handle
 * converting back and forth between the Pilaster data structures and the 
 * Lucene Document format.
 * @package Pilaster
 * @subpackage LuceneDriver
 */
class LuceneDocumentConverter {
    
    /**
     * Field name of the document ID.
     */
    const docid_field_name = 'id';
    /**
     * Field name of the data storage item.
     */
    const pristine_field_name = '__pristine';
    
    /**
     * Convert a Pilaster Document to a Lucene Document.
     * A Lucene document is a pre-indexed Lucene-specific document description.
     * 
     * @param array $data
     *  An associative array of document data.
     * @return Zend_Search_Lucene_Document 
     *  A document prepared for indexing.
     */
    function pilasterToLucene($data) {
        $ldoc = new Zend_Search_Lucene_Document();
        
        if (isset($data[self::pristine_field_name])) {
          throw new Exception('Illegal field name: ' . $data[self::pristine_field_name]);
        }
        
        foreach ($data as $k => $v) {
          
          // Skip any keys that begin with double underscores.
          if (strpos($k, '__') === 0) continue;
          
          // Index arrays of values as Keywords.
          if(is_array($v)) {
            $buff = '';
            foreach($v as $vv) {
              if (is_scalar($vv)) {
                // Unlike real Lucene, Zend's implementation cannot store
                // multiple fields with the same name. This is an implementation
                // detail of Zend's version, which uses an array for storing field
                // data.
                //$ldoc->addField(Zend_Search_Lucene_Field::Text($k, $vv));
                $buff .= $vv . ' ';
              }
            }
            // Text is analyzed. This is a hack to support multi-value.
            $ldoc->addField(Zend_Search_Lucene_Field::unStored($k, $buff));
            $alt = '___' . $k;
            $ldoc->addField(new Zend_Search_Lucene_Field($alt, $buff, '', FALSE, TRUE, FALSE));
          }
          // Index single (scalar) values as Text fields.
          elseif (is_scalar($v)) {
              //$ldoc->addField(Zend_Search_Lucene_Field::keyword($k, $v));
              $ldoc->addField(Zend_Search_Lucene_Field::unStored($k, $v));
              //$ldoc->addField(new Zend_Search_Lucene_Field($k, $v, '', FALSE, TRUE, FALSE));
              $alt = '___' . $k;
              $ldoc->addField(new Zend_Search_Lucene_Field($alt, $v, '', FALSE, TRUE, FALSE));
          }
        }
        
        // The entire document is serialized and stored. We do this so that we can
        // reconstruct the exact document later.
        $ldoc->addField(Zend_Search_Lucene_Field::keyword(
            self::pristine_field_name, 
            serialize($data)
        ));
                
        return $ldoc;
    }
    
    /**
     * Convert a Lucene document back to its original data structure.
     *
     * @param Zend_Search_Lucene_Document $lucene_document
     *  The Lucene document.
     * @return mixed
     *  The deserialized data structure.
     */
    function luceneToPilaster(Zend_Search_Lucene_Document $lucene_document) {
        $data = $lucene_document->getFieldValue(self::pristine_field_name);
        return empty($data) ? $data : unserialize($data);
    }
}