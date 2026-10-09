<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$user = $arResult['USER'] ?? [];
$isAuth = !empty($user['AUTHORIZED']);
?>

<div class="audit-request-container" style="max-width: 760px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div style="border-bottom: 2px solid #1a365d; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h2 style="color: #1a365d; font-size: 1.5rem; margin: 0 0 0.5rem 0; font-weight: 700;">Gửi Yêu Cầu Dịch Vụ Kiểm Toán</h2>
            <p style="color: #4a5568; margin: 0; font-size: 0.95rem;">Cung cấp thông tin doanh nghiệp để Trưởng phòng kiểm toán AASC khảo sát và lập Thư đề xuất dịch vụ.</p>
        </div>
        <?php if ($isAuth): ?>
            <div style="background: #ebf8ff; border: 1px solid #bee3f8; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; color: #2b6cb0;">
                <div>Tài khoản: <strong><?= htmlspecialcharsbx($user['CONTACT_NAME']) ?></strong></div>
                <div>Email: <?= htmlspecialcharsbx($user['EMAIL']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div id="form-alert" style="display: none; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.95rem;"></div>

    <form id="auditRequestForm" method="POST" action="<?= htmlspecialcharsbx($arResult['SUBMIT_URL']) ?>">
        <?= bitrix_sessid_post() ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Tên doanh nghiệp <span style="color: #e53e3e;">*</span></label>
                <input type="text" name="COMPANY_NAME" required value="<?= htmlspecialcharsbx($user['COMPANY_NAME'] ?? '') ?>" placeholder="Ví dụ: Công ty Cổ phần Sữa ABC" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Mã số thuế (MST) <span style="color: #e53e3e;">*</span></label>
                <input type="text" name="TAX_CODE" required value="<?= htmlspecialcharsbx($user['TAX_CODE'] ?? '') ?>" placeholder="10 hoặc 13 chữ số" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Doanh thu năm gần nhất (VNĐ) <span style="color: #e53e3e;">*</span></label>
                <input type="number" name="ANNUAL_REVENUE" required placeholder="Ví dụ: 80000000000" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
                <small style="color: #718096; font-size: 0.8rem;">Dùng để xác định quy mô cuộc kiểm toán</small>
            </div>
            <div>
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Dịch vụ yêu cầu</label>
                <select name="SERVICE_ID" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem; background: #fff;">
                    <?php if (!empty($arResult['SERVICES'])): ?>
                        <?php foreach ($arResult['SERVICES'] as $service): ?>
                            <option value="<?= (int)$service['ID'] ?>"><?= htmlspecialcharsbx($service['NAME']) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="1">Kiểm toán Báo cáo tài chính</option>
                        <option value="2">Kiểm toán Quyết toán vốn đầu tư</option>
                        <option value="3">Thẩm định giá tài sản</option>
                        <option value="4">Tư vấn thuế doanh nghiệp</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Người liên hệ <span style="color: #e53e3e;">*</span></label>
                <input type="text" name="CONTACT_NAME" required value="<?= htmlspecialcharsbx($user['CONTACT_NAME'] ?? '') ?>" placeholder="Họ và tên" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Số điện thoại <span style="color: #e53e3e;">*</span></label>
                <input type="tel" name="PHONE" required value="<?= htmlspecialcharsbx($user['PHONE'] ?? '') ?>" placeholder="Ví dụ: 0912345678" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 0.35rem; font-size: 0.9rem;">Email <span style="color: #e53e3e;">*</span></label>
                <input type="email" name="EMAIL" required value="<?= htmlspecialcharsbx($user['EMAIL'] ?? '') ?>" placeholder="name@company.com" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 0.95rem;">
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="/portal/my-requests/" style="color: #2b6cb0; font-size: 0.9rem; text-decoration: none;">&larr; Xem các hồ sơ đã gửi</a>
            <button type="submit" id="submitBtn" style="background: #1a365d; color: #ffffff; padding: 0.75rem 1.75rem; border: none; border-radius: 4px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                Gửi Hồ Sơ Yêu Cầu
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('auditRequestForm');
    var alertBox = document.getElementById('form-alert');
    var submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang gửi hồ sơ...';
        alertBox.style.display = 'none';

        var formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Gửi Hồ Sơ Yêu Cầu';

            if (data.status === 'success' && data.data) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#c6f6d5';
                alertBox.style.color = '#22543d';
                alertBox.style.border = '1px solid #9ae6b4';
                alertBox.innerHTML = '<strong>Thành công:</strong> ' + data.data.message + 
                    ' (Mã hồ sơ: #' + data.data.requestId + ', Lead CRM: #' + data.data.leadId + '). ' +
                    '<a href="/portal/my-requests/" style="color: #22543d; font-weight: 700; text-decoration: underline; margin-left: 0.5rem;">Xem lịch sử hồ sơ &rarr;</a>';
                form.reset();
            } else {
                var errorMsg = 'Đã có lỗi xảy ra.';
                if (data.errors && data.errors.length > 0) {
                    errorMsg = data.errors.map(function(err) { return err.message; }).join('<br>');
                }
                alertBox.style.display = 'block';
                alertBox.style.background = '#fed7d7';
                alertBox.style.color = '#742a2a';
                alertBox.style.border = '1px solid #feb2b2';
                alertBox.innerHTML = '<strong>Lỗi:</strong> ' + errorMsg;
            }
        })
        .catch(function(err) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Gửi Hồ Sơ Yêu Cầu';
            alertBox.style.display = 'block';
            alertBox.style.background = '#fed7d7';
            alertBox.style.color = '#742a2a';
            alertBox.style.border = '1px solid #feb2b2';
            alertBox.innerHTML = '<strong>Lỗi mạng:</strong> Không thể kết nối tới máy chủ. Vui lòng thử lại.';
        });
    });
});
</script>
