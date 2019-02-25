<html>
<head><title>SetUp DB</title></head>
<body>

<h1>Set Banner Image</h1>
<h2>Current Banner Images</h2>
<div id="root">
    <form action="./MarketExecute.php" method="post" id="template">
        <!-- Banner Preview -->
        <img src="" class="preview" style="width: 500px;" mode="aspectFit">
        <!-- Action -->
        <input type="hidden" name="Action" value="DeleteBannerImageWithId">
        <!-- Banner Id -->
        <input type="hidden" name="ImageId" value="ImageId" class="imgId">
        <input type="submit" value="Delete">
    </form>
</div>
<form action="./MarketExecute.php" method="post" enctype="multipart/form-data">
    <input type="file" name="BannerImage" accept="image/*">
    <input type="hidden" name="Action" value="UploadBannerImage">
    <input type="submit" value="Submit">
</form>
<script type="text/javascript">
var url = "https://s3.us-east-2.amazonaws.com/cusa-market-mnp/resources/images/BannerImages/[BannerId].png";
function request() {
    return new Promise((res, rej)=>{
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        console.log(this);
        if (this.readyState == 4 && this.status == 200){
            res(JSON.parse(this.responseText).BannerImages);
        }
      };
      xhttp.open("POST", "./MarketExecute", true);
      xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhttp.send("Action=ShowAllBannerImage");        
  })
}
request()
.then(banners=>{
    console.log(banners);
    // make templates
    var rt = document.getElementById("root");
    var itm = document.getElementById("template")
    for (var i=1;i<banners.length;i++){
        var cln = itm.cloneNode(true);
        rt.appendChild(cln);
    }
    // fill in id and img
    var previews = document.getElementsByClassName("preview");
    var imgId = document.getElementsByClassName("imgId");
    for (var i=0;i<banners.length;i++){
        previews[i].setAttribute("src", 
            url.replace("[BannerId]", banners[i].BannerImageId));
        imgId[i].setAttribute("value", banners[i].BannerImageId);
    }
})

</script>

</body>
</html>
