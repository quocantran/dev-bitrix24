<?php
/**
 * /local/routes/web.php
 * Bitrix D7 Web Routing Configuration
 */

use Bitrix\Main\Routing\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    // 1. Chi tiết & Theo dõi tiến độ hồ sơ kiểm toán động
    $routeHandler = function ($id) {
        global $APPLICATION;
        require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

        $APPLICATION->IncludeComponent(
            'aasc:audit.request.detail',
            '.default',
            [
                'REQUEST_ID' => (int)$id,
                'CACHE_TYPE' => 'N',
            ]
        );

        require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
    };

    // Hỗ trợ cả định dạng có và không có dấu gạch chéo cuối
    $routes->get('/portal/my-requests/{id}', $routeHandler)->where('id', '[0-9]+');
    $routes->get('/portal/my-requests/{id}/', $routeHandler)->where('id', '[0-9]+');
};
