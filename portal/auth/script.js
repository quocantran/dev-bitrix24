/**
 * Logic xử lý đăng nhập & đăng ký Cổng thông tin AASC (ES6+)
 */

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('authApp');
    if (!container) return;

    const targetUrl = container.dataset.backurl || '/portal/request/';
    const alertBox = document.getElementById('authAlert');
    const loginForm = document.getElementById('loginForm');
    const regForm = document.getElementById('registerForm');
    const tabBtnLogin = document.getElementById('tabBtnLogin');
    const tabBtnRegister = document.getElementById('tabBtnRegister');
    const loginBtn = document.getElementById('loginBtn');
    const regBtn = document.getElementById('registerBtn');

    // Chuyển đổi giữa 2 tab Đăng nhập và Đăng ký
    const switchTab = (mode) => {
        if (!alertBox || !loginForm || !regForm || !tabBtnLogin || !tabBtnRegister) return;

        alertBox.classList.remove('is-visible', 'success', 'error');

        if (mode === 'login') {
            loginForm.classList.remove('is-hidden');
            loginForm.classList.add('is-active');
            regForm.classList.remove('is-active');
            regForm.classList.add('is-hidden');
            tabBtnLogin.classList.add('active');
            tabBtnRegister.classList.remove('active');
        } else {
            loginForm.classList.remove('is-active');
            loginForm.classList.add('is-hidden');
            regForm.classList.remove('is-hidden');
            regForm.classList.add('is-active');
            tabBtnRegister.classList.add('active');
            tabBtnLogin.classList.remove('active');
        }
    };

    if (tabBtnLogin) {
        tabBtnLogin.addEventListener('click', () => switchTab('login'));
    }
    if (tabBtnRegister) {
        tabBtnRegister.addEventListener('click', () => switchTab('register'));
    }

    // Xử lý gửi Form Đăng nhập
    if (loginForm && loginBtn && alertBox) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            loginBtn.disabled = true;
            loginBtn.textContent = 'Đang xác thực...';
            alertBox.classList.remove('is-visible', 'success', 'error');

            const formData = new FormData(loginForm);

            try {
                const response = await fetch(loginForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                loginBtn.disabled = false;
                loginBtn.textContent = 'Đăng Nhập';

                if (data.status === 'success') {
                    alertBox.className = 'auth-alert success is-visible';
                    const redirectTarget = (data.data && data.data.redirectUrl) ? data.data.redirectUrl : targetUrl;
                    alertBox.innerHTML = '<strong>Đăng nhập thành công!</strong> Đang chuyển hướng...';
                    setTimeout(() => {
                        window.location.href = redirectTarget;
                    }, 600);
                } else {
                    let msg = 'Đăng nhập không thành công.';
                    if (data.errors && data.errors.length > 0) {
                        msg = data.errors.map(err => err.message).join('<br>');
                    }
                    alertBox.className = 'auth-alert error is-visible';
                    alertBox.innerHTML = `<strong>Lỗi:</strong> ${msg}`;
                }
            } catch {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Đăng Nhập';
                alertBox.className = 'auth-alert error is-visible';
                alertBox.innerHTML = '<strong>Lỗi mạng:</strong> Không thể kết nối tới máy chủ. Vui lòng thử lại sau.';
            }
        });
    }

    // Xử lý gửi Form Đăng ký
    if (regForm && regBtn && alertBox) {
        regForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            regBtn.disabled = true;
            regBtn.textContent = 'Đang khởi tạo tài khoản...';
            alertBox.classList.remove('is-visible', 'success', 'error');

            const formData = new FormData(regForm);

            try {
                const response = await fetch(regForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                regBtn.disabled = false;
                regBtn.textContent = 'Tạo Tài Khoản';

                if (data.status === 'success') {
                    alertBox.className = 'auth-alert success is-visible';
                    alertBox.innerHTML = '<strong>Đăng ký thành công!</strong> Đang chuyển tiếp vào hệ thống...';
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 700);
                } else {
                    let msg = 'Đăng ký không thành công.';
                    if (data.errors && data.errors.length > 0) {
                        msg = data.errors.map(err => err.message).join('<br>');
                    }
                    alertBox.className = 'auth-alert error is-visible';
                    alertBox.innerHTML = `<strong>Lỗi:</strong> ${msg}`;
                }
            } catch {
                regBtn.disabled = false;
                regBtn.textContent = 'Tạo Tài Khoản';
                alertBox.className = 'auth-alert error is-visible';
                alertBox.innerHTML = '<strong>Lỗi mạng:</strong> Không thể kết nối tới máy chủ. Vui lòng thử lại sau.';
            }
        });
    }
});
