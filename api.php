<?php
declare(strict_types=1);

error_log("api.php 被请求: " . $_SERVER['REQUEST_URI'] . " | Method: " . $_SERVER['REQUEST_METHOD']);



$isDirectRequest = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;
if ($isDirectRequest) {
    header('Content-Type: application/json; charset=utf-8');
}

final class ApiException extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function respond(int $status, array $body): void
{
    error_log("api.php 响应: HTTP {$status} | Body: " . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function inputData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw === false ? '' : $raw, true);
        if (!is_array($data)) {
            throw new ApiException('请求体必须是有效的 JSON 对象。', 400);
        }
        return $data;
    }

    return $_POST;
}

function requiredId(array $data, string $key): int
{
    $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < 1) {
        throw new ApiException("参数 {$key} 必须是正整数。", 400);
    }
    return $value;
}

function optionalId(array $data, string $key): ?int
{
    if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
        return null;
    }
    return requiredId($data, $key);
}

function requiredText(array $data, string $key): string
{
    if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
        throw new ApiException("参数 {$key} 不能为空。", 400);
    }
    return trim($data[$key]);
}

function imageFilePath(?string $image): ?string
{
    if ($image === null || trim($image) === '') {
        return null;
    }

    $path = str_replace('\\', '/', trim($image));
    if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $path)) {
        return null;
    }
    $path = preg_replace('~^(?:\./)?images/~i', '', $path);
    if ($path === '' || $path === '.' || $path === '..' || basename($path) !== $path) {
        return null;
    }

    return __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $path;
}

function removeImageFiles(array $images): array
{
    $failures = [];
    foreach (array_unique($images) as $image) {
        $path = imageFilePath(is_string($image) ? $image : null);
        if ($path !== null && (is_file($path) || is_link($path)) && !@unlink($path)) {
            $failures[] = basename($path);
        }
    }
    return $failures;
}

function removeImageIfUnreferenced(PDO $pdo, ?string $image): array
{
    $path = imageFilePath($image);
    if ($path === null) {
        return [];
    }

    $stmt = $pdo->query('SELECT image FROM goods WHERE image IS NOT NULL');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $referencedImage) {
        $referencedPath = imageFilePath(is_string($referencedImage) ? $referencedImage : null);
        if ($referencedPath !== null && strcasecmp($path, $referencedPath) === 0) {
            return [];
        }
    }

    return removeImageFiles([$image]);
}

function saveUploadedImage(): ?string
{
    if (!isset($_FILES['image'])) {
        return null;
    }

    $file = $_FILES['image'];
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'])) {
        throw new ApiException('上传图片参数无效。', 400);
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new ApiException('图片上传失败。', 400);
    }

    $imageDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'images';
    if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
        throw new RuntimeException('无法创建 images 目录。');
    }

    $imageName = date('YmdHis') . '.jpg';
    $imagePath = $imageDirectory . DIRECTORY_SEPARATOR . $imageName;
    if (file_exists($imagePath)) {
        throw new ApiException('同名图片文件已存在，请稍后重试。', 409);
    }
    if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
        throw new RuntimeException('无法保存上传图片。');
    }

    return $imageName;
}

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new ApiException('该操作只允许使用 POST 请求。', 405);
    }
}

function getDatabaseConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'database.sqlite';
    if (!is_file($dbFile)) {
        throw new RuntimeException('SQLite 数据库文件不存在。');
    }

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = OFF');

    return $pdo;
}

function getShopList(): array
{
    $pdo = getDatabaseConnection();
    $shops = $pdo->query(
        'SELECT id, name, address, sort_num FROM shops ORDER BY sort_num ASC, id ASC'
    )->fetchAll();
    $goods = $pdo->query(
        'SELECT g.id, g.shop_id, g.image, g.code, g.sort_num,
                o.order_content, o.order_date
         FROM goods g
         LEFT JOIN orders o ON o.good_id = g.id
         ORDER BY g.shop_id ASC, g.sort_num ASC, g.id ASC'
    )->fetchAll();
    $goodsByShop = [];
    foreach ($goods as $item) {
        $goodsByShop[(int) $item['shop_id']][] = $item;
    }
    foreach ($shops as &$shop) {
        $shop['goods'] = $goodsByShop[(int) $shop['id']] ?? [];
    }
    unset($shop);

    return $shops;
}

