<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

global $USER;
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$backUrl = $request->get('backurl') ?: '/portal/request/';
// Sanitize backurl to prevent open redirect
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

$APPLICATION->SetTitle("Đăng Nhập & Đăng Ký - Cổng Thông Tin AASC");
$mode = $request->get('mode') === 'register' ? 'register' : 'login';
$loggedOut = $request->get('logged_out') === '1';
?>

<div style="max-width: 520px; margin: 1rem auto 3rem auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden;">
    
    <!-- Tab chuyển đổi -->
    <div style="display: flex; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
        <button type="button" id="tabBtnLogin" onclick="switchTab('login')" style="flex: 1; padding: 1rem; border: none; background: <?= $mode === 'login' ? '#ffffff' : '#f8fafc' ?>; font-weight: 700; font-size: 1rem; color: <?= $mode === 'login' ? '#1a365d' : '#718096' ?>; cursor: pointer; border-bottom: <?= $mode === 'login' ? '3px solid #1a365d' : '3px solid transparent' ?>;">
            Đăng Nhập
        </button>
        <button type="button" id="tabBtnRegister" onclick="switchTab('register')" style="flex: 1; padding: 1rem; border: none; background: <?= $mode === 'register' ? '#ffffff' : '#f8fafc' ?>; font-weight: 700; font-size: 1rem; color: <?= $mode === 'register' ? '#1a365d' : '#718096' ?>; cursor: pointer; border-bottom: <?= $mode === 'register' ? '3px solid #1a365d' : '3px solid transparent' ?>;">
            Đăng Ký Tài Khoản
        </button>
    </div>

    <div style="padding: 2rem;">
        <?php if ($loggedOut): ?>
            <div style="background: #ebf8ff; border: 1px solid #bee3f8; color: #2b6cb0; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                Bạn đã đăng xuất an toàn khỏi cổng thông tin AASC.
            </div>
        <?php endif; ?>

        <?php if (!empty($request->get('backurl'))): ?>
            <div style="background: #fffaf0; border: 1px solid #feebc8; color: #7b341e; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                Khu vực <strong>Gửi yêu cầu kiểm toán</strong> yêu cầu đăng nhập. Vui lòng đăng nhập hoặc tạo tài khoản mới để tiếp tục.
            </div>
        <?php endif; ?>

        <div id="authAlert" style="display: none; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem;"></div>

        <!-- FORM ĐĂNG NHẬP -->
        <form id="loginForm" style="display: <?= $mode === 'login' ? 'block' : 'none' ?>;" method="POST" action="/bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.login">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="backurl" value="<?= htmlspecialcharsbx($backUrl) ?>">

            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Email hoặc Tên đăng nhập <span style="color: #e53e3e;">*</span></label>
                <input type="text" name="login" required placeholder="name@company.com" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Mật khẩu <span style="color: #e53e3e;">*</span></label>
                <input type="password" name="password" required placeholder="Mật khẩu của bạn" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: #4a5568; cursor: pointer;">
                    <input type="checkbox" name="remember" value="Y" checked> Ghi nhớ đăng nhập
                </label>
            </div>

            <button type="submit" id="loginBtn" style="width: 100%; background: #1a365d; color: #ffffff; padding: 0.75rem; border: none; border-radius: 4px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                Đăng Nhập
            </button>
        </form>

        <!-- FORM ĐĂNG KÝ -->
        <form id="registerForm" style="display: <?= $mode === 'register' ? 'block' : 'none' ?>;" method="POST" action="/bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.register">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="backurl" value="<?= htmlspecialcharsbx($backUrl) ?>">

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Họ và tên người liên hệ <span style="color: #e53e3e;">*</span></label>
                <input type="text" name="name" required placeholder="Ví dụ: Nguyễn Văn A" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Email <span style="color: #e53e3e;">*</span></label>
                    <input type="email" name="email" required placeholder="name@company.com" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Số điện thoại <span style="color: #e53e3e;">*</span></label>
                    <input type="tel" name="phone" required placeholder="0912345678" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Tên doanh nghiệp</label>
                    <input type="text" name="companyName" placeholder="Công ty ABC" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Mã số thuế</label>
                    <input type="text" name="taxCode" placeholder="10 hoặc 13 số" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Mật khẩu <span style="color: #e53e3e;">*</span></label>
                    <input type="password" name="password" required placeholder="Tối thiểu 6 ký tự" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Xác nhận mật khẩu <span style="color: #e53e3e;">*</span></label>
                    <input type="password" name="confirmPassword" required placeholder="Nhập lại mật khẩu" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
                </div>
            </div>

            <button type="submit" id="registerBtn" style="width: 100%; background: #b7791f; color: #ffffff; padding: 0.75rem; border: none; border-radius: 4px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                Tạo Tài Khoản
            </button>
        </form>
    </div>
</div>

