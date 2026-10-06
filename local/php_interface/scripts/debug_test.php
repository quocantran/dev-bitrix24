<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/html";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Aasc\Audit\Model\AuditRequestTable;
use Aasc\Audit\Service\CrmBridgeService;

try {
    Loader::includeModule("crm");
    Loader::includeModule("iblock");

    echo "Testing AuditRequestTable::add...\n";
    $testData = [
        'COMPANY_NAME'   => 'Công ty Cổ phần Sữa Ba Vì',
        'TAX_CODE'       => '0101234567',
        'ANNUAL_REVENUE' => 85000000000.0,
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
        echo "AuditRequestTable OK ID: " . $addRes->getId() . "\n";
    } else {
        echo "AuditRequestTable Error: " . implode(', ', $addRes->getErrorMessages()) . "\n";
    }

    echo "Testing CrmBridgeService::createLeadFromRequest...\n";
    $leadId = CrmBridgeService::createLeadFromRequest($addRes->getId(), $testData);
    echo "Lead ID: " . $leadId . "\n";

} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
}
