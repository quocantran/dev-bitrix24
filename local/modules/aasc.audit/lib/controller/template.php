<?php
namespace Aasc\Audit\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;

/**
 * Controller cung cấp mẫu Email giao dịch từ Bitrix IBlock cho n8n và các dịch vụ nội bộ
 */
class Template extends Controller
{
    /**
     * Cấu hình bộ lọc hành động: Gỡ bỏ CSRF & Auth phiên làm việc để phục vụ lời gọi server-to-server
     */
    public function configureActions(): array
    {
        return [
            'get' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * Lấy nội dung mẫu email theo mã template
     *
     * @param string $code Mã định danh mẫu (ví dụ: LEAD_CONFIRMATION)
     * @return array|null
     */
    public function getAction(string $code = ''): ?array
    {
        $request = $this->getRequest();
        $server = $request->getServer();

        if ($code === '') {
            $code = (string)($request->get('code') ?: $_GET['code'] ?? $_POST['code'] ?? $_REQUEST['code'] ?? '');
        }

        $code = trim($code);
        if ($code === '') {
            $this->addError(new Error('Mã template không được để trống.', 'empty_code'));
            return null;
        }

        // 1. Kiểm tra xác thực bảo mật Server-to-Server
        $remoteAddr = (string)($server->get('REMOTE_ADDR') ?: '');
        $apiKey = (string)($server->get('HTTP_X_API_KEY') 
            ?: $request->getHeader('X-API-Key') 
            ?: $request->getHeader('x-api-key') 
            ?: $request->get('apiKey') 
            ?: '');

        $expectedKey = Option::get('aasc.audit', 'internal_api_key', 'AascAuditInternalSecretKey2026!');

        // Cho phép localhost (IPv4, IPv6) và IP máy ảo nội bộ
        $allowedIps = ['127.0.0.1', '::1', 'localhost', '192.168.48.100'];
        $isLocalIp = in_array($remoteAddr, $allowedIps, true) || str_starts_with($remoteAddr, '192.168.48.');

        if (!$isLocalIp && $apiKey !== $expectedKey) {
            $this->addError(new Error('Từ chối truy cập: Địa chỉ IP hoặc mã khóa bảo mật không hợp lệ.', 'unauthorized'));
            return null;
        }

        // 2. Kiểm tra nạp module IBlock
        if (!Loader::includeModule('iblock')) {
            $this->addError(new Error('Module iblock không khả dụng trên hệ thống.', 'module_missing'));
            return null;
        }

        // 3. Cơ chế lưu đệm Cache D7
        $cache = Cache::createInstance();
        $cacheTtl = 3600;
        $cacheId = 'aasc_email_tpl_' . md5($code);
        $cacheDir = '/aasc/email_templates';

        if ($cache->initCache($cacheTtl, $cacheId, $cacheDir)) {
            $vars = $cache->getVars();
            if (!empty($vars['data'])) {
                return $vars['data'];
            }
        }

        // 4. Tìm IBlock aasc_email_templates
        $ibRes = \CIBlock::GetList([], ['=CODE' => 'aasc_email_templates']);
        if (!($arIb = $ibRes->Fetch())) {
            $this->addError(new Error('Không tìm thấy Information Block mẫu email (aasc_email_templates).', 'iblock_not_found'));
            return null;
        }
        $iblockId = (int)$arIb['ID'];

        // 5. Truy vấn phần tử mẫu theo CODE
        $elRes = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                '=CODE' => $code,
                'ACTIVE' => 'Y',
            ],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'DETAIL_TEXT_TYPE', 'PREVIEW_TEXT', 'PROPERTY_SUBJECT']
        );

        if (!($arEl = $elRes->GetNext())) {
            $this->addError(new Error("Không tìm thấy mẫu email đang hoạt động với mã: {$code}", 'template_not_found'));
            return null;
        }

        // Trích xuất dữ liệu gốc
        $subject = (string)($arEl['PROPERTY_SUBJECT_VALUE'] ?? '');
        $bodyHtml = (string)($arEl['~DETAIL_TEXT'] ?? $arEl['DETAIL_TEXT'] ?? '');
        $previewText = (string)($arEl['PREVIEW_TEXT'] ?? '');

        $result = [
            'id'          => (int)$arEl['ID'],
            'code'        => (string)$arEl['CODE'],
            'name'        => (string)$arEl['NAME'],
            'subject'     => $subject,
            'bodyHtml'    => $bodyHtml,
            'previewText' => $previewText,
        ];

        // Ghi cache
        $cache->startDataCache();
        $cache->endDataCache(['data' => $result]);

        return $result;
    }
}
