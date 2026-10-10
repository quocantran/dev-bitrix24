<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Aasc\Audit\Model\AuditRequestTable;
use Aasc\Audit\Handler\PortalAccessHandler;

class AascAuditRequestDetailComponent extends \CBitrixComponent
{
    /**
     * Danh sách 6 bước tiến trình kiểm toán chuẩn VSA tại AASC
     */
    public const WORKFLOW_STEPS = [
        1 => [
            'CODE'  => 'NEW',
            'TITLE' => 'Tiếp nhận đơn',
            'DESC'  => 'Hồ sơ đã được gửi và chuyển vào hệ thống CRM tiếp nhận ban đầu.',
        ],
        2 => [
            'CODE'  => 'PROPOSAL',
            'TITLE' => 'Thẩm định & Báo giá',
            'DESC'  => 'Chủ nhiệm kiểm toán thẩm định quy mô và lập dự toán chi phí dịch vụ.',
        ],
        3 => [
            'CODE'  => 'CONTRACT',
            'TITLE' => 'Ký kết hợp đồng',
            'DESC'  => 'Hợp đồng kiểm toán được xác lập, Trưởng nhóm tiến hành lập kế hoạch (VSA 300).',
        ],
        4 => [
            'CODE'  => 'FIELDWORK',
            'TITLE' => 'Kiểm toán thực địa',
            'DESC'  => 'Trợ lý và Trưởng nhóm kiểm toán thực hiện kiểm tra chi tiết tại doanh nghiệp (VSA 500).',
        ],
        5 => [
            'CODE'  => 'REVIEW',
            'TITLE' => 'Soát xét báo cáo',
            'DESC'  => 'Chủ nhiệm và Ban Giám đốc soát xét hồ sơ kiểm soát chất lượng độc lập (EQCR).',
        ],
        6 => [
            'CODE'  => 'COMPLETED',
            'TITLE' => 'Báo cáo chính thức',
            'DESC'  => 'Ban Giám đốc ký phát hành Báo cáo kiểm toán độc lập chính thức (VSA 700).',
        ],
    ];

    public function onPrepareComponentParams($arParams)
    {
        $arParams['REQUEST_ID'] = (int)($arParams['REQUEST_ID'] ?? 0);
        return $arParams;
    }

