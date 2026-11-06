window.scrollTo({ top: 0, behavior: 'smooth' });
document.querySelector('.accordion-button').click();


// 拖动店铺排序
new Sortable(document.getElementById('sortableAccordion'), {
    animation: 150,            // 拖拽时的过渡动画时长（毫秒）
    //   ghostClass: 'bg-light',    // 拖拽占位符的样式，这里用 Bootstrap 的浅色背景
    handle: '.drag-handle',    // 可选：如果你只想让某个图标触发拖拽，可指定类名
    onEnd: function (evt) {
        // 拖拽结束后的回调：这里可以获取新的顺序并发送给后端
        const ids = Array.from(evt.to.children).map(item => item.dataset.id);
        console.log('新的排序 ID 数组:', ids);
        trashDeleteOrSort(evt, 'shops', ids);
    }
});
// 拖动商品排序
Array.from(document.getElementsByClassName('accordion-body')).forEach(function (bodyEl) {
    new Sortable(bodyEl, {
        animation: 150,            // 拖拽时的过渡动画时长（毫秒）
        ghostClass: 'bg-light',    // 拖拽占位符的样式，这里用 Bootstrap 的浅色背景
        handle: '.drag-handle',    // 可选：如果你只想让某个图标触发拖拽，可指定类名
        onEnd: function (evt) {
            // 拖拽结束后的回调：这里可以获取新的顺序并发送给后端
            const ids = Array.from(evt.to.children).map(item => item.dataset.id);
            console.log('新的排序 ID 数组:', ids);
            const shopId = bodyEl.closest('.accordion-item').dataset.id;
            trashDeleteOrSort(evt, 'goods', ids, shopId)
        }
    });
})
// 拖动到垃圾桶：删除元素和数据库数据！
function trashDeleteOrSort(evt, action, ids, shopId = null) {
    const { clientX, clientY } = evt.originalEvent;
    const trashRect = document.getElementById("trash").getBoundingClientRect();
    const isOverTrash = (
        clientX >= trashRect.left && clientX <= trashRect.right &&
        clientY >= trashRect.top && clientY <= trashRect.bottom
    );
    const formData = new FormData();
    if (isOverTrash) {
        evt.item.remove(); // 从原列表删除
        const id = evt.item.dataset.id;
        formData.append('action', action + '.delete');
        formData.append('id', id);
    } else {
        formData.append('action', action + '.sort');
        formData.append('shop_id', shopId);
        ids.forEach(id => formData.append('ids[]', id));
    }
    fetch('api.php', { method: 'POST', body: formData })
}


