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
            'getStatus' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET, ActionFilter\HttpMethod::METHOD_POST]),
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
                'status'    => 'success',
                'dealId'    => (int)$request['CRM_DEAL_ID'],
                'stepIndex' => 3,
                'message'   => 'Hồ sơ đã được ký hợp đồng dịch vụ trước đó.',
            ];
        }

        if ((string)($request['STATUS'] ?? '') === 'CONTRACT_SIGNED') {
            return [
                'status'    => 'success',
                'stepIndex' => 3,
                'message'   => 'Hồ sơ đã được bạn xác nhận đồng ý ký kết hợp đồng trước đó.',
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

        $leadStatusId = (string)($lead['STATUS_ID'] ?? 'NEW');
        $fee = (float)($lead['OPPORTUNITY'] ?? 0);
        if ($fee <= 0 && !empty($lead['UF_ESTIMATED_FEE'])) {
            $fee = (float)$lead['UF_ESTIMATED_FEE'];
        }

        if (!$isApproved && !in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED'], true)) {
            $this->addError(new Error('Hồ sơ chưa có dự toán chi phí được phê duyệt. Vui lòng chờ phản hồi báo giá từ kiểm toán viên.'));
            return null;
        }

        if ($fee <= 0) {
            $this->addError(new Error('Chưa có thông tin dự toán phí dịch vụ hợp lệ để ký hợp đồng.'));
            return null;
        }

        // Cập nhật trạng thái trong aasc_audit_request sang CONTRACT_SIGNED (người dùng đã đồng ý ký)
        AuditRequestTable::update($requestId, [
            'STATUS' => 'CONTRACT_SIGNED',
        ]);

        // Ghi nhận vào Timeline của Lead trong CRM (chỉ khi chưa có)
        $connection = \Bitrix\Main\Application::getConnection();
        $checkSignComment = $connection->query("
            SELECT t.ID FROM b_crm_timeline t
            JOIN b_crm_timeline_bind b ON b.OWNER_ID = t.ID
            WHERE b.ENTITY_TYPE_ID = " . (int)\CCrmOwnerType::Lead . "
              AND b.ENTITY_ID = " . (int)$leadId . "
              AND t.COMMENT LIKE '%đồng ý ký kết hợp đồng trên Cổng thông tin%'
            LIMIT 1
        ")->fetch();

        if (!$checkSignComment) {
            \Bitrix\Crm\Timeline\CommentEntry::create([
                'TEXT'      => 'Khách hàng (' . ($request['COMPANY_NAME'] ?? '') . ') đã xác nhận chấp thuận dự toán phí (' . number_format($fee, 0, ',', '.') . ' VNĐ) và đồng ý ký kết hợp đồng trên Cổng thông tin (Portal). Đề nghị Trưởng phòng (Manager) và Ban Giám đốc (Director) vào CRM chuyển đổi hồ sơ sang Hợp đồng (Deal).',
                'AUTHOR_ID' => $currentUserId,
                'BINDINGS'  => [
                    ['ENTITY_TYPE_ID' => \CCrmOwnerType::Lead, 'ENTITY_ID' => $leadId],
                ]
            ]);
        }

        // Gửi thông báo chuông nội bộ cho Director (user 4) và Manager (user 5)
        if (\Bitrix\Main\Loader::includeModule('im')) {
            $dirUser = \Bitrix\Main\UserTable::getList(['filter' => ['=LOGIN' => 'director', '=ACTIVE' => 'Y'], 'select' => ['ID']])->fetch();
            $directorId = $dirUser ? (int)$dirUser['ID'] : 4;

            $mgrUser = \Bitrix\Main\UserTable::getList(['filter' => ['=LOGIN' => 'manager', '=ACTIVE' => 'Y'], 'select' => ['ID']])->fetch();
            $managerId = $mgrUser ? (int)$mgrUser['ID'] : (int)($lead['ASSIGNED_BY_ID'] ?? 5);

            $msg = 'Khách hàng (' . ($request['COMPANY_NAME'] ?? '') . ') đã đồng ý ký hợp đồng kiểm toán cho hồ sơ #' . $requestId . ' (Lead #' . $leadId . '). Bạn có thể vào CRM để chuyển đổi hồ sơ sang Hợp đồng (Deal).';

            // Gửi tới Director
            \CIMNotify::Add([
                'TO_USER_ID'     => $directorId,
                'FROM_USER_ID'   => $currentUserId,
                'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                'NOTIFY_MODULE'  => 'aasc.audit',
                'NOTIFY_TAG'     => 'AASC|CONTRACT_DIR|' . $requestId . '|' . time(),
                'NOTIFY_MESSAGE' => $msg,
                'NOTIFY_LINK'    => '/crm/lead/details/' . $leadId . '/',
            ]);

            // Gửi tới Manager
            if ($managerId > 0 && $managerId !== $directorId) {
                \CIMNotify::Add([
                    'TO_USER_ID'     => $managerId,
                    'FROM_USER_ID'   => $currentUserId,
                    'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                    'NOTIFY_MODULE'  => 'aasc.audit',
                    'NOTIFY_TAG'     => 'AASC|CONTRACT_MGR|' . $requestId . '|' . time(),
                    'NOTIFY_MESSAGE' => $msg,
                    'NOTIFY_LINK'    => '/crm/lead/details/' . $leadId . '/',
                ]);
            }
        }

        // Phát sóng Push & Pull cập nhật thời gian thực cho Portal (Step 3: Người dùng đồng ý ký)
        if (\Bitrix\Main\Loader::includeModule('pull')) {
            $eventData = [
                'module_id' => 'aasc.audit',
                'command'   => 'request_status_updated',
                'params'    => [
                    'requestId' => $requestId,
                    'leadId'    => $leadId,
                    'statusId'  => 'CONTRACT_SIGNED',
                    'stepIndex' => 3,
                ]
            ];
            \CPullWatch::AddToStack('AASC_AUDIT_REQUEST_' . $requestId, $eventData);
            if ($currentUserId > 0) {
                \Bitrix\Pull\Event::add($currentUserId, $eventData);
            }
        }

        return [
            'status'    => 'success',
            'requestId' => $requestId,
            'stepIndex' => 3,
            'message'   => 'Bạn đã xác nhận đồng ý ký kết hợp đồng kiểm toán thành công. Thông tin đã được gửi tới Ban Giám đốc và Trưởng phòng kiểm toán AASC.',
        ];
    }

    /**
     * Action lấy trạng thái thời gian thực của hồ sơ (Real-time polling & sync)
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.request.getStatus&requestId=123
     */
    public function getStatusAction(int $requestId = 0): ?array
    {
        if ($requestId <= 0) {
            $requestId = (int)$this->getRequest()->get('requestId');
        }

        if ($requestId <= 0) {
            $this->addError(new Error('Mã hồ sơ không hợp lệ.'));
            return null;
        }

        $request = AuditRequestTable::getById($requestId)->fetch();
        if (!$request) {
            $this->addError(new Error('Không tìm thấy thông tin hồ sơ #' . $requestId));
            return null;
        }

        global $USER;
        $currentUserId = (int)$USER->GetID();
        $isOwner = ((int)$request['USER_ID'] === $currentUserId);
        $isInternal = \Aasc\Audit\Handler\PortalAccessHandler::isInternalUser($currentUserId);

        if (!$isOwner && !$isInternal) {
            $this->addError(new Error('Bạn không có quyền xem thông tin hồ sơ này.'));
            return null;
        }

        $leadId = (int)($request['CRM_LEAD_ID'] ?? 0);
        $dealId = (int)($request['CRM_DEAL_ID'] ?? 0);
        $requestStatus = (string)($request['STATUS'] ?? 'NEW');

        $leadStatusId = 'NEW';
        $estimatedFee = 0.0;
        $isQuoteApproved = false;
        $dealStageId = '';

        if (\Bitrix\Main\Loader::includeModule('crm')) {
            if ($leadId > 0) {
                $lead = \CCrmLead::GetByID($leadId, false);
                if ($lead) {
                    $leadStatusId = (string)($lead['STATUS_ID'] ?? 'NEW');
                    $estimatedFee = (float)($lead['OPPORTUNITY'] ?? 0);
                    if ($estimatedFee <= 0 && !empty($lead['UF_ESTIMATED_FEE'])) {
                        $estimatedFee = (float)$lead['UF_ESTIMATED_FEE'];
                    }

                    global $USER_FIELD_MANAGER;
                    $statusVal = $USER_FIELD_MANAGER->GetUserFieldValue("CRM_LEAD", "UF_APPROVAL_STATUS", $leadId);
                    if (!empty($statusVal)) {
                        if (is_numeric($statusVal)) {
                            $enumRow = \CUserFieldEnum::GetList([], ["ID" => (int)$statusVal])->Fetch();
                            $isQuoteApproved = ($enumRow && $enumRow["XML_ID"] === "APPROVED");
                        } else {
                            $isQuoteApproved = ((string)$statusVal === "APPROVED");
                        }
                    }
                }
            }

            if ($dealId > 0) {
                $deal = \CCrmDeal::GetByID($dealId, false);
                if ($deal) {
                    $dealStageId = (string)($deal['STAGE_ID'] ?? '');
                    if (!empty($deal['OPPORTUNITY']) && (float)$deal['OPPORTUNITY'] > 0) {
                        $estimatedFee = (float)$deal['OPPORTUNITY'];
                    }
                }
            }
        }

        // 5. Xác định bước tiến trình (Current Step: 1 -> 6)
        // Mức 1: Tiếp nhận hồ sơ (NEW, IN_PROCESS)
        // Mức 2: Thẩm định & Báo giá sẵn sàng (manager nhập tiền + sang status PROCESSED/APPROVED)
        // Mức 3: Người dùng đồng ý ký hợp đồng (CONTRACT_SIGNED hoặc Deal C1:PREPARATION/C1:NEW)
        // Mức 4: Kiểm toán thực địa (C1:FIELDWORK)
        // Mức 5: Soát xét báo cáo (C1:REVIEW_MANAGER, C1:REVIEW_DIRECTOR)
        // Mức 6: Báo cáo chính thức (C1:WON)
        $currentStep = 1;
        if ($dealId > 0 && !empty($dealStageId)) {
            if ($dealStageId === 'C1:FIELDWORK' || $dealStageId === 'EXECUTING') {
                $currentStep = 4;
            } elseif (in_array($dealStageId, ['C1:REVIEW_MANAGER', 'C1:REVIEW_DIRECTOR'], true)) {
                $currentStep = 5;
            } elseif ($dealStageId === 'C1:WON' || $dealStageId === 'WON') {
                $currentStep = 6;
            } else {
                $currentStep = 3;
            }
        } else {
            if ($requestStatus === 'CONTRACT_SIGNED') {
                $currentStep = 3;
            } elseif ($isQuoteApproved || in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED'], true)) {
                $currentStep = 2;
            } else {
                $currentStep = 1;
            }
        }

        $isContractSigned = ($requestStatus === 'CONTRACT_SIGNED' || $dealId > 0);
        $canSignContract = (!$isContractSigned && ($isQuoteApproved || in_array($leadStatusId, ['PROPOSAL_SENT', 'APPROVED', 'PROCESSED'], true)) && $estimatedFee > 0);

        return [
            'status'                => 'success',
            'requestId'             => $requestId,
            'currentStep'           => $currentStep,
            'requestStatus'         => $requestStatus,
            'leadStatusId'          => $leadStatusId,
            'dealStageId'           => $dealStageId,
            'dealId'                => $dealId,
            'estimatedFee'          => $estimatedFee,
            'estimatedFeeFormatted' => number_format($estimatedFee, 0, ',', '.') . ' VNĐ',
            'isQuoteApproved'       => $isQuoteApproved,
            'canSignContract'       => $canSignContract,
            'isContractSigned'      => $isContractSigned,
            'hasFinalReport'        => ($currentStep === 6),
            'reportUrl'             => '/portal/my-requests/' . $requestId . '/report/',
        ];
    }
}

