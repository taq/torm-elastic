<?php
/**
 * ElasticSearch test
 *
 * PHP version 5.5
 *
 * @category Tests
 * @package  TORM
 * @author   Eustáquio Rangel <taq@bluefish.com.br>
 * @license  http://www.gnu.org/copyleft/gpl.html GPL
 * @link     http://github.com/taq/torm
 */
require_once "../vendor/autoload.php";
require_once "../models/user.php";
require_once "../models/elastic_user.php";

/**
 * ElasticSearch test main class 
 *
 * PHP version 5.5
 *
 * @category Tests
 * @package  TORM
 * @author   Eustáquio Rangel <taq@bluefish.com.br>
 * @license  http://www.gnu.org/copyleft/gpl.html GPL
 * @link     http://github.com/taq/torm
 */
class ElasticSearchTest extends PHPUnit_Framework_TestCase
{
    protected static $con  = null;
    protected static $user = null;

    /**
     * Run when initializing
     *
     * @return null
     */
    public static function setUpBeforeClass()
    {
        $file = realpath(dirname(__FILE__)."/../database/test.sqlite3");
        self::$con  = new PDO("sqlite:$file");
        // self::$con  = new PDO('mysql:host=localhost;dbname=torm',"torm","torm");

        TORM\Connection::setConnection(self::$con, "test");
        TORM\Connection::setEncoding("UTF-8");
        TORM\Connection::setDriver("sqlite");
        // TORM\Connection::setDriver("mysql");
        TORM\Factory::setFactoriesPath("./factories");
        TORM\Log::enable(false);
        self::_deleteAll();

        self::$user        = new ElasticUser();
        self::$user->id    = 1;
        self::$user->name  = "John Doe Jr.";
        self::$user->email = "jr@doe.com";
        self::$user->code  = "12345";
        self::$user->level = 1;
        self::$user->save();
        ElasticUser::refreshElastic();

        ElasticUser::setElasticSearchIndex("torm");
        ElasticUser::setElasticSearchValues(null);
    }

    /**
     * Run before each test
     *
     * @return null
     */
    public function setUp()
    {
        TORM\ElasticSearch::avoidElasticOnTests(false);
    }

    /**
     * Test the ElasticSearch index name
     *
     * @return null
     */
    public function testIndexName() 
    {
        $this->assertEquals("torm_test", ElasticUser::getElasticSearchIndex());
    }

    /**
     * Test the ElasticSearch type
     *
     * @return null
     */
    public function testType() 
    {
        $this->assertEquals("elastic_users", ElasticUser::getElasticSearchType());
    }

    /**
     * Test the ElasticSearch values
     *
     * @return null
     */
    public function testValues()
    {
        $values = self::$user->getElasticSearchValues();
        $this->assertEquals(7,              sizeof($values));
        $this->assertEquals("1",            $values["id"]);
        $this->assertEquals("John Doe Jr.", $values["name"]);
        $this->assertEquals("jr@doe.com",   $values["email"]);
        $this->assertEquals("1",            $values["level"]);
        $this->assertEquals("12345",        $values["code"]);
    }

    /**
     * Test the ElasticSearch custom values
     *
     * @return null
     */
    public function testCustomValues()
    {
        ElasticUser::setElasticSearchValues(["name"]);

        $values = self::$user->getElasticSearchValues();
        $this->assertEquals(1,              sizeof($values));
        $this->assertEquals("John Doe Jr.", $values["name"]);
    }

    /**
     * Test the ElasticSearch id
     *
     * @return null
     */
    public function testId()
    {
        $this->assertEquals(strval(self::$user->id), self::$user->getElasticSearchId());
    }

    /**
     * Test the ElasticSearch update method
     *
     * @return null
     */
    public function testUpdate()
    {
        ElasticUser::setElasticSearchValues(["name"]);
        $rtn = self::$user->getElasticSearchLastStatus();
        $this->assertNotNull($rtn);
        $this->assertTrue(sizeof($rtn) > 0);
    }