    public function executeComponent()
    {
        global $USER, $APPLICATION;

        // 1. Kiểm tra đăng nhập
        if (!is_object($USER) || !$USER->IsAuthorized()) {
            LocalRedirect('/portal/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
            die();
        }

        if (!Loader::includeModule('aasc.audit')) {
            ShowError('Module aasc.audit chưa được kích hoạt trên hệ thống.');
            return;
        }

        $requestId = (int)$this->arParams['REQUEST_ID'];
        if ($requestId <= 0) {
            ShowError('Mã hồ sơ yêu cầu không hợp lệ.');
            return;
        }

        // 2. Truy vấn thông tin hồ sơ từ bảng aasc_audit_request
        $request = AuditRequestTable::getById($requestId)->fetch();
        if (!$request) {
            ShowError('Không tìm thấy thông tin hồ sơ kiểm toán #' . $requestId);
            return;
        }

        // 3. Kiểm tra phân quyền truy cập: Chỉ chủ sở hữu tài khoản hoặc nhân viên nội bộ
        $currentUserId = (int)$USER->GetID();
        $isOwner = ((int)$request['USER_ID'] === $currentUserId);
        $isInternal = PortalAccessHandler::isInternalUser($currentUserId);

        if (!$isOwner && !$isInternal) {
            ShowError('Bạn không có quyền truy cập hồ sơ kiểm toán này.');
            return;
        }

        $this->arResult['REQUEST'] = $request;
        $this->arResult['IS_OWNER'] = $isOwner;
        $this->arResult['IS_INTERNAL'] = $isInternal;

        // 4. Truy vấn trạng thái CRM Lead và Deal tương ứng
        $leadId = (int)($request['CRM_LEAD_ID'] ?? 0);
        $dealId = (int)($request['CRM_DEAL_ID'] ?? 0);
        $leadData = null;
        $dealData = null;
        $leadStatusId = 'NEW';
        $assignedUserName = 'Chuyên viên tư vấn AASC';
        $estimatedFee = 0.0;
        $isQuoteApproved = false;

        if (Loader::includeModule('crm')) {
            if ($leadId > 0) {
                $leadData = \CCrmLead::GetByID($leadId, false);
                if ($leadData) {
                    $leadStatusId = (string)($leadData['STATUS_ID'] ?? 'NEW');
                    $assignedId = (int)($leadData['ASSIGNED_BY_ID'] ?? 0);
                    if ($assignedId > 0) {
                        $assignedUser = \Bitrix\Main\UserTable::getById($assignedId)->fetch();
                        if ($assignedUser) {
                            $assignedUserName = \CUser::FormatName(\CSite::GetNameFormat(), $assignedUser, true, false) ?: $assignedUser['LOGIN'];
                        }
                    }

                    $estimatedFee = (float)($leadData['OPPORTUNITY'] ?? 0);
                    if ($estimatedFee <= 0 && !empty($leadData['UF_ESTIMATED_FEE'])) {
                        $estimatedFee = (float)$leadData['UF_ESTIMATED_FEE'];
                    }

                    global $USER_FIELD_MANAGER;
                    $statusVal = $USER_FIELD_MANAGER->GetUserFieldValue("CRM_LEAD", "UF_APPROVAL_STATUS", $leadId);
                    if (!empty($statusVal)) {
                        if (is_numeric($statusVal)) {
                            $enumRow = \CUserFieldEnum::GetList([], ["ID" => (int)$statusVal])->Fetch();
                            $isQuoteApproved = ($enumRow && $enumRow["XML_ID"] === "APPROVED");
                        } else {
                            $isQuoteApproved = ((string)$statusVal === "APPROVED");
                        }
                    }
                }
            }

            if ($dealId > 0) {
                $dealData = \CCrmDeal::GetByID($dealId, false);
                if ($dealData) {
                    $dealAssignedId = (int)($dealData['ASSIGNED_BY_ID'] ?? 0);
                    if ($dealAssignedId > 0) {
                        $dealUser = \Bitrix\Main\UserTable::getById($dealAssignedId)->fetch();
                        if ($dealUser) {
                            $assignedUserName = \CUser::FormatName(\CSite::GetNameFormat(), $dealUser, true, false) ?: $dealUser['LOGIN'];
                        }
                    }
                    if (!empty($dealData['OPPORTUNITY']) && (float)$dealData['OPPORTUNITY'] > 0) {
                        $estimatedFee = (float)$dealData['OPPORTUNITY'];
                    }
                }
            }
        }

        // Bổ sung thông tin số điện thoại & email nếu bản ghi còn thiếu
        $phone = trim((string)($request['PHONE'] ?? ''));
        $email = trim((string)($request['EMAIL'] ?? ''));

        $requestUserId = (int)($request['USER_ID'] ?? 0);
        if (($phone === '' || $email === '') && $requestUserId > 0) {
            $userRow = \Bitrix\Main\UserTable::getRow([
                'select' => ['ID', 'EMAIL', 'PERSONAL_PHONE'],
                'filter' => ['=ID' => $requestUserId],
            ]);
            if ($userRow) {
                if ($phone === '' && !empty($userRow['PERSONAL_PHONE'])) {
                    $phone = trim((string)$userRow['PERSONAL_PHONE']);
                }
                if ($email === '' && !empty($userRow['EMAIL'])) {
                    $email = trim((string)$userRow['EMAIL']);
                }
            }
        }

        if (($phone === '' || $email === '') && $leadId > 0 && Loader::includeModule('crm')) {
            $fmRes = \CCrmFieldMulti::GetList(
                ['ID' => 'ASC'],
                [
                    'ENTITY_ID' => \CCrmOwnerType::LeadName,
                    'ELEMENT_ID' => $leadId,
                ]
            );
            while ($fm = $fmRes->Fetch()) {
                if ($fm['TYPE_ID'] === 'PHONE' && $phone === '') {
                    $phone = trim((string)$fm['VALUE']);
                }
                if ($fm['TYPE_ID'] === 'EMAIL' && $email === '') {
                    $email = trim((string)$fm['VALUE']);
                }
            }
        }

        // Định dạng thời gian tạo hồ sơ
        $createdAtFormatted = '';
        if (!empty($request['CREATED_AT'])) {
            if ($request['CREATED_AT'] instanceof \Bitrix\Main\Type\DateTime) {
                $createdAtFormatted = $request['CREATED_AT']->format('d/m/Y H:i');
            } else {
                $createdAtFormatted = (string)$request['CREATED_AT'];
            }
        }

        // Lấy tên loại hình dịch vụ kiểm toán
        $servicesMap = [
            1 => 'Kiểm toán Báo cáo tài chính',
            2 => 'Kiểm toán Quyết toán vốn đầu tư',
            3 => 'Thẩm định giá tài sản',
            4 => 'Tư vấn thuế doanh nghiệp',
        ];
        if (Loader::includeModule('iblock')) {
            $iblock = \CIBlock::GetList([], ['TYPE' => 'services', '=CODE' => 'audit_services'])->Fetch();
            if ($iblock) {
                $resServices = \Bitrix\Iblock\ElementTable::getList([
                    'select' => ['ID', 'NAME'],
                    'filter' => ['=IBLOCK_ID' => (int)$iblock['ID'], '=ACTIVE' => 'Y'],
                ]);
                while ($s = $resServices->fetch()) {
                    $servicesMap[(int)$s['ID']] = $s['NAME'];
                }
            }
        }
        $serviceName = $servicesMap[(int)($request['SERVICE_ID'] ?? 0)] ?? 'Kiểm toán Báo cáo tài chính';

        // Quy mô doanh thu
        $annualRevenue = (float)($request['ANNUAL_REVENUE'] ?? 0);
        $revenueFormatted = ($annualRevenue > 0)
            ? number_format($annualRevenue, 0, ',', '.') . ' VNĐ'
            : 'Chưa cung cấp';

        // Gán lại vào $request với đầy đủ alias tương thích
        $request['PHONE'] = $phone;
        $request['CONTACT_PHONE'] = $phone;
        $request['EMAIL'] = $email;
        $request['CONTACT_EMAIL'] = $email;
        $request['AUDIT_TYPE'] = $serviceName;
        $request['SERVICE_NAME'] = $serviceName;
        $request['REVENUE_SCALE'] = $revenueFormatted;
        $request['ANNUAL_REVENUE_FORMATTED'] = $revenueFormatted;
        $request['DATE_CREATE'] = $createdAtFormatted;
        $request['CREATED_AT_FORMATTED'] = $createdAtFormatted;

        $this->arResult['REQUEST'] = $request;
        $this->arResult['LEAD'] = $leadData;
        $this->arResult['DEAL'] = $dealData;
        $this->arResult['ASSIGNED_USER_NAME'] = $assignedUserName;
        $this->arResult['ESTIMATED_FEE'] = $estimatedFee;
        $this->arResult['IS_QUOTE_APPROVED'] = $isQuoteApproved;

        // 5. Xác định bước tiến trình (Current Step: 1 -> 6)
        // Mức 1: Tiếp nhận hồ sơ (NEW, IN_PROCESS)
        // Mức 2: Thẩm định & Báo giá sẵn sàng (manager nhập tiền + sang status PROCESSED/APPROVED)
        // Mức 3: Người dùng đồng ý ký hợp đồng (CONTRACT_SIGNED hoặc Deal C1:PREPARATION/C1:NEW)
        // Mức 4: Kiểm toán thực địa (C1:FIELDWORK)
        // Mức 5: Soát xét báo cáo (C1:REVIEW_MANAGER, C1:REVIEW_DIRECTOR)
        // Mức 6: Báo cáo chính thức (C1:WON)
        $currentStep = 1;
        $requestStatus = (string)($request['STATUS'] ?? 'NEW');

        if ($dealId > 0 && $dealData) {
            $dealStage = (string)($dealData['STAGE_ID'] ?? '');
            if ($dealStage === 'C1:FIELDWORK' || $dealStage === 'EXECUTING') {
                $currentStep = 4;
            } elseif (in_array($dealStage, ['C1:REVIEW_MANAGER', 'C1:REVIEW_DIRECTOR'], true)) {
                $currentStep = 5;
            } elseif ($dealStage === 'C1:WON' || $dealStage === 'WON') {
                $currentStep = 6;
            } else {
                // C1:NEW, C1:PREPARATION
                $currentStep = 3;
            }
        } else {
            // Còn ở giai đoạn Lead
            if ($requestStatus === 'CONTRACT_SIGNED') {
                $currentStep = 3;
            } elseif ($isQuoteApproved || in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED'], true)) {
                $currentStep = 2;
            } else {
                $currentStep = 1;
            }
        }

        // Kiểm tra điều kiện cho phép khách hàng ký hợp đồng
        $isContractSigned = ($requestStatus === 'CONTRACT_SIGNED' || $dealId > 0);
        $canSignContract = false;
        if (!$isContractSigned && ($isQuoteApproved || in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED'], true)) && $estimatedFee > 0) {
            $canSignContract = true;
        }

        $this->arResult['CAN_SIGN_CONTRACT'] = $canSignContract;
        $this->arResult['IS_CONTRACT_SIGNED'] = $isContractSigned;
        $this->arResult['CURRENT_STEP'] = $currentStep;
        $this->arResult['WORKFLOW_STEPS'] = self::WORKFLOW_STEPS;
        $this->arResult['HAS_FINAL_REPORT'] = ($currentStep === 6);
        $this->arResult['REPORT_URL'] = '/portal/my-requests/' . $requestId . '/report/';

        // 6. Nạp lịch sử trao đổi từ CRM Timeline của Lead và Deal
        $this->arResult['TIMELINE_COMMENTS'] = $this->loadTimelineComments($leadId, $dealId);

        // 7. Đăng ký kênh Push & Pull theo dõi thời gian thực
        if (Loader::includeModule('pull')) {
            \CJSCore::Init(['pull', 'pull.client']);
            if (class_exists('\Bitrix\Main\UI\Extension')) {
                \Bitrix\Main\UI\Extension::load(['pull.client', 'ui.notification']);
            }
            \CPullWatch::Add($currentUserId, 'AASC_AUDIT_REQUEST_' . $requestId, true);
        }

        // Thiết lập tiêu đề trang
        $pageTitle = 'Hồ Sơ Kiểm Toán #' . $request['ID'] . ' - ' . htmlspecialcharsbx($request['COMPANY_NAME']);
        $APPLICATION->SetTitle($pageTitle);

        $this->includeComponentTemplate();
    }

    /**
     * Nạp danh sách trao đổi / ghi chú từ CRM Timeline (Lead và Deal)
     */
    protected function loadTimelineComments(int $leadId, int $dealId = 0): array
    {
        $comments = [];
        if (($leadId <= 0 && $dealId <= 0) || !Loader::includeModule('crm')) {
            return $comments;
        }

        $filter = ['LOGIC' => 'OR'];
        if ($leadId > 0) {
            $filter[] = [
                '=ENTITY_TYPE_ID' => \CCrmOwnerType::Lead,
                '=ENTITY_ID'      => $leadId,
            ];
        }
        if ($dealId > 0) {
            $filter[] = [
                '=ENTITY_TYPE_ID' => \CCrmOwnerType::Deal,
                '=ENTITY_ID'      => $dealId,
            ];
        }

        $bindingRows = \Bitrix\Crm\Timeline\Entity\TimelineBindingTable::getList([
            'filter' => $filter,
            'select' => ['OWNER_ID'],
        ])->fetchAll();

        $timelineIds = array_unique(array_filter(array_column($bindingRows, 'OWNER_ID')));
        if (empty($timelineIds)) {
            return $comments;
        }

        $items = \Bitrix\Crm\Timeline\Entity\TimelineTable::getList([
            'filter' => [
                '@ID'      => $timelineIds,
                '=TYPE_ID' => \Bitrix\Crm\Timeline\TimelineType::COMMENT,
            ],
            'select' => [
                'ID',
                'TYPE_ID',
                'COMMENT',
                'CREATED',
                'AUTHOR_ID',
            ],
            'order' => ['CREATED' => 'ASC'],
        ]);

        $authorIds = [];
        $rawRows = [];
        while ($row = $items->fetch()) {
            $commentText = trim(\Bitrix\Main\Text\Emoji::decode((string)$row['COMMENT']));
            if ($commentText !== '') {
                $row['COMMENT'] = $commentText;
                $rawRows[] = $row;
                if (!empty($row['AUTHOR_ID'])) {
                    $authorIds[] = (int)$row['AUTHOR_ID'];
                }
            }
        }

        $authorsMap = [];
        if (!empty($authorIds)) {
            $users = \Bitrix\Main\UserTable::getList([
                'filter' => ['@ID' => array_unique($authorIds)],
                'select' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN'],
            ]);
            while ($u = $users->fetch()) {
                $authorsMap[$u['ID']] = \CUser::FormatName(
                    \CSite::GetNameFormat(),
                    $u,
                    true,
                    false
                ) ?: $u['LOGIN'];
            }
        }

        $seen = [];
        $uniqueComments = [];
        foreach ($rawRows as $row) {
            $authorId = (int)$row['AUTHOR_ID'];
            $createdObj = $row['CREATED'];
            $timeFormatted = is_object($createdObj) ? $createdObj->format('H:i d/m/Y') : (string)$createdObj;
            $commentText = (string)$row['COMMENT'];

            // Lọc bỏ bình luận phê duyệt bị sai tác giả (do bug đệ quy cũ tạo dưới tên khách hàng)
            $isApprovalText = (strpos($commentText, 'Ban Giám đốc đã phê duyệt dự toán chi phí') !== false);
            if ($isApprovalText && !in_array($authorId, [1, 4], true)) {
                continue;
            }

            // Loại bỏ hoàn toàn các bình luận trùng lặp nội dung
            $normKey = preg_replace('/\s+/', ' ', trim($commentText));
            if (isset($seen[$normKey])) {
                continue;
            }
            $seen[$normKey] = true;

            $uniqueComments[] = [
                'ID'         => (int)$row['ID'],
                'TEXT'       => $commentText,
                'AUTHOR_ID'  => $authorId,
                'AUTHOR_NAME'=> $authorsMap[$authorId] ?? 'Hệ thống AASC',
                'CREATED'    => $timeFormatted,
            ];
        }

        return $uniqueComments;
    }
}
