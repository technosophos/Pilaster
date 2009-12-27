<?php
/**
 * Files describing a Pilaster Document.
 * 
 * Pilaster uses a specially formatted file to store in its database.
 * This library provides a tidy abstraction on top of that document.
 * @package Pilaster
 * @subpackage Document
 * @license GPL 3
 */

require_once "PilasterPHP/BasicPublishing.php";


define('PILASTER_DOC_STATE_PUBLISHED','Published');
define('PILASTER_DOC_STATE_LOCKED','Checked Out');
define('PILASTER_DOC_STATE_REMOVED', 'Archived');
define('PILASTER_DOC_STATE_UNLOCKED','Checked In');
    
    
// NetBeans screwed up the indentation here. Need to fix that... with something
// other than NetBeans.
    
/**
 * Pilaster Document.
 */    
class PilasterDocument {
    /**Namespace.*/
    const ns_caryatid = "http://aleph-null.tv/caryatid/document";
    /** Roote element name. */
    const name_root = 'doc';
    /** Metadata element name. */
    const name_metadata = "metadata";
    /** Data element name. */
    const name_data = "data";
    /** Templates element name. */
    const name_templates = 'templates';
    /** Template element name. */
    const name_template = 'template';
    
    
    //var $db;
    var $docID;
    var $dom;
    var $metadata;
    var $contentFormat;
    var $content;
    var $templates;
    var $defaultTemplate;
    var $publishing = null;
    var $errText;
    
    // FIXME: Need a constructor that builds document from XML.
    // See AbstractDB.php for examples.
    
    /*
    function __construct($filename) {
        $this->db = new AbstractDB();
        $this->docID = $filename;
        $rawfile = $this->db->getDocument($filename);
        if($rawfile === false) 
                return $this->_exception("$filename not found!");
        $this->dom = DOMDocument::loadXML($rawfile);
        if($this->dom === false)
                return $this->_exception("$filename not found!");

    }*/
    
    function __construct($xmlDocument = null) {
        if(empty($xmlDocument)) {
            // Initialize an empty document:
            $this->docID = $this->generateDocID();
            $this->contentFormat = 'data';
            $this->content = '';
            $this->templates = array();
            $this->defaultTemplate = '';
            $this->publishing = null;//new Publishing($this->docID);
            $this->metadata = array();
            $this->dom = new DOMDocument();
            
            // By default: No owner, unlocked.
            //$this->publishing->init();
            $this->initPublishing();
            
        } elseif(strlen($xmlDocument) < strlen('<?xml version="1.0" ?>')) {
            // Assume this is a legacy use:
            throw new Exception("Deprecated use of constructor!");
        } else {
            //print "DOCUMENT <pre>$xmlDocument</pre> END";
            $this->fromXML($xmlDocument);
            /*
            $this->dom = DOMDocument::loadXML($xmlDocument);
            $this->docID = $this->dom->documentElement->getAttribute('docid');
             * 
             */
        }
    }
    
    
    protected function fromXML($str) {
        $this->dom = DOMDocument::loadXML($str);
        $this->docID = $this->dom->documentElement->getAttribute('name');
        $this->metadata = array();
        
        // Get all of the metadata:
        $ele_root = $this->dom->documentElement;
        $metas = $ele_root->getElementsByTagname('metadata');
        foreach($metas as $meta) {
            $nodes = $meta->childNodes;
            foreach($nodes as $node) {
                if($node->nodeType == XML_ELEMENT_NODE) {
                    $nodeName = $node->tagName;
                    $nodeValue = '';
                    $textNodes = $node->childNodes;
                    foreach($textNodes as $textNode) {
                        $nodeType = $textNode->nodeType;
                        if($nodeType == XML_TEXT_NODE 
                              || $nodeType == XML_CDATA_SECTION_NODE) {
                            $nodeValue .= $textNode->textContent;
                        } elseif($nodeType == XML_ELEMENT_NODE 
                              && $node->tagName == 'value') {
                            if(!is_array($nodeValue)) {
                                $tstr = $nodeValue;
                                $nodeValue = array();
                                if(!empty($tstr)) $nodeValue[] = $tstr;
                            }
                            $nodeValue[] = $textNode->textContent;
                           
                        }
                    }
                    $this->metadata[$nodeName] = $nodeValue;
                }
            }
        }
        $this->extractContentFormat($this->dom);
        $this->extractContent($this->dom);
        $this->extractPublishing($this->dom);
        $this->extractTemplates($this->dom);
        $this->extractDefaultTemplate($this->dom);
        
    }

