<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Danh Mục Dịch Vụ Kiểm Toán - AASC");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

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

<div style="max-width: 960px; margin: 0 auto; padding: 1.5rem 0;">
    <div style="border-bottom: 2px solid #1a365d; padding-bottom: 1rem; margin-bottom: 2rem;">
        <h2 style="color: #1a365d; margin: 0 0 0.5rem 0; font-size: 1.75rem;">Dịch Vụ Kiểm Toán Chuyên Nghiệp</h2>
        <p style="color: #4a5568; margin: 0;">Hãng Kiểm toán AASC cung cấp các giải pháp kiểm toán, quyết toán dự án, thẩm định giá và tư vấn thuế đạt chuẩn mực quốc gia.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem;">
        <?php foreach ($services as $item): ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="color: #2b6cb0; margin: 0 0 0.75rem 0; font-size: 1.2rem;"><?= htmlspecialcharsbx($item['NAME']) ?></h3>
                <p style="color: #4a5568; font-size: 0.95rem; line-height: 1.5; margin: 0 0 1rem 0;"><?= htmlspecialcharsbx($item['PREVIEW_TEXT']) ?></p>
                <a href="/portal/request/?service=<?= (int)$item['ID'] ?>" style="display: inline-block; color: #1a365d; font-weight: 600; text-decoration: none; font-size: 0.9rem;">Yêu cầu báo giá &rarr;</a>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="background: #ebf8ff; border: 1px solid #bee3f8; border-radius: 6px; padding: 1rem; font-size: 0.85rem; color: #2c5282;">
        <strong>Thông tin kỹ thuật (Cache Engine):</strong> Dữ liệu trang được quản lý bởi cơ chế <code>Tagged Cache (taggedCache)</code> kết nối bộ đệm Redis. Khi Quản trị viên cập nhật dịch vụ trong IBlock, hệ thống tự động xóa thẻ <code>iblock_id_16</code> để nạp nội dung mới tức thì mà không gây tải cho cơ sở dữ liệu MySQL.
    </div>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
