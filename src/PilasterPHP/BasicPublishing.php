<?php
/*

The Caryatid CMS System
Matt Butcher <mbutcher@aleph-null.tv>
Copyright (C) 2003, 2004 Matt Butcher

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/
//if(!isset($basedir))
//	include_once('includes/settings.php');

//require_once("$basedir/classes/AbstractDB.php");
//require_once("$basedir/classes/CaryatidDigester.php");

// Define constants for publishing states
define('CARYATID_DOC_STATE_PUBLISHED','Published');
define('CARYATID_DOC_STATE_LOCKED','Checked Out');
define('CARYATID_DOC_STATE_REMOVED', 'Archived');
define('CARYATID_DOC_STATE_UNLOCKED','Checked In');

define('OLD_CARYATID_DOC_STATE_PUBLISHED','Published');
define('OLD_CARYATID_DOC_STATE_LOCKED','Locked');
define('OLD_CARYATID_DOC_STATE_REMOVED', 'Removed');
define('OLD_CARYATID_DOC_STATE_UNLOCKED','Unlocked');

/** Constant to identify this publishing type */
define('CARYATID_PUBLISHING_MODULE_ID', 'BasicPublishing v.2.1');
/** Older version. Still compatible formats. */
define('CARYATID_PUBLISHING_MODULE_ID_V2', 'BasicPublishing v.2');

/** Old Version. Incompatible and automatically upgraded by this object. */
define('CARYATID_PUBLISHING_MODULE_ID_V1', 'BasicPublishing v.1');

/**
 * This class provides a basic publishing object for Carytid Document state
 * awareness. This should faciliate elementary document status.
 *
 * I have chosen to extract this publishing code from the CaryatidFile 
 * description so that it will be possible to create multiple publishing
 * some more advanced than this.
 *
 *************************************************************************
 * UPDATE: I am moving this from metadata to its own section of the XML 
 * document. I do this so that publishing can be more robust and flexible.
 * it seems silly to clutter up metadata with this sort of info, and the
 * added structural flexibility of a dedicated publishing section would
 * be nice.
 *************************************************************************
 *
 * Like other metadata, <state/> is stored as an element in the <metadata/>
 * section of the document. In effect, that persists the data within the
 * object itself, which has both positives and negatives. For instance, if
 * an unpublished document is accidentally sent to a production server, it
 * will still note be displayed. However, denial of access to the document
 * itself, or even the independant index files, can prevent even publishing a 
 * document.
 *
 * At this point, I have opted to connect both editorial state and publish 
 * state as the same attribute -- e.g. it is not possible for a published
 * resource to be locked, as the act of locking will un-publish it. Version
 * tracking (my next major project after this) will make this a non-issue, and
 * it seems to be liveable in the short term.
 *
 * Document States:
 * - Published: Resource is published to public
 * - Checked Out (Locked): Resource is open only to Lock Owner
 * - Archived (Removed): Resource is unavailable
 * - Checked In (Unlocked): Resource is not published, but is available to editors.
 */
class Publishing {
    var $docId;
    var $state;
    //var $noState = false;
    //var $commit = false; 
    var $user;
    var $lastmod; // Timestamp for last mod.

    var $state_list = Array(
        CARYATID_DOC_STATE_LOCKED, 
        CARYATID_DOC_STATE_UNLOCKED, 
        CARYATID_DOC_STATE_REMOVED,
        CARYATID_DOC_STATE_PUBLISHED 
    );

    /**
     * Versions that can be upgraded.
     * @var array Array of string version IDs that this module knows how to upgrade.
     */
    var $can_upgrade = array(
        CARYATID_PUBLISHING_MODULE_ID_V2,
        CARYATID_PUBLISHING_MODULE_ID_V1,
    );

    /**
     * Construct an empty Publishing object.
     * 
     * Typically, building a Publishing object is accomplished in two stages:
     * <code>
     * // Construct
     * $pub = new Publishing($docID);
     * 
     * // Init
     * $pub->init(CARYATID_DOC_STATE_LOCKED, 'mbutcher');
     * </code>
     * 
     * Saved publishing states are loaded this way:
     * <code>
     * $pub = new Publishing($docID);
     * $pub->initFromXML($DOMElement);
     * </code>
     * The above attempts to retrieve publishing info from the DOM.
     */
    function __construct($docId) {
        $this->docId = $docId;
    }

