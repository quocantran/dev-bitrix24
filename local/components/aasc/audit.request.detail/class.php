<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Aasc\Audit\Model\AuditRequestTable;
use Aasc\Audit\Handler\PortalAccessHandler;

Loc::loadMessages(__FILE__);

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

        $this->arResult['LEAD'] = $leadData;
        $this->arResult['DEAL'] = $dealData;
        $this->arResult['ASSIGNED_USER_NAME'] = $assignedUserName;
        $this->arResult['ESTIMATED_FEE'] = $estimatedFee;
        $this->arResult['IS_QUOTE_APPROVED'] = $isQuoteApproved;

        // 5. Xác định bước tiến trình (Current Step: 1 -> 6)
        $currentStep = 1;
        if ($dealId > 0 && $dealData) {
            $dealStage = (string)($dealData['STAGE_ID'] ?? '');
            if ($dealStage === 'C1:FIELDWORK') {
                $currentStep = 4;
            } elseif (in_array($dealStage, ['C1:REVIEW_MANAGER', 'C1:REVIEW_DIRECTOR'], true)) {
                $currentStep = 5;
            } elseif ($dealStage === 'C1:WON') {
                $currentStep = 6;
            } else {
                // C1:NEW, C1:PREPARATION
                $currentStep = 3;
            }
        } else {
            // Còn ở giai đoạn Lead
            if (in_array($leadStatusId, ['IN_PROCESS', 'PROPOSAL_SENT', 'APPROVED', 'PROCESSED'])) {
                $currentStep = 2;
            } elseif (in_array($leadStatusId, ['CONVERTED', 'WON', 'COMPLETED'])) {
                $currentStep = 3;
            } else {
                $currentStep = 1;
            }
        }

        // Kiểm tra điều kiện cho phép khách hàng ký hợp đồng
        $canSignContract = false;
        if ($dealId <= 0 && ($isQuoteApproved || in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED'], true)) && $estimatedFee > 0) {
            $canSignContract = true;
        }

        $this->arResult['CAN_SIGN_CONTRACT'] = $canSignContract;
        $this->arResult['CURRENT_STEP'] = $currentStep;
        $this->arResult['WORKFLOW_STEPS'] = self::WORKFLOW_STEPS;
        $this->arResult['HAS_FINAL_REPORT'] = ($currentStep === 6);
        $this->arResult['REPORT_URL'] = '/portal/my-requests/' . $requestId . '/report/';

        // 6. Nạp lịch sử trao đổi từ CRM Timeline của Lead và Deal
        $this->arResult['TIMELINE_COMMENTS'] = $this->loadTimelineComments($leadId, $dealId);

        // 7. Đăng ký kênh Push & Pull theo dõi thời gian thực
        if (Loader::includeModule('pull')) {
            \CPullWatch::Add($currentUserId, 'AASC_AUDIT_REQUEST_' . $requestId);
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

        $bindings = \Bitrix\Crm\Timeline\Entity\TimelineBindingTable::getList([
            'filter' => $filter,
            'select' => [
                'ID'        => 'ITEM.ID',
                'TYPE_ID'   => 'ITEM.TYPE_ID',
                'COMMENT'   => 'ITEM.COMMENT',
                'CREATED'   => 'ITEM.CREATED',
                'AUTHOR_ID' => 'ITEM.AUTHOR_ID',
            ],
            'order' => ['ITEM.CREATED' => 'ASC'],
        ]);

        $authorIds = [];
        $rawRows = [];
        while ($row = $bindings->fetch()) {
            if (!empty($row['COMMENT'])) {
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

        foreach ($rawRows as $row) {
            $authorId = (int)$row['AUTHOR_ID'];
            $createdObj = $row['CREATED'];
            $timeFormatted = is_object($createdObj) ? $createdObj->format('H:i d/m/Y') : (string)$createdObj;

            $comments[] = [
                'ID'         => (int)$row['ID'],
                'TEXT'       => (string)$row['COMMENT'],
                'AUTHOR_ID'  => $authorId,
                'AUTHOR_NAME'=> $authorsMap[$authorId] ?? 'Hệ thống AASC',
                'CREATED'    => $timeFormatted,
            ];
        }

        return $comments;
    }
}
