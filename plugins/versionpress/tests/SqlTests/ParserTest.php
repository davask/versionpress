<?php

namespace VersionPress\Tests\SqlTests;

use PHPUnit_Framework_MockObject_Generator;
use PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount;
use PHPUnit_Framework_MockObject_Stub_Return;
use PHPUnit_Framework_TestCase;
use SqlParser\Parser;
use SqlParser\Statements\DeleteStatement;
use VersionPress\Database\DbSchemaInfo;
use VersionPress\Database\ParsedQueryData;
use VersionPress\Database\SqlQueryParser;

class ParserTest extends PHPUnit_Framework_TestCase {

    /**
     * @var DbSchemaInfo
     */
    private static $DbSchemaInfo;

    /** @var \wpdb $wpdbStub */
    private $wpdbStub;

    /** @var  SqlQueryParser */
    private $sqlParser;

    public static function setUpBeforeClass() {
        self::$DbSchemaInfo = new DbSchemaInfo(__DIR__ . '/../../src/Database/wordpress-schema.neon', 'wp_', PHP_INT_MAX);
    }

    public function setup() {
        $this->wpdbStub = $this->getMockBuilder('\wpdb')->disableOriginalConstructor()->getMock();
        $this->sqlParser = new SqlQueryParser(self::$DbSchemaInfo, $this->wpdbStub);
    }

    /**
     * @test
     * @dataProvider updateQueryParseTestDataProvider
     */
    public function whereClausesFromUpdate($query, $select, $data, $where) {

        $this->wpdbStub->expects(new PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount)->method("get_col");
        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);

