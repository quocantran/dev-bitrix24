<?php
namespace Aasc\Audit\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use Bitrix\Main\UserTable;
use Bitrix\Main\Loader;

class Approval extends Controller
{
    public function configureActions(): array
    {
        return [
            'decide' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
        ];
    }

    /**
     * Action phê duyệt hoặc từ chối báo giá
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.approval.decide
     */
    public function decideAction(int $leadId, string $decision, string $comment = ''): ?array
    {
        global $USER;
        $currentUserId = (int)$USER->GetID();
        $currentUserLogin = $USER->GetLogin();

        Loader::includeModule('crm');
        Loader::includeModule('im');

        $lead = \CCrmLead::GetByID($leadId, false);
        if (!$lead) {
            $this->addError(new Error('Không tìm thấy hồ sơ Lead có mã: ' . $leadId));
            return null;
        }

        $assignedAuditorId = (int)$lead['ASSIGNED_BY_ID'];
        $leadObj = new \CCrmLead(false);

        // Trường hợp 1: Từ chối yêu cầu
        if ($decision === 'REJECT') {
            $enumId = $this->getStatusEnumId('REJECTED');
            $updateReject = ['UF_APPROVAL_STATUS' => $enumId];
            $leadObj->Update($leadId, $updateReject);

            \CIMNotify::Add([
                'TO_USER_ID'     => $assignedAuditorId,
                'FROM_USER_ID'   => $currentUserId,
                'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                'NOTIFY_MODULE'  => 'aasc.audit',
                'NOTIFY_MESSAGE' => 'Dự thảo báo giá cho hồ sơ #' . $leadId . ' đã bị từ chối bởi ' . $currentUserLogin . '. Ghi chú: ' . htmlspecialcharsbx($comment),
            ]);

            return [
                'status'   => 'rejected',
                'leadId'   => $leadId,
                'message'  => 'Đã từ chối dự thảo báo giá.',
            ];
        }

        // Trường hợp 2: Trưởng phòng (manager) duyệt
        if ($currentUserLogin === 'manager' || $currentUserId === 5) {
            // Lấy doanh thu từ chuỗi comments hoặc cơ sở dữ liệu
            $revenue = (float)($lead['OPPORTUNITY'] ?? 0);
            if ($revenue <= 0 && preg_match('/Doanh thu:\s*([0-9,\.]+)/', $lead['COMMENTS'] ?? '', $m)) {
                $revenue = (float)str_replace([',', '.'], '', $m[1]);
            }

            if ($revenue >= 50000000000) {
                // Khách lớn (>= 50 tỷ): Cần Ban Giám đốc duyệt tiếp
                $director = UserTable::getList([
                    'filter' => ['=LOGIN' => 'director', '=ACTIVE' => 'Y'],
                    'select' => ['ID'],
                ])->fetch();

                $enumId = $this->getStatusEnumId('WAITING_DIR');
                $updateWaitDir = ['UF_APPROVAL_STATUS' => $enumId];
                $leadObj->Update($leadId, $updateWaitDir);

                if ($director) {
                    \CIMNotify::Add([
                        'TO_USER_ID'     => (int)$director['ID'],
                        'FROM_USER_ID'   => $currentUserId,
                        'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                        'NOTIFY_MODULE'  => 'aasc.audit',
                        'NOTIFY_MESSAGE' => 'Hồ sơ lớn #' . $leadId . ' (' . number_format($revenue) . ' VNĐ): Trưởng phòng đã duyệt, chuyển tiếp Ban Giám đốc phê duyệt lần cuối.',
                    ]);
                }

                return [
                    'status'   => 'forwarded_to_director',
                    'leadId'   => $leadId,
                    'message'  => 'Trưởng phòng đã duyệt. Đã chuyển tiếp lên Ban Giám đốc phê duyệt (Doanh thu >= 50 tỷ).',
                ];
            } else {
                // Khách thông thường (< 50 tỷ): Duyệt xong là hoàn tất
                $enumId = $this->getStatusEnumId('APPROVED');
                $updateApproved = ['UF_APPROVAL_STATUS' => $enumId];
                $leadObj->Update($leadId, $updateApproved);

                \CIMNotify::Add([
                    'TO_USER_ID'     => $assignedAuditorId,
                    'FROM_USER_ID'   => $currentUserId,
                    'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                    'NOTIFY_MODULE'  => 'aasc.audit',
                    'NOTIFY_MESSAGE' => 'Báo giá cho hồ sơ #' . $leadId . ' đã được Trưởng phòng phê duyệt. Bạn có thể gửi Thư đề xuất chính thức cho khách.',
                ]);

                return [
                    'status'   => 'approved',
                    'leadId'   => $leadId,
                    'message'  => 'Trưởng phòng đã phê duyệt dự toán báo giá.',
                ];
            }
        }

        // Trường hợp 3: Ban Giám đốc (director) duyệt
        if ($currentUserLogin === 'director' || $currentUserId === 4 || $currentUserId === 1) {
            $enumId = $this->getStatusEnumId('APPROVED');
            $updateFinal = ['UF_APPROVAL_STATUS' => $enumId];
            $leadObj->Update($leadId, $updateFinal);

            \CIMNotify::Add([
                'TO_USER_ID'     => $assignedAuditorId,
                'FROM_USER_ID'   => $currentUserId,
                'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                'NOTIFY_MODULE'  => 'aasc.audit',
                'NOTIFY_MESSAGE' => 'Ban Giám đốc đã phê duyệt báo giá cho hồ sơ #' . $leadId . '. Bạn được phép ban hành Thư đề xuất dịch vụ chính thức.',
            ]);

            return [
                'status'   => 'approved',
                'leadId'   => $leadId,
                'message'  => 'Ban Giám đốc đã phê duyệt báo giá.',
            ];
        }

        $this->addError(new Error('Tài khoản hiện tại không có thẩm quyền phê duyệt hồ sơ này.'));
        return null;
    }

    private function getStatusEnumId(string $xmlId): ?int
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
}
