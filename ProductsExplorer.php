<?php

    # convenient class for accessing different products
    # @author: Gaochang Li

    final class ProductsExplorer
    {

        private function __construct()
        {
        }

        /**
         * Get all products sold by a user referred by a user ID.
         * @TODO: We should log these errors, currently there's no way of knowing
         *       that bad thing has happended.
         * @param int $UserId UserId.
         * @param const $Status ProductStatus constant of which the products
         *       to return should have (OPTIONAL, defaults to only active products).
         *       Pass in NULL to get products of all status.
         * @param $StartId parameter used for pagination
         * @param $ListLength parameter used for pagination
         * @return An array of product ID.
         */
        public static function GetProductsByUserId($UserId, $Status = Product::PSTATUS_ACTIVE, $StartId, $ListLength)
        {
            $UserId = intval($UserId);
            $DB = new Database();

            # check if user Id is valid
            $DB->Query("SELECT * FROM Users WHERE UserId = ".$UserId);
            if ($DB->NumOfRows() < 1) {
                return array();
            }

            # if want all products
            if (is_null($Status)) {
                $SQL = "SELECT ProductId From Products WHERE ProductOwner = " . $UserId;
            } else {
                # check Status code validity
                if ($Status != Product::PSTATUS_ACTIVE &&
                    $Status != Product::PSTATUS_EXPIRED &&
                    $Status != Product::PSTATUS_ENDED) {
                    return array();
                } else {
                    $Code = Product::GetStatusCodeForDB($Status);
                    $SQL = "SELECT ProductId From Products WHERE ProductOwner = "
                        . $UserId . " AND ProductStatus = " . $Code;
                }
            }

            # main work happens here
            $DB->Query(self::Pagination($SQL, $StartId, $ListLength));
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
         * @param $StartId parameter used for pagination
         * @param $ListLength parameter used for pagination
         * @return An array of product IDs of all the products of the required type.
         */
        public static function GetProductsByType($Type, $StartId, $ListLength)
        {
            $Code = Product::GetTypeCodeForDB($Type);
            $Ret = array();
            $DB = new Database();

            $StaCode = Product::GetStatusCodeForDB(Product::PSTATUS_ACTIVE);
            $SQL = self::Pagination("SELECT ProductId FROM Products WHERE ProductType = " . $Code .
                " AND ProductStatus = " . $StaCode, $StartId, $ListLength);

            $DB->Query($SQL);
            while ($Row = $DB->NextRow()) {
                $Ret[] = $Row["ProductId"];
            }
            $DB->Close();
            return $Ret;
        }

        /**
         * Get all active products ordered by productId.
         * @param $StartId parameter used for pagination
         * @param $ListLength parameter used for pagination
         * @return An array of ALL product ID that are ACTIVE.
         */
        public static function GetAllActiveProducts($StartId, $ListLength)
        {
            $Code = Product::GetStatusCodeForDB(Product::PSTATUS_ACTIVE);
            $Ret = array();
            $DB = new Database();
            $DB->Query(self::Pagination("SELECT ProductId FROM Products WHERE ProductStatus = "
                . $Code, $StartId, $ListLength));

            while ($Row = $DB->NextRow()) {
                $Ret[] = $Row["ProductId"];
            }
            $DB->Close();
            return $Ret;
        }

        /**
         * This method modifies the given query for the purpose of pagination. It takes in the original query,
         * change the query list to descending order by product ID, and takes only a specified amount of products
         * with ID smaller than (i.e. posted earlier than) a specified product.
         * @param string $Query the original query by default order.
         * @param int $StartId the ID of the specified product before the newest product to be returned
         * @param int $ListLength the size of the sublist to be returned
         * @return a new SQL query modified for purpose of pagination.
         */
        private static function Pagination($Query, $StartId = -1, $ListLength = -1)
        {
            $TempQuery = "SELECT * FROM (" . $Query . " ORDER BY ProductId DESC) AS temp";
            if ($StartId > 0)
                $TempQuery = $TempQuery . " WHERE ProductID < " . $StartId;
            if ($ListLength > 0)
                $TempQuery = $TempQuery . " LIMIT " . $ListLength;
            return $TempQuery;
        }
    }
?>