        $this->assertEquals($where, $parsedQueryData->where);

    }

    /**
     * @test
     * @dataProvider updateQueryParseTestDataProvider
     */
    public function dataToSetFromUpdate($query, $select, $data) {

        $this->wpdbStub->expects(new PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount)->method("get_col");
        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);

        $this->assertEquals($data, $parsedQueryData->data);

    }


    /**
     * @test
     * @dataProvider updateQueryParseTestDataProvider
     */
    public function detectUseOfSqlFunctionsInUpdate($query, $select, $data, $where, $usesSqlFunctions) {

        $this->wpdbStub->expects(new PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount)->method("get_col");
        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);
        $this->assertEquals($usesSqlFunctions, $parsedQueryData->usesSqlFunctions);
    }


    /**
     * @test
     * @dataProvider updateQueryParseTestDataProvider
     */
    public function selectQueryFromUpdate($query, $select, $data, $where, $usesSqlFunctions, $ids) {

        $this->wpdbStub->expects(new PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount)->method("get_col")
            ->with($select)->will(new PHPUnit_Framework_MockObject_Stub_Return($ids));
        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);
        $this->assertEquals($select, $parsedQueryData->query);
    }

    /**
     * @test
     * @dataProvider insertQueryParseTestDataProvider
     */
    public function dataFromInsert($query, $data) {

        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);
        print_r($parsedQueryData);
        $this->assertEquals($data, $parsedQueryData->data);
    }

    /**
     * @test
     * @dataProvider insertQueryParseTestDataProvider
     */
    public function detectUseOfSqlFunctionsInInsert($query, $data, $usesSqlFunctions) {

        /** @var ParsedQueryData $parsedQueryData */
        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);
        $this->assertEquals($usesSqlFunctions, $parsedQueryData->usesSqlFunctions);
    }

    /**
     * @test
     * @dataProvider insertQueryParseTestDataProvider
     */
    public function detectNonStandardInsert($query, $data, $usesSqlFunctions, $queryType) {

        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);

        $this->assertEquals($queryType, $parsedQueryData->queryType);
    }

    /**
     * @test
     * @dataProvider deleteQueryParseTestDataProvider
     */
    public function selectQueryFromDelete($query, $select, $testIds) {
        $this->wpdbStub->expects(new PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount)->method("get_col")
            ->with($select)->will(new PHPUnit_Framework_MockObject_Stub_Return($testIds));
        $parsedQueryData = $this->sqlParser->parseQuery($query, self::$DbSchemaInfo, $this->wpdbStub);
        $this->assertEquals($select, $parsedQueryData->query);
    }


    public function deleteQueryParseTestDataProvider() {
        $testIds = [1, 3, 15];
        return array(
            array("DELETE o1 FROM `wp_options` AS o1 JOIN `wp_options` AS o2 ON o1.option_name=o2.option_name WHERE o2.option_id > o1.option_id",
                "SELECT option_name FROM `wp_options` AS o1 JOIN `wp_options` AS o2 ON o1.option_name=o2.option_name WHERE o2.option_id > o1.option_id",
                $testIds
            ),
            array("DELETE o1 FROM `wp_options` AS o1 JOIN `wp_options` AS o2 USING (`option_name`) WHERE o2.option_id > o1.option_id",
                "SELECT option_name FROM `wp_options` AS o1 JOIN `wp_options` AS o2 ON o1.option_name=o2.option_name WHERE o2.option_id > o1.option_id",
                $testIds
            ),
            array("DELETE FROM `wp_usermeta` WHERE meta_key IN ('key1', 'key2')",
                "SELECT umeta_id FROM `wp_usermeta` WHERE meta_key IN ('key1', 'key2')",
                $testIds
            ),
            array("DELETE FROM `wp_usermeta` WHERE meta_key = 'key1'",
                "SELECT umeta_id FROM `wp_usermeta` WHERE meta_key = 'key1'",
                $testIds
            )
        );
    }


    public function insertQueryParseTestDataProvider() {
        return array(
            array("INSERT INTO `wp_options` (`option_name`, `option_value`, `autoload`) VALUES ('name', 'value', 1) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)"
            ),
            array(
                "INSERT INTO `wp_term_relationships` (object_id, term_taxonomy_id, term_order) VALUES (10, 4, 5) , (20, 10, 15) ON DUPLICATE KEY UPDATE term_order = VALUES(term_order)",
                array(array("object_id" => "10", "term_taxonomy_id" => "4", "term_order" => "5"),
                    array("object_id" => "20", "term_taxonomy_id" => "10", "term_order" => "15")),
                1,
                ParsedQueryData::INSERT_QUERY
            ),
            array(
                "INSERT IGNORE INTO `wp_terms` (term_id, name, slug, term_group) VALUES (10, 'term name', 'term-name', 5) , (20, 'term another', 'term-another', 15)",
                array(array("term_id" => "10", "name" => "term name", "slug" => "term-name", "term_group" => "5"),
                    array("term_id" => "20", "name" => "term another", "slug" => "term-another", "term_group" => "15")),
            0,
                ParsedQueryData::INSERT_IGNORE_QUERY
        ),
            array(
                "INSERT INTO `wp_terms` (term_id, name, slug, term_group) VALUES (10, 'term name', 'term-name', 5)",
                array(array("term_id" => "10", "name" => "term name", "slug" => "term-name", "term_group" => "5")),
                0,
                ParsedQueryData::INSERT_QUERY
            ),
            array(
                "INSERT INTO `wp_terms` (term_id, name, slug, term_group) VALUES (10, 'term name', 'term-name', 5) , (20, 'term another', 'term-another', 15)",
                array(array("term_id" => "10", "name" => "term name", "slug" => "term-name", "term_group" => "5"),
                    array("term_id" => "20", "name" => "term another", "slug" => "term-another", "term_group" => "15")),
                0,
                ParsedQueryData::INSERT_QUERY
            ),
            array(
                "INSERT INTO `wp_terms` (term_id, date) VALUES (10, NOW())",
                array(array("term_id" => "10", "date" => "NOW")),
                1,
                ParsedQueryData::INSERT_QUERY
            )

        );
    }

    public function updateQueryParseTestDataProvider() {
        $testIds = [1, 3, 15];
        return array(
            array(
                "UPDATE  `wp_posts` SET post_modified = NOW() WHERE post_author = 'B'",
                "SELECT ID FROM `wp_posts` WHERE post_author = 'B'",
                array("post_modified" => "NOW()"),
                array("post_author = 'B'"),
                1,
                $testIds
            ),
            array(
                "UPDATE `wp_options` SET option_value=REPLACE(option_value, 'wp-links/links-images/', 'wp-images/links/') WHERE option_name LIKE '%_' AND option_value LIKE '%s'",
                "SELECT option_name FROM `wp_options` WHERE option_name LIKE '%_' AND option_value LIKE '%s'",
                array("option_value" => ""),
                array("option_name LIKE '%_'", "option_value LIKE '%s'"),
                1,
                $testIds
            ),
            array(
                "UPDATE `wp_options` SET option_value=REPLACE(option_value, 'wp-links/links-images/', 'wp-images/links/'), option_abc = 'def' WHERE option_name LIKE '%A' AND option_value LIKE '%s'",
                "SELECT option_name FROM `wp_options` WHERE option_name LIKE '%A' AND option_value LIKE '%s'",
                array("option_value" => ""),
                array("option_name LIKE '%A'", "option_value LIKE '%s'"),
                1,
                $testIds
            ),
            array(
                "UPDATE `wp_posts` SET post_parent = '10', post_type='page' WHERE post_type = 'attachment' AND ID IN (" . join(',', $testIds) . ")",
                "SELECT ID FROM `wp_posts` WHERE post_type = 'attachment' AND ID IN (" . join(',', $testIds) . ")",
                array("post_parent" => "'10'", "post_type" => "'page'"),
                array("post_type = 'attachment'", "ID IN (" . join(',', $testIds) . ")"),
                0,
                $testIds
            ),
            array(
                "UPDATE `wp_posts` SET post_date_gmt = DATE_ADD(post_date, INTERVAL '01:20' HOUR_MINUTE)",
                "SELECT ID FROM `wp_posts` WHERE 1=1",
                array("post_date_gmt" => "DATE_ADD(post_date"),
                array("1=1"),
                1,
                $testIds
            ),
            array(
                "UPDATE  `wp_posts` SET post_author = 'A' WHERE post_author = 'B'",
                "SELECT ID FROM `wp_posts` WHERE post_author = 'B'",
                array("post_author" => "'A'"),
                array("post_author = 'B'"),
                0,
                $testIds
            )


        );
    }


}
