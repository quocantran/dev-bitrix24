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
            'signContract' => [
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

    /**
     * Action khách hàng ký hợp đồng dịch vụ kiểm toán
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.request.signContract
     */
    public function signContractAction(int $requestId = 0): ?array
    {
        if ($requestId <= 0) {
            $requestId = (int)$this->getRequest()->getPost('requestId');
        }

        if ($requestId <= 0) {
            $this->addError(new Error('Mã hồ sơ yêu cầu kiểm toán không hợp lệ.'));
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
            $this->addError(new Error('Bạn không có quyền thực hiện ký hợp đồng cho hồ sơ này.'));
            return null;
        }

        // Nếu đã có Deal thì trả về thành công
        if (!empty($request['CRM_DEAL_ID']) && (int)$request['CRM_DEAL_ID'] > 0) {
            return [
                'status'  => 'success',
                'dealId'  => (int)$request['CRM_DEAL_ID'],
                'message' => 'Hồ sơ đã được ký hợp đồng dịch vụ trước đó.',
            ];
        }

        $leadId = (int)($request['CRM_LEAD_ID'] ?? 0);
        if ($leadId <= 0) {
            $this->addError(new Error('Hồ sơ chưa được đồng bộ với CRM Lead.'));
            return null;
        }

        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            $this->addError(new Error('Không thể tải phân hệ CRM.'));
            return null;
        }

        $lead = \CCrmLead::GetByID($leadId, false);
        if (!$lead) {
            $this->addError(new Error('Không tìm thấy Lead #' . $leadId));
            return null;
        }

        // Kiểm tra xem báo giá đã được duyệt chưa
        global $USER_FIELD_MANAGER;
        $statusVal = $USER_FIELD_MANAGER->GetUserFieldValue("CRM_LEAD", "UF_APPROVAL_STATUS", $leadId);
        $isApproved = false;
        if (!empty($statusVal)) {
            if (is_numeric($statusVal)) {
                $enumRow = \CUserFieldEnum::GetList([], ["ID" => (int)$statusVal])->Fetch();
                $isApproved = ($enumRow && $enumRow["XML_ID"] === "APPROVED");
            } else {
                $isApproved = ((string)$statusVal === "APPROVED");
            }
        }

        $fee = (float)($lead['OPPORTUNITY'] ?? 0);
        if ($fee <= 0 && !empty($lead['UF_ESTIMATED_FEE'])) {
            $fee = (float)$lead['UF_ESTIMATED_FEE'];
        }

        if (!$isApproved && $fee <= 0) {
            $this->addError(new Error('Hồ sơ chưa có dự toán chi phí được phê duyệt. Vui lòng chờ phản hồi báo giá từ kiểm toán viên.'));
            return null;
        }

        // Tìm tài khoản Senior (Trưởng nhóm kiểm toán) để phân công
        $seniorUser = \Bitrix\Main\UserTable::getList([
            'filter' => ['=LOGIN' => 'senior', '=ACTIVE' => 'Y'],
            'select' => ['ID', 'LOGIN'],
        ])->fetch();
        $seniorId = $seniorUser ? (int)$seniorUser['ID'] : 6;

        $baseCurrency = 'USD';
        if (class_exists('\CCrmCurrency')) {
            $baseCurrency = \CCrmCurrency::GetBaseCurrencyID() ?: 'USD';
        }

        if (class_exists('\Bitrix\Crm\Category\Entity\DealCategoryTable')) {
            \Bitrix\Crm\Category\Entity\DealCategoryTable::cleanCache();
        }

        // Tạo Deal trong Category 1: Quy trình Kiểm toán AASC
        $dealFields = [
            'TITLE'               => 'Hợp đồng kiểm toán: ' . ($request['COMPANY_NAME'] ?? ''),
            'CATEGORY_ID'         => 1,
            'STAGE_ID'            => 'C1:PREPARATION',
            'OPENED'              => 'Y',
            'ASSIGNED_BY_ID'      => $seniorId,
            'OPPORTUNITY'         => $fee,
            'CURRENCY_ID'         => 'VND',
            'ACCOUNT_CURRENCY_ID' => 'VND',
            'EXCH_RATE'           => 1.0,
            'OPPORTUNITY_ACCOUNT' => $fee,
            'COMPANY_TITLE'       => $request['COMPANY_NAME'] ?? '',
            'COMMENTS'            => 'Mã yêu cầu: #' . $requestId . ' | MST: ' . ($request['TAX_CODE'] ?? '') . ' | Doanh thu: ' . number_format((float)($request['ANNUAL_REVENUE'] ?? 0)) . ' VNĐ',
            'LEAD_ID'             => $leadId,
            'IS_PORTAL_SIGN'      => true,
        ];

        \Aasc\Audit\Handler\LeadApprovalHandler::$isPortalSign = true;
        try {
            $dealObj = new \CCrmDeal(false);
            $dealId = $dealObj->Add($dealFields, true, ['CURRENT_USER' => 1, 'CATEGORY_ID' => 1]);

            if (!$dealId) {
                $this->addError(new Error('Không thể khởi tạo Deal kiểm toán: ' . $dealObj->LAST_ERROR));
                return null;
            }

            // Cập nhật CRM_DEAL_ID và trạng thái vào aasc_audit_request
            AuditRequestTable::update($requestId, [
                'CRM_DEAL_ID' => (int)$dealId,
                'STATUS'      => 'C1:PREPARATION',
            ]);

            // Cập nhật trạng thái Lead thành CONVERTED
            $leadObj = new \CCrmLead(false);
            $leadUpdate = [
                'STATUS_ID'      => 'CONVERTED',
                'IS_PORTAL_SIGN' => true,
            ];
            $leadObj->Update($leadId, $leadUpdate, true, ['CURRENT_USER' => 1]);
        } finally {
            \Aasc\Audit\Handler\LeadApprovalHandler::$isPortalSign = false;
        }

        // Ghi nhận vào Timeline
        \Bitrix\Crm\Timeline\CommentEntry::create([
            'TEXT'      => 'Khách hàng đã ký hợp đồng kiểm toán dịch vụ trên Cổng thông tin. Hồ sơ được chuyển sang Deal #' . $dealId . ' và bàn giao cho Trưởng nhóm kiểm toán (' . ($seniorUser['LOGIN'] ?? 'senior') . ') lập kế hoạch thực địa (VSA 300).',
            'AUTHOR_ID' => $currentUserId,
            'BINDINGS'  => [
                ['ENTITY_TYPE_ID' => \CCrmOwnerType::Lead, 'ENTITY_ID' => $leadId],
                ['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $dealId],
            ]
        ]);

        // Gửi thông báo cho Senior và Manager
        if (\Bitrix\Main\Loader::includeModule('im')) {
            $msg = 'Khách hàng (' . ($request['COMPANY_NAME'] ?? '') . ') đã hoàn tất ký hợp đồng dịch vụ cho hồ sơ #' . $requestId . '. Deal #' . $dealId . ' đã được giao cho bạn lập kế hoạch kiểm toán (VSA 300).';
            \CIMNotify::Add([
                'TO_USER_ID'     => $seniorId,
                'FROM_USER_ID'   => $currentUserId,
                'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                'NOTIFY_MODULE'  => 'aasc.audit',
                'NOTIFY_TAG'     => 'AASC|CONTRACT|' . $requestId,
                'NOTIFY_MESSAGE' => $msg,
            ]);

            $managerId = (int)($lead['ASSIGNED_BY_ID'] ?? 5);
            if ($managerId > 0 && $managerId !== $seniorId) {
                \CIMNotify::Add([
                    'TO_USER_ID'     => $managerId,
                    'FROM_USER_ID'   => $currentUserId,
                    'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                    'NOTIFY_MODULE'  => 'aasc.audit',
                    'NOTIFY_TAG'     => 'AASC|CONTRACT_MGR|' . $requestId,
                    'NOTIFY_MESSAGE' => 'Hồ sơ #' . $requestId . ' (' . ($request['COMPANY_NAME'] ?? '') . ') đã được khách hàng ký hợp đồng thành công. Deal #' . $dealId . ' đã chuyển cho Trưởng nhóm lập kế hoạch.',
                ]);
            }
        }

        // Phát sóng Push & Pull cập nhật thời gian thực cho Portal (Step 3)
        if (\Bitrix\Main\Loader::includeModule('pull')) {
            \CPullWatch::AddToStack('AASC_AUDIT_REQUEST_' . $requestId, [
                'module_id' => 'aasc.audit',
                'command'   => 'request_status_updated',
                'params'    => [
                    'requestId' => $requestId,
                    'dealId'    => $dealId,
                    'statusId'  => 'C1:PREPARATION',
                    'stepIndex' => 3,
                ]
            ]);
        }

        return [
            'status'  => 'success',
            'dealId'  => (int)$dealId,
            'message' => 'Hợp đồng kiểm toán đã được ký kết thành công. Nhóm kiểm toán AASC đang bắt đầu lập kế hoạch thực địa.',
        ];
    }
}

