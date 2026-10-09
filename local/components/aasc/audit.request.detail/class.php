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
     * Danh sách 4 bước tiến trình kiểm toán chuẩn AASC
     */
    public const WORKFLOW_STEPS = [
        1 => [
            'CODE'  => 'NEW',
            'TITLE' => 'Tiếp nhận hồ sơ',
            'DESC'  => 'Hồ sơ đã được gửi và chuyển vào hệ thống CRM tiếp nhận ban đầu.',
        ],
        2 => [
            'CODE'  => 'IN_PROCESS',
            'TITLE' => 'Thẩm định & Khảo sát',
            'DESC'  => 'Chuyên viên kiểm toán đang đánh giá hồ sơ và khảo sát quy mô doanh nghiệp.',
        ],
        3 => [
            'CODE'  => 'PROPOSAL',
            'TITLE' => 'Phương án & Báo giá',
            'DESC'  => 'Lập dự toán chi phí kiểm toán và trình phê duyệt kế hoạch dịch vụ.',
        ],
        4 => [
            'CODE'  => 'CONTRACT',
            'TITLE' => 'Hợp đồng & Triển khai',
            'DESC'  => 'Hợp đồng dịch vụ kiểm toán đã được ký kết, sẵn sàng triển khai thực tế.',
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

        // 4. Truy vấn trạng thái CRM Lead tương ứng
        $leadId = (int)($request['CRM_LEAD_ID'] ?? 0);
        $leadData = null;
        $leadStatusId = 'NEW';
        $assignedUserName = 'Chuyên viên tư vấn AASC';

        if ($leadId > 0 && Loader::includeModule('crm')) {
            $leadData = \CCrmLead::GetByID($leadId, false);
            if ($leadData) {
                $leadStatusId = (string)($leadData['STATUS_ID'] ?? 'NEW');
                $assignedId = (int)($leadData['ASSIGNED_BY_ID'] ?? 0);
                if ($assignedId > 0) {
                    $assignedUser = \Bitrix\Main\UserTable::getById($assignedId)->fetch();
                    if ($assignedUser) {
                        $assignedUserName = \CUser::FormatName(
                            \CSite::GetNameFormat(),
                            $assignedUser,
                            true,
                            false
                        ) ?: $assignedUser['LOGIN'];
                    }
                }
            }
        }

        $this->arResult['LEAD'] = $leadData;
        $this->arResult['ASSIGNED_USER_NAME'] = $assignedUserName;

        // 5. Xác định bước tiến trình (Current Step: 1 -> 4)
        $currentStep = 1;
        if (in_array($leadStatusId, ['IN_PROCESS', 'ASSIGNED', '2'])) {
            $currentStep = 2;
        } elseif (in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED', '3'])) {
            $currentStep = 3;
        } elseif (in_array($leadStatusId, ['CONVERTED', 'WON', 'COMPLETED', '4'])) {
            $currentStep = 4;
        }

        $this->arResult['CURRENT_STEP'] = $currentStep;
        $this->arResult['LEAD_STATUS_ID'] = $leadStatusId;
        $this->arResult['WORKFLOW_STEPS'] = self::WORKFLOW_STEPS;

        // 6. Nạp lịch sử trao đổi từ CRM Timeline của Lead
        $this->arResult['TIMELINE_COMMENTS'] = $this->loadLeadTimelineComments($leadId);

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
     * Nạp danh sách trao đổi / ghi chú từ CRM Timeline
     */
    protected function loadLeadTimelineComments(int $leadId): array
    {
        $comments = [];
        if ($leadId <= 0 || !Loader::includeModule('crm')) {
            return $comments;
        }

        $bindings = \Bitrix\Crm\Timeline\Entity\TimelineBindingTable::getList([
            'filter' => [
                '=ENTITY_TYPE_ID' => \CCrmOwnerType::Lead,
                '=ENTITY_ID'      => $leadId,
            ],
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
