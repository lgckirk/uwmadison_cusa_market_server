<?php
# abstraction of a market product
# @See ProductImageFactory.php
# @author: Gaochang Li
date_default_timezone_set('UTC');
class Product {

    # constants defining product type
    const PTYPE_OTHER = 1;
    const PTYPE_FURNITURE = 2;
    const PTYPE_ELECTRONIC = 3;
    const PTYPE_ACADEMIC = 4;
    const PTYPE_CLOTHING = 5;
    const PTYPE_HOUSING = 6;
    const PTYPE_TRANSPORTATION = 7;
    const PTYPE_MAKEUP = 8;

    # constants defining product status
    const PSTATUS_ACTIVE = 7;
    const PSTATUS_EXPIRED = 8;
    const PSTATUS_ENDED = 9;

    # class variables
    private $ProductId = NULL;
    private $DB = NULL;
    private $RecordCache = array();


    /**
    * Class constructor.
    * @param int $ProductId Id of product.
    */
    function __construct($ProductId) {
        $DB = new Database();
        $DB->Query("SELECT * FROM Products WHERE ProductId = ".$ProductId);

        # check if product ID passed in is valid
        if ($DB->NumOfRows() < 1) {
            throw new Exception("ProductId: ".$ProductId." is not present in the DB");
        }
        else {
            # cache the product record
            $Entry = $DB->NextRow();
            foreach ($Entry as $Key => $Val) {
                # for type and status we want to convert it back to constants
                if ($Key == "ProductType") {
                    $Val = Product::GetTypeConstantFromDB($Val);
                }
                else if ($Key == "ProductStatus") {
                    $Val = Product::GetStatusConstantFromDB($Val);
                }
                $RecordCache[$Key] = $Val;
            }

            # cache product contact information
            $ContactInfo = array();
            $DB->Query("SELECT * FROM ProductContact WHERE ProductId = ".$ProductId);
            $Entry = $DB->NextRow();
            foreach ($Entry as $Key => $Val) {
                if ($Key == "ProductId") {
                    continue;
                }
                # NOTE $val could be null
                $ContactInfo[$Key] = $Val;
            }
            $RecordCache["ProductContact"] = $ContactInfo;

            # save parameters to class variables
            $DB->ClearResult();
            $this->DB = $DB;
            $this->ProductId = $ProductId;
            $this->RecordCache = $RecordCache;
        }
    }

/*----------------------------PUBLIC METHODS------------------------------------*/

    /**
    * Create a product with no information but ProductOwner,
    *       ProductStatus and DateCreated. Also initialize its ProductContact.
    * User can set parameters later.
    * @param int $ProductOwner User ID of the owner of this product.
    * @return An empty product just created.
    */
    public static function Create($ProductOwner) {
        $DB = new Database();
        $ProductOwner = intval($ProductOwner);

        # check if user exists to prevent isolated products
        $DB->Query("SELECT * FROM Users WHERE UserId = ".$ProductOwner);
        if ($DB->NumOfRows() < 1) {
            throw new InvalidArgumentException("Can not create product on user ID: "
                    .$ProductOwner." , user not found.");
        }

        # create empty product and get its ID
        $StatusCode = Product::GetStatusCodeForDB(self::PSTATUS_ACTIVE);
        $SQL = "INSERT INTO Products (ProductOwner, DateCreated, ProductStatus) "
                ."VALUES (".$ProductOwner.", NOW(), ".$StatusCode.");";
        $SQL .= "SELECT LAST_INSERT_ID() AS Id;";
        $DB->MultiQuery($SQL);
        $Id = $DB->NextRow()["Id"];

        # initialize its ProductContact
        $DB->Query("INSERT INTO ProductContact (ProductId) VALUES (".$Id.");");

        $DB->ClearResult();
        $DB->Close();
        return (new Product($Id));
    }

    /**
    * Delete this product
    */
    public function Delete() {
        throw new Exception("Unsupported operation: Product::Delete()");
    }

