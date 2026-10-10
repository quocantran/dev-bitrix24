/**
 * Logic xử lý biểu mẫu cho Component aasc:audit.request
 * Chuẩn Bitrix Component 2.0 template script (ES6+)
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('auditRequestForm');
    const alertBox = document.getElementById('form-alert');
    const submitBtn = document.getElementById('submitBtn');

    if (!form || !alertBox || !submitBtn) {
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang gửi hồ sơ...';
        alertBox.style.display = 'none';
        alertBox.className = 'audit-alert';

        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            submitBtn.disabled = false;
            submitBtn.textContent = 'Gửi Hồ Sơ Yêu Cầu';

            if (data.status === 'success' && data.data) {
                alertBox.className = 'audit-alert audit-alert-success';
                alertBox.innerHTML = `<strong>Thành công:</strong> ${data.data.message} (Mã hồ sơ: #${data.data.requestId}, Lead CRM: #${data.data.leadId}). <a href="/portal/my-requests/" class="audit-alert-link">Xem lịch sử hồ sơ &rarr;</a>`;
                alertBox.style.display = 'block';
                form.reset();
            } else {
                let errorMsg = 'Đã có lỗi xảy ra trong quá trình xử lý.';
                if (data.errors && data.errors.length > 0) {
                    errorMsg = data.errors.map((err) => err.message).join('<br>');
                }
                alertBox.className = 'audit-alert audit-alert-error';
                alertBox.innerHTML = `<strong>Lỗi:</strong> ${errorMsg}`;
                alertBox.style.display = 'block';
            }
        } catch (err) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Gửi Hồ Sơ Yêu Cầu';
            alertBox.className = 'audit-alert audit-alert-error';
            alertBox.innerHTML = '<strong>Lỗi mạng:</strong> Không thể kết nối tới máy chủ. Vui lòng kiểm tra lại đường truyền.';
            alertBox.style.display = 'block';
        }
    });
});
