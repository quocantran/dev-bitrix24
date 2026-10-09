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
            'addNote' => [
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

    /**
     * Action gửi phản hồi / ghi chú bổ sung vào CRM Lead Timeline
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.request.addNote
     */
    public function addNoteAction(int $requestId = 0, string $message = ''): ?array
    {
        if ($requestId <= 0) {
            $post = $this->getRequest()->getPostList();
            $requestId = (int)$post->get('requestId');
            $message = (string)$post->get('message');
        }

        $message = trim($message);
        if ($requestId <= 0 || empty($message)) {
            $this->addError(new Error('Vui lòng nhập nội dung phản hồi hợp lệ.'));
            return null;
        }

        $request = AuditRequestTable::getById($requestId)->fetch();
        if (!$request) {
            $this->addError(new Error('Không tìm thấy thông tin hồ sơ kiểm toán #' . $requestId));
            return null;
        }

        global $USER;
        $currentUserId = (int)$USER->GetID();
        $isOwner = ((int)$request['USER_ID'] === $currentUserId);
        $isInternal = \Aasc\Audit\Handler\PortalAccessHandler::isInternalUser($currentUserId);

        if (!$isOwner && !$isInternal) {
            $this->addError(new Error('Bạn không có quyền gửi phản hồi cho hồ sơ này.'));
            return null;
        }

        $leadId = (int)($request['CRM_LEAD_ID'] ?? 0);
        if ($leadId > 0 && \Bitrix\Main\Loader::includeModule('crm')) {
            // Thêm ghi chú vào Timeline của Lead trong CRM
            \Bitrix\Crm\Timeline\CommentEntry::create([
                'TEXT'      => $message,
                'AUTHOR_ID' => $currentUserId,
                'BINDINGS'  => [
                    [
                        'ENTITY_TYPE_ID' => \CCrmOwnerType::Lead,
                        'ENTITY_ID'      => $leadId,
                    ]
                ]
            ]);

            // Bắn thông báo nội bộ cho chuyên viên phụ trách nếu tác giả là khách hàng
            if ($isOwner && \Bitrix\Main\Loader::includeModule('im')) {
                $lead = \CCrmLead::GetByID($leadId, false);
                $assignedId = (int)($lead['ASSIGNED_BY_ID'] ?? 1);
                if ($assignedId > 0 && $assignedId !== $currentUserId) {
                    \CIMNotify::Add([
                        'TO_USER_ID'     => $assignedId,
                        'FROM_USER_ID'   => $currentUserId,
                        'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                        'NOTIFY_MODULE'  => 'aasc.audit',
                        'NOTIFY_TAG'     => 'AASC|NOTE|' . $requestId,
                        'NOTIFY_MESSAGE' => 'Khách hàng vừa gửi ghi chú mới cho hồ sơ #' . $requestId . ' (' . ($request['COMPANY_NAME'] ?? '') . '): ' . $message,
                    ]);
                }
            }
        }

        // Đẩy thông báo thời gian thực qua Push & Pull
        if (\Bitrix\Main\Loader::includeModule('pull')) {
            \CPullWatch::AddToStack('AASC_AUDIT_REQUEST_' . $requestId, [
                'module_id' => 'aasc.audit',
                'command'   => 'new_request_note',
                'params'    => [
                    'requestId'  => $requestId,
                    'authorName' => $USER->GetFullName() ?: $USER->GetLogin(),
                    'message'    => htmlspecialcharsbx($message),
                    'time'       => date('H:i d/m/Y'),
                ]
            ]);
        }

        return [
            'status'  => 'success',
            'message' => 'Đã gửi phản hồi thành công.',
        ];
    }
}

