<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Cổng Thông Tin Dịch Vụ - Hãng Kiểm Toán AASC");

global $USER;
$isAuthorized = is_object($USER) && $USER->IsAuthorized();
?>

<div class="portal-home-container">
    <div class="portal-hero-banner">
        <h1 class="portal-hero-title">Hãng Kiểm Toán AASC</h1>
        <p class="portal-hero-desc">
            Đơn vị kiểm toán độc lập và tư vấn tài chính hàng đầu tại Việt Nam. Cung cấp dịch vụ kiểm toán Báo cáo tài chính, Quyết toán dự án đầu tư, Thẩm định giá tài sản và Tư vấn thuế chuyên nghiệp.
        </p>
        <div class="portal-hero-actions">
            <a href="/portal/services/" class="btn-hero-primary">
                Danh Mục Dịch Vụ &rarr;
            </a>
            <a href="/portal/request/" class="btn-hero-accent">
                Gửi Yêu Cầu Báo Giá
            </a>
            <?php if ($isAuthorized): ?>
                <a href="/portal/my-requests/" class="btn-hero-outline">
                    Hồ Sơ Của Tôi &rarr;
                </a>
            <?php else: ?>
                <a href="/portal/auth/?mode=register" class="btn-hero-outline">
                    Đăng Ký Tài Khoản &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="portal-stats-grid">
        <div class="portal-stat-card">
            <div class="portal-stat-number">30+ Năm</div>
            <div class="portal-stat-label">Kinh nghiệm kiểm toán độc lập tại Việt Nam</div>
        </div>
        <div class="portal-stat-card">
            <div class="portal-stat-number">2.000+</div>
            <div class="portal-stat-label">Doanh nghiệp FDI và Tập đoàn tin cậy</div>
        </div>
        <div class="portal-stat-card">
            <div class="portal-stat-number">24 Giờ</div>
            <div class="portal-stat-label">Cam kết SLA phản hồi và khảo sát ban đầu</div>
        </div>
    </div>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
