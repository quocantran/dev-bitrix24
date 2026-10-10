<?php
if (empty($_SERVER["DOCUMENT_ROOT"])) {
    $docRoot = dirname(__DIR__, 3);
    $_SERVER["DOCUMENT_ROOT"] = file_exists($docRoot . "/bitrix/modules/main/include/prolog_before.php") ? $docRoot : "/var/www/html";
}
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("BX_NO_ACCELERATOR_RESET", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
Loader::includeModule("main");

$cache = \Bitrix\Main\Data\Cache::createInstance();
$cache->cleanDir();

$managedCache = \Bitrix\Main\Application::getInstance()->getManagedCache();
if ($managedCache) {
    $managedCache->cleanAll();
}

$taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
if ($taggedCache) {
    $taggedCache->clearByTag(true);
}

if (function_exists("BXClearCache")) {
    BXClearCache(true);
}

if (Loader::includeModule("pull")) {
    \CPullOptions::SendConfigDie();
}

echo "Bitrix cache cleared successfully.\n";
