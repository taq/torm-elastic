<?php
/**
 * Elasticsearch integration configuration
 *
 * PHP version 5.5
 *
 * @category ElasticSearch
 * @package  TORM
 * @author   Eustáquio Rangel <taq@bluefish.com.br>
 * @license  http://www.gnu.org/copyleft/gpl.html GPL
 * @link     http://github.com/taq/torm
 */
namespace TORM;

/**
 * Main class
 *
 * PHP version 5.5
 *
 * @category ElasticSearch
 * @package  TORM
 * @author   Eustáquio Rangel <taq@bluefish.com.br>
 * @license  http://www.gnu.org/copyleft/gpl.html GPL
 * @link     http://github.com/taq/torm
 */
class ElasticSearchConfigs
{
    private static $_elastic_avoid_test_env = false;

    /**
     * Set if avoid updating a document on test enviroment
     *
     * @param boolean $avoid on test enviroment
     *
     * @return null
     */
    public function avoidOnTests($avoid)
    {
        self::$_elastic_avoid_test_env = $avoid;
    }

    /**
     * Check if is avoiding updating a document on test enviroment
     *
     * @return boolean updating or not
     */
    public function isAvoidingOnTests()
    {
        return self::$_elastic_avoid_test_env;
    }
}
?>