<script>
function switchTab(mode) {
    var loginForm = document.getElementById('loginForm');
    var regForm = document.getElementById('registerForm');
    var tabBtnLogin = document.getElementById('tabBtnLogin');
    var tabBtnRegister = document.getElementById('tabBtnRegister');
    var alertBox = document.getElementById('authAlert');

    alertBox.style.display = 'none';

    if (mode === 'login') {
        loginForm.style.display = 'block';
        regForm.style.display = 'none';
        tabBtnLogin.style.background = '#ffffff';
        tabBtnLogin.style.color = '#1a365d';
        tabBtnLogin.style.borderBottom = '3px solid #1a365d';
        tabBtnRegister.style.background = '#f8fafc';
        tabBtnRegister.style.color = '#718096';
        tabBtnRegister.style.borderBottom = '3px solid transparent';
    } else {
        loginForm.style.display = 'none';
        regForm.style.display = 'block';
        tabBtnRegister.style.background = '#ffffff';
        tabBtnRegister.style.color = '#1a365d';
        tabBtnRegister.style.borderBottom = '3px solid #1a365d';
        tabBtnLogin.style.background = '#f8fafc';
        tabBtnLogin.style.color = '#718096';
        tabBtnLogin.style.borderBottom = '3px solid transparent';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var targetUrl = <?= json_encode($backUrl) ?>;
    var alertBox = document.getElementById('authAlert');

    // Handler form đăng nhập
    var loginForm = document.getElementById('loginForm');
    var loginBtn = document.getElementById('loginBtn');
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        loginBtn.disabled = true;
        loginBtn.textContent = 'Đang xác thực...';
        alertBox.style.display = 'none';

        var formData = new FormData(loginForm);
        fetch(loginForm.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            loginBtn.disabled = false;
            loginBtn.textContent = 'Đăng Nhập';
            if (data.status === 'success') {
                alertBox.style.display = 'block';
                alertBox.style.background = '#c6f6d5';
                alertBox.style.color = '#22543d';
                var redirectTarget = (data.data && data.data.redirectUrl) ? data.data.redirectUrl : targetUrl;
                alertBox.innerHTML = '<strong>Đăng nhập thành công!</strong> Đang chuyển hướng...';
                setTimeout(function() {
                    window.location.href = redirectTarget;
                }, 600);
            } else {
                var msg = 'Đăng nhập không thành công.';
                if (data.errors && data.errors.length > 0) {
                    msg = data.errors.map(function(e) { return e.message; }).join('<br>');
                }
                alertBox.style.display = 'block';
                alertBox.style.background = '#fed7d7';
                alertBox.style.color = '#742a2a';
                alertBox.style.border = '1px solid #feb2b2';
                alertBox.innerHTML = '<strong>Lỗi:</strong> ' + msg;
            }
        })
        .catch(function() {
            loginBtn.disabled = false;
            loginBtn.textContent = 'Đăng Nhập';
            alertBox.style.display = 'block';
            alertBox.style.background = '#fed7d7';
            alertBox.style.color = '#742a2a';
            alertBox.style.border = '1px solid #feb2b2';
            alertBox.innerHTML = '<strong>Lỗi mạng:</strong> Không thể kết nối tới máy chủ.';
        });
    });

    // Handler form đăng ký
    var regForm = document.getElementById('registerForm');
    var regBtn = document.getElementById('registerBtn');
    regForm.addEventListener('submit', function(e) {
        e.preventDefault();
        regBtn.disabled = true;
        regBtn.textContent = 'Đang khởi tạo tài khoản...';
        alertBox.style.display = 'none';

        var formData = new FormData(regForm);
        fetch(regForm.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            regBtn.disabled = false;
            regBtn.textContent = 'Tạo Tài Khoản';
            if (data.status === 'success') {
                alertBox.style.display = 'block';
                alertBox.style.background = '#c6f6d5';
                alertBox.style.color = '#22543d';
                alertBox.style.border = '1px solid #9ae6b4';
                alertBox.innerHTML = '<strong>Đăng ký thành công!</strong> Đang chuyển tiếp vào hệ thống...';
                setTimeout(function() {
                    window.location.href = targetUrl;
                }, 700);
            } else {
                var msg = 'Đăng ký không thành công.';
                if (data.errors && data.errors.length > 0) {
                    msg = data.errors.map(function(e) { return e.message; }).join('<br>');
                }
                alertBox.style.display = 'block';
                alertBox.style.background = '#fed7d7';
                alertBox.style.color = '#742a2a';
                alertBox.style.border = '1px solid #feb2b2';
                alertBox.innerHTML = '<strong>Lỗi:</strong> ' + msg;
            }
        })
        .catch(function() {
            regBtn.disabled = false;
            regBtn.textContent = 'Tạo Tài Khoản';
            alertBox.style.display = 'block';
            alertBox.style.background = '#fed7d7';
            alertBox.style.color = '#742a2a';
            alertBox.style.border = '1px solid #feb2b2';
            alertBox.innerHTML = '<strong>Lỗi mạng:</strong> Không thể kết nối tới máy chủ.';
        });
    });
});
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
