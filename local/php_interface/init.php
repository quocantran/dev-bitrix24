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

// Bắn thông báo nội bộ và đẩy real-time Push server khi có Lead mới
$eventManager->addEventHandler(
    'crm',
    'OnAfterCrmLeadAdd',
    ['\Aasc\Audit\Handler\LeadApprovalHandler', 'onAfterLeadAdd']
);

// Cách ly tài khoản Khách hàng Portal khỏi mạng nội bộ Intranet
$eventManager->addEventHandler(
    'main',
    'OnBeforeProlog',
    ['\Aasc\Audit\Handler\PortalAccessHandler', 'onBeforeProlog']
);