    /**
     * Initializes object from an XML Element.
     * Takes a DOM Element -- should contain at least the
     * minimal information required for creating a Publishing object: 
     * <publishing/>, <status/>, <user/> , and publishing's attribute "module".
     */
    function initFromXML($ele_publishing) {
        $upgrade = false;
        $module = null;
        if($ele_publishing->hasAttribute('module')) {
            $module = $ele_publishing->getAttribute('module');
            if (CARYATID_PUBLISHING_MODULE_ID == $module) {
                $upgrade = false;
            } elseif(in_array($module, $this->can_upgrade)) {
                $upgrade = true;
                $ele_publishing->setAttribute('module',
                    CARYATID_PUBLISHING_MODULE_ID);
            } else {
                throw new Excetion(
                    "Version mismatch. Got $module, can handle "
                    . CARYATID_PUBLISHING_MODULE_ID);
            }
        }
        if($ele_publishing->hasAttribute('lastmod')) {
            $this->lastmod = $ele_publishing->getAttribute('lastmod');
        }

        $ele_user = $ele_publishing->getElementsByTagname('user');
        $ele_state = $ele_publishing->getElementsByTagname('state');

        if($ele_user->length  > 0) {
            $first = $ele_user->item(0);
            $kids = $first->childNodes;
            $txt = '';
            foreach ($kids as $kid) {
                if($kid->nodeType == XML_TEXT_NODE ||
                        $kid->nodeType == XML_CDATA_SECTION_NODE)
                $txt .= $kid->textContent;
            }
            $this->user = trim($txt);
        } else {
            $this->user = 'UNKNOWN';
            $this->setMod();
        }
        $txt = '';

        if(count($ele_state) == 0) {
            // Assume open
            $this->state = CARYATID_DOC_STATE_UNLOCKED;
            $this->setMod();
        } else {
            $ff = $ele_state->item(0);
            $kids = $ff->childNodes;
            foreach ($kids as $kid) {
              if($kid->nodeType == XML_TEXT_NODE ||
                    $kid->nodeType == XML_CDATA_SECTION_NODE)
                $txt .= $kid->textContent;
            }
            $mystate = trim($txt);
            // ADD VALIDITY TESTING!!!
            if($this->stateExists($mystate)) $this->state = $mystate;
            elseif ($upgrade) {
                //FINISH ME
                $this->state = $this->upgradeState($module, $mystate);
            } else {
                $this->state = CARYATID_DOC_STATE_UNLOCKED;
                $this->setMod();
            }
        }
        return true;
    }

    /**
     * Alternate initializer.
     * This is just the bare-bones init. This version always sets 
     * modification time to now. It is intended to be used when creating a 
     * new document, but it can be used at other times as well.
     * 
     * @param string $mystate Initial state of publishing.
     * @param string $user Initial user. If locked, this is the user who holds the lock.
     */
    function init($mystate = CARYATID_DOC_STATE_UNLOCKED, $user = '') {
        $this->user = $user;
        $this->setMod();

        if ($this->stateExists($mystate)) $this->state = $mystate;
        else $this->state = CARYATID_DOC_STATE_UNLOCKED;

        return true;
    }

    /**
     * Check to see if the XML representation of this object needs upgrading.
     * Publishing is upgrade-aware, and can upgrade XML objects. This 
     * simply tells a user if the object can be upgraded.
     * @param DOMElement $ele_publishing
     * @return string Human readible information.
     */
    function upgradeFromXml( $ele_publishing ) {
        if($ele_publishing->hasAttribute('module')) {
            $module = $ele_publishing->getAttribute('module');
            if (CARYATID_PUBLISHING_MODULE_ID == $module) {
                return "No Upgrade Needed";
            } elseif (in_array($module, $can_upgrade)) {
                return "Needs upgrade.";
            } else {
                return "Cannot upgrade from publishing module $module.";
            }
        }

    }

    /**
     * Returns the ID string for this module.
     * @return string The version string.
     */
    function getModuleInfo() {
            return CARYATID_PUBLISHING_MODULE_ID;
    }

    /**
     * Returns an UNADDED DOM element named "publishing".
     * Implementing classes MUST add the document to the DOM 
     * on their own.
     *  @param DOMDocument DOM to add elements to. Note that this generates
     * elements in this DOM, but does not append them. Implementing apps must
     * do the adding.
     * @return DOMElement A DOM element containing the object description.
     */
    function toDomElement($dom) {
        if(!isset($this->lastmod)) $this->setMod();
        if(!isset($this->user) || count(trim($this->user)) == 0)
                $this->user = "UNKNOWN";
        $ele_root = $dom->documentElement;
        $ele_publishing = $dom->createElement('publishing');
        $ele_name = $dom->createElement('user');
        $ele_state = $dom->createElement('state');
        $txt_name = $dom->createTextNode(utf8_encode($this->user));
        $txt_state = $dom->createTextNode($this->state);

        $ele_name->appendChild($txt_name);
        $ele_state->appendChild($txt_state);

        $ele_publishing->setAttribute('module', CARYATID_PUBLISHING_MODULE_ID);
        $ele_publishing->setAttribute('lastmod', $this->lastmod);

        $ele_publishing->appendChild($ele_name);
        $ele_publishing->appendChild($ele_state);

        return $ele_publishing;
    }

