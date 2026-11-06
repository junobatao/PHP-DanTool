<?php
$uri = $_SERVER['REQUEST_URI'] ?? '';
if ($uri === '/favicon.ico' || strpos($uri, '/.well-known/') === 0) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/api.php';
$shops = getShopList();
// echo '<pre>';
// print_r($shops);
// echo '</pre>';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TJ小工具 | 拿货排单</title>
    <link href="./assets/bootstrap.min.css" rel="stylesheet">
    <script src="./assets/bootstrap.bundle.min.js"></script>
    <script src="./assets/Sortable.min.js"></script>
    </head>

<body>

    <nav class="navbar navbar-expand-sm navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="javascript:void(0)">TJ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mynavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mynavbar">
                <div class="me-auto d-flex">
                    <input class="form-control me-2" type="text" style="width: 500px;">
                    <button class="btn btn-danger" type="button" id="SearchGoods">搜索</button>
                </div>
                <div class="d-flex">
                    <a class="btn btn-danger" href="./print.php">打印预览</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-5">
        <div class="accordion" id="sortableAccordion">
            <?php foreach ($shops as $shop) { ?>
            <div class="accordion-item card" data-id="<?php echo $shop['id']; ?>">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapse<?php echo $shop['id']; ?>" aria-expanded="true" aria-controls="collapse<?php echo $shop['id']; ?>">
                        <span class="drag-handle me-2" style="cursor: move">☰</span>
                        <span class="shop-address"><?php echo $shop['name'].'@'.$shop['address']; ?></span>
                    </button>
                </h2>
                <div id="collapse<?php echo $shop['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#sortableAccordion">
                    <div class="accordion-body row">
                        <?php foreach ($shop['goods'] as $good) { ?>
                        <div class="good-item card col-1" data-id="<?php echo $good['id']; ?>" data-order="<?php echo $good['order_date']==date('Ymd') ? $good['order_content'] : ''; ?>">
                            <img class="card-img-top drag-handle" style="cursor: move" src="<?php echo $good['image'] ? './images/'.$good['image'] : './assets/faild.png'; ?>"
                                alt="Card image">
                            <div class="card-body">
                                <p class="card-text"><?php echo $good['code']; ?></p>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>


    <!-- 模态框 -->
    <div class="modal" id="myModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <form class="was-validated" id="myForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="info.update">
                        <input type="hidden" name="shop_id" id="shopId">
                        <input type="hidden" name="good_id" id="goodId">
                    
                        <div class="form-group">
                            <label for="shopAddress" class="form-label"></label>
                            <input type="text" class="form-control" id="shopAddress" name="address" required>
                            <div class="valid-feedback"></div>
                            <div class="invalid-feedback">请输入地址！</div>
                        </div>    
                        <div class="form-group">
                            <label for="shopName" class="form-label"></label>
                            <input type="text" class="form-control" id="shopName" name="name"  required>
                            <div class="valid-feedback"></div>
                            <div class="invalid-feedback">请输入厂家！</div>
                        </div>
                        <div class="form-group">
                            <label for="goodCode" class="form-label"></label>
                            <input type="text" class="form-control" id="goodCode" name="code" required>
                            <div class="valid-feedback"></div>
                            <div class="invalid-feedback">请输入编码！</div>
                        </div>
                        <div class="form-group">
                            <label for="fileInputImage" class="form-label"></label>
                            <input type="file" class="form-control" id="fileInputImage" name="image" accept="image/*" required>
                            <div class="valid-feedback"></div>
                            <div class="invalid-feedback">请选择图片！</div>
                            <img src="" class="img-rounded img-responsive" id="imgShowImage" width="200px">
                        </div>
                        <div class="form-group">
                            <label for="orderContent" class="form-label"></label>
                            <textarea class="form-control" rows="3" id="orderContent" name="order_content"></textarea>
                        </div>
                    </form>
                </div>

                <!-- 模态框底部 -->
                <div class="modal-footer">
                    <button type="button" id="saveBtn" class="btn btn-danger" data-bs-dismiss="modal">保存</button>
                </div>

            </div>
        </div>
    </div>


    <div class="position-fixed bottom-0 end-0 m-4" style="z-index: 1050;" id="trash">
        <button class="btn btn-danger rounded-circle shadow" style="width:56px;height:56px;">
            🗑️
        </button>
    </div>

    <script src="./assets/main.js"></script>
</body>

</html>