// 搜索店铺和商品: 积木城堡&D2356女童吊带蝴蝶结套装
document.getElementById("SearchGoods").onclick = function () {
    inputEl = this.parentElement.querySelector('input');
    const value = inputEl.value
    if (value === '') return;
    const [inputShop, inputSku] = value.split('&');

    const items = document.getElementsByClassName("shop-address");
    let noExistShop = true;
    Array.from(items).forEach(function (item) {
        const [titleName, titleAddress] = item.textContent.split('@')
        if (titleName !== inputShop) return;
        noExistShop = false; //店铺存在，可以继续添加产品

        const accordionItem = item.closest('.accordion-item'); if (!accordionItem) return;
        const collapse = accordionItem.querySelector('.accordion-collapse'); if (!collapse) return;
        const btn = accordionItem.querySelector('.accordion-button'); if (!btn) return;
        if (!collapse.classList.contains('show')) btn.click(); //点击效果

        const rect = accordionItem.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        window.scrollTo({
            top: rect.top + scrollTop + rect.height / 2 - window.innerHeight / 2,
            behavior: 'smooth'
        });

        let noExistGoods = true;
        let goodImage = null;
        const shopId = accordionItem.getAttribute('data-id') || '';
        const cardTexts = accordionItem.querySelectorAll('.card-text');
        Array.from(cardTexts).forEach(function (textEl) {
            // console.log(textEl.textContent.trim(), inputSku);

            if (textEl.textContent.trim() === inputSku) {
                // card.classList.add('text-danger');
                noExistGoods = false; //产品已经存在！
                const itemId = textEl.closest('.good-item');
                const goodId = itemId.getAttribute('data-id') || '';
                const orderContent = itemId.getAttribute('data-order') || '';
                goodImage = itemId.querySelector('.card-img-top').getAttribute('src') || '';
                showOrderInfoModal(shopId, goodId, inputShop, inputSku, titleAddress, goodImage, orderContent);
            }
        })
        if (noExistGoods) showOrderInfoModal(shopId, '', inputShop, inputSku, titleAddress, goodImage, '')
    })
    if (noExistShop) showOrderInfoModal('', '', inputShop, inputSku, '', '', '')
}
// 双击商品图片，弹出模态框：这样绑定，新增的dom没有双击事件，改成委托绑定到父元素上，使用事件冒泡来处理
// document.querySelectorAll('.card-img-top').forEach(obj => {
//     obj.addEventListener('dblclick', function (e) {
//         const image = obj.getAttribute('src');
//         const accordionItem = obj.closest('.accordion-item');
//         const shopId = accordionItem.getAttribute('data-id') || '';
//         const [shop, address] = accordionItem.querySelector('.shop-address').textContent.trim().split('@');
//         const cardEl = obj.closest('.good-item');
//         const goodId = cardEl.getAttribute('data-id') || '';
//         const order = cardEl.getAttribute('data-order') || '';
//         const sku = cardEl.querySelector('.card-text').textContent.trim();
//         showOrderInfoModal(shopId, goodId, shop, sku, address, image, order)
//     });
// });
document.getElementById('sortableAccordion').addEventListener('dblclick', function (e) {
    const obj = e.target.closest('.card-img-top');
    if (!obj) return;   // 双击的不是商品图片，忽略
    const image = obj.getAttribute('src');
    const accordionItem = obj.closest('.accordion-item');
    const shopId = accordionItem.getAttribute('data-id') || '';
    const [shop, address] = accordionItem.querySelector('.shop-address').textContent.trim().split('@');
    const cardEl = obj.closest('.good-item');
    const goodId = cardEl.getAttribute('data-id') || '';
    const order = cardEl.getAttribute('data-order') || '';
    const sku = cardEl.querySelector('.card-text').textContent.trim();
    showOrderInfoModal(shopId, goodId, shop, sku, address, image, order)
});



// 显示订单信息模态框
function showOrderInfoModal(shopId, goodId, shop, sku, address, image, order) {
    console.log('showOrderInfoModal', shopId, goodId, shop, sku, address, image, order)

    const modalEl = document.getElementById('myModal');

    //初始化：置空
    modalEl.querySelector('#shopId').value = '';
    modalEl.querySelector('#goodId').value = '';
    modalEl.querySelector('#shopName').value = '';
    modalEl.querySelector('#shopName').value = '';
    modalEl.querySelector('#goodCode').value = '';
    modalEl.querySelector('#shopAddress').value = '';
    modalEl.querySelector('#fileInputImage').value = '';
    modalEl.querySelector('#imgShowImage').setAttribute('src', '');
    modalEl.querySelector('#orderContent').value = '';

    //填充数据
    modalEl.querySelector('#shopId').value = shopId;
    modalEl.querySelector('#goodId').value = goodId;
    modalEl.querySelector('#shopName').value = shop;
    modalEl.querySelector('#goodCode').value = sku;
    modalEl.querySelector('#shopAddress').value = address;
    modalEl.querySelector('#imgShowImage').setAttribute('src', image);
    modalEl.querySelector('#orderContent').value = order;
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    inputEl = modalEl.querySelector('#fileInputImage');
    inputEl.setAttribute('required', 'required');
    if (image) {
        inputEl.removeAttribute('required');
    }
}
// 处理图片上传预览
const fileInputImageEl = document.getElementById('fileInputImage');
const imgSrcImageEl = document.getElementById('imgShowImage')
fileInputImageEl.addEventListener('change', function (e) {
    const filePath = URL.createObjectURL(this.files[0]);
    imgSrcImageEl.setAttribute('src', filePath);
})

