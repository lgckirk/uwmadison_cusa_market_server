# About

This repository is the live version of the CUSAMNP. Files for testing purposes should **NOT** be present here.

# Interface Documentation

## Interface Overview

* https://mnpserver.uwmadisoncusa.com/MarketExecute.php （这是和二手市场交互的入口）
* https://mnpserver.uwmadisoncusa.com/LoginExecute.php (这是login后换取UserId的入口)

* 所有通向接口的HTTP request的method都必须使用**POST**，Content-type使用**application/x-www-form-urlencoded**.
* 所有接口返回的数据都是json格式


## LoginExecute Interface

* 调用wx.login()后函数会返回一个code（详见API），需要把这个code发送至本接口换取openid，
    然后本接口会返回一个int格式的UserId， 客服端需storage本UserId，作为用户的唯一标识

* 请求参数： LoginCode (wx.login()函数返回的code)

* TODO: 登陆态维护：调用checkSession， 如果没问题就不用再login了



## MarketExecute Interface

* 所有请求附带数据都必须有*Action*这个参数来确定请求的目的，根据Action值的不同需附带其他信息

* 所有Action的返回json均会带有*ErrorCode*和*ErrorMessage*这两个参数，ErrorCode == 1 代表一切正常，客户端需验证这个code，如果不是1要做对应处理


### 以下是Acion可能的值和他们对应的请求参数和返回json带有的参数

1. GetProductsByType （查找某一类的商品）

    - 请求参数： 
        - TypeId (int) 商品的类别代码，以下是TypeId允许的值：
                1 -> 其他
                2 -> 家具
                3 -> 电子产品
                4 -> 学术
                5 -> 衣服
                6 -> 租房
                7 -> 交通
                8 -> 化妆
        
        - StartId(int) 选填，返回比该ID所对应商品早上架的商品。若不填或填入无效ID，则默认返回最新的商品。
        - ListLength(int) 选填，返回指定长度的商品列表。若不填或填入无效长度，则默认返回20个商品。

    - 返回参数： ErrorCode (int), ErrorMessage (string), Products (array, 返回数据，可以是空的)

            - Products的参数 (除了ProductName, ProductId, ProductOwner, ProductType 其他都是optional，需要检查是不是null)：
                    ProductId (int)
                    ProductName (string)
                    ProductCondition (string)
                    ProductPrice (int)
                    ProductDescription (string)
                    ProductType (int, 允许值见上文)
                    ProductOwner (int, UserId)
                    DateCreated (string)
                    DateExpire (string)
                    ProductStatus (不用管这个)
                    ProductContact (array, 包含ContactName, ContactPhone, ContactEmail, ContactWechat, 都可能是NULL)
                    ProductImages (array, URLs)

2. GetProductsByUserId （查找某一用户的商品）

    - 请求参数： 
        - UserId (int) 用户的唯一标识，来自于LoginExecute接口的返回
        - StartId(int) 选填，返回比该ID所对应商品早上架的商品。若不填或填入无效ID，则默认返回最新的商品。
        - ListLength(int) 选填，返回指定长度的商品列表。若不填或填入无效长度，则默认返回20个商品。

    - 返回参数： ErrorCode (int), ErrorMessage (string), Products (array, Product参数见"GetProductsByType")

3. PostProductWithImage （发布商品/商品图片）

    - 请求参数：

            - 必须有：
                ProductOwner (int) 商品所有者的UserId
                ProductName (string) 商品的名称
                ProductType (int) 商品的类别代码，代码允许的值见上文

            - 选填：
                ProductCondition (string) 商品状态
                ProductDescription （string) 商品描述
                ProductPrice (int) 商品价格, 对于住房类商品，价格指的是一个月租金
                                (注意价格应该做成可选的而不是强制要求）
                ContactName, ContactEmail, ContactPhone, ContactWechat (string, optional) 商品的联系人信息
                DateExpire (string) 商品下架日期，日期格式：
                            带时分秒 -> YYYY-MM-DD HH:mm:SS
                            不带时分秒 -> YYYY-MM-DD 或 YYYY/MM/DD
                            如果不明确下架日期，默认为上架日期的90天后

    - 图片上传的form name是"ProductImage" (没有s)

    - 返回参数： ErrorCode, ErrorMessage, ProductId (int, 该产品的Id)

4. EndListing （下架商品）

    - 请求参数： ProductId (int) 商品Id

    - 返回参数： ErrorCode, ErrorMessage

5. GetAllProducts (不按类型的获取商品)
    - 请求参数：
        - StartId(int) 选填，返回比该ID所对应商品早上架的商品。若不填或填入无效ID，则默认返回最新的商品。
        - ListLength(int) 选填，返回指定长度的商品列表。若不填或填入无效长度，则默认返回20个商品。
        
    - 返回参数： ErrorCode (int), ErrorMessage (string), Products (array, Product参数见"GetProductsByType")

### Deprecated interface functionality

* PostProduct （发布商品）

    - 请求参数：

            - 必须有：
                ProductOwner (int) 商品所有者的UserId
                ProductName (string) 商品的名称
                ProductType (int) 商品的类别代码，代码允许的值见上文

            - 选填：
                ProductCondition (string) 商品状态
                ProductDescription （string) 商品描述
                ProductPrice (int) 商品价格, 对于住房类商品，价格指的是一个月租金
                                (注意价格应该做成可选的而不是强制要求）
                ContactName, ContactEmail, ContactPhone, ContactWechat (string, optional) 商品的联系人信息
                DateExpire (string) 商品下架日期，日期格式：
                            带时分秒 -> YYYY-MM-DD HH:mm:SS
                            不带时分秒 -> YYYY-MM-DD 或 YYYY/MM/DD
                            如果不明确下架日期，默认为上架日期的90天后

    - 返回参数： ErrorCode, ErrorMessage, ProductId (int, 该产品的Id)

* PostProductImages （发布商品图片）

    - 请求参数： ProductId (int) 商品Id

    - 图片上传的form name是"ProductImage" (没有s)

    - 返回参数： ErrorCode, ErrorMessage

* GetProductImages （获取商品url）

    - 请求参数： ProductId (int) 商品Id

    - 返回参数： ErrorCode, ErrorMessage 和 ProductImages (array，里面是照片的absolute url)

