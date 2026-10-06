<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);

require('/var/www/html/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loader::includeModule('pull');
print_r(Option::getForModule('pull'));
