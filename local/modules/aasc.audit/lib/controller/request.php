<?php
namespace Aasc\Audit\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use Bitrix\Main\Type\DateTime;
use Aasc\Audit\Model\AuditRequestTable;
use Aasc\Audit\Service\CrmBridgeService;

class Request extends Controller
{
    public function configureActions(): array
    {
        return [
            'send' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
        ];
    }

    /**
     * Action tiếp nhận form từ Portal
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.request.send
     */
    public function sendAction(array $formData = []): ?array
    {
        if (empty($formData)) {
            $formData = $this->getRequest()->getPostList()->toArray();
        }

        // 1. Kiểm tra tính hợp lệ dữ liệu
        $companyName = trim($formData['COMPANY_NAME'] ?? '');
        $taxCode     = trim($formData['TAX_CODE'] ?? '');
        $contactName = trim($formData['CONTACT_NAME'] ?? '');
        $phone       = trim($formData['PHONE'] ?? '');
        $email       = trim($formData['EMAIL'] ?? '');
        $serviceId   = (int)($formData['SERVICE_ID'] ?? 0);
        $revenue     = (float)($formData['ANNUAL_REVENUE'] ?? 0);

        if (empty($companyName) || empty($taxCode)) {
            $this->addError(new Error('Vui lòng nhập tên công ty và mã số thuế.'));
            return null;
        }

        if (!preg_match('/^[0-9]{10}(-[0-9]{3})?$/', $taxCode)) {
            $this->addError(new Error('Mã số thuế không đúng định dạng (10 hoặc 13 số).'));
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError(new Error('Địa chỉ email không đúng định dạng.'));
            return null;
        }

        global $USER;
        $currentUserId = is_object($USER) && $USER->IsAuthorized() ? (int)$USER->GetID() : 0;

        // 2. Ghi bản ghi vào bảng D7 ORM
        $addResult = AuditRequestTable::add([
            'USER_ID'        => $currentUserId,
            'COMPANY_NAME'   => $companyName,
            'TAX_CODE'       => $taxCode,
            'ANNUAL_REVENUE' => $revenue,
            'CONTACT_NAME'   => $contactName,
            'PHONE'          => $phone,
            'EMAIL'          => $email,
            'SERVICE_ID'     => $serviceId,
            'STATUS'         => 'NEW',
            'REMINDER_SENT'  => 'N',
            'CREATED_AT'     => new DateTime(),
            'UPDATED_AT'     => new DateTime(),
        ]);

        if (!$addResult->isSuccess()) {
            foreach ($addResult->getErrorMessages() as $msg) {
                $this->addError(new Error($msg));
            }
            return null;
        }

        $requestId = $addResult->getId();

        // 3. Khởi tạo Lead trong CRM
        $leadId = CrmBridgeService::createLeadFromRequest($requestId, [
            'COMPANY_NAME'   => $companyName,
            'TAX_CODE'       => $taxCode,
            'ANNUAL_REVENUE' => $revenue,
            'CONTACT_NAME'   => $contactName,
            'PHONE'          => $phone,
            'EMAIL'          => $email,
            'SERVICE_ID'     => $serviceId,
        ]);

        return [
            'status'    => 'success',
            'requestId' => $requestId,
            'leadId'    => $leadId,
            'message'   => 'Yêu cầu kiểm toán đã được tiếp nhận thành công. Trưởng phòng kiểm toán AASC sẽ liên hệ sớm nhất.',
        ];
    }
}