    /**
     * A convenience function for getMEtadataAssoc() designed
     * mainly for people who are afraid of the term 'metadata' -- 
     * which is a surprisingly large number!
     */
    function getProperties() {
        return $this->getMetadataAssoc();
    }

    /**
     * returns an associative array of element=>values for 
     * metadata.
     */
    function getMetadataAssoc() {
        return $this->metadata;
    }
    
    /**
     * Get a single metadatum.
     * @param string $name
     * @return string Value
     */
    function getMetadatum($name) {
        return $this->metadata[$name];
    }
    
    /**
     * Set metadata.
     * @param array $md_array Associative array of name/value pairs.
     */
    function setMetadata($md_array) {
        $this->metadata = $md_array;
    }
    
    function setDocId($docID) {
        $this->docID = $docID;
    }

    /**
     * Gets the file name.
     * 
     * This is the unique identifier for this document.
     * @return string Document ID
     */
    function getDocId() {
        return $this->docID;
    }

    /**
     * Returns the DOM for this document.
     * @return DOMDocument
     */
    function getDom() {
        
        $templatesArray = $this->getTemplateList()->getTemplates();
        
        //$dom = new DOMDocument('1.0', 'UTF-8');
        $dom = new DOMDocument();
        if(empty($this->docID)) $this->docID = $this->generateDocID();
        
        $n = $dom->createElementNS(PilasterDocument::ns_caryatid, 'caryatid:'.PilasterDocument::name_root);
        $meta = $dom->createElementNS(PilasterDocument::ns_caryatid, 
                'caryatid:'.PilasterDocument::name_metadata);
        $data = $dom->createElementNS(PilasterDocument::ns_caryatid, 
                'caryatid:'.PilasterDocument::name_data);

        $n->setAttribute('name',$this->docID);
        
        $dom->appendChild($n);
        $n->appendChild($meta);
        $n->appendChild($data);
        
        /*
        if(!empty($this->publishing)) {
            $this->metadata['publishing_state'] = $this->getPublishing()->getStatus();
            $this->metadata['publishing_owner'] = $this->getPublishing()->getOwner();
            //unset($this->publishing);
        }
         * 
         */
        
        $dt = null;
        $textNode = null;
        $tmpNode = null;
        foreach($this->metadata as $e=>$v) {
            //$dt = $db->getMetadataDatatype($e);
            //if($dt == false) $dt = 'string';
            // FIXME Don't know namespace of these b/c of lame DOM parser
            $tmpNode = $dom->createElement($e);
            $meta->appendChild($tmpNode);
                
            if(is_array($v)) {
                foreach($v as $value) {
                    $valueEle = $dom->createElement('value');
                    $valueEle->appendChild($dom->createTextNode(utf8_encode($value)));
                    $tmpNode->appendChild($valueEle);
                }
                //$textNode = $dom->createTextNode(utf8_encode($v));
                //$tmpNode->appendChild($textNode);
                $tmpNode->setAttribute('type', 'array');    
            } else {
                $textNode = $dom->createTextNode(utf8_encode($v));
                $tmpNode->appendChild($textNode);
                $tmpNode->setAttribute('type', 'string');
            }
        }
        
        // Templates:
        $this->templatesToDom($dom, $templatesArray);
        
        // Publishing:
        /*if(empty($this->publishing)) {
            $myuser = $_SERVER['PHP_AUTH_USER'];
            if(!isset($myuser) || strlen(trim($myuser)) == 0) 
                $myuser = "unkown_user";
            $pub = new Publishing($docID);
            $pub->init(CARYATID_DOC_STATE_LOCKED, $myuser);
            $ele_publishing = $pub->toDomElement($dom);
        } else {
            $ele_publishing = $this->getPublishing()->toDomElement($dom);
        }
        $n->appendChild($ele_publishing);
        
        */

        // Content:
        if(!isset($this->contentType) || $this->contentType=="") $this->contentType = "cdata";
        $data->setAttribute('format',$this->contentType);

        if($this->contentType == 'text') {
                // Just put it in doc (encode for UTF-8)
                $data->appendChild($dom->createTextNode(utf8_encode($this->content)));
        // FIXME: Would it be good to see if element is DOM already?
        } elseif($this->contentType == 'xml') {
                // Parse and clone the root element
                $conXml = new DOMDocument();
                $conXml->loadXML($This->content);
                $con_root = $conXml->documentElement;
                $con_clone = $dom->importNode($con_root, true); // Switched from cloneNode
                $data->appendChild($con_clone);
        }
        else {
                // Put it in as <![CDATA[]]>, _don't_ encode.
                $conCdata = $dom->createCDATASection($this->content);
                $data->appendChild($conCdata);
        }
        //print "Done getting DOM";
        
        $this->dom = $dom;
        return $dom;
    }
    
