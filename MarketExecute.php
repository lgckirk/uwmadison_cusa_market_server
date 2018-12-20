<?php

    # 开放接口，负责响应前端发来的请求
    # 前端HTTP请求应使用 application/x-www-form-urlencoded
    # 返回结果均为json
    #
    # 前端收到结果后需验证返回json的ErrorCode / ErrorMessage属性，
    #       并作相应的error handling
    # TODO: add check for input
    # TODO: use error code for error
    # TODO: assign exception code to exceptions throughout the program
    # TODO: allow caller to specify a limit on how many records to return
    # @author Gaochang Li

/*-----------------------------LOCAL FUNCTIONS----------------------------------*/

/**
* Check if param is set in POST. Echo error json if not set.
* @param string $Param Parameter name.
* @return TRUE on success, FALSE otherwise.
*/
function CheckPostParam($Param) {
    if (!isset($_POST[$Param])) {
        echo json_encode(array("ErrorCode" => ERROR_PARAMNOTSET,
                "ErrorMessage" => "Post parameter "
                .$Param." for 'Action=".$_POST["Action"]."' is not set"));
        return FALSE;
    }
    return TRUE;
}


function CheckImageParam($File){
    if (!is_uploaded_file($_FILES[$File]['tmp_name']) ||
        $_FILES[$File]["error"] != UPLOAD_ERR_OK)
    {
        $Msg = "File is not received";
        if (is_uploaded_file($_FILES[$File]['tmp_name'])) {
            $Msg .= " with a error number: ".$_FILES[$File]["error"];
        }
        throw new Exception($Msg);
    }

    # check if this image is acutally an image
    if (getimagesize($_FILES[$File]["tmp_name"]) === FALSE) {
        throw new Exception("File passed in is not a image");
    }
}

$TmpFolder = "./tmp/";

function GetPath($File){
    global $TmpFolder;
    $FileSysIte = new FilesystemIterator($TmpFolder, FilesystemIterator::SKIP_DOTS);

    $ImageExtension = pathinfo($_FILES[$File]["name"], PATHINFO_EXTENSION);
    $TmpPath = $TmpFolder.iterator_count($FileSysIte).".".$ImageExtension;

    if (!move_uploaded_file($_FILES[$File]["tmp_name"], $TmpPath)) {
        return FALSE;
    }

    return array(
        "Extension" => $ImageExtension,
        "TmpPath" => $TmpPath
    );
}

function Upload($TmpPath, $Identifier){
    $Client = Aws\S3\S3Client::factory(array(
        "region" => "us-east-2",
        "signature" => "v4"
    ));

    $Client->putObject(array(
        'Bucket' => "cusa-market-mnp",
        'Key'    => "/resources/images/$Identifier",
        'Body'   => fopen($TmpPath, 'r+b')
    ));
}

/**
* Retrieve image files from HTTP request (type: multipart/form-data),
*       and store them on server under directory:
*       ./resources/images/[product id]/index(1,2,3 ...)
* @NOTE In case additional images are added, this function will NOT
*       override existing image files.
* @param int $ProductId ProductId.
* @return TRUE on success, FALSE otherwise.
*/
function FetchProductImages($ProductId) {
    # check if files have been successfully uploaded
    CheckImageParam("ProductImage");

    # TODO: add a check for image size.

    # store the image as tmp file
    $ImageInfo = GetPath("ProductImage");
    $ImageExtension = $ImageInfo["Extension"];
    $TmpPath = $ImageInfo["TmpPath"];

    # update database
    $DB = new Database();
    $DB->MultiQuery("INSERT INTO ProductImages (ProductId, ImageExtension)"
            ." VALUES(".$ProductId.", '".$ImageExtension."');"
            ."SELECT LAST_INSERT_ID() AS Id;");
    $ImageId = $DB->NextRow()["Id"];

    # upload the image onto S3
    try
    {
        Upload($TmpPath, "$ProductId/$ImageId.$ImageExtension");
    }
    # if we fail, delete the record and bail out
    catch (Exception $e)
    {
        $DB->Query("DELETE FROM ProductImages WHERE ProductImageId = ".$ImageId);
        return FALSE;
    }
    # no matter what, we clear out cache
    finally {
        unlink($TmpPath);
    }

    return TRUE;
}

