<?php
/**
 * Script nạp dữ liệu nền tảng (Seeding Platform Data)
 * Khởi tạo: Table aasc_audit_request, Departments, Users, User Fields, IBlock Services
 */
$_SERVER["DOCUMENT_ROOT"] = "/var/www/html";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("BX_NO_ACCELERATOR_RESET", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

Loader::includeModule("main");
Loader::includeModule("crm");
Loader::includeModule("iblock");

$connection = Application::getConnection();

echo "=== BAT DAU KHOI TAO DU LIEU NEN TANG AASC ===\n";

// 1. TAO BANG DATABASE aasc_audit_request
echo "[1/5] Kiem tra va tao bang aasc_audit_request...\n";
$sqlTable = "CREATE TABLE IF NOT EXISTS aasc_audit_request (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    COMPANY_NAME VARCHAR(255) NOT NULL,
    TAX_CODE VARCHAR(20) NOT NULL,
    ANNUAL_REVENUE DECIMAL(18,2) DEFAULT 0.00,
    CONTACT_NAME VARCHAR(255) NOT NULL,
    PHONE VARCHAR(50) NOT NULL,
    EMAIL VARCHAR(255) NOT NULL,
    SERVICE_ID INT DEFAULT 0,
    STATUS VARCHAR(50) DEFAULT 'NEW',
    REMINDER_SENT CHAR(1) DEFAULT 'N',
    CRM_LEAD_ID INT DEFAULT 0,
    CREATED_AT DATETIME NOT NULL,
    UPDATED_AT DATETIME NULL,
    INDEX idx_status_created (STATUS, CREATED_AT),
    INDEX idx_tax_code (TAX_CODE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$connection->queryExecute($sqlTable);
echo " -> Bang aasc_audit_request da san sang.\n";

// 2. KHOI TAO PHONG BAN (DEPARTMENTS) TRONG IBLOCK STRUCTURE
echo "[2/5] Kiem tra phong ban trong IBlock structure...\n";
$deptIblockId = 3; // IBlock Departments mặc định trong Bitrix24
$deptSection = new \CIBlockSection();

// Tìm hoặc tạo Ban Giám đốc
$resBoard = \CIBlockSection::GetList([], ['IBLOCK_ID' => $deptIblockId, '=NAME' => 'Ban Giám đốc']);
if ($row = $resBoard->Fetch()) {
    $boardDeptId = (int)$row['ID'];
} else {
    $boardDeptId = (int)$deptSection->Add([
        'IBLOCK_ID' => $deptIblockId,
        'NAME'      => 'Ban Giám đốc',
        'CODE'      => 'board_of_directors',
        'SORT'      => 10,
    ]);
}
echo " -> Ban Giam doc ID: " . $boardDeptId . "\n";

// Tìm hoặc tạo Phòng Kiểm toán 1
$resAudit = \CIBlockSection::GetList([], ['IBLOCK_ID' => $deptIblockId, '=NAME' => 'Phòng Kiểm toán 1']);
if ($row = $resAudit->Fetch()) {
    $auditDeptId = (int)$row['ID'];
} else {
    $auditDeptId = (int)$deptSection->Add([
        'IBLOCK_ID' => $deptIblockId,
        'NAME'      => 'Phòng Kiểm toán 1',
        'CODE'      => 'audit_dept_1',
        'SORT'      => 20,
    ]);
}
echo " -> Phong Kiem toan 1 ID: " . $auditDeptId . "\n";

// 3. TAO 4 USER MAU
echo "[3/5] Khoi tao 4 tai khoan mau...\n";
$userObj = new \CUser();

$defaultPass = $_ENV['DEFAULT_USER_PASSWORD'] ?? getenv('DEFAULT_USER_PASSWORD') ?: 'Aasc@2026Pass!';

$usersConfig = [
    [
        'LOGIN'         => 'director',
        'EMAIL'         => 'director@aasc.test',
        'NAME'          => 'An',
        'LAST_NAME'     => 'Nguyễn Văn',
        'PASSWORD'      => $defaultPass,
        'WORK_POSITION' => 'Phó Tổng Giám đốc / Partner',
        'UF_DEPARTMENT' => [$boardDeptId],
    ],
    [
        'LOGIN'         => 'manager',
        'EMAIL'         => 'manager@aasc.test',
        'NAME'          => 'Bình',
        'LAST_NAME'     => 'Trần Thị',
        'PASSWORD'      => $defaultPass,
        'WORK_POSITION' => 'Trưởng phòng Kiểm toán 1',
        'UF_DEPARTMENT' => [$auditDeptId],
    ],
    [
        'LOGIN'         => 'senior',
        'EMAIL'         => 'senior@aasc.test',
        'NAME'          => 'Nam',
        'LAST_NAME'     => 'Lê Hoàng',
        'PASSWORD'      => $defaultPass,
        'WORK_POSITION' => 'Trưởng nhóm kiểm toán',
        'UF_DEPARTMENT' => [$auditDeptId],
    ],
    [
        'LOGIN'         => 'junior',
        'EMAIL'         => 'junior@aasc.test',
        'NAME'          => 'Chi',
        'LAST_NAME'     => 'Phạm Quỳnh',
        'PASSWORD'      => $defaultPass,
        'WORK_POSITION' => 'Trợ lý kiểm toán',
        'UF_DEPARTMENT' => [$auditDeptId],
    ],
];

foreach ($usersConfig as $u) {
    $existing = \Bitrix\Main\UserTable::getList([
        'filter' => ['=LOGIN' => $u['LOGIN']],
        'select' => ['ID'],
    ])->fetch();

    if ($existing) {
        echo " -> User " . $u['LOGIN'] . " da ton tai (ID: " . $existing['ID'] . ").\n";
        $userObj->Update($existing['ID'], [
            'PASSWORD'         => $u['PASSWORD'],
            'CONFIRM_PASSWORD' => $u['PASSWORD'],
            'UF_DEPARTMENT'    => $u['UF_DEPARTMENT'],
            'WORK_POSITION'    => $u['WORK_POSITION'],
        ]);
    } else {
        $u['GROUP_ID']         = [2, 3, 4];
        $u['ACTIVE']           = 'Y';
        $u['CONFIRM_PASSWORD'] = $u['PASSWORD'];
        $newId = $userObj->Add($u);
        if ($newId > 0) {
            echo " -> Tao moi User " . $u['LOGIN'] . " thanh cong (ID: " . $newId . ").\n";
        } else {
            echo " -> Loi tao User " . $u['LOGIN'] . ": " . $userObj->LAST_ERROR . "\n";
        }
    }
}

// 4. TAO CAC USER FIELDS CHO CRM LEAD
echo "[4/5] Khoi tao User Fields cho CRM Lead...\n";
$userFieldObj = new \CUserTypeEntity();

// 4.1 UF_AUDIT_TEAM (Nguoi dung da tri - multiple)
$teamField = \CUserTypeEntity::GetList([], ['ENTITY_ID' => 'CRM_LEAD', 'FIELD_NAME' => 'UF_AUDIT_TEAM'])->Fetch();
if (!$teamField) {
    $userFieldObj->Add([
        'ENTITY_ID'         => 'CRM_LEAD',
        'FIELD_NAME'        => 'UF_AUDIT_TEAM',
        'USER_TYPE_ID'      => 'employee',
        'XML_ID'            => 'UF_AUDIT_TEAM',
        'SORT'              => 100,
        'MULTIPLE'          => 'Y',
        'EDIT_FORM_LABEL'   => ['vi' => 'Đoàn kiểm toán', 'en' => 'Audit Team'],
        'LIST_COLUMN_LABEL' => ['vi' => 'Đoàn kiểm toán', 'en' => 'Audit Team'],
    ]);
    echo " -> Da tao field UF_AUDIT_TEAM.\n";
} else {
    echo " -> Field UF_AUDIT_TEAM da ton tai.\n";
}

// 4.2 UF_ESTIMATED_FEE (So tien du toan)
$feeField = \CUserTypeEntity::GetList([], ['ENTITY_ID' => 'CRM_LEAD', 'FIELD_NAME' => 'UF_ESTIMATED_FEE'])->Fetch();
if (!$feeField) {
    $userFieldObj->Add([
        'ENTITY_ID'         => 'CRM_LEAD',
        'FIELD_NAME'        => 'UF_ESTIMATED_FEE',
        'USER_TYPE_ID'      => 'double',
        'XML_ID'            => 'UF_ESTIMATED_FEE',
        'SORT'              => 110,
        'MULTIPLE'          => 'N',
        'SETTINGS'          => ['PRECISION' => 2],
        'EDIT_FORM_LABEL'   => ['vi' => 'Phí kiểm toán dự toán (VNĐ)', 'en' => 'Estimated Fee (VND)'],
        'LIST_COLUMN_LABEL' => ['vi' => 'Phí dự toán', 'en' => 'Estimated Fee'],
    ]);
    echo " -> Da tao field UF_ESTIMATED_FEE.\n";
} else {
    echo " -> Field UF_ESTIMATED_FEE da ton tai.\n";
}

// 4.3 UF_APPROVAL_STATUS (Danh sach trang thai phe duyet)
$approvalField = \CUserTypeEntity::GetList([], ['ENTITY_ID' => 'CRM_LEAD', 'FIELD_NAME' => 'UF_APPROVAL_STATUS'])->Fetch();
if (!$approvalField) {
    $appId = $userFieldObj->Add([
        'ENTITY_ID'         => 'CRM_LEAD',
        'FIELD_NAME'        => 'UF_APPROVAL_STATUS',
        'USER_TYPE_ID'      => 'enumeration',
        'XML_ID'            => 'UF_APPROVAL_STATUS',
        'SORT'              => 120,
        'MULTIPLE'          => 'N',
        'EDIT_FORM_LABEL'   => ['vi' => 'Trạng thái phê duyệt', 'en' => 'Approval Status'],
        'LIST_COLUMN_LABEL' => ['vi' => 'Duyệt giá', 'en' => 'Approval'],
    ]);
    if ($appId > 0) {
        $obEnum = new \CUserFieldEnum();
        $obEnum->SetEnumValues($appId, [
            'n0' => ['VALUE' => 'Chưa gửi duyệt (DRAFT)', 'XML_ID' => 'DRAFT', 'DEF' => 'Y'],
            'n1' => ['VALUE' => 'Chờ Trưởng phòng duyệt', 'XML_ID' => 'WAITING_MGR', 'DEF' => 'N'],
            'n2' => ['VALUE' => 'Chờ Ban Giám đốc duyệt', 'XML_ID' => 'WAITING_DIR', 'DEF' => 'N'],
            'n3' => ['VALUE' => 'Đã phê duyệt (APPROVED)', 'XML_ID' => 'APPROVED', 'DEF' => 'N'],
            'n4' => ['VALUE' => 'Yêu cầu sửa lại (REJECTED)', 'XML_ID' => 'REJECTED', 'DEF' => 'N'],
        ]);
    }
    echo " -> Da tao field UF_APPROVAL_STATUS kem danh muc enum.\n";
} else {
    echo " -> Field UF_APPROVAL_STATUS da ton tai.\n";
}

// 5. TAO IBLOCK SERVICES VA NAP 4 DICH VU KIEM TOAN MAU
echo "[5/5] Khoi tao IBlock Services va cac dich vu mau...\n";
$ibTypeObj = new \CIBlockType();
$ibObj = new \CIBlock();
$ibElemObj = new \CIBlockElement();

$resType = \CIBlockType::GetByID('services')->Fetch();
if (!$resType) {
    $ibTypeObj->Add([
        'ID'       => 'services',
        'SECTIONS' => 'Y',
        'IN_RSS'   => 'N',
        'SORT'     => 500,
        'LANG'     => [
            'en' => ['NAME' => 'Services', 'SECTION_NAME' => 'Sections', 'ELEMENT_NAME' => 'Elements'],
            'ru' => ['NAME' => 'Сервисы', 'SECTION_NAME' => 'Разделы', 'ELEMENT_NAME' => 'Элементы'],
        ],
    ]);
}

$resIb = \CIBlock::GetList([], ['TYPE' => 'services', '=CODE' => 'audit_services'])->Fetch();
if ($resIb) {
    $servicesIblockId = (int)$resIb['ID'];
    echo " -> IBlock audit_services da ton tai (ID: " . $servicesIblockId . ").\n";
} else {
    $servicesIblockId = (int)$ibObj->Add([
        'IBLOCK_TYPE_ID' => 'services',
        'SITE_ID'        => ['s1'],
        'NAME'           => 'Dịch vụ Kiểm toán AASC',
        'CODE'           => 'audit_services',
        'ACTIVE'         => 'Y',
        'SORT'           => 10,
        'GROUP_ID'       => ['2' => 'R'],
    ]);
    echo " -> Da tao IBlock audit_services (ID: " . $servicesIblockId . ").\n";
}

$servicesList = [
    [
        'NAME' => 'Kiểm toán Báo cáo tài chính',
        'CODE' => 'kiem-toan-bctc',
        'PREVIEW' => 'Dịch vụ kiểm toán Báo cáo tài chính độc lập theo chuẩn mực Kiểm toán Việt Nam (VSA) dành cho doanh nghiệp FDI, công ty niêm yết và tập đoàn.',
        'SORT' => 10,
    ],
    [
        'NAME' => 'Kiểm toán Quyết toán vốn đầu tư',
        'CODE' => 'kiem-toan-quyet-toan-von',
        'PREVIEW' => 'Kiểm tra, rà soát chi phí xây dựng, đối chiếu khối lượng dự toán và hồ sơ nghiệm thu công trình theo quy định của Bộ Xây dựng và Bộ Tài chính.',
        'SORT' => 20,
    ],
    [
        'NAME' => 'Thẩm định giá tài sản & Doanh nghiệp',
        'CODE' => 'tham-dinh-gia',
        'PREVIEW' => 'Xác định giá trị thị trường của bất động sản, máy móc thiết bị, tài sản vô hình và định giá phục vụ cổ phần hóa, mua bán sáp nhập (M&A).',
        'SORT' => 30,
    ],
    [
        'NAME' => 'Tư vấn thuế & Tuân thủ pháp lý',
        'CODE' => 'tu-van-thue',
        'PREVIEW' => 'Đánh giá rủi ro thuế doanh nghiệp, tư vấn giao dịch liên kết (Transfer Pricing), lập kế hoạch thuế và hỗ trợ thanh tra kiểm tra thuế.',
        'SORT' => 40,
    ],
];

foreach ($servicesList as $s) {
    $existingElem = \CIBlockElement::GetList([], [
        'IBLOCK_ID' => $servicesIblockId,
        '=CODE'     => $s['CODE'],
    ])->Fetch();

    if (!$existingElem) {
        $ibElemObj->Add([
            'IBLOCK_ID'         => $servicesIblockId,
            'NAME'              => $s['NAME'],
            'CODE'              => $s['CODE'],
            'PREVIEW_TEXT'      => $s['PREVIEW'],
            'PREVIEW_TEXT_TYPE' => 'text',
            'ACTIVE'            => 'Y',
            'SORT'              => $s['SORT'],
        ]);
        echo " -> Da them dich vu: " . $s['NAME'] . "\n";
    } else {
        echo " -> Dich vu " . $s['NAME'] . " da co.\n";
    }
}

echo "=== HOAN TAT KHOI TAO DU LIEU NEN TANG! ===\n";