    function generateDocID() {
        $extra = rand(0, 999);
        return date("Ymd-Hi-") . "$extra.xml";
    }
    /**
     * Get the XML contents as a string.
     * @return string XML as a string.
     */
    function toXML($pretty=false) {
        $dom = $this->getDom();
        //print $dom->saveXML();
        if($pretty) $dom->formatOutput = true;
        return $dom->saveXML(/*$dom->documentElement*/);
    }

    /**
     * Returns the kind of content stored in here.
     * 
     * When a document is created, the content format of the document's body
     * can be set. This attempts to find out what type of body content is 
     * stored in this document.
     * 
     * Values are usually:
     * * text
     * * cdata (protected body section)
     * * xml
     * * empty
     * 
     * Note that if the content type was not entered initially, this 
     * will attempt to automatically determine the type.
     * @return string Body content type.
     */
    function getContentFormat() {
        return $this->contentFormat;
    }
    
    /**
     * Parse the relevant XML:
     * @return <type>
     */
    private function extractContentFormat($dom) {
        //if(!isset($this->contentFormat)) {
            $ele_root = $dom->documentElement;//$this->dom->documentElement;
            $data = $ele_root->getElementsByTagname('data');
            if($data === false || $data->length == 0) { 
                throw new Exception("Document has no content.");
            }
            $first = $data->item(0);
            $attrVal = $first->getAttribute('format');
            if(!isset($attrVal) || $attrVal == "") {
                // Figure out whether it's CDATA, XML, or text
                $kids = $first->childNodes;
                $hasXml = false;
                $hasText = false;
                $hasCDATA = false;
                $nodeType;
                foreach($kids as $kid) {
                    $nodeType = $kid->nodeType;
                    switch($nodeType) {
                        case XML_ELEMENT_NODE:
                                $hasXml = true;
                                break;
                        case XML_TEXT_NODE:
                                $hasText = true;
                                break;
                        case XML_CDATA_SECTION_NODE:
                                $hasCDATA = true;
                                break;
                    }
                }
                /*
                 * Basically, we are ignoring PIs and other atypical XML
                 * constructs. If you need them, get the DOM.
                 */
                if($hasXml) $this->contentFormat = 'xml';
                elseif($hasCDATA) $this->contentFormat = 'cdata';
                elseif($hasText) $this->contentFormat = 'text';
                else $this->contentFormat = 'empty';
            } else {
                $this->contentFormat = $attrVal;
            }
        //}
        //return $this->contentFormat;
    }
    
    function setContent($body, $contentFormat = 'cdata') {
        $this->contentFormat = $contentFormat;
        $this->content = $body;
    }

    /**
     * This grabs the body of the document.
     *
     * It does not store the contents internally, and must query the DOM tree
     * every time it is called, so it is best to only call once.
     * 
     * @return string Content
     */
    function getContent() {
        return $this->content;
    }
    