    /**
    * Expire this product
    */
    public function Expire() {
        $this->SetProductStatus(self::PSTATUS_EXPIRED);
    }

    /**
    * End this product
    */
    public function End() {
        $this->SetProductStatus(self::PSTATUS_ENDED);
    }

    public function Republish() {
        $this->SetProductStatus(self::PSTATUS_ACTIVE);
        $Timestamp = strtotime("+90 days");
        $DateArr = array("Year" => date("Y", $Timestamp),
                "Month" => date("m", $Timestamp),
                "Day" => date("d", $Timestamp),
                "Hour" => date("H", $Timestamp),
                "Minute" => date("i", $Timestamp),
                "Second" => date("s", $Timestamp));
        $this->DateExpire($DateArr);
        return 1;
    }

/* @{ */

    /**
    * Get product Id
    * @return Product ID
    */
    public function GetProductId() {
        return $this->ProductId;
    }

    /**
    * Get Product owner.
    * @return Product owner user ID.
    */
    public function GetProductOwner() {
        return $this->RecordCache["ProductOwner"];
    }


    /**
    * Get product status.
    * @return Product status constant.
    */
    public function GetProductStatus() {
        return $this->RecordCache["ProductStatus"];
    }

    /**
    * Get date created.
    * @NOTE Right now we do not provide a way to change DateCreated once it's set,
    *       might add one in the future.
    * @return Date of which this product is created, in the format: YYYY-MM-DD HH:mm:SS.
    */
    public function GetDateCreated() {
        return $this->RecordCache["DateCreated"];
    }

/* @} */

    /**
    * Get/Set Product Name.
    * @param string $NewVal New value to set (OPTIONAL).
    * @return ProductName, NULL if not present.
    */
    public function ProductName($NewVal = NULL) {
        if (!is_null($NewVal)) {
            # update DB
            $this->UpdateDBColumn("ProductName", $NewVal, TRUE);
            # update cache
            $this->RecordCache["ProductName"] = $NewVal;
            return $NewVal;
        }
        else {
            return $this->RecordCache["ProductName"];
        }
    }

    /**
    * Get/Set Product Condition (N/A for housing type).
    * @param string $NewVal New value to set (OPTIONAL).
    * @return ProductCondition, NULL if not present.
    */
    public function ProductCondition($NewVal = NULL) {
        if (!is_null($NewVal)) {
            # update DB
            $this->UpdateDBColumn("ProductCondition", $NewVal, TRUE);
            # update cache
            $this->RecordCache["ProductCondition"] = $NewVal;
            return $NewVal;
        }
        else {
            return $this->RecordCache["ProductCondition"];
        }
    }

    /**
    * Get/Set Product Price.
    * @param int $NewVal New value to set (OPTIONAL).
    * @return ProductPrice, NULL if not present.
    */
    public function ProductPrice($NewVal = NULL) {
        if (!is_null($NewVal)) {
            # update DB
            $this->UpdateDBColumn("ProductPrice", $NewVal);
            # update cache
            $this->RecordCache["ProductPrice"] = $NewVal;
            return $NewVal;
        }
        else {
            return $this->RecordCache["ProductPrice"];
        }
    }

    /**
    * Get/Set Product Description.
    * @param string $NewVal New value to set (OPTIONAL).
    * @return Product Description, NULL if not present.
    */
    public function ProductDescription($NewVal = NULL) {
        if (!is_null($NewVal)) {
            # update DB
            $this->UpdateDBColumn("ProductDescription", $NewVal, TRUE);
            # update cache
            $this->RecordCache["ProductDescription"] = $NewVal;
            return $NewVal;
        }
        else {
            return $this->RecordCache["ProductDescription"];
        }
    }

