<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Danh Mục Dịch Vụ Kiểm Toán - AASC");

Loader::includeModule('iblock');

// Triển khai Tagged Cache chuẩn D7 kết nối Redis
$cache = Application::getInstance()->getCache();
$taggedCache = Application::getInstance()->getTaggedCache();

$cacheTime = 3600;
$cacheId = 'aasc_services_list';
$cacheDir = '/aasc/services';

$services = [];

if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
    // Lấy dữ liệu trực tiếp từ Cache RAM / Redis
    $services = $cache->getVars();
} elseif ($cache->startDataCache()) {
    // Đăng ký thẻ định danh cho khối cache này
    $taggedCache->startTagCache($cacheDir);

    $iblock = \CIBlock::GetList([], ['TYPE' => 'services', '=CODE' => 'audit_services'])->Fetch();
    $iblockId = $iblock ? (int)$iblock['ID'] : 16;

    // Gắn tag iblock_id_16
    $taggedCache->registerTag('iblock_id_' . $iblockId);

    // Truy vấn dữ liệu từ MySQL qua D7 ORM
    $res = ElementTable::getList([
        'select' => ['ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'SORT'],
        'filter' => [
            '=IBLOCK_ID' => $iblockId,
            '=ACTIVE'    => 'Y',
        ],
        'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
    ]);
    $services = $res->fetchAll();

    $taggedCache->endTagCache();
    $cache->endDataCache($services);
}
?>

<div class="services-page-container">
    <div class="services-page-header">
        <h2 class="services-page-title">Dịch Vụ Kiểm Toán Chuyên Nghiệp</h2>
        <p class="services-page-desc">Hãng Kiểm toán AASC cung cấp các giải pháp kiểm toán, quyết toán dự án, thẩm định giá và tư vấn thuế đạt chuẩn mực quốc gia.</p>
    </div>

    <div class="services-cards-grid">
        <?php foreach ($services as $item): ?>
            <div class="service-card">
                <h3 class="service-card-title"><?= htmlspecialcharsbx($item['NAME']) ?></h3>
                <p class="service-card-text"><?= htmlspecialcharsbx($item['PREVIEW_TEXT']) ?></p>
                <a href="/portal/request/?service=<?= (int)$item['ID'] ?>" class="service-card-link">Yêu cầu báo giá &rarr;</a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="services-cache-info">
        <strong>Thông tin kỹ thuật (Cache Engine):</strong> Dữ liệu trang được quản lý bởi cơ chế <code>Tagged Cache (taggedCache)</code> kết nối bộ đệm Redis. Khi Quản trị viên cập nhật dịch vụ trong IBlock, hệ thống tự động xóa thẻ <code>iblock_id_16</code> để nạp nội dung mới tức thì mà không gây tải cho cơ sở dữ liệu MySQL.
    </div>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