    private function extractContent($dom) {
        if(!isset($this->contentFormat)) $this->extractContentFormat($dom);
        //$data = $this->dom->get_elements_by_tagname('data');
        $ele_root = $dom->documentElement;
        $data = $ele_root->getElementsByTagname('data');
        if($data === false || $data->length == 0) { 
            throw new Exception("Document contains no data (extract content).");
        }
        
        $r = '';

        $first = $data->item(0);
        if ($this->contentFormat == 'text') {
            $kids = $first->childNodes;
            $content = '';
            foreach ($kids as $kid) {
                if($kid->nodeType == XML_TEXT_NODE) {
                        $content .= $kid->textContent;
                }
            }
            $this->content = $content;
        } elseif ($this->contentFormat == 'xml') {
            $newDoc = new DOMDocument('1.0');
            $newRoot = $newDoc->importNode($first,true);
            $newDoc->appendChild($newRoot);
            $this->content = $newDoc->saveXML();
        } else {
            // Assume that it's CDATA and text
            $kids = $first->childNodes;
            $content = '';
            foreach ($kids as $kid) {
                $kidType = $kid->nodeType;
                if($kidType == XML_TEXT_NODE || 
                        $kidType == XML_CDATA_SECTION_NODE) {
                   $content .= $kid->textContent;
                }
            }
            $this->content = $content;
        }
    }

/*    
}
// MPB: We need to split these up at some point, because we don't want Pilaster
// requiring the publishing and template code.
class CaryatidFile extends PilasterDocument {
*/
    
    /*
     * Returns a DocumentStatus object containing info on this file.
     *
     * The returned object does not write itself during changes ($commit is
     * false.)
     */
    /* Does this ever get used?
    function getDocumentStatus() {
            return new DocumentStatus($this, false); // Do not allow writes.
    }
     */
    
    /**
     * @deprecated
     * @param <type> $pub
     */
    function setPublishing(Publishing $pub) {
        //$this->publishing = $pub;
        $this->metadata['publishing_state'] = $pub->getStatus();
        $this->metadata['publishing_owner'] = $pub->getOwner();
        
    }

    /**
     * Returns a Publishing object. Use the Publishing->getModuleInfo()
     * method to determine what kind of Publishing module it is.
     * @deprecated 
     */
    function getPublishing() {
        //if(isset($this->publishing)) return $this->publishing;
        
        $state = array_key_exists('publishing_state', $this->metadata) 
          ? $this->metadata['publishing_state'] : PILASTER_DOC_STATE_UNLOCKED;
        $owner = array_key_exists('publishing_owner', $this->metadata) 
          ? $this->metadata['publishing_owner'] : 'Unknown';
        
        //return $this->publishing;
        $p = new Publishing($this->docID);
        $p->init($state, $owner);
    }
 
    /**
     * Configure default publishing.
     * Assumes Metadata array is available.
     */
    protected function initPublishing() {
        $this->metadata['publishing_state'] = PILASTER_DOC_STATE_UNLOCKED;
        $this->metadata['publishing_owner'] = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
    }
    
    /**
     * Extract publishing information from the document.
     * 
     * This extracts publishing information from the (deprecated) Publishing
     * XML construct.
     * 
     * 
     * @param <type> $dom
     */
    private function extractPublishing($dom) {
        $ele_root = $dom->documentElement;
        $arr_publishing = $ele_root->getElementsByTagname('publishing');
        if(isset($arr_publishing) || $arr_publishing->length > 0) {
            //throw new Exception("No publishing information.");
        
            $this->publishing = new Publishing($this->docID);
            $pub_first = $arr_publishing->item(0);
            if($pub_first) {
                $this->publishing->initFromXml($pub_first);


                $this->metadata['publishing_state'] = $this->publishing->getStatus();
                $this->metadata['publishing_owner'] = $this->publishing->getOwner();
            }
        }
    }
    
    /**
     * Get the publishing state
     * @return String Name of the current publishing state.
     */
    function getPublishingState() {
        if(!isset($this->metadata['publishing_state'])) 
          $this->metadata['publishing_state'] = PILASTER_DOC_STATE_UNLOCKED;
        return $this->metadata['publishing_state'];
    }
    
    /**
     * Return the current owner of this document.
     * Note that an owner can be different than an author.
     * @return string Name of publishing owner.
     */
    function getPublishingOwner() {
        if(!isset($this->metadata['publishing_onwer'])) 
          $this->metadata['publishing_owner'] = 'Unknown';
        return $this->metadata['publishing_owner'];
    }
    
