<?php
namespace Aasc\Audit\Handler;

class PortalAccessHandler
{
    /**
     * Bắt sự kiện OnBeforeProlog: Cách ly hoàn toàn người dùng Portal Client khỏi Intranet nội bộ công ty
     */
    public static function onBeforeProlog(): void
    {
        global $USER, $APPLICATION;

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            return;
        }

        $userGroups = $USER->GetUserGroupArray();
        $isClient = in_array(17, $userGroups);

        // Danh sách các nhóm nội bộ: Quản trị viên (1), Nhân viên AASC (12), Ban Giám đốc (10), Quản trị Portal (13)
        $internalGroups = [1, 6, 9, 10, 11, 12, 13, 14, 15];
        $isInternal = false;
        foreach ($internalGroups as $gid) {
            if (in_array($gid, $userGroups)) {
                $isInternal = true;
                break;
            }
        }

        // Nếu là tài khoản Khách hàng Portal và không thuộc bất kỳ nhóm nhân viên nội bộ nào
        if ($isClient && !$isInternal) {
            $curPage = $APPLICATION->GetCurPage(true);

            // Cho phép các đường dẫn API / AJAX / Tools lõi của Bitrix mà client cần sử dụng
            if (
                strpos($curPage, '/bitrix/services/main/ajax.php') === 0 ||
                strpos($curPage, '/bitrix/tools/') === 0 ||
                strpos($curPage, '/bitrix/components/') === 0 ||
                strpos($curPage, '/upload/') === 0
            ) {
                return;
            }

            // Nếu người dùng cố tình truy cập vào Intranet nội bộ (/, /stream/, /company/, /crm/, /workgroups/...)
            // mà không nằm trong /portal/, lập tức chuyển hướng về /portal/
            if (strpos($curPage, '/portal/') !== 0) {
                LocalRedirect('/portal/');
                die();
            }
        }
    }
}
