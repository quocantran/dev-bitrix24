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
Loader::includeModule('crm');

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

// Nạp thông tin CRM Lead và CRM Deal tương ứng để phản ánh chính xác trạng thái thực
$leadIds = array_values(array_filter(array_map(fn($r) => (int)($r['CRM_LEAD_ID'] ?? 0), $requests)));
$dealIds = array_values(array_filter(array_map(fn($r) => (int)($r['CRM_DEAL_ID'] ?? 0), $requests)));

$leadsMap = [];
if (!empty($leadIds) && Loader::includeModule('crm')) {
    $resLeads = \Bitrix\Crm\LeadTable::getList([
        'filter' => ['@ID' => $leadIds],
        'select' => ['ID', 'STATUS_ID', 'OPPORTUNITY', 'CURRENCY_ID'],
    ]);
    while ($l = $resLeads->fetch()) {
        $leadsMap[(int)$l['ID']] = $l;
    }
}

$dealsMap = [];
if (!empty($dealIds) && Loader::includeModule('crm')) {
    $resDeals = \Bitrix\Crm\DealTable::getList([
        'filter' => ['@ID' => $dealIds],
        'select' => ['ID', 'STAGE_ID', 'OPPORTUNITY', 'CURRENCY_ID', 'CATEGORY_ID'],
    ]);
    while ($d = $resDeals->fetch()) {
        $dealsMap[(int)$d['ID']] = $d;
    }
}
?>

