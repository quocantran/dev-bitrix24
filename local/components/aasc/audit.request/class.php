<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
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

        // Tìm IBlock audit_services
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
        $this->arResult['SERVICES'] = $this->loadServices();
        $this->arResult['SUBMIT_URL'] = '/bitrix/services/main/ajax.php?action=aasc:audit.controller.request.send';

        $this->includeComponentTemplate();
    }
}