    /**
    * Get/Set Product Type.
    * @param const $NewVal New value to set (OPTIONAL).
    *       Needs to be the ProductType class constant.
    * @return Product type constant, NULL if not present.
    */
    public function ProductType($NewVal = NULL) {
        if (!is_null($NewVal)) {
            # get type DB value
            $Code = Product::GetTypeCodeForDB($NewVal);
            # update DB
            $this->UpdateDBColumn("ProductType", $Code);
            # update cache
            $this->RecordCache["ProductType"] = $NewVal;
            return $NewVal;
        }
        else {
            return $this->RecordCache["ProductType"];
        }
    }

    /**
    * Get/set contact information of this product.
    *       Keys: ContactName, ContactPhone, ContactEmail, ContactWechat
    *       All these parameters are optional.
    * @NOTE If a contact method already exists, it will be overriden.
    * @param array $NewVal Associative array of contact information.
    * @return Associative array of contact information. The result contains
    *       all contact methods, some of which can be NULL.
    */
    public function ProductContact($NewVal = NULL) {
        if (!is_null($NewVal)) {
            # param must be an array
            if (!is_array($NewVal)) {
                throw new Exception("Product::ProductContact(".
                        $NewVal."): parameter must be an array");
            }

            foreach ($NewVal as $Method => $Val) {
                # check if $Method is not one of the supported one
                $Supported = array("ContactName", "ContactPhone", "ContactEmail", "ContactWechat");
                if (!in_array($Method, $Supported)) {
                    throw new InvalidArgumentException("Contact parameter: ".$Method." is not supported.");
                }
                else {
                    # update DB
                    $SQL = "UPDATE ProductContact SET ".$Method
                            ." = '".$Val."' WHERE ProductId = ".$this->ProductId;
                    $this->DB->Query($SQL);
                }
            }

            # update cache
            $ContactInfo = array();
            $this->DB->Query("SELECT * FROM ProductContact WHERE ProductId = ".$this->ProductId);
            $Entry = $this->DB->NextRow();
            foreach ($Entry as $Key => $Val) {
                if ($Key == "ProductId") {
                    continue;
                }
                $ContactInfo[$Key] = $Val;
            }
            $this->RecordCache["ProductContact"] = $ContactInfo;
        }
        else {
            return $this->RecordCache["ProductContact"];
        }
    }

    /**
    * Get/Set date expire.
    * @param array $Date Associative array where key is time unit, value is
    *       the time value (string, 24 hour based). Hour, Minute, Second
    *       are optional. Year is 4 digits, other units should all be 2 digits.
    * @return A string of date expire with format YYYY-MM-DD HH:mm:SS
    */
    public function DateExpire($Date = NULL) {
        if (!is_null($Date)) {
            $Time = "YYYY-MM-DD HH:mm:SS";
            $Pattern = array("YYYY", "MM", "DD", "HH", "mm", "SS");
            $Replace = array($Date["Year"], $Date["Month"], $Date["Day"],
                    isset($Date["Hour"]) ? $Date["Hour"] : "00",
                    isset($Date["Minute"]) ? $Date["Minute"] : "00",
                    isset($Date["Second"]) ? $Date["Second"] : "00");

            # get date string
            $Time = str_replace($Pattern, $Replace, $Time);
            # update DB
            $this->UpdateDBColumn("DateExpire", $Time, TRUE);
            # update cache
            $this->RecordCache["DateExpire"] = $Time;
            return $Time;
        }
        else {
            return $this->RecordCache["DateExpire"];
        }
    }

    /**
    * Check if product exists.
    * @param int $ProductId ProductId.
    * @return bool TRUE if exists.
    */
    public static function ProductExists($ProductId) {
        $DB = new Database();
        $DB->Query("SELECT * FROM Products WHERE ProductId = ".$ProductId);
        if ($DB->NumOfRows() < 1) {
            $DB->Close();
            return FALSE;
        }
        else {
            $DB->Close();
            return TRUE;
        }
    }

