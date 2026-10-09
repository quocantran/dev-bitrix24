<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Bitrix\Iblock\ElementTable;

class AascAuditRequestComponent extends \CBitrixComponent
{
    /**
     * Chuẩn bị danh mục dịch vụ từ IBlock cho View
     */
    protected function loadServices(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $iblock = \CIBlock::GetList([], ['TYPE' => 'services', '=CODE' => 'audit_services'])->Fetch();
        if (!$iblock) {
            return [];
        }

        $res = ElementTable::getList([
            'select' => ['ID', 'NAME', 'CODE'],
            'filter' => [
                '=IBLOCK_ID' => (int)$iblock['ID'],
                '=ACTIVE'    => 'Y',
            ],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        return $res->fetchAll();
    }

    public function executeComponent()
    {
        global $USER;
        $this->arResult['SERVICES'] = $this->loadServices();
        $this->arResult['SUBMIT_URL'] = '/bitrix/services/main/ajax.php?action=aasc:audit.controller.request.send';

        $userData = [
            'AUTHORIZED'   => false,
            'ID'           => 0,
            'CONTACT_NAME' => '',
            'EMAIL'        => '',
            'PHONE'        => '',
            'COMPANY_NAME' => '',
            'TAX_CODE'     => '',
        ];

        if (is_object($USER) && $USER->IsAuthorized()) {
            $userId = (int)$USER->GetID();
            $userRow = UserTable::getRow([
                'select' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL', 'PERSONAL_PHONE', 'WORK_COMPANY', 'WORK_PROFILE'],
                'filter' => ['=ID' => $userId],
            ]);

            if ($userRow) {
                $fullName = trim(($userRow['NAME'] ?? '') . ' ' . ($userRow['LAST_NAME'] ?? ''));
                if (empty($fullName)) {
                    $fullName = (string)$USER->GetLogin();
                }

                $userData = [
                    'AUTHORIZED'   => true,
                    'ID'           => $userId,
                    'CONTACT_NAME' => $fullName,
                    'EMAIL'        => (string)($userRow['EMAIL'] ?? ''),
                    'PHONE'        => (string)($userRow['PERSONAL_PHONE'] ?? ''),
                    'COMPANY_NAME' => (string)($userRow['WORK_COMPANY'] ?? ''),
                    'TAX_CODE'     => (string)($userRow['WORK_PROFILE'] ?? ''),
                ];
            }
        }

        $this->arResult['USER'] = $userData;

        $this->includeComponentTemplate();
    }
}
