<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Page\Asset;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
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
                <a href="<?=SITE_DIR?>">AASC AUDIT & CONSULTING</a>
            </div>
            <div class="header-contacts">
                <?php
                // Vùng nhúng tĩnh cho số điện thoại hotline
                $APPLICATION->IncludeComponent(
                    "bitrix:main.include",
                    "",
                    [
                        "AREA_FILE_SHOW" => "file",
                        "PATH" => SITE_DIR . "include/hotline.php",
                        "EDIT_TEMPLATE" => ""
                    ]
                );
                ?>
            </div>
        </div>
    </header>

    <!-- Bắt đầu vùng làm việc chính (#WORK_AREA#) -->
    <main class="main-content-area">
        <div class="page-container">
            <h1 class="page-main-heading"><?php $APPLICATION->ShowTitle(false); ?></h1>