<div style="max-width: 1100px; margin: 0 auto; padding: 1.5rem 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="color: #1a365d; font-size: 1.6rem; margin: 0 0 0.25rem 0; font-weight: 700;">Danh Sách Hồ Sơ Kiểm Toán Của Bạn</h2>
            <p style="color: #4a5568; margin: 0; font-size: 0.95rem;">Theo dõi trạng thái thẩm định, dự toán chi phí và tiến độ kiểm toán trực tiếp từ chuyên viên AASC.</p>
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
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;" id="myRequestsTable">
                    <thead>
                        <tr style="background: #edf2f7; color: #2d3748; border-bottom: 2px solid #cbd5e0;">
                            <th style="padding: 0.85rem 1rem;">Mã hồ sơ</th>
                            <th style="padding: 0.85rem 1rem;">Doanh nghiệp & MST</th>
                            <th style="padding: 0.85rem 1rem;">Dịch vụ yêu cầu</th>
                            <th style="padding: 0.85rem 1rem;">Phí dự toán</th>
                            <th style="padding: 0.85rem 1rem;">Ngày gửi</th>
                            <th style="padding: 0.85rem 1rem;">Trạng thái xử lý</th>
                            <th style="padding: 0.85rem 1rem;">Mã CRM</th>
                            <th style="padding: 0.85rem 1rem; text-align: center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="myRequestsTableBody">
                        <?php foreach ($requests as $item): 
                            $status = (string)$item['STATUS'];
                            $leadId = (int)($item['CRM_LEAD_ID'] ?? 0);
                            $dealId = (int)($item['CRM_DEAL_ID'] ?? 0);
                            $leadRow = $leadsMap[$leadId] ?? null;
                            $dealRow = $dealsMap[$dealId] ?? null;

                            $leadStatus = $leadRow ? (string)($leadRow['STATUS_ID'] ?? '') : $status;
                            $dealStage = $dealRow ? (string)($dealRow['STAGE_ID'] ?? '') : '';
                            $opp = (float)($dealRow['OPPORTUNITY'] ?? ($leadRow['OPPORTUNITY'] ?? 0));

                            // Xác định chính xác nhãn trạng thái và màu sắc tương ứng theo tiến trình nghiệp vụ
                            if ($dealId > 0 && !empty($dealStage)) {
                                if ($dealStage === 'C1:WON' || $dealStage === 'WON') {
                                    $statusLabel = 'Đã phát hành báo cáo (VSA 700)';
                                    $badgeBg = '#d1fae5';
                                    $badgeColor = '#065f46';
                                } elseif (in_array($dealStage, ['C1:REVIEW_DIRECTOR', 'C1:REVIEW_MANAGER'], true)) {
                                    $statusLabel = 'Soát xét hồ sơ kiểm toán';
                                    $badgeBg = '#f3e8ff';
                                    $badgeColor = '#6b21a8';
                                } elseif ($dealStage === 'C1:FIELDWORK' || $dealStage === 'EXECUTING') {
                                    $statusLabel = 'Kiểm toán thực địa';
                                    $badgeBg = '#e0e7ff';
                                    $badgeColor = '#3730a3';
                                } else {
                                    $statusLabel = 'Ký HĐ & Lập kế hoạch';
                                    $badgeBg = '#fef3c7';
                                    $badgeColor = '#92400e';
                                }
                            } elseif ($status === 'CONTRACT_SIGNED') {
                                $statusLabel = 'Đã đồng ý ký HĐ (Chờ phân công)';
                                $badgeBg = '#dbeafe';
                                $badgeColor = '#1e40af';
                            } elseif (in_array($leadStatus, ['PROCESSED', 'APPROVED', 'PROPOSAL_SENT'], true)) {
                                $statusLabel = 'Báo giá sẵn sàng (Chờ ký HĐ)';
                                $badgeBg = '#c6f6d5';
                                $badgeColor = '#22543d';
                            } elseif ($leadStatus === 'IN_PROCESS') {
                                $statusLabel = 'Đang thẩm định & Lập dự toán';
                                $badgeBg = '#feebc8';
                                $badgeColor = '#c05621';
                            } elseif ($status === 'REJECTED' || $leadStatus === 'JUNK') {
                                $statusLabel = 'Từ chối';
                                $badgeBg = '#fed7d7';
                                $badgeColor = '#9b2c2c';
                            } else {
                                $statusLabel = 'Đang tiếp nhận';
                                $badgeBg = '#ebf8ff';
                                $badgeColor = '#2b6cb0';
                            }

                            $serviceName = $servicesMap[(int)$item['SERVICE_ID']] ?? 'Kiểm toán Báo cáo tài chính';
                        ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 1rem; font-weight: 700; color: #1a365d;">#<?= (int)$item['ID'] ?></td>
                                <td style="padding: 1rem;">
                                    <div style="font-weight: 600; color: #2d3748;"><?= htmlspecialcharsbx($item['COMPANY_NAME']) ?></div>
                                    <div style="font-size: 0.8rem; color: #718096;">MST: <?= htmlspecialcharsbx($item['TAX_CODE']) ?></div>
                                </td>
                                <td style="padding: 1rem; color: #4a5568;"><?= htmlspecialcharsbx($serviceName) ?></td>
                                <td style="padding: 1rem; font-family: monospace; color: #2d3748; font-weight: 600;">
                                    <?= $opp > 0 ? number_format($opp, 0, ',', '.') . ' VNĐ' : '<span style="color:#a0aec0;font-weight:normal;font-style:italic;">Chờ dự toán</span>' ?>
                                </td>
                                <td style="padding: 1rem; color: #718096; font-size: 0.85rem;">
                                    <?= $item['CREATED_AT'] instanceof \Bitrix\Main\Type\DateTime ? $item['CREATED_AT']->format('d/m/Y H:i') : '' ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; display: inline-block;">
                                        <?= htmlspecialcharsbx($statusLabel) ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; font-family: monospace; color: #4a5568; font-size: 0.85rem;">
                                    <?php if ($dealId > 0): ?>
                                        <span style="color: #2b6cb0; font-weight: 600;">Deal #<?= $dealId ?></span>
                                    <?php elseif ($leadId > 0): ?>
                                        Lead #<?= $leadId ?>
                                    <?php else: ?>
                                        <span style="color:#a0aec0;font-style:italic;">Đang tạo</span>
                                    <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tự động kiểm tra cập nhật trạng thái thời gian thực mỗi 5 giây
    setInterval(function() {
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTbody = doc.querySelector('#myRequestsTableBody');
                const curTbody = document.querySelector('#myRequestsTableBody');
                if (newTbody && curTbody && newTbody.innerHTML.trim() !== curTbody.innerHTML.trim()) {
                    curTbody.innerHTML = newTbody.innerHTML;
                }
            })
            .catch(() => {});
    }, 5000);
});
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
