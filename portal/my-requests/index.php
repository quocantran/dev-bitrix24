<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Hồ Sơ Yêu Cầu Kiểm Toán Của Tôi - AASC");

global $USER;
if (!is_object($USER) || !$USER->IsAuthorized()) {
    LocalRedirect("/portal/auth/?backurl=" . urlencode($APPLICATION->GetCurPageParam()));
    die();
}

use Bitrix\Main\Loader;
use Aasc\Audit\Model\AuditRequestTable;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('aasc.audit');
Loader::includeModule('iblock');

$userId = (int)$USER->GetID();

// Lấy danh mục dịch vụ để hiển thị tên
$servicesMap = [];
$resServices = ElementTable::getList([
    'select' => ['ID', 'NAME'],
    'filter' => ['=ACTIVE' => 'Y'],
]);
while ($s = $resServices->fetch()) {
    $servicesMap[(int)$s['ID']] = $s['NAME'];
}
$servicesMap[1] = $servicesMap[1] ?? 'Kiểm toán Báo cáo tài chính';
$servicesMap[2] = $servicesMap[2] ?? 'Kiểm toán Quyết toán vốn đầu tư';
$servicesMap[3] = $servicesMap[3] ?? 'Thẩm định giá tài sản';
$servicesMap[4] = $servicesMap[4] ?? 'Tư vấn thuế doanh nghiệp';

// Truy vấn các hồ sơ của riêng người dùng này qua D7 ORM
$requests = AuditRequestTable::getList([
    'filter' => ['=USER_ID' => $userId],
    'order'  => ['CREATED_AT' => 'DESC', 'ID' => 'DESC'],
])->fetchAll();
?>

<div style="max-width: 1060px; margin: 0 auto; padding: 1.5rem 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="color: #1a365d; font-size: 1.6rem; margin: 0 0 0.25rem 0; font-weight: 700;">Danh Sách Hồ Sơ Kiểm Toán Của Bạn</h2>
            <p style="color: #4a5568; margin: 0; font-size: 0.95rem;">Theo dõi trạng thái thẩm định và kết nối CRM trực tiếp từ chuyên viên AASC.</p>
        </div>
        <div>
            <a href="/portal/request/" style="background: #1a365d; color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                + Gửi Yêu Cầu Mới
            </a>
        </div>
    </div>

    <?php if (empty($requests)): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 3rem 2rem; text-align: center;">
            <p style="color: #718096; font-size: 1.05rem; margin-bottom: 1.5rem;">Bạn chưa có hồ sơ yêu cầu kiểm toán nào được ghi nhận trên hệ thống.</p>
            <a href="/portal/request/" style="background: #ed8936; color: #ffffff; padding: 0.75rem 1.5rem; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 1rem;">
                Gửi Hồ Sơ Đầu Tiên
            </a>
        </div>
    <?php else: ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: #edf2f7; color: #2d3748; border-bottom: 2px solid #cbd5e0;">
                            <th style="padding: 0.85rem 1rem;">Mã hồ sơ</th>
                            <th style="padding: 0.85rem 1rem;">Doanh nghiệp & MST</th>
                            <th style="padding: 0.85rem 1rem;">Dịch vụ yêu cầu</th>
                            <th style="padding: 0.85rem 1rem;">Doanh thu</th>
                            <th style="padding: 0.85rem 1rem;">Ngày gửi</th>
                            <th style="padding: 0.85rem 1rem;">Trạng thái xử lý</th>
                            <th style="padding: 0.85rem 1rem;">Mã CRM Lead</th>
                            <th style="padding: 0.85rem 1rem; text-align: center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $item): 
                            $status = (string)$item['STATUS'];
                            $badgeBg = '#edf2f7';
                            $badgeColor = '#4a5568';
                            $statusLabel = 'Đang tiếp nhận';

                            if ($status === 'NEW') {
                                $badgeBg = '#ebf8ff';
                                $badgeColor = '#2b6cb0';
                                $statusLabel = 'Đang tiếp nhận';
                            } elseif ($status === 'WAITING_DIR') {
                                $badgeBg = '#feebc8';
                                $badgeColor = '#c05621';
                                $statusLabel = 'Chờ BGĐ duyệt';
                            } elseif ($status === 'APPROVED') {
                                $badgeBg = '#c6f6d5';
                                $badgeColor = '#22543d';
                                $statusLabel = 'Đã phê duyệt báo giá';
                            } elseif ($status === 'REJECTED') {
                                $badgeBg = '#fed7d7';
                                $badgeColor = '#9b2c2c';
                                $statusLabel = 'Từ chối';
                            }
                            $serviceName = $servicesMap[(int)$item['SERVICE_ID']] ?? 'Kiểm toán tiêu chuẩn';
                        ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 1rem; font-weight: 700; color: #1a365d;">#<?= (int)$item['ID'] ?></td>
                                <td style="padding: 1rem;">
                                    <div style="font-weight: 600; color: #2d3748;"><?= htmlspecialcharsbx($item['COMPANY_NAME']) ?></div>
                                    <div style="font-size: 0.8rem; color: #718096;">MST: <?= htmlspecialcharsbx($item['TAX_CODE']) ?></div>
                                </td>
                                <td style="padding: 1rem; color: #4a5568;"><?= htmlspecialcharsbx($serviceName) ?></td>
                                <td style="padding: 1rem; font-family: monospace; color: #2d3748;">
                                    <?= number_format((float)$item['ANNUAL_REVENUE'], 0, ',', '.') ?> VNĐ
                                </td>
                                <td style="padding: 1rem; color: #718096; font-size: 0.85rem;">
                                    <?= $item['CREATED_AT'] instanceof \Bitrix\Main\Type\DateTime ? $item['CREATED_AT']->format('d/m/Y H:i') : '' ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; display: inline-block;">
                                        <?= htmlspecialcharsbx($statusLabel) ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; font-family: monospace; color: #4a5568;">
                                    <?= !empty($item['CRM_LEAD_ID']) ? '#' . (int)$item['CRM_LEAD_ID'] : 'Đang đồng bộ' ?>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <a href="/portal/my-requests/<?= (int)$item['ID'] ?>/" style="background: #2b6cb0; color: #ffffff; padding: 0.35rem 0.75rem; border-radius: 4px; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-block;">
                                        Xem tiến độ &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
