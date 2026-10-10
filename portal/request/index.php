<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER, $APPLICATION;
if (!is_object($USER) || !$USER->IsAuthorized()) {
    LocalRedirect("/portal/auth/?backurl=" . urlencode($APPLICATION->GetCurPageParam()));
    die();
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Gửi Yêu Cầu Kiểm Toán - AASC");
?>

<div class="portal-request-wrapper">
    <?php
    $APPLICATION->IncludeComponent(
        "aasc:audit.request",
        ".default",
        [],
        false
    );
    ?>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