// 保存按钮点击事件
document.getElementById('saveBtn').addEventListener('click', async function () {
    const form = document.getElementById('myForm');

    // 触发表单原生校验
    if (!form.checkValidity()) return;

    // 2. 用 FormData 收集所有字段（含文件）
    const formData = new FormData(form);
    console.log('shop_id', formData.get('shop_id'));  // 店铺ID
    console.log('good_id', formData.get('good_id')); // 商品ID
    console.log('address', formData.get('address'));  // 地址
    console.log('name', formData.get('name'));     // 厂家
    console.log('code', formData.get('code'));     // 编码
    console.log('order_content', formData.get('order_content')); // 内容

    // 查看文件：用 get 拿到的是 File 对象
    const file = formData.get('image');
    console.log('image', file);          // File 对象
    console.log('image_name', file.name);     // 文件名
    console.log('image_size', file.size);     // 文件大小（字节）
    console.log('image_type', file.type);     // MIME 类型，如 image/jpeg
    try {
        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();
        console.log('PHP 返回结果:', result); //{"success":true,"data":{"shop_id":2,"good_id":46,"order_id":8}}
        if (result.success) {
            //这里代码重复，有时间再优化吧！！！
            const shopId = formData.get('shop_id');
            const goodId = formData.get('good_id');
            if(!shopId && !goodId) {
                // 如果是新店铺：(1).accordion-body 只保留一个.good-item，其他的删除; (2)更新数据内容; (3)最后追加到 #sortableAccordion 开头
                const newAccordionItem = document.querySelector('.accordion-item').cloneNode(true);
                // console.log('newAccordionItem', newAccordionItem);
                newAccordionItem.setAttribute('data-id', result.data.shop_id);
                newAccordionItem.querySelector('.accordion-button').setAttribute('data-bs-target', '#collapse' + result.data.shop_id);
                newAccordionItem.querySelector('.accordion-button').setAttribute('aria-controls', 'collapse' + result.data.shop_id);
                newAccordionItem.querySelector('.accordion-collapse').setAttribute('id', 'collapse' + result.data.shop_id);
                newAccordionItem.querySelector('.shop-address').textContent = formData.get('name') + '@' + formData.get('address');
                const newGoodItem = document.querySelector('.good-item').cloneNode(true);
                // console.log('newGoodItem', newGoodItem);
                newGoodItem.setAttribute('data-id', result.data.good_id);
                newGoodItem.setAttribute('data-order', formData.get('order_content'));
                newGoodItem.querySelector('.card-img-top').setAttribute('src', imgSrcImageEl.getAttribute('src'));
                newGoodItem.querySelector('.card-text').textContent = formData.get('code');
                newAccordionItem.querySelector('.accordion-body').innerHTML = newGoodItem.outerHTML; // 只保留一个商品
                document.getElementById('sortableAccordion').appendChild(newAccordionItem);
                newAccordionItem.scrollIntoView({ behavior: 'smooth', block: 'start' })
                // console.log('accordion-button:', newAccordionItem.querySelector('.accordion-button'));
                newAccordionItem.querySelector('.accordion-collapse').classList.remove('show'); // 确保按钮是可见的
                newAccordionItem.querySelector('.accordion-button').click();
            }else if(shopId && !goodId) {
                // 如果是新商品，clone .good-item dom, 然后追加到 对应的 .accordion-body 中
                const accordionItem = document.querySelector(`.accordion-item[data-id="${shopId}"]`);
                accordionItem.querySelector('.shop-address').textContent = formData.get('name') + '@' + formData.get('address'); // 更新店铺信息
                const newGoodItem = accordionItem.querySelector('.good-item').cloneNode(true);
                newGoodItem.setAttribute('data-id', result.data.good_id);
                newGoodItem.setAttribute('data-order', formData.get('order_content'));
                newGoodItem.querySelector('.card-img-top').setAttribute('src', imgSrcImageEl.getAttribute('src'));
                newGoodItem.querySelector('.card-text').textContent = formData.get('code');
                accordionItem.querySelector('.accordion-body').appendChild(newGoodItem);
            }else if(shopId && goodId) {
                // 更新数据内容！
                const accordionItem = document.querySelector(`.accordion-item[data-id="${shopId}"]`);
                accordionItem.querySelector('.shop-address').textContent = formData.get('name') + '@' + formData.get('address'); // 更新店铺信息
                const goodItem = accordionItem.querySelector(`.good-item[data-id="${goodId}"]`);
                goodItem.setAttribute('data-order', formData.get('order_content'));
                goodItem.querySelector('.card-img-top').setAttribute('src', imgSrcImageEl.getAttribute('src'));
                goodItem.querySelector('.card-text').textContent = formData.get('code');
            }   
        } else {
            alert(result.msg || '保存失败');
        }
    } catch (error) {
        console.error('请求失败:', error);
        alert('网络错误，请稍后重试');
    }

    // 提交成功后关闭模态框：data-bs-dismiss="modal" 自动关闭了，不需要了！
    // const modal = bootstrap.Modal.getInstance(document.getElementById('yourModalId'));
    // modal.hide();
});





