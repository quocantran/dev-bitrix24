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
        $restrictedStages = [
            "PREPAYMENT_INVOICE", // Invoice (Hóa đơn / Gửi báo giá)
            "EXECUTING",          // In progress (Tiến hành kiểm toán)
            "FINAL_INVOICE",      // Final invoice (Quyết toán)
            "WON"                 // Deal won (Thành công)
        ];

        if (isset($arFields["STAGE_ID"]) && in_array($arFields["STAGE_ID"], $restrictedStages, true)) {
            $dealId = (int)($arFields["ID"] ?? 0);
            if ($dealId <= 0) {
                return true;
            }

            // Lấy thông tin Deal hiện tại
            $deal = \CCrmDeal::GetByID($dealId, false);
            if (!$deal) {
                return true;
            }

            // Nếu không đổi sang stage mới (đã ở stage đó rồi) thì bỏ qua
            if (isset($deal["STAGE_ID"]) && $deal["STAGE_ID"] === $arFields["STAGE_ID"]) {
                return true;
            }

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

            $redis = new \Redis();
            if ($redis->connect($redisHost, $redisPort, 1.0)) {
                if (!empty($redisPass)) {
                    $redis->auth($redisPass);
                }
                $payload = json_encode([
                    'event'       => 'onCrmLeadCreate',
                    'leadId'      => $leadId,
                    'title'       => $arFields['TITLE'] ?? '',
                    'companyName' => $arFields['COMPANY_TITLE'] ?? '',
                    'assignedTo'  => $managerId ?? 1,
                    'timestamp'   => time(),
                ], JSON_UNESCAPED_UNICODE);
                $redis->publish($redisChannel, $payload);
                $redis->close();
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
}
