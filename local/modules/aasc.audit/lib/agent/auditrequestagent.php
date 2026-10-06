<?php
namespace Aasc\Audit\Agent;

use Aasc\Audit\Model\AuditRequestTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader;

class AuditRequestAgent
{
    /**
     * Tác vụ nền kiểm tra SLA các yêu cầu tồn đọng quá 24h
     */
    public static function checkSla24h(): string
    {
        Loader::includeModule('im');

        // Thời điểm ngưỡng: hiện tại trừ đi 24 giờ
        $deadline = (new DateTime())->add('-24 hours');

        $overdueRequests = AuditRequestTable::getList([
            'select' => ['ID', 'COMPANY_NAME', 'CONTACT_NAME', 'PHONE', 'CREATED_AT'],
            'filter' => [
                '=STATUS'        => 'NEW',
                '=REMINDER_SENT' => 'N',
                '<CREATED_AT'    => $deadline,
            ],
            'limit' => 50,
        ])->fetchAll();

        foreach ($overdueRequests as $request) {
            // Gửi cảnh báo SLA tới Trưởng phòng (ID: 5)
            \CIMNotify::Add([
                'TO_USER_ID'     => 5,
                'FROM_USER_ID'   => 0,
                'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                'NOTIFY_MODULE'  => 'aasc.audit',
                'NOTIFY_TAG'     => 'AASC|SLA_OVERDUE|' . $request['ID'],
                'NOTIFY_MESSAGE' => 'Cảnh báo SLA 24h: Yêu cầu kiểm toán từ ' . $request['COMPANY_NAME'] . ' (SĐT: ' . $request['PHONE'] . ') chưa được xử lý.',
            ]);

            // Cập nhật cờ bảo đảm tính Idempotency
            AuditRequestTable::update($request['ID'], [
                'REMINDER_SENT' => 'Y',
                'UPDATED_AT'    => new DateTime(),
            ]);
        }

        return '\Aasc\Audit\Agent\AuditRequestAgent::checkSla24h();';
    }
}