    /**
     * Check a publishing state.
     * Returns true of the document's publishing state is the same as $state.
     * @param string $state
     * @return boolean True if the states match
     */
    function isInPublishingState($state) {
        return $this->getPublishingState() == $state;
    }

    /**
     * Returns true if this resource is locked.
     * @return boolean True if locked, false if in any other state.
     */
    function isLocked() {
        //$pub = $this->getPublishing();
        //return $pub->isLocked();
        return $this->getPublishingState() == PILASTER_DOC_STATE_LOCKED;
    }

    /**
     * Returns true if the file is locked and the user is the lock owner.
     * 
     * If $username is null, then this will check if the currently logged in user
     * (PHP_AUTH_USER)
     * is the owner.
     * 
     * Otherwise, returns false.
     * @return boolean True if the document is locked by the passed in user.
     */
    function isLockedByMe($username = null) {
      if(empty($sername)) {
        $username = $_SERVER['PHP_AUTH_USER'];
      }
      
      return $this->getPublishingState() == PILASTER_DOC_STATE_LOCKED 
        && $this->getPublishingOwner() == $username; 
        
      //$pub = $this->getPublishing();
      //if($pub->isLocked() && $pub->getOwner() == $user) return true;
      //return false;
    }

    /**
     * Returns true if document is published
     * @return True if this has been published.
     */
    function isPublished() {
        return $this->getPublishingState() == PILASTER_DOC_STATE_PUBLISHED;
    }

    /**
     * Returns true if the given document is removed (Archived)
     * @return boolean True if this has been removed.
     */
    function isRemoved() {
        return $this->getPublishingState() == PILASTER_DOC_STATE_REMOVED;
    }
    
    /**
     * Returns true if the document is in the unlocked (Checked In) state.
     * @return boolean True if unlocked (checked in)
     */
    function isUnlocked() {
        return $this->getPublishingState() == PILASTER_DOC_STATE_UNLOCKED;
    }
    
    /**
     * 
     * @param array $defaultTpl Default template id=>name
     * @param array $templates Array of id=>template_name entries
     */
    function setTemplates($defaultTpl, $templates = array()) {
        $this->defaultTemplate = $defaultTpl;
        $this->templates = $templates;
    }

    /**
     * Get a list of templates associated with this document.
     * 
     * Returns an associative array of id=>template_names for
     * the associated templates.
     * 
     * @return array Associative array of ID=>Template name strings.
     * @todo Migrate to CaryatidFile
     */
    function getTemplates () {
        return $this->templates;
        
    }
    private function extractTemplates($dom) {
        //if(!isset($this->templates)) {
            $this->templates = Array();
            $ele_root = $dom->documentElement;
            $temps = $ele_root->getElementsByTagname('templates');
            foreach ($temps as $temp) {
                $nodes = $temp->childNodes;
                foreach ($nodes as $node) {
                    if($node->nodeType == XML_ELEMENT_NODE) {
                        $temp_value = '';
                        if($node->hasAttribute('tid'))
                                $temp_id = $node->getAttribute('tid');
                        else $temp_id = time();
                        $textNodes = $node->childNodes;
                        foreach($textNodes as $textNode) {
                                $nodeType = $textNode->nodeType;
                                if($nodeType == XML_TEXT_NODE ||
                                        $nodeType == XML_CDATA_SECTION_NODE)
                                        $temp_value .= $textNode->textContent;
                        }
                        $this->templates[$temp_id] = $temp_value;

                        // If the defaultTemplate is not already set, then
                        // set it here. (Note: limited to _first_ template
                        // marked as default.
                        if(!isset($this->defaultTemplate) &&
                                $node->hasAttribute('default') &&
                                $node->getAttribute('default') == 'true')
                        {
                                $this->defaultTemplate = Array();
                                $this->defaultTemplate[$temp_id] = $temp_value;
                        }
                    }
                }
            }
        //}
        //echo count($this->templates);
        //return $this->templates;
    }