    /**
     * Test the raw search method
     *
     * @return null
     */
    public function testRawSearch()
    {
        $vals = ["john", "doe", "jr", "john doe", "doe jr"];

        foreach ($vals as $val) {
            $rtn = ElasticUser::elasticRawSearch("name", $val);
            $this->assertEquals("1", $rtn["hits"]["hits"][0]["_id"]);
            $this->assertEquals(self::$user->name, $rtn["hits"]["hits"][0]["_source"]["name"]);
        }
    }

    /**
     * Test the search method
     *
     * @return null
     */
    public function testSearch()
    {
        $vals = ["john", "doe", "jr", "john doe", "doe jr"];

        foreach ($vals as $val) {
            $rtn = ElasticUser::elasticSearch("name", $val);
            $this->assertEquals("1", $rtn[0]["id"]);
            $this->assertEquals(self::$user->name, $rtn[0]["name"]);
        }
    }

    /**
     * Test the search method with more than one result
     *
     * @return null
     */
    public function testMultipleSearch()
    {
        $other        = new ElasticUser();
        $other->name  = "John Doe Father";
        $other->email = "father@doe.com";
        $other->level = 1;
        $other->code  = "54321";
        $this->assertTrue($other->save());

        $vals = ["john", "doe", "jr", "john DOE", "DOE JR"];

        foreach ($vals as $val) {
            $rtns = ElasticUser::elasticSearch("name", $val);
            foreach ($rtns as $rtn) {
                $this->assertTrue(in_array($rtn["id"],   [self::$user->id,   $other->id]));
                $this->assertTrue(in_array($rtn["name"], [self::$user->name, $other->name]));
            }
        }

        $rtn = $other->deleteElastic();
        $this->assertNotNull($rtn);
        $this->assertTrue(sizeof($rtn) > 0);
        $this->assertTrue($other->destroy());
    }

    /**
     * Test the import method
     *
     * @return null
     */
    public function testImport() 
    {
        self::_deleteAll();
        $rtn = ElasticUser::elasticSearch("name", "john");
        $this->assertTrue(sizeof($rtn) == 0);

        ElasticUser::elasticImport();
        ElasticUser::refreshElastic();
    }

    /**
     * Test the document count
     *
     * @return null
     */
    public function testCount()
    {
        self::_deleteAll();
        self::$user->updateElasticSearch();
        ElasticUser::refreshElastic();
        $this->assertEquals(1, ElasticUser::elasticCount());
    }

    /**
     * Test if we can avoid updating when on test enviroment (which,in case, we
     * are here)
     *
     * @return null
     */
    public function testAvoidTest()
    {
        $this->assertNotNull(self::$user->updateElasticSearch());

        TORM\ElasticSearch::avoidElasticOnTests(true);
        $this->assertNull(self::$user->updateElasticSearch());
        $this->assertNull(self::$user->deleteElastic());
    }

    /**
     * Test if document is destroyed when the database record is destroyed
     *
     * @return null
     */
    public function testDestroy()
    {
        $other        = new ElasticUser();
        $other->name  = "John Doe Grandfather";
        $other->email = "grandfather@doe.com";
        $other->level = 1;
        $other->code  = "00000";
        $this->assertTrue($other->save());
        ElasticUser::refreshElastic();
        
        $rtn = ElasticUser::elasticSearch("name", "Grandfather");
        $this->assertTrue(sizeof($rtn) > 0);

        $this->assertTrue($other->destroy());
        ElasticUser::refreshElastic();
        
        $rtn = ElasticUser::elasticSearch("name", "Grandfather");
        $this->assertTrue(sizeof($rtn) < 1);
    }

    /**
     * Delete all documents
     *
     * @return null
     */
    private static function _deleteAll()
    {
        if (self::$user) {
            self::$user->deleteElastic();
        }
        foreach (ElasticUser::all() as $obj) {
            if ($obj) {
                $obj->deleteElastic();
            }
        }
        ElasticUser::refreshElastic();
    }
}
