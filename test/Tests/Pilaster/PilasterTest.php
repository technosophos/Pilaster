<?php
/**
 * Tests for the QueryPath library.
 *
 * @package Tests
 * @author M Butcher <matt@aleph-null.tv>
 * @license The GNU Lesser GPL (LGPL) or an MIT-like license.
 */

/** */
require_once 'PHPUnit/Framework.php';
require_once 'src/Pilaster.php';

define('DB_PATH', 'test/db');
define('DB_NAME', 'pilaster_test')

/**
 * Tests for DOM Query. Primarily, this is focused on the DomQueryImpl
 * class which is exposed through the DomQuery interface and the dq() 
 * factory function.
 */
class PilasterTest extends PHPUnit_Framework_TestCase {
  
}