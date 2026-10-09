<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Cổng Thông Tin Dịch Vụ - Hãng Kiểm Toán AASC");

global $USER;
$isAuthorized = is_object($USER) && $USER->IsAuthorized();
?>

<div style="max-width: 960px; margin: 0 auto; padding: 2rem 0;">
    <div style="background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%); color: #ffffff; padding: 3rem 2rem; border-radius: 8px; margin-bottom: 2.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h1 style="font-size: 2.25rem; font-weight: 700; margin: 0 0 1rem 0; color: #ffffff;">Hãng Kiểm Toán AASC</h1>
        <p style="font-size: 1.15rem; line-height: 1.6; margin: 0 0 2rem 0; color: #e2e8f0; max-width: 700px;">
            Đơn vị kiểm toán độc lập và tư vấn tài chính hàng đầu tại Việt Nam. Cung cấp dịch vụ kiểm toán Báo cáo tài chính, Quyết toán dự án đầu tư, Thẩm định giá tài sản và Tư vấn thuế chuyên nghiệp.
        </p>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="/portal/services/" style="background: #ffffff; color: #1a365d; padding: 0.75rem 1.5rem; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                Danh Mục Dịch Vụ &rarr;
            </a>
            <a href="/portal/request/" style="background: #ed8936; color: #ffffff; padding: 0.75rem 1.5rem; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                Gửi Yêu Cầu Báo Giá
            </a>
            <?php if ($isAuthorized): ?>
                <a href="/portal/my-requests/" style="background: rgba(255,255,255,0.2); color: #ffffff; border: 1px solid rgba(255,255,255,0.4); padding: 0.75rem 1.5rem; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 1rem;">
                    Hồ Sơ Của Tôi &rarr;
                </a>
            <?php else: ?>
                <a href="/portal/auth/?mode=register" style="background: rgba(255,255,255,0.2); color: #ffffff; border: 1px solid rgba(255,255,255,0.4); padding: 0.75rem 1.5rem; border-radius: 4px; font-weight: 600; text-decoration: none; font-size: 1rem;">
                    Đăng Ký Tài Khoản &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: #1a365d; margin-bottom: 0.5rem;">30+ Năm</div>
            <div style="color: #4a5568; font-size: 0.95rem;">Kinh nghiệm kiểm toán độc lập tại Việt Nam</div>
        </div>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: #1a365d; margin-bottom: 0.5rem;">2.000+</div>
            <div style="color: #4a5568; font-size: 0.95rem;">Doanh nghiệp FDI và Tập đoàn tin cậy</div>
        </div>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: #1a365d; margin-bottom: 0.5rem;">24 Giờ</div>
            <div style="color: #4a5568; font-size: 0.95rem;">Cam kết SLA phản hồi và khảo sát ban đầu</div>
        </div>
    </div>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
