<?php
/**
 * Elasticsearch integration
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

trait ElasticSearch
{
    private static $_elastic_configs = array();
    private static $_elastic_client  = array();

    /**
     * Set the ElasticSearch index. This should be your application name or
     * something to define the group of types.
     *
     * @param string $name index
     *
     * @return null
     */
    public static function setElasticSearchIndex($name)
    {
        $cls = get_called_class();
        self::_validateElasticSearchConfig($cls);
        self::$_elastic_configs[$cls]["index"] = $name;
    }

    /**
     * Validate the ElastSearch configurations for the named class
     *
     * @param string $cls class name
     *
     * @return boolean already exists
     */
    private static function _validateElasticSearchConfig($cls)
    {
        if (array_key_exists($cls, self::$_elastic_configs)) {
            return true;
        }
        self::$_elastic_configs[$cls] = array();
        self::$_elastic_configs[$cls]["index"]       = "TORM";
        self::$_elastic_configs[$cls]["keys"]        = [];
        self::$_elastic_configs[$cls]["last_status"] = null;
        self::$_elastic_configs[$cls]["size"]        = 10;
        return false;
    }

    /**
     * Return the ElasticSearch index.
     *
     * @return string index
     */
    public static function getElasticSearchIndex()
    {
        $cls = get_called_class();
        self::_validateElasticSearchConfig($cls);
        $idx = self::$_elastic_configs[$cls]["index"];
        $env = getenv("TORM_ENV");
        $idx = preg_match('/^test$/i', $env) > 0 ? $idx."_test" : $idx;
        return $idx;
    }

    /**
     * Return the ElasticSearch type
     *
     * @return string type name
     */
    public static function getElasticSearchType()
    {
        $cls = get_called_class();
        return Util::decamelize(Inflections::pluralize($cls));
    }

    /**
     * Sets the ElasticSearch values for indexing
     * Can receive an empty value or null to indicate that all the attribute
     * values must be returned
     *
     * @param mixed $keys attribute names
     *
     * @return null
     */
    public static function setElasticSearchValues($keys)
    {
        $cls = get_called_class();
        self::$_elastic_configs[$cls]["keys"] = $keys;
    }

    /**
     * Return the ElasticSearch values for indexing
     *
     * @return mixed values
     */
    public function getElasticSearchValues()
    {
        $cls = get_called_class();
        self::_validateElasticSearchConfig($cls);
        $keys = self::$_elastic_configs[$cls]["keys"];

        if ($keys == null || sizeof($keys) < 1) {
            $keys = self::getColumns();
        }
        $vals = [];
        foreach ($keys as $key) {
            $vals[$key] = $this->get($key);
        }
        return $vals;
    }

    /**
     * Return the ElasticSearch id for indexing
     *
     * @return string id
     */
    public function getElasticSearchId()
    {
        return strval($this->get($this->getPK()));
    }

    /**
     * Return the last status registered
     *
     * @return mixed last status
     */
    public function getElasticSearchLastStatus()
    {
        $cls = get_called_class();
        return self::$_elastic_configs[$cls]["last_status"];
    }

    /**
     * Set the seach size limit
     *
     * @param int $size limit
     *
     * @return null/
     */
    public function setElasticSearchSize($size = 10)
    {
        $cls = get_called_class();
        self::_validateElasticSearchConfig($cls);
        self::$_elastic_configs[$cls]["size"] = $size;
    }

    /**
     * Return the search size limit
     *
     * @return int size
     */
    public function getElasticSearchSize()
    {
        $cls = get_called_class();
        self::_validateElasticSearchConfig($cls);
        return intval(self::$_elastic_configs[$cls]["size"]);
    }

    /**
     * Update the ElasticSearch index
     *
     * @return null
     */
    public function updateElasticSearch()
    {
        $cls             = get_called_class();
        $client          = self::getElasticSearchClient();
        $params          = [];
        $params["id"]    = $this->getElasticSearchId();
        $params["index"] = self::getElasticSearchIndex();
        $params["type"]  = self::getElasticSearchType();
        $params["body"]  = self::getElasticSearchValues();
        try {
            $rtn = $client->index($params);
        } catch (Exception $e) {
            $rtn = false;
        }

        self::$_elastic_configs[$cls]["last_status"] = $rtn;
        return $rtn;
    }

    /**
     * Raw search on ElasticSearch
     *
     * @param string $attr    attribute
     * @param string $value   value
     * @param string $options options array, or null
     *
     * @return mixed document
     */
    public static function elasticRawSearch($attr, $value, $options = null)
    {
        // check for type
        $type = "match";
        if ($options && array_key_exists("match", $options)) {
            $type = $options["match"];
        }

        // check for size
        $size = self::getElasticSearchSize();
        if ($options && array_key_exists("size", $options)) {
            $size = intval($options["size"]);
        }

        $client          = self::getElasticSearchClient();
        $params          = [];
        $params["index"] = self::getElasticSearchIndex();
        $params["type"]  = self::getElasticSearchType();
        $params["size"]  = $size;
        $params["body"]["query"][$type][$attr] = $value;
        return $client->search($params);
    }

    /**
     * Search
     *
     * @param string $attr    attribute
     * @param string $value   value
     * @param string $options options
     *
     * @return mixed document
     */
    public static function elasticSearch($attr, $value, $options = null)
    {
        $rtn  = self::elasticRawSearch($attr, $value, $options);
        $vals = [];
        foreach ($rtn["hits"]["hits"] as $row) {
            $row_val = [];
            $keys    = array_keys($row["_source"]);
            $row_val[self::getPK()] = $row["_id"];
            foreach ($keys as $key) {
                $row_val[$key] = $row["_source"][$key];
            }
            array_push($vals, $row_val);
        }
        return $vals;
    }

    /**
     * Delete 
     *
     * @return deleted
     */
    public function deleteElastic()
    {
        $cls             = get_called_class();
        $client          = self::getElasticSearchClient();
        $params          = [];
        $params["id"]    = $this->getElasticSearchId();
        $params["index"] = self::getElasticSearchIndex();
        $params["type"]  = self::getElasticSearchType();

        try {
            $rtn = $client->delete($params);
        } catch (\Exception $e) {
            $rtn = false;
        }

        self::$_elastic_configs[$cls]["last_status"] = $rtn;
        return $rtn;
    }

    /**
     * Return the ElasticSearch client
     *
     * @return mixed client
     */
    public static function getElasticSearchClient()
    {
        $cls = get_called_class();

        if (!in_array($cls, self::$_elastic_client) || self::$_elastic_client[$cls] == null) {
            self::$_elastic_client[$cls] = new \Elasticsearch\Client();
        }
        return self::$_elastic_client[$cls];
    }

    /**
     * Import collection
     *
     * @return null
     */
    public static function elasticImport()
    {
        $cls = get_called_class();
        foreach ($cls::all() as $obj) {
            $obj->updateElasticSearch();
        }
    }

    /**
     * After initialize 
     *
     * ** THIS METHOD MUST BE EXPLICIT CALLED ON A CLASS THAT IMPLEMENTS ITS OWN afterInitialize 
     * METHOD, AS THE LAST STATEMENT ON THAT METHOD **
     *
     * @return true
     */
    public function afterInitialize()
    {
        $cls = get_called_class();
        $cls::afterSave("updateElasticSearch");
    }

    /**
     * Return the document count
     *
     * @return int document count
     */
    public static function elasticCount()
    {
        $cls             = get_called_class();
        $client          = self::getElasticSearchClient();
        $params["index"] = self::getElasticSearchIndex();
        $params["type"]  = self::getElasticSearchType();
        $rtn             = $client->count($params);
        if (sizeof($rtn) > 0) {
            return intval($rtn["count"]);
        }
        return 0;
    }
}
?>
