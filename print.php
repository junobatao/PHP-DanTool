<?php
require_once __DIR__ . '/api.php';
$shops = getOrdersToday();
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TJ小工具 | 拿货排单</title>
    <link href="./assets/bootstrap.min.css" rel="stylesheet">
    <link href="./assets/print.css" rel="stylesheet">
</head>

<body>

    <nav class="navbar navbar-expand-sm navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="javascript:void(0)">TJ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mynavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mynavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="./index.php">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./index.php">Home</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <buton class="btn btn-danger" id="btnPrint">A4打印</buton>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-2 py-5 print-area">
        <?php 
        foreach ($shops as $key => $shop)
        { 
        ?>
            <table class="plain-table">
                <tr>
                    <td class="name" colspan="2"><?php echo $key+1 . ' - ' . $shop['name'] . '（' . $shop['address'] . '）' ?></td>
                </tr>
                <tr>
                    <?php
                    foreach ($shop['goods'] as $k => $obj)
                    {
                    ?>
                        <td>
                            <img src="./images/<?php echo $obj['image']?> "/>
                            <div class="textright">
                                <div class="title"><?php echo  $obj['code'] ?></div>
                                <div class="order"><?php echo $obj['order_content'] ?></div>
                            </div>
                        </td>
                    <?php
                        if (($k+1) % 2 == 0)
                        {
                            echo "</tr><tr>";
                        }
                    }
                    ?>
                </tr>
            </table>
        <?php 
        }
        ?>
    </div>

<script>
    document.getElementById('btnPrint').addEventListener('click', function(){
        window.print();
        // window.onafterprint = function() {
        //     window.location.href = "index.php";
        // };
    })
</script>
</body>
</html>