    /**
     * Get the state.
     * @return unknown Publishing state.
     */
    function getStatus() {
        return $this->state;
    }
    /**
     * Get the owner of this publishing info.
     * In the case where the item is locked or checked out, this is the owner
     * of the lock.
     * @return string Owner name
     */
    function getOwner() {
      return $this->user;
    }

    /** 
     * Returns true if the document is published.
     * @return boolean
     */
    function isPublished() {
        if($this->state == CARYATID_DOC_STATE_PUBLISHED) return true;
        return false;
    }
    /**
     * Returns true if this is locked or checked out.
     * @return boolean
     */
    function isLocked() {
            if($this->state == CARYATID_DOC_STATE_LOCKED) return true;
            return false;
    }

    /** Returns true if this is checked in or unlocked
     * 
     * @return boolean
     */
    function isUnlocked() {
            if($this->state == CARYATID_DOC_STATE_UNLOCKED) return true;
            return false;
    }
    /**
     * Returns true if this is a removed document that has not yet been 
     * deleted.
     * @return boolean
     */
    function isRemoved() {
            if($this->state == CARYATID_DOC_STATE_REMOVED) return true;
            return false;
    }

    /**
     * Sets the status. lock(), unlock(), publish() and remove() are
     * convenience functions for this method. They should be called instead
     * of this one.
     * @param string $mystate State -- usually one of the CARYATID_DIC_STATE constants.
     * @param string $user Name of associated user.
     * @return boolean True if successful.
     * @throws Exception if setting failed, or if lock was assigned to a null user.
     */
    function setStatus($mystate, $user) {
        if($mystate == CARYATID_DOC_STATE_LOCKED) {
            if(!isset($user) || count(trim($user)) == 0) {
                throw new Exception('Locking requires a user');
            }
        }
        $this->user = $user;

        if($this->stateExists($mystate)) $this->state = $mystate;
        else {
            throw new Exception("Unknown State: $mystate");
        }
        $this->setMod();
        return true;
    }

    /** 
     * Convenience function to lock the document.
     * @param String $user Name of user that is locking the docuemnt
     * @return void
     */
    function lock($user) {
        $this->setStatus(CARYATID_DOC_STATE_LOCKED, $user);
        return;
    }

    /**
     * Unlock a document.
     * @param string $user User doing the unlocking.
     */
    function unlock($user) {
        $this->setStatus(CARYATID_DOC_STATE_UNLOCKED, $user);
    }

    /**
     * Publish the document.
     * @param String $user User who is publishing.
     */
    function publish($user) {
        $this->setStatus(CARYATID_DOC_STATE_PUBLISHED, $user);
    }

    /**
     * Mark the document as removed (from publication/editing)
     * @param string $user User who is removing.
     */
    function remove($user) {
            $this->setStatus(CARYATID_DOC_STATE_REMOVED, $user);
    }

    /**
     * Returns true if this class recognizes the state object.
     * Each *Publishing module can define its own list of supported 
     * states, though locked, published, and removed are required.
     * @param string $state State name
     * @return boolean True if the state exists, false otherwse.
     */
    function stateExists($state) {
        foreach ($this->state_list as $s) {
            if($state == $s) return true;
        }
        return false;
    }

    /**
     * Upgrade an object to the current object model.
     * @param string $version
     * @param string $state
     * @return string
     */
    function upgradeState($version, $state) {
            $ret = null;
            if($version == CARYATID_PUBLISHING_MODULE_ID_V2) {
              return $state;  
            } elseif ($version == CARYATID_PUBLISHING_MODULE_ID_V1) {
                switch($state) {
                case OLD_CARYATID_DOC_STATE_UNLOCKED: 
                    $ret = CARYATID_DOC_STATE_UNLOCKED;
                    break;
                case OLD_CARYATID_DOC_STATE_LOCKED: 
                    $ret = CARYATID_DOC_STATE_LOCKED;
                    break;
                case OLD_CARYATID_DOC_STATE_PUBLISHED: 
                    $ret = CARYATID_DOC_STATE_PUBLISHED;
                    break;
                case OLD_CARYATID_DOC_STATE_REMOVED: 
                    $ret = CARYATID_DOC_STATE_REMOVED;
                    break;
                }
            } else return false;
            return $ret;
    }

    /**
     * Provides a list of supported states.
     * @return array Array of State strings.
     */
    function listStates() {
        return $this->state_list;
    }


    /**
     * set lastmod.
     */
    private function setMod() {
        $this->lastmod = time();
    }

}

/** 
 * Convenience methods for accessing publishing information.
 * 
 * Originally, this was a separate database instance. Now, this class just
 * provides convenience methods for getting info from the DocumentDB.
 * 
 * @Deprecated.
 */