    /**
     * provides an OO wrapper for a list of templates.
     * @return TemplateList
     * @todo Migrate to CaryatidFile
     * @deprecated The TemplateList doesn't seem to work. Does this?
     */
    function getTemplateList() {
        // NOTE: The TemplateList needs to be generated for each request.
        //if(!isset($this->templates)) $this->getTemplates();
        //if(!isset($this->defaultTemplate)) $this->getDefaultTemplate();

        $tl = new TemplateList(Array()); // Create empty template list.
        $dflag = false;
        $dt = -1; // default template id

        if(!empty($this->defaultTemplate)) {
                list($dt,) = each($this->defaultTemplate);
        }

        // Create TemplateEntry objects:
        foreach ($this->templates as $id=>$val) {
                if($id == $dt) $dflag=true;
                $tl->appendTemplate($val, $id, $dflag);
        }

        return $tl;
    }
    

    /**
     * Returns the default template.
     *
     * For some random reason, this returns an array.
     * @return array Array with one element (success) or 0 elements (failure).
     * @todo Migrate to CaryatidFile
     */
    function getDefaultTemplate() {
        return $this->defaultTemplate;
    }
    
    private function extractDefaultTemplate($dom) {
        //if(!isset($this->defaultTemplate)) {
            $this->defaultTemplate = Array();
            $ele_root = $dom->documentElement;
            $temps = $ele_root->getElementsByTagname('templates');
            foreach ($temps as $temp) {
                $nodes = $temp->childNodes;
                foreach ($nodes as $node) {
                    if($node->nodeType == XML_ELEMENT_NODE) {
                        $temp_value = '';
                        if($node->hasAttribute('default') &&
                                $node->getAttribute('default') == 'true')
                        {
                            if($node->hasAttribute('tid'))
                                    $temp_id = $node->getAttribute('tid');
                            else $temp_id = time();
                            $textNodes = $node->childNodes;
                            foreach($textNodes as $textNode) {
                                    $nodeType = $textNode->nodeType;
                                    if($nodeType == XML_TEXT_NODE ||
                                            $nodeType == XML_CDATA_SECTION_NODE)
                                            $temp_value .= $textNode->textContent;
                            }
                            $this->defaultTemplate[$temp_id] = $temp_value;

                            // Limit to one return.
                            return $this->defaultTemplate;
                        }
                    }
                }
            }
        //}
        // This means either defaultTemplate is already set or there are no
        // default templates.
        //return $this->defaultTemplate;
    }
    
    /**
     * Marks the object (including DOM tree) for cleanup. 
     * Useful if you have a long-running
     * script that only needs this object for a short period of time.
     * 
     */
    function destroy() {
        unset($this->dom);
        unset($this->metadata);
        unset($this->templates);
        unset($this->contentFormat);
        unset($this->docID);
        //unset($this->db);
    }

    /**
     * return the XML.
     * 
     * This no longer proxies a connection to the database (as it did in 
     * Caryatid and Sinciput v. 1.0). it now just returns the document as
     * XML. A better method for this is toXML()
     *
     * @deprecated Use toXML()
     */
    function getRawFile() {
        return $this->toXML();//$this->db->getDocument($this->docID);
    }
    
    /**
     * Takes an array of TemplateEntry objects and inserts each into the 
     * XML as a child of the root node.
     * If there is metadata in the dom already, this will insert the template
     * section after the metadata.
     *
     * By default, this deletes all existing <template/> elements first. To
     * prevent this, $replace=false.
     *
     */
    private function templatesToDom($dom, $templates_array, $replace=true) {
        $root_ele = $dom->documentElement;

        // Create the <templates/> and <template/> elements
        // See if templates section already exists. If not, create one.
        $temps_array = 
                $root_ele->getElementsByTagname(PilasterDocument::name_templates);
        if(isset($temps_array) && $temps_array->length > 0) {
            $temps_ele = $temps_array->item(0);
            if($replace) {
                $children = $temps_ele->childNodes;
                foreach ($children as $child) {
                    $temps_ele->removeChild($child);
                }
            }
        } else {
            $temps_ele = $dom->createElementNS(PilasterDocument::ns_caryatid, 
                'caryatid:'.PilasterDocument::name_templates);
            $root_ele->appendChild($temps_ele);
        }
        $temp_ele = null; // Template element
        $txtNode  = null;  // Text node with template pathname.
        foreach ($templates_array as $template) {
            $temp_ele = $dom->createElementNS(PilasterDocument::ns_caryatid,
                'caryatid:'.PilasterDocument::name_template);
            $txtNode = $dom->createTextNode(
                utf8_encode($template->getFilename())
            );
            /*
             * Ideally, template id should be short and different than the
             * file name. For now, though...
             * UPDATE: almost every template that comes through should have
             * and ID already. Usually it is numberic.
             */
            $myId = $template->getTemplateId();
            if($myId == -1) {
                $myId = $template->getFilename();
                $template->setTemplateId($myId);
            }

            // If marked as default, set attribute.
            if($template->getDefault()) 
                $temp_ele->setAttribute('default','true');

            $temp_ele->setAttribute('tid', $myId);
            $temp_ele->appendChild($txtNode);
            $temps_ele->appendChild($temp_ele);
        }

        return;
    }

