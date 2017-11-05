<html>
<head><title>SetUp DB</title></head>
<body>

<h1>DB Set UP, For testing purpose only!</h1>

<p>注：因为程序构架改变的原因，不再提供reset DB的功能。访问此功能请使用GET请求:P=admin</p>
<br>
    <form action="./SetUp.php" method="post">
        <p>Choose what you want to do: </p>
        <input type="submit" value="Show Tables" name="submit"> <br>
        <input type="submit" value="Show All Records" name="submit"> <br>

        <?PHP if (isset($_GET["P"]) && $_GET["P"] == "admin") { ?>
            <input type="submit" value="Reset All Tables" name="submit"> <br>
            <input type="submit" value="Reset Images FROM DB" name="submit"> <br>
        <?PHP } ?>
    </form>
<br>

    <?php
        require("./Database.php");

        $DB = new Database();

        if (isset($_POST["submit"]))
        {

            # Show Tables
            if ($_POST["submit"] == "Show Tables")
            {
                Database::SetUp();
                echo "------------ Complete ------------ <br>";
                echo "The following are the names of the DataBase Tables: <br>";

                $DB->Query("SHOW TABLES;");
                while($Row = $DB->NextRow()) {
                    echo "# Table Name | ".$Row["Tables_in_ebdb"]."<br>";
                }
            }

            # Reset all tables
            if ($_POST["submit"] == "Reset All Tables")
            {
                $DB->Query("DROP TABLE Products;");
                $DB->Query("DROP TABLE ProductContact;");
                $DB->Query("DROP TABLE Users;");
                $DB->Query("DROP TABLE ProductImages;");
                Database::SetUp();
                echo "------------ Complete ------------ <br>";
            }

            # Reset All Images from database
            if ($_POST["submit"] == "Reset Images FROM DB")
            {
                $DB->Query("DELETE FROM ProductImages;");
                echo "------------ Complete ------------ <br>";
            }

            # Show All Records
            if ($_POST["submit"] == "Show All Records")
            {
                echo "---------- Users ---------- <br>";
                $DB->Query("SELECT * FROM Users");
                while ($Row = $DB->NextRow()) {
                    foreach ($Row as $key => $val) {
                        $val = is_null($val) ? "NULL" : $val;
                        echo "# " . $key . " | " . $val . "<br>";
                    }
                    echo "<br>";
                }

                echo "---------- Products ---------- <br>";
                $DB->Query("SELECT * FROM Products");
                while ($Row = $DB->NextRow()) {
                    foreach ($Row as $key => $val) {
                        $val = is_null($val) ? "NULL" : $val;
                        echo "# " . $key . " | " . $val . "<br>";
                    }
                    echo "<br>";
                }

                echo "---------- ProductContact ---------- <br>";
                $DB->Query("SELECT * FROM ProductContact");
                while ($Row = $DB->NextRow()) {
                    foreach ($Row as $key => $val) {
                        $val = is_null($val) ? "NULL" : $val;
                        echo "# " . $key . " | " . $val . "<br>";
                    }
                    echo "<br>";
                }

                echo "---------- ProductImages ---------- <br>";
                $DB->Query("SELECT * FROM ProductImages");
                while ($Row = $DB->NextRow()) {
                    foreach ($Row as $key => $val) {
                        $val = is_null($val) ? "NULL" : $val;
                        echo "# " . $key . " | " . $val . "<br>";
                    }
                    echo "<br>";
                }
                echo "------------ Complete ------------ <br>";
            }
        }


        $DB->Close();

    ?>

</body>
</html>
