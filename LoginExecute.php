<?php
    # 这是微信login返回码的接口
    # 本接口调用微信的公开接口换取openId和session_key然后返回UserId给前端
    # 目前为了省事就不用session了，每次都重新login()一遍

    require("./Database.php");
    header("Content-type: application/json");

    if (isset($_POST["LoginCode"]))
    {
        $Code = $_POST["LoginCode"];

        #TODO: should just make these 2 environmental variables.
        $AppId = "wx00beb80204764058";
        $AppSec = "f16ef1d889f21d9d500452f5d954b960";

        $Url = "https://api.weixin.qq.com/sns/jscode2session?appid="
                .$AppId."&secret=".$AppSec."&js_code="
                .$Code."&grant_type=authorization_code";
        $UserInfo = json_decode(file_get_contents($Url), TRUE);

        # if code retrieval failed (most likely same code is used twice)
        if (isset($UserInfo["errcode"])) {
            echo json_encode($UserInfo);
            return;
        }
        $OpenId = $UserInfo["openid"];

        # create new user if this one is not already present
        $DB = new Database();
        $DB->Query("SELECT UserId FROM Users WHERE OpenId = '".$OpenId."';");
        if ($DB->NumOfRows() == 0) {
            $SQL = "INSERT INTO Users (OpenId) VALUES ('".$OpenId."');";
            $SQL .= "SELECT LAST_INSERT_ID() AS Id;";
            $DB->MultiQuery($SQL);
            $UserId = $DB->NextRow()["Id"];
        }
        else {
            $UserId = $DB->NextRow()["UserId"];
        }

        # send the user id back to front end
        echo json_encode(array("UserId" => $UserId));
        return;
    }



?>