function getOrdersToday(): array
{
    $shops = getShopList();
    $today = date('Ymd');

    foreach ($shops as $index => &$shop) {
        $shop['goods'] = array_values(array_filter(
            $shop['goods'],
            static fn(array $good): bool =>
                $good['order_date'] === $today
                && trim((string) $good['order_content']) !== ''
        ));
        if (!$shop['goods']) {
            unset($shops[$index]);
        }
    }
    unset($shop);

    return array_values($shops);
}

function reorder(PDO $pdo, string $table, array $ids, ?int $shopId = null): void
{
    if (!$ids || count(array_unique($ids)) !== count($ids)) {
        throw new ApiException('排序列表不能为空，且不能包含重复 ID。', 400);
    }

    if ($table === 'shops') {
        $currentIds = $pdo->query('SELECT id FROM shops ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $shopStmt = $pdo->prepare('SELECT id FROM shops WHERE id = ?');
        $shopStmt->execute([$shopId]);
        if (!$shopStmt->fetchColumn()) {
            throw new ApiException('门店不存在。', 404);
        }
        $currentStmt = $pdo->prepare('SELECT id FROM goods WHERE shop_id = ? ORDER BY id');
        $currentStmt->execute([$shopId]);
        $currentIds = $currentStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $expectedIds = array_map('intval', $currentIds);
    $providedIds = $ids;
    sort($expectedIds);
    sort($providedIds);
    if ($expectedIds !== $providedIds) {
        throw new ApiException('排序列表必须包含该范围内的全部 ID，且不能包含其他 ID。', 400);
    }

    $pdo->beginTransaction();
    try {
        if ($table === 'shops') {
            $update = $pdo->prepare('UPDATE shops SET sort_num = ? WHERE id = ?');
            foreach ($ids as $position => $id) {
                $update->execute([$position, $id]);
            }
        } else {
            $update = $pdo->prepare('UPDATE goods SET sort_num = ? WHERE id = ? AND shop_id = ?');
            foreach ($ids as $position => $id) {
                $update->execute([$position, $id, $shopId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function handleApiRequest(): void
{
    try {
        $pdo = getDatabaseConnection();
    $input = inputData();
    $action = $input['action'] ?? $_GET['action'] ?? '';
    if (!is_string($action) || $action === '') {
        throw new ApiException('缺少 action 参数。', 400);
    }

    switch ($action) {
        case 'shops.list':
            respond(200, ['success' => true, 'data' => getShopList()]);

        case 'shops.sort':
            requirePost();
            $rawIds = $input['ids'] ?? null;
            if (!is_array($rawIds)) {
                throw new ApiException('参数 ids 必须是 ID 数组。', 400);
            }
            $ids = array_map(
                static function ($id): int {
                    $value = filter_var($id, FILTER_VALIDATE_INT);
                    if ($value === false || $value < 1) {
                        throw new ApiException('排序列表中的 ID 必须是正整数。', 400);
                    }
                    return $value;
                },
                $rawIds
            );
            reorder($pdo, 'shops', $ids);
            respond(200, ['success' => true]);

        case 'goods.sort':
            requirePost();
            $shopId = requiredId($input, 'shop_id');
            $rawIds = $input['ids'] ?? null;
            if (!is_array($rawIds)) {
                throw new ApiException('参数 ids 必须是 ID 数组。', 400);
            }
            $ids = array_map(
                static function ($id): int {
                    $value = filter_var($id, FILTER_VALIDATE_INT);
                    if ($value === false || $value < 1) {
                        throw new ApiException('排序列表中的 ID 必须是正整数。', 400);
                    }
                    return $value;
                },
                $rawIds
            );
            reorder($pdo, 'goods', $ids, $shopId);
            respond(200, ['success' => true]);

        case 'info.update':
            error_log("api.php 处理 info.update 请求: " . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            requirePost();
            $requestedShopId = optionalId($input, 'shop_id');
            $requestedGoodsId = optionalId($input, 'good_id');
            if (!array_key_exists('order_content', $input) || !is_string($input['order_content'])) {
                throw new ApiException('参数 order_content 必须是字符串，允许为空字符串。', 400);
            }
            if (array_key_exists('address', $input) && !is_string($input['address'])) {
                throw new ApiException('参数 address 必须是字符串。', 400);
            }
            if (array_key_exists('name', $input) && !is_string($input['name'])) {
                throw new ApiException('参数 name 必须是字符串。', 400);
            }
            if (array_key_exists('code', $input) && (!is_string($input['code']) || trim($input['code']) === '')) {
                throw new ApiException('参数 code 必须是非空字符串。', 400);
            }
            if (array_key_exists('image', $input)) {
                throw new ApiException('参数 image 必须通过文件上传提供。', 400);
            }

            $uploadedImage = saveUploadedImage();
            $oldImage = null;
            try {
                $pdo->beginTransaction();
                $shop = null;
                if ($requestedShopId !== null) {
                    $shopStmt = $pdo->prepare('SELECT id, name, address FROM shops WHERE id = ?');
                    $shopStmt->execute([$requestedShopId]);
                    $shop = $shopStmt->fetch();
                }
                // 如果 shop_id 不存在，则创建新门店；如果存在，则更新门店信息（如果有变化）
                if ($shop === false || $shop === null) {
                    $name = requiredText($input, 'name');
                    $address = $input['address'] ?? '';
                    $sortStmt = $pdo->query('SELECT COALESCE(MAX(sort_num), -1) + 1 FROM shops');
                    $sortNum = (int) $sortStmt->fetchColumn();
                    $insertShop = $pdo->prepare(
                        'INSERT INTO shops (name, address, sort_num) VALUES (?, ?, ?)'
                    );
                    $insertShop->execute([$name, $address, $sortNum]);
                    $shopId = (int) $pdo->lastInsertId();
                } else {
                    $shopId = (int) $shop['id'];
                    $name = array_key_exists('name', $input) ? trim($input['name']) : $shop['name'];
                    $address = array_key_exists('address', $input) ? $input['address'] : $shop['address'];
                    if ($name !== $shop['name'] || $address !== $shop['address']) {
                        $updateShop = $pdo->prepare('UPDATE shops SET name = ?, address = ? WHERE id = ?');
                        $updateShop->execute([$name, $address, $shopId]);
                    }
                }

                $goods = null;
                if ($requestedGoodsId !== null) {
                    $goodsStmt = $pdo->prepare(
                        'SELECT id, shop_id, image, code, sort_num FROM goods WHERE id = ?'
                    );
                    $goodsStmt->execute([$requestedGoodsId]);
                    $goods = $goodsStmt->fetch();
                }
                // 如果 good_id 不存在，则创建新商品；如果存在，则更新商品信息（如果有变化）
                if ($goods === false || $goods === null) {
                    $code = requiredText($input, 'code');
                    $image = $uploadedImage;
                    $sortStmt = $pdo->prepare(
                        'SELECT COALESCE(MAX(sort_num), -1) + 1 FROM goods WHERE shop_id = ?'
                    );
                    $sortStmt->execute([$shopId]);
                    $sortNum = (int) $sortStmt->fetchColumn();
                    $insertGoods = $pdo->prepare(
                        'INSERT INTO goods (shop_id, image, code, sort_num) VALUES (?, ?, ?, ?)'
                    );
                    $insertGoods->execute([$shopId, $image, $code, $sortNum]);
                    $goodsId = (int) $pdo->lastInsertId();
                } else {
                    $goodsId = (int) $goods['id'];
                    $code = array_key_exists('code', $input) ? trim($input['code']) : $goods['code'];
                    $image = $uploadedImage ?? $goods['image'];
                    if ($code !== $goods['code'] || $image !== $goods['image']) {
                        $updateGoods = $pdo->prepare('UPDATE goods SET code = ?, image = ? WHERE id = ?');
                        $updateGoods->execute([$code, $image, $goodsId]);
                        if ($image !== $goods['image']) {
                            $oldImage = $goods['image'];
                        }
                    }
                }

                $orderStmt = $pdo->prepare(
                    'SELECT id, order_content FROM orders WHERE good_id = ? ORDER BY id ASC'
                );
                $orderStmt->execute([$goodsId]);
                $orderRows = $orderStmt->fetchAll();
                if (count($orderRows) > 1) {
                    throw new ApiException('同一商品存在多条排单记录，无法按 good_id 唯一更新。', 409);
                }
                if ($orderRows) {
                    if ($orderRows[0]['order_content'] !== $input['order_content']) {
                        $updateOrder = $pdo->prepare('UPDATE orders SET order_content = ? WHERE id = ?');
                        $updateOrder->execute([$input['order_content'], $orderRows[0]['id']]);
                    }
                    $orderId = (int) $orderRows[0]['id'];
                } else {
                    $insertOrder = $pdo->prepare(
                        'INSERT INTO orders (good_id, order_content, order_date) VALUES (?, ?, ?)'
                    );
                    $insertOrder->execute([$goodsId, $input['order_content'], date('Ymd')]);
                    $orderId = (int) $pdo->lastInsertId();
                }

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($uploadedImage !== null) {
                    $cleanupFailures = removeImageFiles([$uploadedImage]);
                    if ($cleanupFailures) {
                        error_log(
                            'api.php failed to clean up uploaded image after rollback: '
                            . implode(', ', $cleanupFailures)
                        );
                    }
                }
                throw $exception;
            }

            $imageFailures = removeImageIfUnreferenced($pdo, $oldImage);
            if ($imageFailures) {
                error_log('api.php failed to remove replaced product image: ' . implode(', ', $imageFailures));
                throw new ApiException('商品信息已更新，但原图片文件删除失败。', 500);
            }

            respond(200, [
                'success' => true,
                'data' => [
                    'shop_id' => $shopId,
                    'good_id' => $goodsId,
                    'order_id' => $orderId,
                ],
            ]);

        case 'shops.delete':
            requirePost();
            $shopId = requiredId($input, 'id');
            $stmt = $pdo->prepare('SELECT id FROM shops WHERE id = ?');
            $stmt->execute([$shopId]);
            if (!$stmt->fetchColumn()) {
                throw new ApiException('门店不存在。', 404);
            }

            $goodsStmt = $pdo->prepare('SELECT id, image FROM goods WHERE shop_id = ?');
            $goodsStmt->execute([$shopId]);
            $shopGoods = $goodsStmt->fetchAll();
            $goodsIds = array_map(static function (array $item): int {
                return (int) $item['id'];
            }, $shopGoods);

            $pdo->beginTransaction();
            try {
                if ($goodsIds) {
                    $placeholders = implode(',', array_fill(0, count($goodsIds), '?'));
                    $deleteOrders = $pdo->prepare("DELETE FROM orders WHERE good_id IN ({$placeholders})");
                    $deleteOrders->execute($goodsIds);
                    $deleteGoods = $pdo->prepare('DELETE FROM goods WHERE shop_id = ?');
                    $deleteGoods->execute([$shopId]);
                }
                $deleteShop = $pdo->prepare('DELETE FROM shops WHERE id = ?');
                $deleteShop->execute([$shopId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            $failures = removeImageFiles(array_column($shopGoods, 'image'));
            if ($failures) {
                error_log('api.php failed to remove shop images: ' . implode(', ', $failures));
                throw new ApiException('门店及产品已删除，但部分图片文件删除失败。', 500);
            }
            respond(200, ['success' => true]);

        case 'goods.delete':
            requirePost();
            $goodsId = requiredId($input, 'id');
            $stmt = $pdo->prepare('SELECT image FROM goods WHERE id = ?');
            $stmt->execute([$goodsId]);
            $image = $stmt->fetchColumn();
            if ($image === false) {
                throw new ApiException('产品不存在。', 404);
            }

            $pdo->beginTransaction();
            try {
                $deleteOrder = $pdo->prepare('DELETE FROM orders WHERE good_id = ?');
                $deleteOrder->execute([$goodsId]);
                $deleteGoods = $pdo->prepare('DELETE FROM goods WHERE id = ?');
                $deleteGoods->execute([$goodsId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            $failures = removeImageFiles([$image]);
            if ($failures) {
                error_log('api.php failed to remove product image: ' . implode(', ', $failures));
                throw new ApiException('产品已删除，但图片文件删除失败。', 500);
            }
            respond(200, ['success' => true]);

        case 'orders.today':
            respond(200, ['success' => true, 'data' => getOrdersToday()]);

        default:
            throw new ApiException('不支持的 action。', 404);
    }
} catch (ApiException $exception) {
    respond($exception->status, ['success' => false, 'error' => $exception->getMessage()]);
} catch (PDOException $exception) {
    error_log('api.php database error: ' . $exception->getMessage());
    respond(500, ['success' => false, 'error' => '数据库操作失败。']);
} catch (Throwable $exception) {
    error_log('api.php unexpected error: ' . $exception->getMessage());
    respond(500, ['success' => false, 'error' => '服务器内部错误。']);
}
}

if ($isDirectRequest) {
    handleApiRequest();
}