/**
* Retrieve image files from HTTP request (type: multipart/form-data),
*       and store them on S3 under directory:
*       ./resources/images/Contacts/[user id].png
* @NOTE Original image will be overriden if multiple contact qr code is uploaded
* @TODO: Check whether the uploaded image is a valid qr code
* @TODO: img format conversion if incompatible
* @Discussion: whether should record contact info in database
* @param int $UserId UserId.
* @return TRUE on success, FALSE otherwise.
*/
function FetchContactImage($UserId){
    CheckImageParam("ContactImage");
    # store the image as tmp file
    $ImageInfo = GetPath("ContactImage");
    $TmpPath = $ImageInfo["TmpPath"];
    # Currently, whether a use has uploaded contact info is not recorded in database
    # upload the image onto S3
    try
    {
        # Regardless of the original file extension, we always use png
        # might need format conversion if the original file is of some incompatible img format
        Upload($TmpPath, "Contacts/$UserId.png");
    }
    # Bail out
    catch (Exception $e)
    {
        return FALSE;
    }
        # no matter what, we clear out cache
    finally {
        unlink($TmpPath);
    }

    return TRUE;
}

/**
* Get absolute urls of this product's images.
* @TODO: Should use S3 pre-signed URL instead of accessing bucket directly.
* @param int $ProductId ProductId.
* @return An array of image urls.
*/
function GetProductImageAbsoluteUrl($ProductId) {
    $DB = new Database();
    $DB->Query("SELECT * FROM ProductImages WHERE ProductId = ".$ProductId);

    if (!$DB->NumOfRows()) {
        $DB->Close();
        return array();
    }
    else {
        while ($Row = $DB->NextRow()) {
            $Ret[] = "https://s3.us-east-2.amazonaws.com/cusa-market-mnp/resources/images/"
                    .$ProductId."/".$Row["ProductImageId"].".".$Row["ImageExtension"];
        }
        return $Ret;
    }
}

/*----------------------------------MAIN----------------------------------------*/

# ErrorCode constants
define("ERROR_DEFAULT", 0);
define("OK", 1);
define("ERROR_ACTIONNOTSET", 100);
define("ERROR_ILLEGALACTIONVALUE", 110);
define("ERROR_PARAMNOTSET", 120);
define("ERROR_ILLEGALOPERATION", 200);
define("ERROR_CANNOTGETIMAGE", 300);

# set a global exception handler just in case!
function DefaultExceptionHandler($e) {
    echo json_encode(array("ErrorCode" => ERROR_DEFAULT,
            "ErrorMessage" => $e->getMessage()));
    exit(0);
}
set_exception_handler("DefaultExceptionHandler");

# disable warning
function DefaultErrorHandler($errno, $errstr) {}
set_error_handler("DefaultErrorHandler");

# include all files in the directory first
require("./Database.php");
require("./Product.php");
require("./ProductsExplorer.php");
require('./vendor/autoload.php');

# first we update product status (active -> expired)
$ActivePro = ProductsExplorer::GetAllActiveProducts();
foreach ($ActivePro as $ProId) {
    $Product = new Product($ProId);
    if (strtotime("now") - strtotime($Product->DateExpire())  >= 0) {
        $Product->Expire();
    }
}

# we output json to client
header("Content-type: application/json");

