<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Gửi Yêu Cầu Kiểm Toán - AASC");

global $USER;
if (!is_object($USER) || !$USER->IsAuthorized()) {
    LocalRedirect("/portal/auth/?backurl=" . urlencode($APPLICATION->GetCurPageParam()));
    die();
}
?>

<div style="padding: 1.5rem 0;">
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
