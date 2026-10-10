<?php
/**
 * /var/www/html/local/php_interface/init.php
 * File nạp tự động toàn cục của Bitrix Framework
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

// 1. Nạp Composer Autoloader và nạp biến môi trường từ file .env (chuẩn 12-Factor App)
$rootPath = dirname(__DIR__, 2);
$composerAutoload = $rootPath . '/vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    $rootPath = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $composerAutoload = $rootPath . '/vendor/autoload.php';
}

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
    if (class_exists(\Dotenv\Dotenv::class) && file_exists($rootPath . '/.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable($rootPath);
        $dotenv->safeLoad();
    }
}

// 2. Nạp Custom Module aasc.audit (Bitrix D7 tự động map PSR-4 Namespace và nạp include.php)
Loader::includeModule('aasc.audit');

// 3. Đăng ký các Event Handlers cho hệ thống CRM
$eventManager = EventManager::getInstance();

// Chặn đổi trạng thái trên LEAD nếu chưa duyệt
$eventManager->addEventHandler(
    'crm',
    'OnBeforeCrmLeadUpdate',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onBeforeLeadUpdate']
);

// Chặn đổi trạng thái trên DEAL (Invoice, In Progress, Won) nếu chưa duyệt
$eventManager->addEventHandler(
    'crm',
    'OnBeforeCrmDealUpdate',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onBeforeDealUpdate']
);

// Chặn tạo DEAL chuyển đổi từ Lead nếu khách hàng chưa ký hợp đồng trên Portal
$eventManager->addEventHandler(
    'crm',
    'OnBeforeCrmDealAdd',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onBeforeDealAdd']
);

// Đồng bộ khi DEAL được khởi tạo từ Lead trong CRM
$eventManager->addEventHandler(
    'crm',
    'OnAfterCrmDealAdd',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onAfterDealAdd']
);

// Bắn thông báo nội bộ và đẩy real-time Push server khi có Lead mới
$eventManager->addEventHandler(
    'crm',
    'OnAfterCrmLeadAdd',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onAfterLeadAdd']
);

// Đẩy cập nhật tiến độ thời gian thực khi trạng thái Lead thay đổi
$eventManager->addEventHandler(
    'crm',
    'OnAfterCrmLeadUpdate',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onAfterLeadUpdate']
);

// Đẩy cập nhật tiến độ thời gian thực khi trạng thái Deal (Kiểm toán thực địa & Soát xét) thay đổi
$eventManager->addEventHandler(
    'crm',
    'OnAfterCrmDealUpdate',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onAfterDealUpdate']
);

// Cách ly tài khoản Khách hàng Portal khỏi mạng nội bộ Intranet
$eventManager->addEventHandler(
    'main',
    'OnBeforeProlog',
    ['\Aasc\Audit\Handler\PortalAccessHandler', 'onBeforeProlog']
);

// Hiển thị thông báo lỗi quy trình trực quan trên giao diện CRM khi cập nhật qua thanh Progress bar
$eventManager->addEventHandler('main', 'OnEpilog', function() {
    global $APPLICATION;
    $curPage = $APPLICATION ? (string)$APPLICATION->GetCurPage(false) : '';
    if (strpos($curPage, '/crm/') === 0 || strpos($curPage, '/bitrix/components/bitrix/crm.') === 0) {
        \Bitrix\Main\Page\Asset::getInstance()->addJs('/local/js/aasc/crm_watcher.js');
    }
});

