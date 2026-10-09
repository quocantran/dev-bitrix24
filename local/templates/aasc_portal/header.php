<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Page\Asset;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

global $USER;
$isAuthorized = is_object($USER) && $USER->IsAuthorized();
$userFullName = $isAuthorized ? ($USER->GetFormattedName() ?: $USER->GetLogin()) : '';
$curPage = $APPLICATION->GetCurPage(false);
?>
<!DOCTYPE html>
<html lang="<?=LANGUAGE_ID?>">
<head>
    <meta charset="<?=LANG_CHARSET?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $APPLICATION->ShowTitle(); ?></title>
    <?php
    // Nạp các thẻ meta hệ thống, canonical, CSS/JS cơ sở
    $APPLICATION->ShowHead();

    // Nạp tài nguyên chuẩn D7 Asset Manager
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/template_styles.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/styles.css");
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/js/portal.js");
    Asset::getInstance()->addString("<link rel='preconnect' href='https://fonts.googleapis.com'>");
    ?>
</head>
<body>
    <!-- Thanh công cụ quản trị Bitrix (chỉ hiển thị khi đăng nhập admin) -->
    <div id="panel"><?php $APPLICATION->ShowPanel(); ?></div>

    <header class="site-header">
        <div class="header-inner">
            <div class="brand-logo">
                <a href="/portal/">AASC AUDIT & CONSULTING</a>
            </div>

            <nav class="portal-main-nav">
                <a href="/portal/" class="<?= $curPage === '/portal/' || $curPage === '/portal/index.php' ? 'active' : '' ?>">Trang Chủ</a>
                <a href="/portal/services/" class="<?= strpos($curPage, '/portal/services/') === 0 ? 'active' : '' ?>">Dịch Vụ</a>
                <a href="/portal/request/" class="<?= strpos($curPage, '/portal/request/') === 0 ? 'active' : '' ?>">Gửi Yêu Cầu</a>
                <?php if ($isAuthorized): ?>
                    <a href="/portal/my-requests/" class="<?= strpos($curPage, '/portal/my-requests/') === 0 ? 'active' : '' ?>">Hồ Sơ Của Tôi</a>
                <?php endif; ?>
            </nav>

            <div class="header-user-controls">
                <?php if ($isAuthorized): ?>
                    <?php if (class_exists('\Aasc\Audit\Handler\PortalAccessHandler') && \Aasc\Audit\Handler\PortalAccessHandler::isInternalUser()): ?>
                        <a href="/stream/" class="btn-intranet-link" title="Chuyển sang Mạng nội bộ / CRM">Vào Intranet &rarr;</a>
                    <?php endif; ?>
                    <span class="user-greeting">Xin chào, <strong><?= htmlspecialcharsbx($userFullName) ?></strong></span>
                    <a href="/portal/auth/?logout=yes" class="btn-auth-logout">Đăng Xuất</a>
                <?php else: ?>
                    <a href="/portal/auth/?mode=login" class="btn-auth-login">Đăng Nhập</a>
                    <a href="/portal/auth/?mode=register" class="btn-auth-register">Đăng Ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Bắt đầu vùng làm việc chính (#WORK_AREA#) -->
    <main class="main-content-area">
        <div class="page-container">
            <h1 class="page-main-heading"><?php $APPLICATION->ShowTitle(false); ?></h1>