    /**
     * Simple exception emulation.
     * param $msg - error message
     * returns false (always)
     * @deprecated
     */
    function _exception($msg) {
        $this->errText = $msg;
        return false;
    }

    /**
     * returns error string
     * @deprecated
     */
    function getErrText() {
        // This is a hack...
        //if($this->errText == "") $this->errText = $this->db->getErrText();
        return $this->errText;
    }
}

// ========================
// Do we still need these?
// ========================

/**
 * Describes a Template
 */
class TemplateEntry {
	var $tid = -1;
	var $name, $default;

	/*
	 * Creates a new TemplateEntry
	 * $tid: template ID (-1 if unknown/undetermined)
	 * $name: name of template file.
	 * $default: boolean (false by default)
	 */
	function TemplateEntry($name, $tid=-1, $default=false) {
		$this->tid = $tid;
		$this->name = $name;
		$this->default = $default;
		return;
	}

	function getTemplateId() {
		return $this->tid;
	}

	function setTemplateId($tid) {
		$this->tid = $tid;
		return;
	}

	function getFilename() {
		return $this->name;
	}

	function setFilename($name) {
		$this->name = $name;
		return;
	}

	function getDefault() {
		return $this->default;
	}

	function setDefault($default=true) {
		$this->default = $default;
		return;
	}

}

/**
* A list of templates.
*/
class TemplateList {
	var $temps;

	/*
	 * Takse array of TemplateEntry objects.
	 */
	function TemplateList($template_array) {
		$this->temps = $template_array;
		return;
	}

	/*
	 * Generates an ID for an element in this list. This will find the highest
	 * numeric ID and add one. NOTE: Manually casts to int, so no float ids
	 * will be generated.
	 */
	function getNextId() {
		$hid = 0;
		foreach ($this->temps as $k=>$v) {
			// If id is numeric and greater than $hid, set it as current.
			if(is_numeric($k) && $k > $hid) $hid = (int)$k;
		}
		return ++$hid;
	}

	function checkId($id) {
		foreach ($this->temps as $k=>$v) {
			if($k == $id) return false;
		}
		return true;
	}

	function getTemplates() {
		return $this->temps;
	}

	/*
	 * Create and add a template based on $file and (optional) $id.
	 * If $id is not given or is -1, one will be generated with getNextId().
	 */
	function appendTemplate($file, $id=-1, $default=false) {
		if(!isset($id) || $id == -1) $id = $this->getNextId();
		elseif(!$this->checkId($id)) return false;
		$t = new TemplateEntry(basename($file), $id, $default);
		$this->temps[] = $t;
		return true;
	}

	/*
	 * Appends a TemplateEntry object.
	 */
	function appendTemplateEntry($temp_entry) {
		$id = $temp_entry->getTemplateId();
		if(!isset($id) || $id == -1) $id = $this->getnextId();
		$this->temps[] = $temp_entry;
		return true;
	}

	/*
	 * Renmoves a template from the list.
	 */
	function removeTemplateById($id) {
		if(array_key_exists($id, $this->temps)) {
			unset($this->temps[$id]);
			return true;
		} else return false;
	}

	/*
	 * Removes a template from list if file name matches.
	 */
	function removeTemplateByFilename($file) {
		// Allow removal of all keys w/ that $file
		$r = false;
		foreach($this->temps as $k=>$v) {
			if($v == $file) {
				unset($this->temps[$k]);
				$r = true;
			}
		}
		return $r;
	}
        

}
