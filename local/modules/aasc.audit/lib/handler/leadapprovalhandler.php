<?php
namespace Aasc\Audit\Handler;

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class LeadApprovalHandler
{
    /**
     * Bắt sự kiện OnBeforeCrmDealUpdate: Chặn chuyển sang Invoice, In Progress, Final Invoice, Won
     * nếu dự toán phí chưa được phê duyệt (UF_APPROVAL_STATUS !== APPROVED)
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

        // Nếu không đổi sang stage mới (đã ở stage đó rồi) thì bỏ qua
        if (empty($newStageId) || $currentStageId === $newStageId) {
            return true;
        }

        $categoryId = (int)($deal["CATEGORY_ID"] ?? 0);

        // 1. Kiểm soát phân quyền cho Deal thuộc Category 1: Quy trình Kiểm toán AASC
        if ($categoryId === 1 || strpos($newStageId, 'C1:') === 0) {
            global $USER;
            $currentUserId = (int)($USER ? $USER->GetID() : 0);
            $userLogin = $USER ? (string)$USER->GetLogin() : '';
            $isAdmin = ($currentUserId === 1 || ($USER && $USER->IsAdmin()));

            // Giai đoạn C1:FIELDWORK (Kiểm toán thực địa): Cần Trưởng nhóm (Senior) hoặc cấp trên phê duyệt kế hoạch
            if ($newStageId === 'C1:FIELDWORK') {
                if (!$isAdmin && !in_array($userLogin, ['senior', 'manager', 'director'], true)) {
                    $msg = "Lỗi phân quyền AASC: Chỉ Trưởng nhóm kiểm toán (Senior) hoặc cấp quản lý mới có quyền phê duyệt kế hoạch để chuyển sang Kiểm toán thực địa.";
                    $arFields["RESULT_MESSAGE"] = $msg;
                    global $APPLICATION;
                    $APPLICATION->ThrowException($msg);
                    return false;
                }
            }

            // Giai đoạn C1:REVIEW_MANAGER (Soát xét chủ nhiệm): Cần Trưởng nhóm (Senior) soát xét cấp 1 xong mới chuyển
            if ($newStageId === 'C1:REVIEW_MANAGER') {
                if (!$isAdmin && !in_array($userLogin, ['senior', 'manager', 'director'], true)) {
                    $msg = "Lỗi phân quyền AASC: Chỉ Trưởng nhóm kiểm toán (Senior) sau khi soát xét cấp 1 mới được chuyển hồ sơ cho Chủ nhiệm kiểm toán (Manager).";
                    $arFields["RESULT_MESSAGE"] = $msg;
                    global $APPLICATION;
                    $APPLICATION->ThrowException($msg);
                    return false;
                }
            }

            // Giai đoạn C1:REVIEW_DIRECTOR (Phê duyệt Ban Giám đốc): Cần Chủ nhiệm (Manager) soát xét cấp 2 xong mới chuyển
            if ($newStageId === 'C1:REVIEW_DIRECTOR') {
                if (!$isAdmin && !in_array($userLogin, ['manager', 'director'], true)) {
                    $msg = "Lỗi phân quyền AASC: Chỉ Chủ nhiệm kiểm toán (Manager) mới có quyền trình hồ sơ lên Ban Giám đốc (Director) phê duyệt.";
                    $arFields["RESULT_MESSAGE"] = $msg;
                    global $APPLICATION;
                    $APPLICATION->ThrowException($msg);
                    return false;
                }
            }

            // Giai đoạn C1:WON (Phát hành Báo cáo chính thức): Chỉ Ban Giám đốc (Director) được phép phê duyệt
            if ($newStageId === 'C1:WON') {
                if (!$isAdmin && $userLogin !== 'director') {
                    $msg = "Lỗi phân quyền AASC: Chỉ Ban Giám đốc (Director) mới có thẩm quyền ký và phát hành Báo cáo kiểm toán chính thức (VSA 700).";
                    $arFields["RESULT_MESSAGE"] = $msg;
                    global $APPLICATION;
                    $APPLICATION->ThrowException($msg);
                    return false;
                }
            }

            return true;
        }

        // 2. Kiểm soát cho Deal Category 0 (General) liên kết từ Lead
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
                $arFields["RESULT_MESSAGE"] = $msg;
                global $APPLICATION;
                $APPLICATION->ThrowException($msg);
                return false;
            }
        }

        return true;
    }

    /**
     * Bắt sự kiện OnBeforeCrmLeadUpdate: Kiểm tra khi cập nhật Lead
     */
    public static function onBeforeLeadUpdate(&$arFields): bool
    {
        $restrictedStatuses = ["PROCESSED", "PROPOSAL_SENT"];
        if (isset($arFields["STATUS_ID"]) && in_array($arFields["STATUS_ID"], $restrictedStatuses, true)) {
            $leadId = (int)($arFields["ID"] ?? 0);
            if ($leadId <= 0) {
                return true;
            }

            global $USER_FIELD_MANAGER;
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

            if (!empty($statusVal) && $xmlId !== "APPROVED") {
                $msg = "Lỗi quy trình: Báo giá cho khách hàng chưa được Trưởng phòng hoặc Ban Giám đốc phê duyệt!";
                $arFields["RESULT_MESSAGE"] = $msg;
                global $APPLICATION;
                $APPLICATION->ThrowException($msg);
                return false;
            }
        }

        return true;
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
        $leadStatusId = (string)($arFields['STATUS_ID'] ?? '');

        if (empty($leadStatusId) && Loader::includeModule('crm')) {
            $lead = \CCrmLead::GetByID($leadId, false);
            $leadStatusId = (string)($lead['STATUS_ID'] ?? 'NEW');
        }

        // Tính bước tiến trình hiện tại (1 -> 6)
        $currentStep = 1;
        if (in_array($leadStatusId, ['IN_PROCESS', 'ASSIGNED', '2'])) {
            $currentStep = 2;
        } elseif (in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED', '3'])) {
            $currentStep = 2;
        } elseif (in_array($leadStatusId, ['CONVERTED', 'WON', 'COMPLETED', '4'])) {
            $currentStep = 3;
        }

        // Cập nhật lại cột STATUS trong bảng aasc_audit_request nếu cần
        \Aasc\Audit\Model\AuditRequestTable::update($requestId, [
            'STATUS' => $leadStatusId,
        ]);

        // Đẩy sự kiện qua Push & Pull để giao diện Stepper của khách hàng cập nhật trực tiếp
        if (Loader::includeModule('pull')) {
            \CPullWatch::AddToStack('AASC_AUDIT_REQUEST_' . $requestId, [
                'module_id' => 'aasc.audit',
                'command'   => 'request_status_updated',
                'params'    => [
                    'requestId' => $requestId,
                    'leadId'    => $leadId,
                    'statusId'  => $leadStatusId,
                    'stepIndex' => $currentStep,
                ]
            ]);
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

