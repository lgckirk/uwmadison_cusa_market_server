<?php

    # convenient class for accessing different products
    # @author: Gaochang Li

    final class ProductsExplorer {

        private function __construct() {}

        /**
        * Get all products sold by a user referred by a user ID.
        * @TODO: We should log these errors, currently there's no way of knowing
        *       that bad thing has happended.
        * @param int $UserId UserId.
        * @param const $Status ProductStatus constant of which the products
        *       to return should have (OPTIONAL, defaults to all products).
        * @return An array of product ID.
        */
        public static function GetProductsByUserId($UserId, $Status = Product::PSTATUS_ACTIVE) {
            $UserId = intval($UserId);
            $DB = new Database();

            # check if user Id is valid
            $DB->Query("SELECT * FROM Users WHERE UserId = ".$UserId);
            if ($DB->NumOfRows() < 1) {
                return array();
            }

            # if want all products
            if (is_null($Status)) {
                $SQL = "SELECT ProductId From Products WHERE ProductOwner = ".$UserId;
            }
            else {
                # check Status code validity
                if ($Status != Product::PSTATUS_ACTIVE &&
                        $Status != Product::PSTATUS_EXPIRED &&
                        $Status != Product::PSTATUS_ENDED)
                {
                    return array();
                }
                else {
                    $Code = Product::GetStatusCodeForDB($Status);
                    $SQL = "SELECT ProductId From Products WHERE ProductOwner = "
                            .$UserId. " AND ProductStatus = ".$Code;
                }
            }

            # main work happens here
            $DB->Query($SQL);
            $Ret = array();
            while ($Row = $DB->NextRow()) {
                $Ret[] = $Row["ProductId"];
            }
            $DB->Close();
            return $Ret;
        }

        /**
        * Get the products with a certain product type defined in Product class.
        * @param const $Type Product type constant.
        * @return An array of product IDs of all the products of the required type.
        */
        public static function GetProductsByType($Type) {
            $Code = Product::GetTypeCodeForDB($Type);
            $Ret = array();
            $DB = new Database();

            $StaCode = Product::GetStatusCodeForDB(Product::PSTATUS_ACTIVE);
            $SQL = "SELECT ProductId FROM Products WHERE ProductType = ".$Code." AND ProductStatus = ".$StaCode;
            $DB->Query($SQL);
            while ($Row = $DB->NextRow()) {
                $Ret[] = $Row["ProductId"];
            }
            $DB->Close();
            return $Ret;
        }

        /**
        * Get all active products ordered by productId.
        * @return An array of ALL product ID that are ACTIVE.
        */
        public static function GetAllActiveProducts() {
            $Code = Product::GetStatusCodeForDB(Product::PSTATUS_ACTIVE);
            $Ret = array();
            $DB = new Database();
            $DB->Query("SELECT ProductId FROM Products WHERE ProductStatus = "
                    .$Code." ORDER BY ProductId ASC");
            while ($Row = $DB->NextRow()) {
                $Ret[] = $Row["ProductId"];
            }
            $DB->Close();
            return $Ret;
        }
    }

?>
