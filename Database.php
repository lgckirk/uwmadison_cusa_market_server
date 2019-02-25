<?php

/**
* This class is only a wrapper class of mysqli object.
* @author: Gaochang Li
* TODO: create a user-defined exception rathar than the generic one.
*/
class Database {

    # static vars (these are the default ones)

    # a DB connection
    private $DB = NULL;
    # this should be a mysql_result object or NULL
    private $QueryResult = NULL;

    /**
    * Class constructor, initiate a new connection.
    */
    function __construct() {
        # establish connection to mysql
        $this->DB = new mysqli($_SERVER['RDS_HOSTNAME'], $_SERVER['RDS_USERNAME'], $_SERVER['RDS_PASSWORD'],
                $_SERVER['RDS_DB_NAME'], $_SERVER['RDS_PORT']);
        if ($this->DB->connect_error) {
            throw new Exception("Connection Failed");
        }
    }

    /**
    * Close this connection.
    */
    public function Close() {
        $this->DB->close();
    }

    /**
    * Perform a query.
    */
    public function Query($SQL) {
        $this->ClearResult();
        $Result = $this->DB->query($SQL);
        if ($Result === FALSE) {
            throw new Exception("Query: ".$SQL." not executed correctly.");
        }
        else {
            if ($Result !== TRUE) {
                $this->QueryResult = $Result;
            }
        }
    }
    public function GetLastId(){ return $this->DB->insert_id; }

    /**
    * Performs multi query.
    * NOTE: only the result of the last query is saved. This should be changed someday!!
    */
    public function MultiQuery($SQL) {
        $this->ClearResult();
        $Result = $this->DB->multi_query($SQL);

        if ($Result === FALSE) {
            throw new Exception("Query: ".$SQL." not executed correctly.");
        }
        else {
            while ($this->DB->more_results()) {
                # get a result
                $this->DB->next_result();
                $Set = $this->DB->store_result();

                # save the last one
                if (!$this->DB->more_results()) {
                    # we are not interested in boolean result
                    if (!is_bool($Set)) {
                        $this->QueryResult = $Set;
                    }
                }
                else if (!is_bool($Set)) {
                    $Set->free();
                }
            }
        }
    }

    /**
    * Return the number of rows returned from last query.
    * @return int Number of rows returned from last query.
    */
    public function NumOfRows() {
        if (is_null($this->QueryResult)) {
            throw new Exception("NumOfRows is called when QueryResult is NULL.");
        }
        return $this->QueryResult->num_rows;
    }

    /**
    * Fetch the result of last query in an associative array.
    * @return mixed Result of last query (array).
    */
    public function NextRow() {
        if (is_null($this->QueryResult)) {
            throw new Exception("NextRow is called when QueryResult is NULL.");
        }
        return $this->QueryResult->fetch_assoc();
    }

    /**
    * Clear QueryResult and free memory.
    */
    public function ClearResult() {
        if (!is_null($this->QueryResult)) {
            $this->QueryResult->free();
            $this->QueryResult = NULL;
        }
    }

/*--------------------------------STATIC METHODS--------------------------------*/

    /**
    * NOTE: This is just for temp use. Get rid of this later!!!
    * Set up Database parameters.
    * (This really should only be used once but I'll change it later)
    */
    public static function SetUp() {

        # tables
        $Tables = array();
        $Tables[] = "CREATE TABLE IF NOT EXISTS Users (
            UserId INT(7) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            OpenId VARCHAR(30) NOT NULL,
            Contact TINYINT(2) DEFAULT 0
        );";
        $Tables[] = "CREATE TABLE IF NOT EXISTS Products (
            ProductId INT(9) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ProductName VARCHAR(60),
            ProductCondition VARCHAR(60),
            ProductPrice INT(6),
            ProductDescription TEXT,
            ProductType TINYINT(3),
            ProductOwner INT(7) UNSIGNED NOT NULL,
            DateCreated DATETIME,
            DateExpire DATETIME,
            ProductStatus TINYINT(2),
            FULLTEXT(ProductName, ProductDescription) WITH PARSER ngram
        )ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;
        $Tables[] = "CREATE TABLE IF NOT EXISTS ProductContact (
            ProductId INT(9) UNSIGNED NOT NULL,
            ContactName VARCHAR(30),
            ContactPhone VARCHAR(20),
            ContactEmail VARCHAR(40),
            ContactWechat VARCHAR(40)
        );";
        $Tables[] = "CREATE TABLE IF NOT EXISTS ProductImages (
            ProductImageId INT(9) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ProductId INT(9) UNSIGNED NOT NULL,
            ImageExtension VARCHAR(10),
            Width INT(9) UNSIGNED,
            Height INT(9) UNSIGNED
        );";

        $Tables[] = "CREATE TABLE IF NOT EXISTS BannerImages (
            BannerImageId INT(9) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            RankId INT(9) UNSIGNED NOT NULL
        );";

        # establish connection
        $Connection = new mysqli($_SERVER['RDS_HOSTNAME'], $_SERVER['RDS_USERNAME'], $_SERVER['RDS_PASSWORD'],
                $_SERVER['RDS_DB_NAME'], $_SERVER['RDS_PORT']);

        # create tables
        foreach ($Tables as $Table) {
            $Connection->query($Table);
        }

        $Connection->close();
    }

}

?>