    /**
    * Return an array representing this product for serialization.
    * @return An array containing ALL information about this product,
    *       including ProductId and ProductStatus. If a parameter does not have
    *       value, there will still be a key with a NULL value.
    * @NOTE Value ProductStatus and ProductType are class constants, not DB value.
    */
    public function ArrayForSerialize() {
        return $this->RecordCache;
    }

/*----------------------------PRIVATE METHODS-----------------------------------*/

    /**
    * Convenient methods for getting status code to be stored in DB.
    * @param int $Code Status code class constants.
    * @return Status code to be stored in DB or NULL if param is invalid.
    */
    public static function GetStatusCodeForDB($Code) {
        switch ($Code) {
            case self::PSTATUS_ACTIVE:
                return 1;
                break;

            case self::PSTATUS_EXPIRED:
                return 2;
                break;

            case self::PSTATUS_ENDED:
                return 3;
                break;

            default:
                return NULL;
                break;
        }
    }

    /**
    * Convenient methods for getting product type code to be stored in DB.
    * @param int $Code Type code class constants.
    * @return Product type code to be stored in DB or NULL if param is invalid.
    */
    public static function GetTypeCodeForDB($Code) {
        switch ($Code) {
            case self::PTYPE_OTHER:
                return 1;
                break;

            case self::PTYPE_FURNITURE:
                return 2;
                break;

            case self::PTYPE_ELECTRONIC:
                return 3;
                break;

            case self::PTYPE_ACADEMIC:
                return 4;
                break;

            case self::PTYPE_CLOTHING:
                return 5;
                break;

            case self::PTYPE_HOUSING:
                return 6;
                break;

            case self::PTYPE_TRANSPORTATION:
                return 7;
                break;

            case self::PTYPE_MAKEUP:
                return 8;
                break;

            default:
                return NULL;
                break;
        }
    }

    public static function GetTypeConstantFromDB($Code) {
        switch ($Code) {
            case 1:
                return self::PTYPE_OTHER;
                break;

            case 2:
                return self::PTYPE_FURNITURE;
                break;

            case 3:
                return self::PTYPE_ELECTRONIC;
                break;

            case 4:
                return self::PTYPE_ACADEMIC;
                break;

            case 5:
                return self::PTYPE_CLOTHING;
                break;

            case 6:
                return self::PTYPE_HOUSING;
                break;

            case 7:
                return self::PTYPE_TRANSPORTATION;
                break;

            case 8:
                return self::PTYPE_MAKEUP;
                break;

            default:
                return NULL;
                break;
        }
    }

    public static function GetStatusConstantFromDB($Code) {
        switch ($Code) {
            case 1:
                return self::PSTATUS_ACTIVE;
                break;

            case 2:
                return self::PSTATUS_EXPIRED;
                break;

            case 3:
                return self::PSTATUS_ENDED;
                break;

            default:
                return NULL;
                break;
        }
    }

    /**
    * Set Product status.
    * @param const $NewVal New value to set.
    * @return Product status constant.
    */
    private function SetProductStatus($NewVal) {
        # get code for db
        $Code = Product::GetStatusCodeForDB($NewVal);
        # update DB
        $this->UpdateDBColumn("ProductStatus", $Code);
        # update cache
        $this->RecordCache["ProductStatus"] = $NewVal;
        return $NewVal;
    }

    /**
    * Convenient method for updating values in the product table.
    * @param string $ColName Name of the column.
    * @param string $Val New value for the column.
    * @param bool $ValIsString whether $Val needs Apostrophe around it,
    *       (OPTIONAL, default to FALSE).
    */
    private function UpdateDBColumn($ColName, $Val, $ValIsString = FALSE) {
        # prepare string value
        if ($ValIsString) {
            $Val = "'".$Val."'";
        }
        # update
        $SQL = "UPDATE Products SET ".$ColName." = ".$Val." WHERE ProductId = ".$this->ProductId;
        $this->DB->Query($SQL);
    }

}

?>
