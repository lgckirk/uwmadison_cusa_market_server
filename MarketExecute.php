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
    if (!is_uploaded_file($_FILES['ProductImage']['tmp_name']) ||
            $_FILES["ProductImage"]["error"] != UPLOAD_ERR_OK)
    {
        $Msg = "File is not received";
        if (is_uploaded_file($_FILES['ProductImage']['tmp_name'])) {
            $Msg .= " with a error number: ".$_FILES["ProductImage"]["error"];
        }
        throw new Exception($Msg);
    }

    # check if this image is acutally an image
    if (getimagesize($_FILES["ProductImage"]["tmp_name"]) === FALSE) {
        throw new Exception("File passed in is not a image");
    }

    # TODO: add a check for image size.

    # make product image folder (if there is one, use that one)
    $Path = "./resources/images/".$ProductId."/";
    if (!file_exists($Path)) {
        mkdir($Path);
    }

    # figure out name of file
    $ImageName = (string)ProductImageCount($ProductId);
    $ImageName .= ".".pathinfo($_FILES["ProductImage"]["name"], PATHINFO_EXTENSION);
    $Path .= $ImageName;

    # it should not be possible to have duplicate name
    if (file_exists($Path)) {
        throw new Exception("File: ".$Path." already exists!");
    }

    # save image files
    return move_uploaded_file($_FILES["ProductImage"]["tmp_name"], $Path);
}

/**
* Get the number of images this product has on server.
* @param int $ProductId ProductId.
* @return number of images this product has.
*/
function ProductImageCount($ProductId) {
    $Path = "./resources/images/".$ProductId."/";
    if (file_exists($Path)) {
        $FileSysIte = new FilesystemIterator($Path, FilesystemIterator::SKIP_DOTS);
        return iterator_count($FileSysIte);
    }
    else {
        return 0;
    }
}


/**
* Get absolute urls of this product's images.
* @TODO: It is bad to hard code url like this, fix it later.
* @param int $ProductId ProductId.
* @return An array of image urls.
*/
function GetProductImageAbsoluteUrl($ProductId) {
    if (!ProductImageCount($ProductId)) {
        return array();
    }
    else {
        $FileNames = array_diff(scandir("./resources/images/".$ProductId."/"),
                array('..', '.'));
    }
    $Ret = array();
    foreach ($FileNames as $Name) {
        $Ret[] = "https://gaochangli.com/resources/images/"
                .$ProductId."/".$Name;
    }
    return $Ret;
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
    switch ($_POST["Action"]) {
        case "GetAllProducts":
            echo json_encode(array("ErrorCode" => ERROR_ILLEGALOPERATION,
                    "ErrorMessage" => "GetAllProducts: unsupported operation"));
            break;

        case "GetProductsByType":
            if (!CheckPostParam("TypeId")) {
                return;
            }
            $TypeId = $_POST["TypeId"];
            $Array = array();
            $Products = ProductsExplorer::GetProductsByType($TypeId);

            foreach ($Products as $ProductId) {
                $Product = new Product($ProductId);
                $Array[] = $Product->ArrayForSerialize();
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
            $Products = ProductsExplorer::GetProductsByUserId($UserId);

            foreach ($Products as $ProductId) {
                $Product = new Product($ProductId);
                $Array[] = $Product->ArrayForSerialize();
            }
            echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
                    "Products" => $Array));
            break;

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

            echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => "",
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

        case "PostProductImages":
            if (!CheckPostParam("ProductId")) {
                return;
            }
            # form name for file should be "ProductImage"
            if (FetchProductImages(intval($_POST["ProductId"]))) {
                echo json_encode(array("ErrorCode" => OK, "ErrorMessage" => ""));
            }
            else {
                echo json_encode(array("ErrorCode" => ERROR_CANNOTGETIMAGE,
                        "ErrorMessage" => "Image upload has failed"));
            }

            break;

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