class PublishingDB {
    var $db = null;
    
    
        function __construct(DocumentDB $db) {
            $this->db = $db;
        }

	/**
	 * Change the state of a document.
         * This actually does nothing. Originally, it JUST added an entry to 
         * the publishing index. But the index no longer exists.
         * 
         * @deprecated
	 */
	function updateState($docId, $state) {
            
            //throw new Exception("Invalid call to updateState");

            /*
		if($this->bdbf->hasKey($this->dbfile, $docId))
			$r = $this->bdbf->replace($this->dbfile, $docId, $state);
		else {
			$r = $this->bdbf->insert($this->dbfile, $docId, $state);
		}

		if($r === false) 
			return $this->_exception('PubDB Error: '.$this->bdbf->getErrText());

		return true;
            
             */
	}

	/**
         * Returns the state of the given document ID.
         * 
         * Originally, this queried a special index. Now it just searches the index.
         * This incurs the same overhead as simply retrieving the document in question.
         * 
	 * @param string $docID document ID
	 * @returns string state
	 */
	function getState($docID) {
            return $this->db->getDocument($docID)->getPublishing()->getStatus();
	}

	/**
         * Get publishing state info for all docs in an array.
	 * This gets publishing state info for all of the docs in $docID_arr.
 	 * Results are returned in an assoc array of id=>state
	 * @param array $docID_arr array of document ID strings.
	 * @param assoc array of id=>state string pairs.
         * @deprecated This does not give a performance improvement over simply retrieving all docs.
	 */
	function getStates($docID_arr) {
            $a = array();
            foreach($docID_arr as $id) {
                $a[$id] = $this->db->getDocument($id)->getPublishing()->getStatus();
            }
            return $a;
	}

	/**
	 * Takes an array of docIDs and returns an array of those that are in
	 * state $state.
	 * PublishingUtils offers convenience functions for this function.
	 * @param string $stat publishing state
	 * @param array $docID_array array of string document IDs
	 * @return array of string document ids (or false if an error occurs)
         * @deprecated
	 */
	function areInState($state, $docID_array) {
            $docs = $this->getStates($docID_array);
            $a = array();
            foreach($docs as $id=>$mystate) {
                if($mystate == $state) $a[] = $id;
            }
            return $id;
	}

	/**
         * Get all documents withe the given state.
	 * @return array Array of docIds.
	 */
	function getAllWithState($state) {
            $docs = $this->db->getAllDocuments();
            $a = array();
            foreach($docs as $doc) {
                if($doc->getPublishing()->getStatus() == $state) {
                  $a[] = $doc->getDocId();
                }
            }
            unset($docs); // Clean this up right away.
            return $a;
	}

	/**
         * Does nothing.
	 * Originally deleted a document from the index.
         * @deprecated
	 */
	function deleteDocId($docId) {
		if(!$this->bdbf->hasKey($this->dbfile, $docId))
			return $this->_exception("$docId does not exist in index.");
		$r = $this->bdbf->delete($this->dbfile, $docId);

		if($r === false)
			return $this->_exception($this->bdbf->getErrText());
		return $r;
	}

}

/**
 * Utilities for fetching resources based on publishing info.
 *
 * These utilities operate on the index itself (rather than on the XML 
 * file. This makes it much faster for multi-file processing. If you already
 * have the CaryatidFile object or a DOM, use the Publishing class instead.
 *
 * This class is basically optimized for portlet usage. Admin utils should
 * probably use the PublishingDB class.
 */
class PublishingUtils {
    var $db;

    function PublishingUtils(DocumentDB $db) {
        //$this->db = new AbstractDB();
        $this->db = new PublishingDB($db);
    }

    function getAllPublished() {
        return $this->getAllWithState(CARYATID_DOC_STATE_PUBLISHED);
    }

    function getAllRemoved() {
        return $this->getAllWithState(CARYATID_DOC_STATE_REMOVED);
    }

    function getAllUnlocked() {
        return $this->getAllWithState(CARYATID_DOC_STATE_UNLOCKED);
    }

    function getAllLocked() {
        return $this->getAllWithState(CARYATID_DOC_STATE_LOCKED);
    }

    /**
     * Takes an array of docIDs and returns those which are Published.
     * @param array $docID_arr array of string doc ids.
     * @returns array of doc IDs (or false if an error occurs)
     */
    function arePublished($docID_arr) {
        return $this->db->areInState(CARYATID_DOC_STATE_PUBLISHED, $docID_arr);
    }

    /*
     * Returns true if the file is published.
     */
     function isPublished($docID) {
        return ($this->db->getState($docID) == CARYATID_DOC_STATE_PUBLISHED);
     }

    /*
     * This and all of the getAll*() return an array of docIDs.
     */
    function getAllWithState($state) {
        return $db->getAllWithState($state);
    }
}