if (!isset($_POST["Action"])) {
    echo json_encode(array("ErrorCode" => ERROR_ACTIONNOTSET,
            "ErrorMessage" => "Action is not specified"));
}
else {
    # products query pagination offset and page size
    $StartId = isset($_POST["StartId"]) ? $_POST["StartId"] : -1;
    $ListLength = isset($_POST["ListLength"]) ? $_POST["ListLength"] : 20;

    switch ($_POST["Action"]) {
        case "GetAllProducts":
            $Array = [];
            $Products = ProductsExplorer::GetAllActiveProducts($StartId, $ListLength);
            foreach ($Products as $ProductId) {
                $Product = (new Product($ProductId))->ArrayForSerialize();
                $Product["ProductImages"] = GetProductImageAbsoluteUrl($ProductId);
                $Array[] = $Product;
            }
            echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
                    "Products" => $Array));
            break;

        case "GetProductsByType":
            if (!CheckPostParam("TypeId")) {
                return;
            }
            $TypeId = $_POST["TypeId"];
            $Array = array();
            $Products = ProductsExplorer::GetProductsByType($TypeId, $StartId, $ListLength);

            foreach ($Products as $ProductId) {
                $Product = (new Product($ProductId))->ArrayForSerialize();
                $Product["ProductImages"] = GetProductImageAbsoluteUrl($ProductId);
                $Array[] = $Product;
            }
            echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
                    "Products" => $Array));
            break;

        case "GetProductsByUserId":
            if (!CheckPostParam("UserId")) {
                return;
            }
            $UserId = intval($_POST["UserId"]);
            $Array = array();
            $Products = ProductsExplorer::GetProductsByUserId($UserId, $StartId, $ListLength);

            foreach ($Products as $ProductId) {
                $Product = (new Product($ProductId))->ArrayForSerialize();
                $Product["ProductImages"] = GetProductImageAbsoluteUrl($ProductId);
                $Array[] = $Product;
            }
            echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
                    "Products" => $Array));
            break;

        # @deprecated
        case "PostProduct":
            # check for necessary param
            if (!CheckPostParam("ProductOwner") || !CheckPostParam("ProductName")
                    || !CheckPostParam("ProductType"))
            {
                return;
            }

            # create new product and set basic information
            # TODO: need to check input
            $ProductOwner = intval($_POST["ProductOwner"]);
            $ProductName = $_POST["ProductName"];
            $ProductType = intval($_POST["ProductType"]);

            $NewPro = Product::Create($ProductOwner);
            $NewPro->ProductName($ProductName);
            $NewPro->ProductType($ProductType);

            # set optional information
            if (isset($_POST["ProductCondition"])) {
                $NewPro->ProductCondition($_POST["ProductCondition"]);
            }
            if (isset($_POST["ProductDescription"])) {
                $NewPro->ProductDescription($_POST["ProductDescription"]);
            }
            if (isset($_POST["ProductPrice"])) {
                $NewPro->ProductPrice($_POST["ProductPrice"]);
            }

            # set expire time
            $Timestamp = isset($_POST["DateExpire"]) ?
                    strtotime($_POST["DateExpire"]) : strtotime("+90 days");
            $DateArr = array("Year" => date("Y", $Timestamp),
                    "Month" => date("m", $Timestamp),
                    "Day" => date("d", $Timestamp),
                    "Hour" => date("H", $Timestamp),
                    "Minute" => date("i", $Timestamp),
                    "Second" => date("s", $Timestamp));
            $NewPro->DateExpire($DateArr);

            # set contact information
            $ContactInfo = array();
            $ParamName = array("ContactName", "ContactPhone",
                    "ContactEmail", "ContactWechat");
            foreach ($ParamName as $Param) {
                if (isset($_POST[$Param])) {
                    $ContactInfo[$Param] = $_POST[$Param];
                }
            }
            $NewPro->ProductContact($ContactInfo);

            echo json_encode(array("ErrorCode" => OK,
                    "ErrorMessage" => "Warning: this function is deprecated. ".
                    "Use PostProductWithImage for product posting.",
                    "ProductId" => $NewPro->GetProductId()));

            break;

        case "EndListing":
            if (!CheckPostParam("ProductId")) {
                return;
            }
            $Product = new Product(intval($_POST["ProductId"]));
            $Product->End();
            echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => ""));

            break;

        # @deprecated
        case "PostProductImages":
            if (!CheckPostParam("ProductId")) {
                return;
            }
            # form name for file should be "ProductImage"
            if (FetchProductImages(intval($_POST["ProductId"]))) {
                echo json_encode(array("ErrorCode" => OK,
                    "ErrorMessage" => ""));
            }
            else {
                echo json_encode(array("ErrorCode" => ERROR_CANNOTGETIMAGE,
                        "ErrorMessage" => "Image upload has failed"));
            }

            break;
        case "PostUserContact":
            if (!CheckPostParam("UserId")){
                return;
            }
            if (FetchContactImage(intval($_POST["UserId"]))) {
                echo json_encode(array("ErrorCode" => OK,
                    "ErrorMessage" => ""));   
            }else{
                echo json_encode(array("ErrorCode" => ERROR_CANNOTGETIMAGE,
                        "ErrorMessage" => "Image upload has failed"));
            }
            break;
        case "PostProductWithImage":
            # check for necessary param
            if (!CheckPostParam("ProductOwner") || !CheckPostParam("ProductName")
                    || !CheckPostParam("ProductType"))
            {
                return;
            }

            # create new product and set basic information
            # TODO: need to check input
            $ProductOwner = intval($_POST["ProductOwner"]);
            $ProductName = $_POST["ProductName"];
            $ProductType = intval($_POST["ProductType"]);

            $NewPro = Product::Create($ProductOwner);
            $NewPro->ProductName($ProductName);
            $NewPro->ProductType($ProductType);

            # set optional information
            if (isset($_POST["ProductCondition"])) {
                $NewPro->ProductCondition($_POST["ProductCondition"]);
            }
            if (isset($_POST["ProductDescription"])) {
                $NewPro->ProductDescription($_POST["ProductDescription"]);
            }
            if (isset($_POST["ProductPrice"])) {
                $NewPro->ProductPrice($_POST["ProductPrice"]);
            }

            # set expire time
            $Timestamp = isset($_POST["DateExpire"]) ?
                    strtotime($_POST["DateExpire"]) : strtotime("+90 days");
            $DateArr = array("Year" => date("Y", $Timestamp),
                    "Month" => date("m", $Timestamp),
                    "Day" => date("d", $Timestamp),
                    "Hour" => date("H", $Timestamp),
                    "Minute" => date("i", $Timestamp),
                    "Second" => date("s", $Timestamp));
            $NewPro->DateExpire($DateArr);

            # set contact information
            $ContactInfo = array();
            $ParamName = array("ContactName", "ContactPhone",
                    "ContactEmail", "ContactWechat");
            foreach ($ParamName as $Param) {
                if (isset($_POST[$Param])) {
                    $ContactInfo[$Param] = $_POST[$Param];
                }
            }
            $NewPro->ProductContact($ContactInfo);

            # post image
            if (!FetchProductImages($NewPro->GetProductId())) {
                echo json_encode(array("ErrorCode" => ERROR_CANNOTGETIMAGE,
                        "ErrorMessage" => "Image upload has failed",
                        "ProductId" => $NewPro->GetProductId()));
            }
            else {
                echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
                        "ProductId" => $NewPro->GetProductId()));
            }

            break;

        # @deprecated
        case "GetProductImages":
            if (!CheckPostParam("ProductId")) {
                return;
            }
            # form name for file should be "ProductImage"
            $Url = GetProductImageAbsoluteUrl(intval($_POST["ProductId"]));
            echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
                    "ProductImages" => $Url));

            break;

        default:
            echo json_encode(array("ErrorCode" => ERROR_ILLEGALACTIONVALUE,
                    "ErrorMessage" => "Action: '".$_POST["Action"]."' is not valid"));
            break;
    }
}

?>

