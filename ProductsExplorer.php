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
        *       to return should have (OPTIONAL, defaults to only active products).
        *       Pass in NULL to get products of all status.
        * @param $StartId parameter used for pagination
        * @param $ListLength parameter used for pagination
        * @return An array of product ID.
        */
        public static function GetProductsByUserId($UserId, $Status = Product::PSTATUS_ACTIVE, $StartId, $ListLength) {
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
            return self::Pagination($Ret, $StartId, $ListLength);
        }

        /**
        * Get the products with a certain product type defined in Product class.
        * @param const $Type Product type constant.
        * @param $StartId parameter used for pagination
        * @param $ListLength parameter used for pagination
        * @return An array of product IDs of all the products of the required type.
        */
        public static function GetProductsByType($Type, $StartId, $ListLength) {
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
            return self::Pagination($Ret, $StartId, $ListLength);
        }

        /**
        * Get all active products ordered by productId.
        * @param $StartId parameter used for pagination
        * @param $ListLength parameter used for pagination
        * @return An array of ALL product ID that are ACTIVE.
        */
        public static function GetAllActiveProducts($StartId, $ListLength) {
            $Code = Product::GetStatusCodeForDB(Product::PSTATUS_ACTIVE);
            $Ret = array();
            $DB = new Database();
            $DB->Query("SELECT ProductId FROM Products WHERE ProductStatus = "
                    .$Code." ORDER BY ProductId ASC");
            while ($Row = $DB->NextRow()) {
                $Ret[] = $Row["ProductId"];
            }
            $DB->Close();
            return self::Pagination($Ret,$StartId, $ListLength);
        }

        /**
         * This method takes in a list of products and returns a sublist of given length starting from a specified
         * product in reverse order. If the ID from which the sublist starts is not found in the ProductList, a
         * sublist of newest 20 products would be returned. It is used to generate products for one page.
         * @param array $ProductList the entire list of products
         * @param int $StartId the ID of the product before the newest product in the list to be returned
         * @param int $ListLength the size of the sublist to be returned
         * @return a sublist of ProductList in reverse order.
         */
        public static function Pagination($ProductList, $StartId, $ListLength)
        {
            if($StartId <= 0){
                return array_slice(array_reverse($ProductList), 0, $ListLength);
            }

            $ProductList = array_reverse($ProductList);
            $Ret = array();
            $Found = False;
            foreach($ProductList as $ProductID){
                if($Found){
                    $Ret[] = $ProductID;
                    if(count($Ret) >= $ListLength){
                        return $Ret;
                    }
                }else if($StartId == $ProductID){
                    $Found = True;
                }
            }

            if(!$Found){
                return array_slice($ProductList, 0, $ListLength);
            }
        }
    }

?>
