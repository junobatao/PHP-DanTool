<?php
// var_dump(extension_loaded('pdo_sqlite'));

$dbFile = __DIR__ . '/database.sqlite'; // 确保 data 目录存在且可写
// 确保目录存在
if (!is_dir(dirname($dbFile))) {
    mkdir(dirname($dbFile), 0777, true);
}

try
{
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    //SQLite 外键需要每次连接开启
    $pdo->exec('PRAGMA foreign_keys = ON');

    //门店
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shops (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            address TEXT,
            sort_num INTEGER NOT NULL DEFAULT 0
        )
    ");
    //商品
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS goods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            shop_id INTEGER NOT NULL,
            image TEXT,
            code TEXT NOT NULL,
            sort_num INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (shop_id) REFERENCES shop(id) ON DELETE CASCADE
        )
    ");
    //排单
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            good_id INTEGER NOT NULL,
            order_content TEXT,
            order_date TEXT NOT NULL DEFAULT (strftime('%Y%m%d', 'now')),
            FOREIGN KEY (good_id) REFERENCES goods(id) ON DELETE CASCADE
        )
    ");

    $orderColumns = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('good_id', $orderColumns, true) && !in_array('good_id', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders RENAME COLUMN good_id TO good_id');
    } elseif (!in_array('good_id', $orderColumns, true)) {
        throw new RuntimeException('orders 表缺少 good_id 字段，无法完成数据库迁移。');
    }

    // 索引
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_shops_sort_num ON shops(sort_num)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_goods_sort_num ON goods(sort_num)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_goods_shop_id ON goods(shop_id)');
    $pdo->exec('DROP INDEX IF EXISTS idx_orders_good_id');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_good_id ON orders(good_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_order_date ON orders(order_date)');
    
    echo "数据库和表创建成功：$dbFile\n";

}
catch (PDOException $e)
{
    die("数据库连接失败: " . $e->getMessage());
}


