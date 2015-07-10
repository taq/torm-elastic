<?php
class ElasticUser extends User {
    use TORM\ElasticSearch;
}
ElasticUser::setTableName("users");
ElasticUser::setElasticSearchIndex("torm");
?>
