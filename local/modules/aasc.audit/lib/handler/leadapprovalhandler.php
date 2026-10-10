<?php
namespace Aasc\Audit\Handler;

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class LeadApprovalHandler
{
    /** Cờ đánh dấu luồng ký hợp đồng hợp lệ từ Cổng thông tin (Portal) */
    public static bool $isPortalSign = false;

    /** Cờ khóa ngăn chặn đệ quy khi cập nhật Lead/Deal */
    private static bool $isHandlingLeadUpdate = false;

    /** Bảng lưu trạng thái trước đó của Lead theo ID */
    private static array $prevStatusMap = [];

    /**
     * Bắt sự kiện OnBeforeCrmDealUpdate: Chặn chuyển sang Invoice, In Progress, Final Invoice, Won
     * nếu dự toán phí chưa được phê duyệt (UF_APPROVAL_STATUS !== APPROVED)
     */
    /**
     * Bắt sự kiện OnBeforeCrmDealUpdate: Chặn cập nhật Deal sang các giai đoạn tiếp theo
     * nếu dự toán phí chưa được phê duyệt (UF_APPROVAL_STATUS !== APPROVED)
     * hoặc người dùng không đủ thẩm quyền theo quy trình kiểm toán AASC
     */
    public static function onBeforeDealUpdate(&$arFields): bool
    {
        $dealId = (int)($arFields["ID"] ?? 0);
        if ($dealId <= 0) {
            return true;
        }

        // Lấy thông tin Deal hiện tại
        $deal = \CCrmDeal::GetByID($dealId, false);
        if (!$deal) {
            return true;
        }

        $newStageId = (string)($arFields["STAGE_ID"] ?? '');
        $currentStageId = (string)($deal["STAGE_ID"] ?? '');

        // Đảm bảo đơn vị tiền tệ của Deal luôn là VND
        if (isset($arFields['CURRENCY_ID']) && $arFields['CURRENCY_ID'] !== 'VND') {
            $arFields['CURRENCY_ID'] = 'VND';
        }
        if (isset($arFields['ACCOUNT_CURRENCY_ID']) && $arFields['ACCOUNT_CURRENCY_ID'] !== 'VND') {
            $arFields['ACCOUNT_CURRENCY_ID'] = 'VND';
        }

        // Nếu không đổi sang stage mới (đã ở stage đó rồi) thì bỏ qua
        if (empty($newStageId) || $currentStageId === $newStageId) {
            return true;
        }

        global $USER;
        $currentUserId = (int)($USER ? $USER->GetID() : 0);
        $userLogin = $USER ? (string)$USER->GetLogin() : '';
        $isAdmin = ($currentUserId === 1 || ($USER && $USER->IsAdmin()));
        $isDirector = ($isAdmin || $userLogin === 'director' || $currentUserId === 4);

        $categoryId = (int)($deal["CATEGORY_ID"] ?? 0);

        // Danh sách các stage đóng Deal (hoàn tất thành công hoặc hủy/thất bại)
        $closingStages = [
            'C1:WON',    // Phát hành Báo cáo chính thức (Thành công - Cat 1)
            'C1:LOSE',   // Hủy cuộc kiểm toán (Thất bại / Hủy - Cat 1)
            'WON',       // Deal won (Thành công - Cat 0)
            'LOSE',      // Deal lost (Thất bại - Cat 0)
            'APOLOGY',   // Hủy / Từ chối (Cat 0)
        ];

        // 1. Kiểm soát thẩm quyền ĐÓNG DEAL (Close Deal / Hủy cuộc kiểm toán):
        // Thẩm quyền tối cao thuộc về Ban Giám đốc (Director). Trưởng phòng (Manager) và các vai trò khác không có quyền.
        if (in_array($newStageId, $closingStages, true)) {
            if (!$isDirector) {
                $msg = "Lỗi quy trình AASC: Chỉ Ban Giám đốc (Director) mới có thẩm quyền ký phát hành báo cáo kiểm toán chính thức hoặc hủy cuộc kiểm toán (Close Deal). Trưởng phòng (Manager) không có quyền hoàn tất hợp đồng này.";
                return self::abortDealUpdate($msg, $dealId, $currentStageId, $arFields);
            }
        }

        // 2. Kiểm soát phân quyền cho Deal thuộc Category 1: Quy trình Kiểm toán AASC
        if ($categoryId === 1 || strpos($newStageId, 'C1:') === 0) {
            // Giai đoạn C1:FIELDWORK (Kiểm toán thực địa): Cần Trưởng nhóm (Senior) hoặc cấp quản lý phê duyệt kế hoạch
            if ($newStageId === 'C1:FIELDWORK') {
                if (!$isAdmin && !in_array($userLogin, ['senior', 'manager', 'director'], true)) {
                    $msg = "Lỗi quy trình AASC: Chỉ Trưởng nhóm kiểm toán (Senior) hoặc cấp quản lý mới có quyền phê duyệt kế hoạch để chuyển sang Kiểm toán thực địa.";
                    return self::abortDealUpdate($msg, $dealId, $currentStageId, $arFields);
                }
            }

            // Giai đoạn C1:REVIEW_MANAGER (Soát xét chủ nhiệm): Cần Trưởng nhóm (Senior) soát xét cấp 1 xong mới chuyển
            if ($newStageId === 'C1:REVIEW_MANAGER') {
                if (!$isAdmin && !in_array($userLogin, ['senior', 'manager', 'director'], true)) {
                    $msg = "Lỗi quy trình AASC: Chỉ Trưởng nhóm kiểm toán (Senior) sau khi soát xét cấp 1 mới được chuyển hồ sơ cho Chủ nhiệm kiểm toán (Manager).";
                    return self::abortDealUpdate($msg, $dealId, $currentStageId, $arFields);
                }
            }

            // Giai đoạn C1:REVIEW_DIRECTOR (Phê duyệt Ban Giám đốc): Cần Chủ nhiệm (Manager) soát xét cấp 2 xong mới chuyển
            if ($newStageId === 'C1:REVIEW_DIRECTOR') {
                if (!$isAdmin && !in_array($userLogin, ['manager', 'director'], true)) {
                    $msg = "Lỗi quy trình AASC: Chỉ Chủ nhiệm kiểm toán (Manager) mới có quyền trình hồ sơ lên Ban Giám đốc (Director) phê duyệt.";
                    return self::abortDealUpdate($msg, $dealId, $currentStageId, $arFields);
                }
            }

            return true;
        }

        // 3. Kiểm soát cho Deal Category 0 (General) liên kết từ Lead
        $restrictedStages = [
            "PREPAYMENT_INVOICE", // Invoice (Hóa đơn / Gửi báo giá)
            "EXECUTING",          // In progress (Tiến hành kiểm toán)
            "FINAL_INVOICE",      // Final invoice (Quyết toán)
            "WON"                 // Deal won (Thành công)
        ];

        if (in_array($newStageId, $restrictedStages, true)) {
            // Tra cứu Lead gốc của Deal này
            $leadId = (int)($deal["LEAD_ID"] ?? 0);
            $xmlId = "";

            if ($leadId > 0) {
                global $USER_FIELD_MANAGER;
                $statusVal = $USER_FIELD_MANAGER->GetUserFieldValue("CRM_LEAD", "UF_APPROVAL_STATUS", $leadId);
                if (!empty($statusVal)) {
                    if (is_numeric($statusVal)) {
                        $enumRow = \CUserFieldEnum::GetList([], ["ID" => (int)$statusVal])->Fetch();
                        $xmlId = $enumRow ? $enumRow["XML_ID"] : "";
                    } else {
                        $xmlId = (string)$statusVal;
                    }
                }
            }

            // Nếu Deal này xuất phát từ Lead kiểm toán và trạng thái chưa phải là APPROVED
            if ($leadId > 0 && $xmlId !== "APPROVED") {
                $statusName = $enumRow["VALUE"] ?? "Chưa được duyệt";
                $msg = "Lỗi quy trình AASC: Hồ sơ kiểm toán chưa được phê duyệt dự toán phí! (Trạng thái hiện tại: " . $statusName . "). Vui lòng hoàn tất phê duyệt trước khi chuyển sang bước Hóa đơn / Triển khai.";
                return self::abortDealUpdate($msg, $dealId, $currentStageId, $arFields);
            }
        }

        return true;
    }

    /**
     * Helper ngắt luồng cập nhật Deal và thiết lập đầy đủ thông báo lỗi cho Bitrix CRM UI
     */
    private static function abortDealUpdate(string $msg, int $dealId, string $currentStageId, array &$arFields): bool
    {
        $arFields["RESULT_MESSAGE"] = $msg;
        global $APPLICATION;
        if ($APPLICATION) {
            $APPLICATION->ThrowException($msg);
            $APPLICATION->ThrowException(new \CAdminException([
                ['id' => 'STAGE_ID', 'text' => $msg]
            ]));
        }

        // Nếu request gửi từ Stage bar hoặc Termination Dialog (SAVE_PROGRESS)
        $action = (string)($_REQUEST['action'] ?? $_REQUEST['ACTION'] ?? '');
        if ($action === 'SAVE_PROGRESS' && function_exists('__CrmDealListEndResponse')) {
            __CrmDealListEndResponse([
                'TYPE'  => \CCrmOwnerType::DealName,
                'ID'    => $dealId,
                'VALUE' => $currentStageId,
                'ERROR' => $msg,
            ]);
        }

        return false;
    }

    /**
     * Bắt sự kiện OnBeforeCrmLeadUpdate: Kiểm tra khi cập nhật Lead
     */
    public static function onBeforeLeadUpdate(&$arFields): bool
    {
        if (self::$isHandlingLeadUpdate) {
            return true;
        }

        $newStatusId = (string)($arFields["STATUS_ID"] ?? '');
        $leadId = (int)($arFields["ID"] ?? 0);
        if ($leadId <= 0) {
            return true;
        }

        $lead = \CCrmLead::GetByID($leadId, false);
        if (!$lead) {
            return true;
        }

        $prevStatus = (string)($lead['STATUS_ID'] ?? 'NEW');
        self::$prevStatusMap[$leadId] = $prevStatus;
        $prevFee = (float)($lead['OPPORTUNITY'] ?? 0);
        $effectiveStatus = !empty($newStatusId) ? $newStatusId : $prevStatus;
        $newFee = isset($arFields['OPPORTUNITY']) ? (float)$arFields['OPPORTUNITY'] : $prevFee;

        // Đảm bảo tiền tệ luôn là VND
        $arFields['CURRENCY_ID'] = 'VND';
        $arFields['ACCOUNT_CURRENCY_ID'] = 'VND';
        $arFields['EXCH_RATE'] = 1.0;
        if (isset($arFields['OPPORTUNITY'])) {
            $arFields['OPPORTUNITY_ACCOUNT'] = (float)$arFields['OPPORTUNITY'];
        }

        global $USER, $APPLICATION, $USER_FIELD_MANAGER;
        $currentUserId = (int)($USER ? $USER->GetID() : 0);
        $currentUserLogin = $USER ? (string)$USER->GetLogin() : '';
        $isAdmin = ($currentUserId === 1 || ($USER && $USER->IsAdmin()));
        $isDirector = ($isAdmin || $currentUserLogin === 'director' || $currentUserId === 4);
        $isManager = ($currentUserLogin === 'manager' || $currentUserId === 5);

        // 1. Khi chuyển sang IN_PROCESS và nhập tiền xong -> Bắn thông báo chuông tới Director để duyệt
        $feeChanged = isset($arFields['OPPORTUNITY']) && abs((float)$arFields['OPPORTUNITY'] - $prevFee) > 0.01;
        $statusChangedToInProcess = ($effectiveStatus === 'IN_PROCESS' && $prevStatus !== 'IN_PROCESS');
        if ($effectiveStatus === 'IN_PROCESS' && $newFee > 0 && ($statusChangedToInProcess || ($feeChanged && $prevFee <= 0))) {
            $waitingDirEnumId = self::getApprovalStatusEnumId('WAITING_DIR');
            if ($waitingDirEnumId) {
                $arFields['UF_APPROVAL_STATUS'] = $waitingDirEnumId;
            }

            // Gửi thông báo chuông nội bộ cho Director
            $directorUser = \Bitrix\Main\UserTable::getList([
                'filter' => ['=LOGIN' => 'director', '=ACTIVE' => 'Y'],
                'select' => ['ID'],
            ])->fetch();
            $directorId = $directorUser ? (int)$directorUser['ID'] : 4;

            if (\Bitrix\Main\Loader::includeModule('im') && $currentUserId !== $directorId) {
                \CIMNotify::Add([
                    'TO_USER_ID'     => $directorId,
                    'FROM_USER_ID'   => $currentUserId > 0 ? $currentUserId : 5,
                    'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                    'NOTIFY_MODULE'  => 'aasc.audit',
                    'NOTIFY_TAG'     => 'AASC|WAITING_DIR|' . $leadId,
                    'NOTIFY_MESSAGE' => 'Trưởng phòng đã thẩm định và lập dự toán chi phí ' . number_format($newFee, 0, ',', '.') . ' VND cho hồ sơ #' . $leadId . ' (' . ($lead['TITLE'] ?? '') . '). Vui lòng vào xem xét và phê duyệt sang trạng thái Đã xử lý (Processed).',
                    'NOTIFY_LINK'    => '/crm/lead/details/' . $leadId . '/',
                ]);
            }

            // Ghi nhận vào CRM Timeline (chỉ khi chưa có ghi chú tương tự để tránh trùng lặp)
            if (\Bitrix\Main\Loader::includeModule('crm')) {
                $connection = \Bitrix\Main\Application::getConnection();
                $checkExist = $connection->query("
                    SELECT t.ID FROM b_crm_timeline t
                    JOIN b_crm_timeline_bind b ON b.OWNER_ID = t.ID
                    WHERE b.ENTITY_TYPE_ID = " . (int)\CCrmOwnerType::Lead . "
                      AND b.ENTITY_ID = " . (int)$leadId . "
                      AND t.COMMENT LIKE '%Trưởng phòng đã lập dự toán chi phí%'
                    LIMIT 1
                ")->fetch();

                if (!$checkExist) {
                    self::$isHandlingLeadUpdate = true;
                    try {
                        \Bitrix\Crm\Timeline\CommentEntry::create([
                            'TEXT'      => 'Trưởng phòng đã lập dự toán chi phí: ' . number_format($newFee, 0, ',', '.') . ' VND và chuyển hồ sơ sang trạng thái Đang xử lý (In Progress) để trình Ban Giám đốc phê duyệt.',
                            'AUTHOR_ID' => $currentUserId > 0 ? $currentUserId : 5,
                            'BINDINGS'  => [
                                ['ENTITY_TYPE_ID' => \CCrmOwnerType::Lead, 'ENTITY_ID' => $leadId],
                            ],
                        ]);
                    } finally {
                        self::$isHandlingLeadUpdate = false;
                    }
                }
            }
        }

        // 2. Kiểm soát khi chuyển sang PROCESSED / PROPOSAL_SENT
        if (in_array($newStatusId, ["PROCESSED", "PROPOSAL_SENT"], true)) {
            // Nếu Lead đã ở trạng thái này rồi thì bỏ qua
            if ($prevStatus === $newStatusId) {
                return true;
            }

            // Kiểm tra chi phí dự toán (bắt buộc phải có trước khi phê duyệt)
            $fee = $newFee;
            if ($fee <= 0 && !empty($arFields['UF_ESTIMATED_FEE'])) {
                $fee = (float)$arFields['UF_ESTIMATED_FEE'];
            }
            if ($fee <= 0 && !empty($lead['UF_ESTIMATED_FEE'])) {
                $fee = (float)$lead['UF_ESTIMATED_FEE'];
            }

            if ($fee <= 0) {
                $msg = "Lỗi quy trình AASC: Vui lòng điền chi phí dịch vụ kiểm toán (Số tiền / Opportunity) trước khi phê duyệt phát hành báo giá!";
                $arFields["RESULT_MESSAGE"] = $msg;
                $APPLICATION->ThrowException($msg);
                return false;
            }

            // Kiểm tra trạng thái phê duyệt hiện tại
            $statusVal = $USER_FIELD_MANAGER->GetUserFieldValue("CRM_LEAD", "UF_APPROVAL_STATUS", $leadId);
            $xmlId = "";
            if (!empty($statusVal)) {
                if (is_numeric($statusVal)) {
                    $enumRow = \CUserFieldEnum::GetList([], ["ID" => (int)$statusVal])->Fetch();
                    $xmlId = $enumRow ? $enumRow["XML_ID"] : "";
                } else {
                    $xmlId = (string)$statusVal;
                }
            }

            // A. Ban Giám đốc (Director) hoặc Admin thực hiện phê duyệt
            if ($isDirector) {
                $approvedEnumId = self::getApprovalStatusEnumId('APPROVED');
                if ($approvedEnumId) {
                    $arFields['UF_APPROVAL_STATUS'] = $approvedEnumId;
                }
                return true;
            }

            // B. Trưởng phòng (Manager) thực hiện
            if ($isManager) {
                if ($xmlId !== 'APPROVED') {
                    $msg = "Lỗi quy trình AASC: Trưởng phòng (Manager) có vai trò lập dự toán chi phí (" . number_format($fee, 0, ',', '.') . " VNĐ). Thẩm quyền duyệt phát hành báo giá sang trạng thái Đã xử lý (Processed) thuộc về Ban Giám đốc (Director). Vui lòng chuyển hồ sơ cho Ban Giám đốc phê duyệt!";
                    $arFields["RESULT_MESSAGE"] = $msg;
                    $APPLICATION->ThrowException($msg);
                    return false;
                }
                return true;
            }

            // C. Các vai trò khác
            if ($xmlId !== 'APPROVED') {
                $msg = "Lỗi phân quyền AASC: Bạn không có quyền phê duyệt phát hành báo giá cho hồ sơ này.";
                $arFields["RESULT_MESSAGE"] = $msg;
                $APPLICATION->ThrowException($msg);
                return false;
            }
        }

        // 3. Chặn tự ý chuyển đổi Lead sang Deal (CONVERTED) khi khách hàng chưa ký hợp đồng trên Portal
        if ($newStatusId === "CONVERTED") {
            $auditRequest = \Aasc\Audit\Model\AuditRequestTable::getList([
                'filter' => ['=CRM_LEAD_ID' => $leadId],
                'select' => ['ID', 'CRM_DEAL_ID', 'STATUS'],
            ])->fetch();

            $isSignedByClient = $auditRequest && ($auditRequest['STATUS'] === 'CONTRACT_SIGNED' || (int)($auditRequest['CRM_DEAL_ID'] ?? 0) > 0 || !empty($arFields['IS_PORTAL_SIGN']) || self::$isPortalSign);
            if ($auditRequest && !$isSignedByClient) {
                $msg = "Lỗi quy trình AASC: Khách hàng chưa xác nhận đồng ý ký hợp đồng trên Cổng thông tin (Portal). Chỉ khi khách hàng xác nhận chấp thuận báo giá và ký hợp đồng thì mới được chuyển đổi trạng thái hồ sơ sang Đã chuyển đổi (Converted).";
                $arFields["RESULT_MESSAGE"] = $msg;
                $APPLICATION->ThrowException($msg);
                return false;
            }
        }

        return true;
    }

    /**
     * Bắt sự kiện OnBeforeCrmDealAdd: Chặn tạo Deal nếu chuyển đổi từ Lead mà khách hàng chưa ký hợp đồng trên Portal
     */
    public static function onBeforeDealAdd(&$arFields): bool
    {
        $leadId = (int)($arFields['LEAD_ID'] ?? 0);
        if ($leadId <= 0) {
            return true;
        }

        // Đảm bảo Deal luôn có đơn vị tiền tệ là VND
        $arFields['CURRENCY_ID'] = 'VND';
        $arFields['ACCOUNT_CURRENCY_ID'] = 'VND';
        $arFields['EXCH_RATE'] = 1.0;
        if (isset($arFields['OPPORTUNITY'])) {
            $arFields['OPPORTUNITY_ACCOUNT'] = (float)$arFields['OPPORTUNITY'];
        }

        // Kiểm tra xem Lead này có liên kết với yêu cầu kiểm toán nào trong aasc_audit_request không
        $auditRequest = \Aasc\Audit\Model\AuditRequestTable::getList([
            'filter' => ['=CRM_LEAD_ID' => $leadId],
            'select' => ['ID', 'CRM_DEAL_ID', 'STATUS'],
        ])->fetch();

        if (!$auditRequest) {
            return true;
        }

        // Nếu hồ sơ này đã có Deal
        $existingDealId = (int)($auditRequest['CRM_DEAL_ID'] ?? 0);
        if ($existingDealId > 0) {
            $msg = "Lỗi quy trình AASC: Hồ sơ kiểm toán này đã có Hợp đồng kiểm toán (Deal #{$existingDealId}). Không thể chuyển đổi tạo thêm Deal mới từ Lead này.";
            $arFields["RESULT_MESSAGE"] = $msg;
            global $APPLICATION;
            $APPLICATION->ThrowException($msg);
            return false;
        }

        // Kiểm tra xem khách hàng đã đồng ý ký hợp đồng trên Portal chưa
        $isSignedByClient = ($auditRequest['STATUS'] === 'CONTRACT_SIGNED' || !empty($arFields['IS_PORTAL_SIGN']) || self::$isPortalSign);

        if (!$isSignedByClient) {
            $msg = "Lỗi quy trình AASC: Khách hàng chưa xác nhận đồng ý ký hợp đồng trên Cổng thông tin (Portal). Chỉ khi khách hàng xác nhận chấp thuận báo giá và ký hợp đồng thì Ban Giám đốc / Trưởng phòng mới được chuyển đổi hồ sơ sang Hợp đồng (Deal).";
            $arFields["RESULT_MESSAGE"] = $msg;
            global $APPLICATION;
            $APPLICATION->ThrowException($msg);
            return false;
        }

        return true;
    }

    /**
     * Bắt sự kiện OnAfterCrmDealAdd: Đồng bộ Deal vừa tạo vào aasc_audit_request và đẩy Push & Pull
     */
    public static function onAfterDealAdd(&$arFields): void
    {
        $dealId = (int)($arFields['ID'] ?? 0);
        $leadId = (int)($arFields['LEAD_ID'] ?? 0);
        if ($dealId <= 0 || $leadId <= 0) {
            return;
        }

        $auditRequest = \Aasc\Audit\Model\AuditRequestTable::getList([
            'filter' => ['=CRM_LEAD_ID' => $leadId],
            'select' => ['ID', 'USER_ID', 'COMPANY_NAME'],
        ])->fetch();

        if (!$auditRequest) {
            return;
        }

        $requestId = (int)$auditRequest['ID'];
        $stageId = (string)($arFields['STAGE_ID'] ?? 'C1:PREPARATION');

        // Cập nhật lại cột STATUS và CRM_DEAL_ID trong bảng aasc_audit_request
        \Aasc\Audit\Model\AuditRequestTable::update($requestId, [
            'CRM_DEAL_ID' => $dealId,
            'STATUS'      => $stageId,
        ]);

        // Đẩy sự kiện qua Push & Pull
        if (Loader::includeModule('pull')) {
            \CPullWatch::AddToStack('AASC_AUDIT_REQUEST_' . $requestId, [
                'module_id' => 'aasc.audit',
                'command'   => 'request_status_updated',
                'params'    => [
                    'requestId' => $requestId,
                    'dealId'    => $dealId,
                    'statusId'  => $stageId,
                    'stepIndex' => 3,
                ]
            ]);
        }
    }

    /**
     * Tra cứu ID giá trị enum của thuộc tính UF_APPROVAL_STATUS theo mã XML_ID
     */
    public static function getApprovalStatusEnumId(string $xmlId): ?int
    {
        $rs = \CUserFieldEnum::GetList([], [
            'USER_FIELD_NAME' => 'UF_APPROVAL_STATUS',
            'XML_ID'          => $xmlId,
        ]);
        if ($row = $rs->Fetch()) {
            return (int)$row['ID'];
        }
        return null;
    }

    /**
     * Bắt sự kiện OnAfterCrmLeadAdd: Bắn thông báo nội bộ và đẩy Redis Pub/Sub
     */
    public static function onAfterLeadAdd(&$arFields): void
    {
        $leadId = (int)($arFields["ID"] ?? 0);
        if ($leadId <= 0) {
            return;
        }

        $managerUser = UserTable::getList([
            'filter' => ['=LOGIN' => 'manager', '=ACTIVE' => 'Y'],
            'select' => ['ID'],
        ])->fetch();

        if ($managerUser) {
            $managerId = (int)$managerUser['ID'];

            // Gửi thông báo chuông nội bộ
            if (Loader::includeModule('im')) {
                \CIMNotify::Add([
                    'TO_USER_ID'     => $managerId,
                    'FROM_USER_ID'   => 0,
                    'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                    'NOTIFY_MODULE'  => 'aasc.audit',
                    'NOTIFY_TAG'     => 'AASC|NEW_LEAD|' . $leadId,
                    'NOTIFY_MESSAGE' => 'Có hồ sơ kiểm toán mới từ Portal: ' . ($arFields['TITLE'] ?? '') . '. Vui lòng thẩm định và thành lập Đoàn kiểm toán.',
                ]);
            }
        }

        // Bắn trực tiếp vào Redis Pub/Sub qua cấu hình ENV (chuẩn 12-Factor App)
        try {
            $redisHost    = $_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST') ?: '127.0.0.1';
            $redisPort    = (int)($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379);
            $redisPass    = $_ENV['REDIS_PASSWORD'] ?? getenv('REDIS_PASSWORD') ?: '';
            $redisChannel = $_ENV['REDIS_CHANNEL'] ?? getenv('REDIS_CHANNEL') ?: 'bitrix:pull:events';

            $payload = json_encode([
                'event'       => 'onCrmLeadCreate',
                'leadId'      => $leadId,
                'title'       => $arFields['TITLE'] ?? '',
                'companyName' => $arFields['COMPANY_TITLE'] ?? '',
                'assignedTo'  => $managerId ?? 1,
                'timestamp'   => time(),
                'data'        => [
                    'ID'            => $leadId,
                    'TITLE'         => $arFields['TITLE'] ?? '',
                    'COMPANY_TITLE' => $arFields['COMPANY_TITLE'] ?? '',
                    'NAME'          => $arFields['NAME'] ?? '',
                    'EMAIL'         => $arFields['EMAIL'] ?? '',
                    'PHONE'         => $arFields['PHONE'] ?? '',
                ],
            ], JSON_UNESCAPED_UNICODE);

            if (class_exists('\Redis')) {
                $redis = new \Redis();
                if ($redis->connect($redisHost, $redisPort, 1.0)) {
                    if (!empty($redisPass)) {
                        $redis->auth($redisPass);
                    }
                    $redis->publish($redisChannel, $payload);
                    $redis->close();
                }
            } else {
                // Fallback socket publish cho môi trường không có ext-redis (ví dụ Windows dev)
                $fp = @fsockopen($redisHost, $redisPort, $errno, $errstr, 1.0);
                if ($fp) {
                    stream_set_timeout($fp, 1);
                    if (!empty($redisPass)) {
                        fwrite($fp, "*2\r\n$4\r\nAUTH\r\n$" . strlen($redisPass) . "\r\n$redisPass\r\n");
                        fgets($fp);
                    }
                    fwrite($fp, "*3\r\n$7\r\nPUBLISH\r\n$" . strlen($redisChannel) . "\r\n$redisChannel\r\n$" . strlen($payload) . "\r\n$payload\r\n");
                    fgets($fp);
                    fclose($fp);
                }
            }
        } catch (\Throwable $e) {
            // Không làm gián đoạn luồng nghiệp vụ nếu Redis tạm thời gián đoạn
        }

        // Bắn bản tin thời gian thực qua module Pull (kết nối Redis & Node.js push server)
        if (Loader::includeModule('pull')) {
            \CPullStack::AddShared([
                'module_id' => 'aasc.audit',
                'command'   => 'new_audit_request',
                'params'    => [
                    'leadId'      => $leadId,
                    'title'       => $arFields['TITLE'] ?? '',
                    'companyName' => $arFields['COMPANY_TITLE'] ?? '',
                ],
            ]);
        }
    }

    /**
     * Bắt sự kiện OnAfterCrmLeadUpdate: Phát sóng cập nhật trạng thái thời gian thực qua WebSocket
     */
    public static function onAfterLeadUpdate(&$arFields): void
    {
        if (self::$isHandlingLeadUpdate) {
            return;
        }

        self::$isHandlingLeadUpdate = true;
        try {
            $leadId = (int)($arFields['ID'] ?? 0);
            if ($leadId <= 0) {
                return;
            }

            // Kiểm tra xem Lead này có liên kết với yêu cầu kiểm toán nào trong aasc_audit_request không
            $auditRequest = \Aasc\Audit\Model\AuditRequestTable::getList([
                'filter' => ['=CRM_LEAD_ID' => $leadId],
                'select' => ['ID', 'USER_ID', 'STATUS'],
            ])->fetch();

            if (!$auditRequest) {
                return;
            }

            $requestId = (int)$auditRequest['ID'];
            $requestUserId = (int)($auditRequest['USER_ID'] ?? 0);
            $leadStatusId = (string)($arFields['STATUS_ID'] ?? '');

            if (empty($leadStatusId) && Loader::includeModule('crm')) {
                $lead = \CCrmLead::GetByID($leadId, false);
                $leadStatusId = (string)($lead['STATUS_ID'] ?? 'NEW');
            }

            $prevStatus = self::$prevStatusMap[$leadId] ?? '';
            $statusChanged = (!empty($prevStatus) && $prevStatus !== $leadStatusId);

            // Tính bước tiến trình hiện tại (1 -> 6)
            // Mức 1: Tiếp nhận hồ sơ (NEW, IN_PROCESS)
            // Mức 2: Thẩm định & Báo giá sẵn sàng (PROCESSED, APPROVED, PROPOSAL_SENT)
            // Mức 3: Người dùng đồng ý ký hợp đồng (CONTRACT_SIGNED, CONVERTED)
            $currentStep = 1;
            $auditReqStatus = (string)($auditRequest['STATUS'] ?? '');
            if ($auditReqStatus === 'CONTRACT_SIGNED' || in_array($leadStatusId, ['CONVERTED', 'WON', 'COMPLETED', '4'])) {
                $currentStep = 3;
            } elseif (in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED', '3'])) {
                $currentStep = 2;
            } else {
                $currentStep = 1;
            }

            // Cập nhật lại cột STATUS trong bảng aasc_audit_request nếu khách hàng chưa ký hợp đồng và có đổi trạng thái
            if ($auditReqStatus !== 'CONTRACT_SIGNED' && $statusChanged) {
                \Aasc\Audit\Model\AuditRequestTable::update($requestId, [
                    'STATUS' => $leadStatusId,
                ]);
            }

            // Đẩy sự kiện qua Push & Pull (WebSocket) để giao diện Stepper của khách hàng cập nhật trực tiếp
            if (Loader::includeModule('pull')) {
                $eventData = [
                    'module_id' => 'aasc.audit',
                    'command'   => 'request_status_updated',
                    'params'    => [
                        'requestId' => $requestId,
                        'leadId'    => $leadId,
                        'statusId'  => $leadStatusId,
                        'stepIndex' => $currentStep,
                    ]
                ];

                \CPullWatch::AddToStack('AASC_AUDIT_REQUEST_' . $requestId, $eventData);

                if ($requestUserId > 0) {
                    \Bitrix\Pull\Event::add($requestUserId, $eventData);
                }
            }

            // Nếu thực sự chuyển sang PROCESSED: Ghi nhận Timeline CRM và bắn thông báo chuông cho Manager
            // CHỈ GHI KHI: trạng thái vừa đổi sang PROCESSED (từ trạng thái khác) và chưa từng có ghi chú phê duyệt cho Lead này
            if ($leadStatusId === 'PROCESSED' && $statusChanged && Loader::includeModule('crm')) {
                $connection = \Bitrix\Main\Application::getConnection();
                $checkExist = $connection->query("
                    SELECT t.ID FROM b_crm_timeline t
                    JOIN b_crm_timeline_bind b ON b.OWNER_ID = t.ID
                    WHERE b.ENTITY_TYPE_ID = " . (int)\CCrmOwnerType::Lead . "
                      AND b.ENTITY_ID = " . (int)$leadId . "
                      AND t.COMMENT LIKE '%Ban Giám đốc đã phê duyệt dự toán chi phí%'
                    LIMIT 1
                ")->fetch();

                if (!$checkExist) {
                    $directorUser = \Bitrix\Main\UserTable::getList([
                        'filter' => ['=LOGIN' => 'director', '=ACTIVE' => 'Y'],
                        'select' => ['ID'],
                    ])->fetch();
                    $directorId = $directorUser ? (int)$directorUser['ID'] : 4;

                    $lead = \CCrmLead::GetByID($leadId, false);
                    $feeVal = (float)($lead['OPPORTUNITY'] ?? 0);
                    $feeStr = $feeVal > 0 ? number_format($feeVal, 0, ',', '.') . ' VNĐ' : '';

                    \Bitrix\Crm\Timeline\CommentEntry::create([
                        'TEXT'      => 'Ban Giám đốc đã phê duyệt dự toán chi phí' . ($feeStr ? ' (' . $feeStr . ')' : '') . '. Hồ sơ được chuyển sang trạng thái Đã xử lý (Processed) để phát hành báo giá cho khách hàng trên Cổng thông tin.',
                        'AUTHOR_ID' => $directorId,
                        'BINDINGS'  => [
                            ['ENTITY_TYPE_ID' => \CCrmOwnerType::Lead, 'ENTITY_ID' => $leadId],
                        ],
                    ]);

                    if (Loader::includeModule('im')) {
                        $assignedId = (int)($lead['ASSIGNED_BY_ID'] ?? 5);
                        if ($assignedId > 0 && $assignedId !== $directorId) {
                            \CIMNotify::Add([
                                'TO_USER_ID'     => $assignedId,
                                'FROM_USER_ID'   => $directorId,
                                'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                                'NOTIFY_MODULE'  => 'aasc.audit',
                                'NOTIFY_TAG'     => 'AASC|APPROVED|' . $leadId,
                                'NOTIFY_MESSAGE' => 'Hồ sơ #' . $leadId . ' (' . ($lead['TITLE'] ?? '') . ') đã được Ban Giám đốc phê duyệt dự toán phí ' . $feeStr . '. Báo giá đã được phát hành cho khách hàng.',
                            ]);
                        }
                    }
                }
            }
        } finally {
            self::$isHandlingLeadUpdate = false;
        }
    }

    /**
     * Bắt sự kiện OnAfterCrmDealUpdate: Đồng bộ tiến độ kiểm toán Deal và phát sóng Push & Pull
     */
    public static function onAfterDealUpdate(&$arFields): void
    {
        $dealId = (int)($arFields['ID'] ?? 0);
        if ($dealId <= 0) {
            return;
        }

        if (!Loader::includeModule('crm')) {
            return;
        }

        $deal = \CCrmDeal::GetByID($dealId, false);
        if (!$deal) {
            return;
        }

        $stageId = (string)($deal['STAGE_ID'] ?? '');
        $leadId = (int)($deal['LEAD_ID'] ?? 0);

        // Tìm kiếm hồ sơ yêu cầu kiểm toán tương ứng
        $auditRequest = \Aasc\Audit\Model\AuditRequestTable::getList([
            'filter' => [
                'LOGIC' => 'OR',
                ['=CRM_DEAL_ID' => $dealId],
                ['=CRM_LEAD_ID' => $leadId],
            ],
            'select' => ['ID', 'USER_ID', 'STATUS', 'COMPANY_NAME'],
        ])->fetch();

        if (!$auditRequest) {
            return;
        }

        $requestId = (int)$auditRequest['ID'];

        // Map 6 bước tiến trình theo chuẩn VSA:
        // 1: Tiếp nhận đơn
        // 2: Thẩm định & Báo giá
        // 3: Ký hợp đồng & Lập kế hoạch (C1:NEW, C1:PREPARATION)
        // 4: Kiểm toán thực địa (C1:FIELDWORK)
        // 5: Soát xét báo cáo (C1:REVIEW_MANAGER, C1:REVIEW_DIRECTOR)
        // 6: Phát hành Báo cáo chính thức (C1:WON)
        $stepIndex = 3;
        if ($stageId === 'C1:FIELDWORK') {
            $stepIndex = 4;
        } elseif (in_array($stageId, ['C1:REVIEW_MANAGER', 'C1:REVIEW_DIRECTOR'], true)) {
            $stepIndex = 5;
        } elseif ($stageId === 'C1:WON') {
            $stepIndex = 6;
        }

        // Cập nhật lại cột STATUS và CRM_DEAL_ID trong bảng aasc_audit_request
        \Aasc\Audit\Model\AuditRequestTable::update($requestId, [
            'CRM_DEAL_ID' => $dealId,
            'STATUS'      => $stageId,
        ]);

        // Gửi thông báo chuông nội bộ theo từng chặng
        if (Loader::includeModule('im')) {
            global $USER;
            $currentUid = (int)($USER ? $USER->GetID() : 0);
            $targetUser = null;
            $notifyMsg = '';

            if ($stageId === 'C1:FIELDWORK') {
                $junior = UserTable::getList(['filter' => ['=LOGIN' => 'junior', '=ACTIVE' => 'Y'], 'select' => ['ID']])->fetch();
                if ($junior) {
                    $targetUser = (int)$junior['ID'];
                    $notifyMsg = 'Hồ sơ kiểm toán #' . $requestId . ' (' . ($auditRequest['COMPANY_NAME'] ?? '') . ') đã hoàn tất lập kế hoạch. Mời Trợ lý kiểm toán bắt đầu thực hiện kiểm toán thực địa.';
                }
            } elseif ($stageId === 'C1:REVIEW_MANAGER') {
                $mgr = UserTable::getList(['filter' => ['=LOGIN' => 'manager', '=ACTIVE' => 'Y'], 'select' => ['ID']])->fetch();
                if ($mgr) {
                    $targetUser = (int)$mgr['ID'];
                    $notifyMsg = 'Trưởng nhóm đã hoàn thành soát xét cấp 1 cho hồ sơ #' . $requestId . '. Mời Chủ nhiệm kiểm toán soát xét cấp 2.';
                }
            } elseif ($stageId === 'C1:REVIEW_DIRECTOR') {
                $dir = UserTable::getList(['filter' => ['=LOGIN' => 'director', '=ACTIVE' => 'Y'], 'select' => ['ID']])->fetch();
                if ($dir) {
                    $targetUser = (int)$dir['ID'];
                    $notifyMsg = 'Chủ nhiệm đã trình hồ sơ #' . $requestId . ' lên Ban Giám đốc để soát xét kiểm soát chất lượng (EQCR).';
                }
            } elseif ($stageId === 'C1:WON') {
                $clientId = (int)$auditRequest['USER_ID'];
                if ($clientId > 0) {
                    $targetUser = $clientId;
                    $notifyMsg = 'Báo cáo kiểm toán chính thức cho hồ sơ #' . $requestId . ' đã được ký phát hành! Bạn có thể tải báo cáo từ Cổng thông tin.';
                }
            }

            if ($targetUser && !empty($notifyMsg)) {
                \CIMNotify::Add([
                    'TO_USER_ID'     => $targetUser,
                    'FROM_USER_ID'   => $currentUid > 0 ? $currentUid : 0,
                    'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                    'NOTIFY_MODULE'  => 'aasc.audit',
                    'NOTIFY_TAG'     => 'AASC|DEAL_STAGE|' . $dealId,
                    'NOTIFY_MESSAGE' => $notifyMsg,
                ]);
            }
        }

        // Đẩy sự kiện qua Push & Pull
        if (Loader::includeModule('pull')) {
            \CPullWatch::AddToStack('AASC_AUDIT_REQUEST_' . $requestId, [
                'module_id' => 'aasc.audit',
                'command'   => 'request_status_updated',
                'params'    => [
                    'requestId' => $requestId,
                    'dealId'    => $dealId,
                    'statusId'  => $stageId,
                    'stepIndex' => $stepIndex,
                ]
            ]);
        }
    }
}

