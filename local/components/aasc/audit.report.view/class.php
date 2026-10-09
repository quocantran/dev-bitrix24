<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Aasc\Audit\Model\AuditRequestTable;
use Aasc\Audit\Handler\PortalAccessHandler;

class AascAuditReportViewComponent extends \CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['REQUEST_ID'] = (int)($arParams['REQUEST_ID'] ?? 0);
        return $arParams;
    }

    public function executeComponent()
    {
        global $USER, $APPLICATION;

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            LocalRedirect('/portal/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
            die();
        }

        if (!Loader::includeModule('aasc.audit')) {
            ShowError('Module aasc.audit chưa được kích hoạt trên hệ thống.');
            return;
        }

        $requestId = (int)$this->arParams['REQUEST_ID'];
        if ($requestId <= 0) {
            ShowError('Mã hồ sơ yêu cầu không hợp lệ.');
            return;
        }

        $request = AuditRequestTable::getById($requestId)->fetch();
        if (!$request) {
            ShowError('Không tìm thấy thông tin hồ sơ kiểm toán #' . $requestId);
            return;
        }

        $currentUserId = (int)$USER->GetID();
        $isOwner = ((int)$request['USER_ID'] === $currentUserId);
        $isInternal = PortalAccessHandler::isInternalUser($currentUserId);

        if (!$isOwner && !$isInternal) {
            ShowError('Bạn không có quyền truy cập báo cáo kiểm toán này.');
            return;
        }

        // Kiểm tra xem hồ sơ đã hoàn thành chưa
        $isCompleted = false;
        $dealId = (int)($request['CRM_DEAL_ID'] ?? 0);
        $dealData = null;

        if ($dealId > 0 && Loader::includeModule('crm')) {
            $dealData = \CCrmDeal::GetByID($dealId, false);
            if ($dealData && ($dealData['STAGE_ID'] === 'C1:WON' || $dealData['STAGE_ID'] === 'WON')) {
                $isCompleted = true;
            }
        }

        if (!$isCompleted && $request['STATUS'] === 'C1:WON') {
            $isCompleted = true;
        }

        $this->arResult['REQUEST'] = $request;
        $this->arResult['DEAL'] = $dealData;
        $this->arResult['IS_COMPLETED'] = $isCompleted;
        $this->arResult['REPORT_NO'] = sprintf('%04d/2026/BCKT-AASC', $requestId);
        $this->arResult['ISSUE_DATE'] = date('d/m/Y');

        $APPLICATION->SetTitle('Báo Cáo Kiểm Toán Độc Lập - ' . htmlspecialcharsbx($request['COMPANY_NAME']));

        $this->includeComponentTemplate();
    }
}
