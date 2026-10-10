<?php
if (empty($_SERVER["DOCUMENT_ROOT"])) {
    $docRoot = dirname(__DIR__, 3);
    $_SERVER["DOCUMENT_ROOT"] = file_exists($docRoot . "/bitrix/modules/main/include/prolog_before.php") ? $docRoot : "/var/www/html";
}
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

\Bitrix\Main\Loader::includeModule("iblock");
\Bitrix\Main\Loader::includeModule("main");

$bs = new \CIBlockSection();

// Tìm root section
$root = \CIBlockSection::GetList([], ['IBLOCK_ID' => 3, 'DEPTH_LEVEL' => 1])->Fetch();
$rootId = $root ? (int)$root['ID'] : 1;

$boardId = $bs->Add([
    'IBLOCK_ID'         => 3,
    'IBLOCK_SECTION_ID' => $rootId,
    'NAME'              => 'Ban Giám đốc',
    'CODE'              => 'board_of_directors',
    'SORT'              => 10,
    'ACTIVE'            => 'Y',
]);
if (!$boardId) {
    echo "Loi tao Ban Giam doc: " . $bs->LAST_ERROR . "\n";
} else {
    echo "Da tao Ban Giam doc ID: " . $boardId . "\n";
}

$auditDeptId = $bs->Add([
    'IBLOCK_ID'         => 3,
    'IBLOCK_SECTION_ID' => $rootId,
    'NAME'              => 'Phòng Kiểm toán 1',
    'CODE'              => 'audit_dept_1',
    'SORT'              => 20,
    'ACTIVE'            => 'Y',
]);
if (!$auditDeptId) {
    echo "Loi tao Phong Kiem toan 1: " . $bs->LAST_ERROR . "\n";
} else {
    echo "Da tao Phong Kiem toan 1 ID: " . $auditDeptId . "\n";
}

// Cập nhật UF_DEPARTMENT cho 4 users:
$userObj = new \CUser();
if ($boardId > 0) {
    $director = \Bitrix\Main\UserTable::getList(['filter' => ['=LOGIN' => 'director']])->fetch();
    if ($director) {
        $userObj->Update($director['ID'], ['UF_DEPARTMENT' => [$boardId]]);
        echo "Cap nhat director vao Ban Giam doc.\n";
    }
}

if ($auditDeptId > 0) {
    $mgr = \Bitrix\Main\UserTable::getList(['filter' => ['=LOGIN' => 'manager']])->fetch();
    if ($mgr) {
        $userObj->Update($mgr['ID'], ['UF_DEPARTMENT' => [$auditDeptId]]);
        echo "Cap nhat manager vao Phong Kiem toan 1.\n";
    }
    $snr = \Bitrix\Main\UserTable::getList(['filter' => ['=LOGIN' => 'senior']])->fetch();
    if ($snr) {
        $userObj->Update($snr['ID'], ['UF_DEPARTMENT' => [$auditDeptId]]);
        echo "Cap nhat senior vao Phong Kiem toan 1.\n";
    }
    $jnr = \Bitrix\Main\UserTable::getList(['filter' => ['=LOGIN' => 'junior']])->fetch();
    if ($jnr) {
        $userObj->Update($jnr['ID'], ['UF_DEPARTMENT' => [$auditDeptId]]);
        echo "Cap nhat junior vao Phong Kiem toan 1.\n";
    }
}
