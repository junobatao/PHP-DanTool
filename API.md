## API 接口

接口地址：`api.php`。成功响应为 `{"success":true,"data":...}`；失败响应包含 `success:false` 和 `error`。查询使用 GET，写入、排序和删除使用 POST JSON。

| action | 参数 | 说明 |
|---|---|---|
| `shops.list` | — | 获取门店及其产品，均按 `sort_num` 升序；产品包含 `order_content`、`order_date`，无排单时为 `null` |
| `shops.sort` | `ids`：门店 ID 数组 | 按数组顺序重排所有门店，`sort_num` 从 0 开始 |
| `goods.sort` | `shop_id`、`ids`：该门店的全部产品 ID 数组 | 重排门店内产品 |
| `info.update` | 必须提供 `order_content`（可为空字符串）；可选 `shop_id`、`good_id`、`name`、`address`、`code`、`image` | 统一新增或修改门店、产品和排单。ID 对应记录存在时只更新有变化的字段；ID 未提供或记录不存在时新建（新门店需 `name`，新产品需 `code`）。新建产品使用本次门店 ID，排序号自动追加；排单按 `good_id` 唯一新增或更新，新记录日期为今天 |
| `shops.delete` | `id`：门店 ID | 删除门店、其产品、相关排单及未再被引用的图片 |
| `goods.delete` | `id`：产品 ID | 删除产品、相关排单及未再被引用的图片 |
| `orders.today` | — | 返回与 `shops.list` 相同的门店及嵌套产品结构；每个门店的 `goods` 只包含今天且 `order_content` 非空的排单产品，筛选后没有产品的门店不返回 |

统一更新示例：`POST api.php`，请求体为 JSON：

```json
{
  "action": "info.update",
  "shop_id": 1,
  "good_id": 12,
  "name": "门店名称",
  "address": "门店地址",
  "code": "商品编号",
  "image": "product.jpg",
  "order_content": "红色：100"
}
```

图片路径填写 `images` 目录下的文件名；新建门店或产品时分别需要提供 `name` 或 `code`。商品图片变更后会删除不再被其它商品引用的旧图片。`order_content` 可传空字符串，表示清空排单；新排单的日期为今天，更新排单时保留原日期。

其它 PHP 文件可直接引入 `api.php` 并调用以下方法，无需创建或传递 PDO 连接；`getShopList()` 返回的每个产品都包含 `order_content`、`order_date`，无排单时为 `null`：

```php
require_once __DIR__ . '/api.php';

$shops = getShopList();
$orders = getOrdersToday();
```