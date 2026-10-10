<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 * @var CBitrixComponentTemplate $this
 * @var CMain $APPLICATION
 */

$user = $arResult['USER'] ?? [];
$isAuth = !empty($user['AUTHORIZED']);
?>

<div class="audit-request-container">
    <div class="audit-request-header">
        <div>
            <h2 class="audit-request-title">Gửi Yêu Cầu Dịch Vụ Kiểm Toán</h2>
            <p class="audit-request-subtitle">Cung cấp thông tin doanh nghiệp để Trưởng phòng kiểm toán AASC khảo sát và lập Thư đề xuất dịch vụ.</p>
        </div>
        <?php if ($isAuth): ?>
            <div class="audit-user-badge">
                <div>Tài khoản: <strong><?= htmlspecialcharsbx($user['CONTACT_NAME']) ?></strong></div>
                <div>Email: <?= htmlspecialcharsbx($user['EMAIL']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div id="form-alert" class="audit-alert"></div>

    <form id="auditRequestForm" method="POST" action="<?= htmlspecialcharsbx($arResult['SUBMIT_URL']) ?>">
        <?= bitrix_sessid_post() ?>

        <div class="audit-form-grid-2">
            <div class="audit-form-group">
                <label class="audit-form-label" for="fieldCompanyName">Tên doanh nghiệp <span class="required">*</span></label>
                <input type="text" id="fieldCompanyName" name="COMPANY_NAME" class="audit-form-input" required value="<?= htmlspecialcharsbx($user['COMPANY_NAME'] ?? '') ?>" placeholder="Ví dụ: Công ty Cổ phần Sữa ABC">
            </div>
            <div class="audit-form-group">
                <label class="audit-form-label" for="fieldTaxCode">Mã số thuế (MST) <span class="required">*</span></label>
                <input type="text" id="fieldTaxCode" name="TAX_CODE" class="audit-form-input" required value="<?= htmlspecialcharsbx($user['TAX_CODE'] ?? '') ?>" placeholder="10 hoặc 13 chữ số">
            </div>
        </div>

        <div class="audit-form-grid-2">
            <div class="audit-form-group">
                <label class="audit-form-label" for="fieldAnnualRevenue">Doanh thu năm gần nhất (VNĐ) <span class="required">*</span></label>
                <input type="number" id="fieldAnnualRevenue" name="ANNUAL_REVENUE" class="audit-form-input" required placeholder="Ví dụ: 80000000000">
                <small class="audit-form-hint">Dùng để xác định quy mô cuộc kiểm toán</small>
            </div>
            <div class="audit-form-group">
                <label class="audit-form-label" for="fieldServiceId">Dịch vụ yêu cầu</label>
                <select id="fieldServiceId" name="SERVICE_ID" class="audit-form-select">
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

        <div class="audit-form-grid-3">
            <div class="audit-form-group">
                <label class="audit-form-label" for="fieldContactName">Người liên hệ <span class="required">*</span></label>
                <input type="text" id="fieldContactName" name="CONTACT_NAME" class="audit-form-input" required value="<?= htmlspecialcharsbx($user['CONTACT_NAME'] ?? '') ?>" placeholder="Họ và tên">
            </div>
            <div class="audit-form-group">
                <label class="audit-form-label" for="fieldPhone">Số điện thoại <span class="required">*</span></label>
                <input type="tel" id="fieldPhone" name="PHONE" class="audit-form-input" required value="<?= htmlspecialcharsbx($user['PHONE'] ?? '') ?>" placeholder="Ví dụ: 0912345678">
            </div>
            <div class="audit-form-group">
                <label class="audit-form-label" for="fieldEmail">Email <span class="required">*</span></label>
                <input type="email" id="fieldEmail" name="EMAIL" class="audit-form-input" required value="<?= htmlspecialcharsbx($user['EMAIL'] ?? '') ?>" placeholder="name@company.com">
            </div>
        </div>

        <div class="audit-form-actions">
            <a href="/portal/my-requests/" class="audit-back-link">&larr; Xem các hồ sơ đã gửi</a>
            <button type="submit" id="submitBtn" class="audit-submit-btn">
                Gửi Hồ Sơ Yêu Cầu
            </button>
        </div>
    </form>
</div>
