<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Aasc\Audit\Model\AuditRequestTable;
use Bitrix\Iblock\ElementTable;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER, $APPLICATION;
if (!is_object($USER) || !$USER->IsAuthorized()) {
    LocalRedirect("/portal/auth/?backurl=" . urlencode($APPLICATION->GetCurPageParam()));
    die();
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Hồ Sơ Yêu Cầu Kiểm Toán Của Tôi - AASC");

Asset::getInstance()->addCss('/portal/my-requests/style.css');
Asset::getInstance()->addJs('/portal/my-requests/script.js');

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

// Đăng ký theo dõi toàn bộ hồ sơ của người dùng qua kênh Push & Pull
if (!empty($requests) && Loader::includeModule('pull')) {
    foreach ($requests as $r) {
        \CPullWatch::Add($userId, 'AASC_AUDIT_REQUEST_' . (int)$r['ID'], true);
    }
}
?>

<div class="requests-container">
    <div class="requests-header">
        <div>
            <h2 class="requests-title">Danh Sách Hồ Sơ Kiểm Toán Của Bạn</h2>
            <p class="requests-subtitle">Theo dõi trạng thái thẩm định, dự toán chi phí và tiến độ kiểm toán trực tiếp từ chuyên viên AASC.</p>
        </div>
        <div>
            <a href="/portal/request/" class="btn-create-request">
                + Gửi Yêu Cầu Mới
            </a>
        </div>
    </div>

    <?php if (empty($requests)): ?>
        <div class="requests-empty-card">
            <p class="requests-empty-text">Bạn chưa có hồ sơ yêu cầu kiểm toán nào được ghi nhận trên hệ thống.</p>
            <a href="/portal/request/" class="btn-first-request">
                Gửi Hồ Sơ Đầu Tiên
            </a>
        </div>
    <?php else: ?>
        <div class="requests-table-card">
            <div class="requests-table-scroll">
                <table class="requests-table" id="myRequestsTable">
                    <thead>
                        <tr class="requests-table-head">
                            <th class="requests-th">Mã hồ sơ</th>
                            <th class="requests-th">Doanh nghiệp & MST</th>
                            <th class="requests-th">Dịch vụ yêu cầu</th>
                            <th class="requests-th">Phí dự toán</th>
                            <th class="requests-th">Ngày gửi</th>
                            <th class="requests-th">Trạng thái xử lý</th>
                            <th class="requests-th">Mã CRM</th>
                            <th class="requests-th text-center">Thao tác</th>
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

                            // Xác định nhãn trạng thái và CSS class tương ứng theo tiến trình nghiệp vụ
                            if ($dealId > 0 && !empty($dealStage)) {
                                if ($dealStage === 'C1:WON' || $dealStage === 'WON') {
                                    $statusLabel = 'Đã phát hành báo cáo (VSA 700)';
                                    $badgeClass = 'badge-status-won';
                                } elseif (in_array($dealStage, ['C1:REVIEW_DIRECTOR', 'C1:REVIEW_MANAGER'], true)) {
                                    $statusLabel = 'Soát xét hồ sơ kiểm toán';
                                    $badgeClass = 'badge-status-review';
                                } elseif ($dealStage === 'C1:FIELDWORK' || $dealStage === 'EXECUTING') {
                                    $statusLabel = 'Kiểm toán thực địa';
                                    $badgeClass = 'badge-status-fieldwork';
                                } else {
                                    $statusLabel = 'Ký HĐ & Lập kế hoạch';
                                    $badgeClass = 'badge-status-preparation';
                                }
                            } elseif ($status === 'CONTRACT_SIGNED') {
                                $statusLabel = 'Đã đồng ý ký HĐ (Chờ phân công)';
                                $badgeClass = 'badge-status-signed';
                            } elseif (in_array($leadStatus, ['PROCESSED', 'APPROVED', 'PROPOSAL_SENT'], true)) {
                                $statusLabel = 'Báo giá sẵn sàng (Chờ ký HĐ)';
                                $badgeClass = 'badge-status-quote';
                            } elseif ($leadStatus === 'IN_PROCESS') {
                                $statusLabel = 'Đang thẩm định & Lập dự toán';
                                $badgeClass = 'badge-status-process';
                            } elseif ($status === 'REJECTED' || $leadStatus === 'JUNK') {
                                $statusLabel = 'Từ chối';
                                $badgeClass = 'badge-status-rejected';
                            } else {
                                $statusLabel = 'Đang tiếp nhận';
                                $badgeClass = 'badge-status-default';
                            }

                            $serviceName = $servicesMap[(int)$item['SERVICE_ID']] ?? 'Kiểm toán Báo cáo tài chính';
                        ?>
                            <tr class="requests-row">
                                <td class="requests-td requests-id-col">#<?= (int)$item['ID'] ?></td>
                                <td class="requests-td">
                                    <div class="requests-company-name"><?= htmlspecialcharsbx($item['COMPANY_NAME']) ?></div>
                                    <div class="requests-tax-code">MST: <?= htmlspecialcharsbx($item['TAX_CODE']) ?></div>
                                </td>
                                <td class="requests-td"><?= htmlspecialcharsbx($serviceName) ?></td>
                                <td class="requests-td requests-fee-col">
                                    <?= $opp > 0 ? number_format($opp, 0, ',', '.') . ' VNĐ' : '<span class="requests-fee-waiting">Chờ dự toán</span>' ?>
                                </td>
                                <td class="requests-td requests-date-col">
                                    <?= $item['CREATED_AT'] instanceof \Bitrix\Main\Type\DateTime ? $item['CREATED_AT']->format('d/m/Y H:i') : '' ?>
                                </td>
                                <td class="requests-td">
                                    <span class="badge-status <?= $badgeClass ?>">
                                        <?= htmlspecialcharsbx($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="requests-td requests-crm-col">
                                    <?php if ($dealId > 0): ?>
                                        <span class="requests-crm-deal">Deal #<?= $dealId ?></span>
                                    <?php elseif ($leadId > 0): ?>
                                        Lead #<?= $leadId ?>
                                    <?php else: ?>
                                        <span class="requests-fee-waiting">Đang tạo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="requests-td text-center">
                                    <a href="/portal/my-requests/<?= (int)$item['ID'] ?>/" class="btn-view-detail">
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
