<?php
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Context;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER, $APPLICATION;
$request = Context::getCurrent()->getRequest();
$backUrl = $request->get('backurl') ?: '/portal/request/';
if (!preg_match('#^/portal/#', $backUrl)) {
    $backUrl = '/portal/request/';
}

// Xử lý đăng xuất nếu có tham số logout
if ($request->get('logout') === 'yes') {
    if (is_object($USER)) {
        $USER->Logout();
    }
    LocalRedirect('/portal/auth/?logged_out=1');
    die();
}

// Nếu đã đăng nhập thì tự động chuyển tiếp tới không gian tương ứng
if (is_object($USER) && $USER->IsAuthorized()) {
    $isInternal = class_exists('\Aasc\Audit\Handler\PortalAccessHandler')
        && \Aasc\Audit\Handler\PortalAccessHandler::isInternalUser((int)$USER->GetID());

    if ($isInternal) {
        LocalRedirect('/online/');
    } else {
        LocalRedirect($backUrl);
    }
    die();
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Đăng Nhập & Đăng Ký - Cổng Thông Tin AASC");

Asset::getInstance()->addCss('/portal/auth/style.css');
Asset::getInstance()->addJs('/portal/auth/script.js');

$mode = $request->get('mode') === 'register' ? 'register' : 'login';
$loggedOut = $request->get('logged_out') === '1';
?>

<div class="auth-container" id="authApp" data-backurl="<?= htmlspecialcharsbx($backUrl) ?>">
    <!-- Tab chuyển đổi -->
    <div class="auth-tabs">
        <button type="button" id="tabBtnLogin" class="auth-tab-btn <?= $mode === 'login' ? 'active' : '' ?>">
            Đăng Nhập
        </button>
        <button type="button" id="tabBtnRegister" class="auth-tab-btn <?= $mode === 'register' ? 'active' : '' ?>">
            Đăng Ký Tài Khoản
        </button>
    </div>

    <div class="auth-body">
        <?php if ($loggedOut): ?>
            <div class="auth-notice-loggedout">
                Bạn đã đăng xuất an toàn khỏi cổng thông tin AASC.
            </div>
        <?php endif; ?>

        <?php if (!empty($request->get('backurl'))): ?>
            <div class="auth-notice-backurl">
                Khu vực <strong>Gửi yêu cầu kiểm toán</strong> yêu cầu đăng nhập. Vui lòng đăng nhập hoặc tạo tài khoản mới để tiếp tục.
            </div>
        <?php endif; ?>

        <div id="authAlert" class="auth-alert"></div>

        <!-- FORM ĐĂNG NHẬP -->
        <form id="loginForm" class="auth-form <?= $mode === 'login' ? 'is-active' : 'is-hidden' ?>" method="POST" action="/bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.login">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="backurl" value="<?= htmlspecialcharsbx($backUrl) ?>">

            <div class="auth-form-group">
                <label class="auth-label" for="loginInput">Email hoặc Tên đăng nhập <span class="required">*</span></label>
                <input type="text" id="loginInput" name="login" class="auth-input" required placeholder="name@company.com">
            </div>

            <div class="auth-form-group">
                <label class="auth-label" for="passwordInput">Mật khẩu <span class="required">*</span></label>
                <input type="password" id="passwordInput" name="password" class="auth-input" required placeholder="Mật khẩu của bạn">
            </div>

            <div class="auth-remember-row">
                <label class="auth-remember-label">
                    <input type="checkbox" name="remember" value="Y" checked> Ghi nhớ đăng nhập
                </label>
            </div>

            <button type="submit" id="loginBtn" class="auth-submit-btn auth-submit-login">
                Đăng Nhập
            </button>
        </form>

        <!-- FORM ĐĂNG KÝ -->
        <form id="registerForm" class="auth-form <?= $mode === 'register' ? 'is-active' : 'is-hidden' ?>" method="POST" action="/bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.register">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="backurl" value="<?= htmlspecialcharsbx($backUrl) ?>">

            <div class="auth-form-group-compact">
                <label class="auth-label" for="regNameInput">Họ và tên người liên hệ <span class="required">*</span></label>
                <input type="text" id="regNameInput" name="name" class="auth-input" required placeholder="Ví dụ: Nguyễn Văn A">
            </div>

            <div class="auth-form-grid-2">
                <div>
                    <label class="auth-label" for="regEmailInput">Email <span class="required">*</span></label>
                    <input type="email" id="regEmailInput" name="email" class="auth-input" required placeholder="name@company.com">
                </div>
                <div>
                    <label class="auth-label" for="regPhoneInput">Số điện thoại <span class="required">*</span></label>
                    <input type="tel" id="regPhoneInput" name="phone" class="auth-input" required placeholder="0912345678">
                </div>
            </div>

            <div class="auth-form-grid-2">
                <div>
                    <label class="auth-label" for="regCompanyInput">Tên doanh nghiệp</label>
                    <input type="text" id="regCompanyInput" name="companyName" class="auth-input" placeholder="Công ty ABC">
                </div>
                <div>
                    <label class="auth-label" for="regTaxInput">Mã số thuế</label>
                    <input type="text" id="regTaxInput" name="taxCode" class="auth-input" placeholder="10 hoặc 13 số">
                </div>
            </div>

            <div class="auth-form-grid-2">
                <div>
                    <label class="auth-label" for="regPassInput">Mật khẩu <span class="required">*</span></label>
                    <input type="password" id="regPassInput" name="password" class="auth-input" required placeholder="Tối thiểu 6 ký tự">
                </div>
                <div>
                    <label class="auth-label" for="regConfirmPassInput">Xác nhận mật khẩu <span class="required">*</span></label>
                    <input type="password" id="regConfirmPassInput" name="confirmPassword" class="auth-input" required placeholder="Nhập lại mật khẩu">
                </div>
            </div>

            <button type="submit" id="registerBtn" class="auth-submit-btn auth-submit-register">
                Tạo Tài Khoản
            </button>
        </form>
    </div>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
