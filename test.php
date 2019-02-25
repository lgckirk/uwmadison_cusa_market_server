<?php
require("./Database.php");
require("./Product.php");
header("Content-type: application/json");
$Pat = "兰蔻小黑瓶";
$Type = -1;
$Offset = 0;
$ListLength = 20;
function SearchProducts($Pat, $Offset = 0, $Type = -1, $ListLength = 20){
    $DB = new Database();
    $Code = Product::GetStatusCodeForDB(Product::PSTATUS_ACTIVE);
    // $ini = "SET character_set_client = utf8;SET character_set_connection = utf8;SET character_set_database = utf8;SET character_set_results = utf8;SET character_set_server = utf8;";
    // $DB->Query($ini);
    $TempQuery = "SELECT *, MATCH (ProductName,ProductDescription) AGAINST ('$Pat' IN NATURAL LANGUAGE MODE) AS score FROM Products WHERE MATCH (ProductName,ProductDescription) AGAINST ('$Pat' IN NATURAL LANGUAGE MODE)>=0.1 AND ProductStatus = $Code" ;
    if ($Type != -1)  $TempQuery .= " AND ProductType = $Type";
    // $TempQuery .= " ORDER BY score DESC LIMIT $Offset, $ListLength";
    // $TempQuery = "SELECT *, MATCH (ProductName,ProductDescription) AGAINST ('全新' IN NATURAL LANGUAGE MODE) AS score FROM Products ORDER BY score DESC";
    $TempQuery = "SELECT *, MATCH (ProductDescription, ProductName) AGAINST ('兰蔻' IN NATURAL LANGUAGE MODE) AS score FROM Products WHERE MATCH (ProductDescription, ProductName) AGAINST ('兰蔻' IN NATURAL LANGUAGE MODE)>0 AND ProductStatus = 1 ORDER BY score DESC;";
    $DB->Query($TempQuery);

    $Ret = array();
    while ($Row = $DB->NextRow()) {
        $Ret[] = $Row;
    }
    $DB->Close();
    echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
            "Products" => $Ret));    
}
SearchProducts($Pat, $Offset, $Type, $ListLength);
?>