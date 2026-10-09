<?php
namespace Aasc\Audit\Handler;

class PortalAccessHandler
{
    /**
     * Danh sách các nhóm nội bộ: Quản trị viên (1), Nhân viên AASC (12), Ban Giám đốc (10), Quản trị Portal (13)
     */
    public const INTERNAL_GROUP_IDS = [1, 6, 9, 10, 11, 12, 13, 14, 15];

    /**
     * Danh sách các phân vùng Mạng nội bộ độc quyền của công ty (Intranet Blacklist)
     */
    public const RESTRICTED_INTRANET_PREFIXES = [
        '/stream/',      // Bảng tin nội bộ nhân viên
        '/company/',     // Sơ đồ nhân sự, danh bạ nhân viên
        '/crm/',         // Quản trị quan hệ khách hàng nội bộ
        '/workgroups/',  // Nhóm dự án nội bộ
        '/timeman/',     // Quản lý giờ công, chấm công
        '/bizproc/',     // Quy trình phê duyệt nội bộ
        '/calendar/',    // Lịch làm việc công ty
        '/docs/',        // Ổ đĩa tài liệu nội bộ
        '/mail/',        // Webmail nội bộ
        '/telephony/',   // Tổng đài nội bộ
        '/marketing/',   // Phân hệ Marketing
        '/automation/',  // Tự động hóa
        '/services/',    // Dịch vụ nội bộ Intranet
        '/marketplace/', // Chợ ứng dụng nội bộ
    ];

    /**
     * Kiểm tra người dùng có phải nhân viên nội bộ hay không
     */
    public static function isInternalUser($userId = null): bool
    {
        global $USER;
        if (!is_object($USER) || !$USER->IsAuthorized()) {
            return false;
        }

        $userGroups = $USER->GetUserGroupArray();
        foreach (self::INTERNAL_GROUP_IDS as $gid) {
            if (in_array($gid, $userGroups)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bắt sự kiện OnBeforeProlog: Điều hướng trang chủ và cách ly mạng nội bộ
     */
    public static function onBeforeProlog(): void
    {
        global $USER, $APPLICATION;

        if (!is_object($APPLICATION)) {
            return;
        }

        $curPage = $APPLICATION->GetCurPage(false);

        // Bỏ qua các tệp/đường dẫn API AJAX và công cụ hệ thống cần thiết
        if (
            strpos($curPage, '/bitrix/services/main/ajax.php') === 0 ||
            strpos($curPage, '/bitrix/tools/') === 0 ||
            strpos($curPage, '/bitrix/components/') === 0 ||
            strpos($curPage, '/upload/') === 0
        ) {
            return;
        }

        $isRoot = ($curPage === '/' || $curPage === '/index.php');

        $isRestrictedIntranet = $isRoot;
        if (!$isRestrictedIntranet) {
            foreach (self::RESTRICTED_INTRANET_PREFIXES as $prefix) {
                if (strpos($curPage, $prefix) === 0) {
                    $isRestrictedIntranet = true;
                    break;
                }
            }
        }

        $isAuthorized = is_object($USER) && $USER->IsAuthorized();

        // TRƯỜNG HỢP 1: KHÁCH VÃNG LAI (CHƯA ĐĂNG NHẬP)
        if (!$isAuthorized) {
            // Nếu truy cập vào trang chủ gốc /, tự động chuyển hướng ngay về /portal/
            if ($isRoot) {
                LocalRedirect('/portal/');
                die();
            }

            // Nếu truy cập vào các trang Intranet nhạy cảm, chuyển hướng sang trang đăng nhập cổng thông tin
            if ($isRestrictedIntranet) {
                LocalRedirect('/portal/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
                die();
            }

            return;
        }

        // TRƯỜNG HỢP 2: ĐÃ ĐĂNG NHẬP
        $isInternal = self::isInternalUser();
        $userGroups = $USER->GetUserGroupArray();
        $isClient = in_array(17, $userGroups);

        // Nếu là tài khoản Khách hàng (Client) và không phải nhân viên nội bộ
        if ($isClient && !$isInternal) {
            // Cố tình truy cập vào bất kỳ phân vùng nào thuộc Intranet (bao gồm cả trang chủ /)
            if ($isRestrictedIntranet) {
                LocalRedirect('/portal/');
                die();
            }
        }

        // Nếu là Nhân viên nội bộ ($isInternal == true):
        // Cho phép truy cập bình thường vào Intranet /stream/, /, /crm/, v.v.
    }
}
