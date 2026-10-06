<?php
namespace Aasc\Audit\Service;

use Aasc\Audit\Model\AuditRequestTable;
use Bitrix\Main\UserTable;

class CrmBridgeService
{
    /**
     * Khởi tạo Lead trong Bitrix CRM từ yêu cầu kiểm toán
     */
    public static function createLeadFromRequest(int $requestId, array $data): int
    {
        // Tìm ID của Trưởng phòng (manager) để gán tiếp nhận ban đầu
        $managerUser = UserTable::getList([
            'filter' => ['=LOGIN' => 'manager', '=ACTIVE' => 'Y'],
            'select' => ['ID'],
        ])->fetch();

        $managerId = $managerUser ? (int)$managerUser['ID'] : 1;

        $revenue = (float)($data['ANNUAL_REVENUE'] ?? 0);

        $draftEnumId = null;
        $rs = \CUserFieldEnum::GetList([], [
            'USER_FIELD_NAME' => 'UF_APPROVAL_STATUS',
            'XML_ID'          => 'DRAFT',
        ]);
        if ($row = $rs->Fetch()) {
            $draftEnumId = (int)$row['ID'];
        }

        $fields = [
            'TITLE'          => 'Yêu cầu kiểm toán: ' . ($data['COMPANY_NAME'] ?? 'Doanh nghiệp mới'),
            'NAME'           => $data['CONTACT_NAME'] ?? '',
            'COMPANY_TITLE'  => $data['COMPANY_NAME'] ?? '',
            'STATUS_ID'      => 'NEW',
            'OPENED'         => 'Y',
            'ASSIGNED_BY_ID' => $managerId,
            'SOURCE_ID'      => 'WEB_PORTAL',
            'OPPORTUNITY'    => 0,
            'COMMENTS'       => 'MST: ' . ($data['TAX_CODE'] ?? '') . ' | Doanh thu: ' . number_format($revenue) . ' VNĐ',
            'FM' => [
                'EMAIL' => ['n0' => ['VALUE' => $data['EMAIL'] ?? '', 'VALUE_TYPE' => 'WORK']],
                'PHONE' => ['n0' => ['VALUE' => $data['PHONE'] ?? '', 'VALUE_TYPE' => 'WORK']],
            ],
            // Khởi tạo các trường tùy biến
            'UF_APPROVAL_STATUS' => $draftEnumId ?? 26,
            'UF_ESTIMATED_FEE'   => 0.0,
        ];

        // Khởi tạo đối tượng nghiệp vụ \CCrmLead để kích hoạt đầy đủ workflow, permission và timeline
        $leadObj = new \CCrmLead(false);
        $leadId = $leadObj->Add($fields, true, ['CURRENT_USER' => 1]);

        if ($leadId > 0) {
            AuditRequestTable::update($requestId, [
                'CRM_LEAD_ID' => (int)$leadId,
            ]);
        }

        return (int)$leadId;
    }
}
