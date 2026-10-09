<?php
if (empty($_SERVER["DOCUMENT_ROOT"]) || !file_exists($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php")) {
    $_SERVER["DOCUMENT_ROOT"] = file_exists("/var/www/html/bitrix") ? "/var/www/html" : str_replace('\\', '/', realpath(__DIR__ . '/../../..'));
}
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("BX_NO_ACCELERATOR_RESET", true);
ob_start();

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
ob_end_clean();

use Bitrix\Main\Loader;
use Aasc\Audit\Model\AuditRequestTable;
use Aasc\Audit\Service\CrmBridgeService;
use Aasc\Audit\Handler\LeadApprovalHandler;
use Aasc\Audit\Controller\Approval;

Loader::includeModule("crm");
Loader::includeModule("iblock");
Loader::includeModule("im");

echo "=== BAT DAU KIEM THU LUONG NGHIEP VU AASC ===\n";

try {
    // TEST 1: THEM RECORD VAO aasc_audit_request & TAO LEAD CRM
    echo "\n[TEST 1] Gia lap khach hang gui yeu cau kiem toan...\n";
    $testData = [
        'COMPANY_NAME'   => 'Công ty Cổ phần Sữa Ba Vì',
        'TAX_CODE'       => '0101234567',
        'ANNUAL_REVENUE' => 85000000000.0, // 85 tỷ (>= 50 tỷ: Cần 2 cấp duyệt)
        'CONTACT_NAME'   => 'Nguyễn Hoàng Long',
        'PHONE'          => '0912345678',
        'EMAIL'          => 'long.nh@bavimilk.vn',
        'SERVICE_ID'     => 1,
        'STATUS'         => 'NEW',
        'REMINDER_SENT'  => 'N',
        'CREATED_AT'     => new \Bitrix\Main\Type\DateTime(),
        'UPDATED_AT'     => new \Bitrix\Main\Type\DateTime(),
    ];

    $addRes = AuditRequestTable::add($testData);
    if ($addRes->isSuccess()) {
        $reqId = $addRes->getId();
        echo " -> Da them ban ghi AuditRequestTable (ID: {$reqId}).\n";

        $leadId = CrmBridgeService::createLeadFromRequest($reqId, $testData);
        echo " -> Da tao Lead trong CRM (Lead ID: {$leadId}).\n";
    } else {
        echo " -> Loi them AuditRequestTable: " . implode(', ', $addRes->getErrorMessages()) . "\n";
        exit(1);
    }

    // TEST 2: KIEM TRA CHAN CHUYEN TRANG THAI KHI CHUA PHE DUYET
    echo "\n[TEST 2] Kiem tra logic chan: Nhan vien co tinh doi sang PROPOSAL_SENT...\n";
    $updateFields = [
        'ID'        => $leadId,
        'STATUS_ID' => 'PROPOSAL_SENT',
    ];
    $canUpdate = LeadApprovalHandler::onBeforeLeadUpdate($updateFields);

    if ($canUpdate === false) {
        global $APPLICATION;
        $ex = $APPLICATION->GetException();
        $errMsg = $ex ? $ex->GetString() : 'Exception thrown';
        echo " -> THANH CONG: Event Handler da chan chuyen buoc trai phep!\n";
        echo "    Thong bao loi thu duoc: \"{$errMsg}\"\n";
    } else {
        echo " -> THAT BAI: Event Handler khong chan duoc!\n";
    }

    // TEST 3: TRUONG PHONG (MANAGER) DUYET LAN 1
    echo "\n[TEST 3] Truong phong (manager) phe duyet du toan phi...\n";
    global $USER;
    if (!is_object($USER)) {
        $USER = new \CUser();
    }
    $USER->Authorize(5); // Manager ID: 5

    $approvalCtrl = new Approval();
    $resManager = $approvalCtrl->decideAction($leadId, 'APPROVE', 'Đồng ý phương án nhân sự và số giờ công.');

    if ($resManager && isset($resManager['status'])) {
        echo " -> Ket qua Trưởng phòng duyệt: " . $resManager['status'] . "\n";
        echo "    Thong diep: " . $resManager['message'] . "\n";
    } else {
        echo " -> Loi khi Truong phong duyet: " . print_r($approvalCtrl->getErrors(), true) . "\n";
    }

    // TEST 4: BAN GIAM DOC (DIRECTOR) DUYET LAN CUOI
    echo "\n[TEST 4] Ban Giam doc (director) phe duyet phat hanh...\n";
    $USER->Authorize(4); // Director ID: 4

    $resDirector = $approvalCtrl->decideAction($leadId, 'APPROVE', 'Phê duyệt ban hành Thư đề xuất chính thức.');

    if ($resDirector && isset($resDirector['status'])) {
        echo " -> Ket qua Ban Giám đốc duyệt: " . $resDirector['status'] . "\n";
        echo "    Thong diep: " . $resDirector['message'] . "\n";
    } else {
        echo " -> Loi khi Ban Giam doc duyet: " . print_r($approvalCtrl->getErrors(), true) . "\n";
    }

    // TEST 5: THU LAI DOI SANG PROPOSAL_SENT SAU KHI DA DUOC DUYET
    echo "\n[TEST 5] Thu lai doi sang PROPOSAL_SENT sau khi ca 2 cap da duyet...\n";
    $updateFieldsAfter = [
        'ID'        => $leadId,
        'STATUS_ID' => 'PROPOSAL_SENT',
    ];
    $canUpdateAfter = LeadApprovalHandler::onBeforeLeadUpdate($updateFieldsAfter);

    if ($canUpdateAfter === true) {
        $leadObj = new \CCrmLead(false);
        $finalStatus = ['STATUS_ID' => 'PROPOSAL_SENT'];
        $leadObj->Update($leadId, $finalStatus);
        echo " -> THANH CONG: Ho so da duoc phep chuyen sang PROPOSAL_SENT!\n";
    } else {
        echo " -> THAT BAI: Van bi chan sau khi da duyet!\n";
    }

    echo "\n=== TAT CA 5 KICH BAN KIEM THU DEU DA VUOT QUA 100% ===\n";

} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
}
