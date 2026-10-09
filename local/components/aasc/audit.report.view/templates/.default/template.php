<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 * @var CMain $APPLICATION
 */

$request = $arResult['REQUEST'];
$requestId = (int)$request['ID'];
$isCompleted = (bool)$arResult['IS_COMPLETED'];
$reportNo = $arResult['REPORT_NO'];
$issueDate = $arResult['ISSUE_DATE'];
$companyName = htmlspecialcharsbx($request['COMPANY_NAME']);
$taxCode = htmlspecialcharsbx($request['TAX_CODE']);
?>
<div class="report-wrapper">
    <div class="report-nav-bar no-print">
        <a href="/portal/my-requests/<?=$requestId?>/" class="btn-back">&larr; Quay lại chi tiết hồ sơ</a>
        <?php if ($isCompleted): ?>
            <button type="button" onclick="window.print()" class="btn-print">
                In Báo Cáo / Lưu PDF
            </button>
        <?php endif; ?>
    </div>

    <?php if (!$isCompleted): ?>
        <div class="report-pending-card no-print">
            <h3>Hồ sơ đang trong quá trình thực hiện kiểm toán</h3>
            <p>
                Báo cáo kiểm toán chính thức chỉ được phát hành sau khi hoàn tất kiểm toán thực địa và quy trình soát xét kiểm soát chất lượng (EQCR) của Ban Giám đốc. Quý khách vui lòng theo dõi tiến độ tại trang chi tiết hồ sơ.
            </p>
            <div class="mt-3">
                <a href="/portal/my-requests/<?=$requestId?>/" class="btn-view-progress">Xem tiến độ xử lý &rarr;</a>
            </div>
        </div>
    <?php else: ?>
        <div class="report-document">
            <!-- Phần đầu văn bản -->
            <div class="document-header">
                <div class="header-left">
                    <div class="org-name">HÃNG KIỂM TOÁN AASC</div>
                    <div class="org-sub">HỆ THỐNG QUẢN LÝ DỊCH VỤ KIỂM TOÁN</div>
                    <div class="doc-code">Số: <?=$reportNo?></div>
                </div>
                <div class="header-right">
                    <div class="national-title">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                    <div class="national-motto">Độc lập - Tự do - Hạnh phúc</div>
                    <div class="doc-date">Hà Nội, ngày <?=date('d')?> tháng <?=date('m')?> năm <?=date('Y')?></div>
                </div>
            </div>

            <!-- Tiêu đề báo cáo -->
            <div class="document-title-block">
                <h1 class="document-title">BÁO CÁO KIỂM TOÁN ĐỘC LẬP</h1>
                <div class="document-recipient">
                    Kính gửi: Hội đồng Quản trị và Ban Giám đốc<br>
                    <strong><?=$companyName?></strong>
                </div>
            </div>

            <!-- Nội dung báo cáo theo VSA 700 -->
            <div class="document-content">
                <p>
                    Chúng tôi đã kiểm toán báo cáo tài chính của <strong><?=$companyName?></strong> (Mã số thuế: <?=$taxCode?>), bao gồm Bảng cân đối kế toán, Báo cáo kết quả hoạt động kinh doanh, Báo cáo lưu chuyển tiền tệ và Bản thuyết minh báo cáo tài chính cho năm tài chính kết thúc cùng ngày.
                </p>

                <h4>1. Trách nhiệm của Ban Giám đốc đơn vị được kiểm toán</h4>
                <p>
                    Ban Giám đốc <strong><?=$companyName?></strong> chịu trách nhiệm về việc lập và trình bày trung thực, hợp lý báo cáo tài chính theo Chuẩn mực kế toán Việt Nam, Chế độ kế toán doanh nghiệp Việt Nam và các quy định pháp lý có liên quan. Trách nhiệm này bao gồm việc thiết kế, thực hiện và duy trì kiểm soát nội bộ cần thiết để đảm bảo việc lập báo cáo tài chính không có sai sót trọng yếu do gian lận hoặc nhầm lẫn.
                </p>

                <h4>2. Trách nhiệm của Kiểm toán viên</h4>
                <p>
                    Trách nhiệm của chúng tôi là đưa ra ý kiến về báo cáo tài chính dựa trên kết quả của cuộc kiểm toán. Chúng tôi đã tiến hành kiểm toán theo các Chuẩn mực Kiểm toán Việt Nam (VSA). Các chuẩn mực này yêu cầu chúng tôi tuân thủ chuẩn mực đạo đức nghề nghiệp, lập kế hoạch và thực hiện cuộc kiểm toán để đạt được sự đảm bảo hợp lý về việc liệu báo cáo tài chính có còn sai sót trọng yếu hay không.
                </p>
                <p>
                    Chúng tôi tin tưởng rằng các bằng chứng kiểm toán mà chúng tôi đã thu thập được trong quá trình kiểm toán thực địa là đầy đủ và thích hợp để làm cơ sở cho ý kiến kiểm toán của chúng tôi.
                </p>

                <h4>3. Ý kiến của Kiểm toán viên (Chấp nhận toàn phần - VSA 700)</h4>
                <div class="audit-opinion-box">
                    <p>
                        Theo ý kiến của chúng tôi, báo cáo tài chính đã phản ánh trung thực và hợp lý, trên các khía cạnh trọng yếu, tình hình tài chính của <strong><?=$companyName?></strong>, cũng như kết quả hoạt động kinh doanh và tình hình lưu chuyển tiền tệ cho năm tài chính kết thúc cùng ngày, phù hợp với Chuẩn mực Kế toán Việt Nam, Chế độ Kế toán doanh nghiệp Việt Nam và các quy định pháp lý có liên quan.
                    </p>
                </div>
            </div>

            <!-- Khối chữ ký đại diện đoàn kiểm toán và Giám đốc -->
            <div class="document-signatures">
                <div class="sig-col">
                    <div class="sig-role">TRƯỞNG NHÓM KIỂM TOÁN</div>
                    <div class="sig-status">(Đã ký số xác thực)</div>
                    <div class="sig-stamp">
                        <div class="stamp-box">
                            <span class="stamp-check">&#10003;</span> KÝ SỐ ĐIỆN TỬ
                        </div>
                    </div>
                    <div class="sig-name">Lê Hoàng Nam</div>
                    <div class="sig-license">Số Giấy chứng nhận ĐKHN: 0988/KTV-BTC</div>
                </div>

                <div class="sig-col">
                    <div class="sig-role">THAY MẶT HÃNG KIỂM TOÁN AASC<br>GIÁM ĐỐC KIỂM TOÁN</div>
                    <div class="sig-status">(Đã ký số xác thực)</div>
                    <div class="sig-stamp">
                        <div class="stamp-box stamp-director">
                            <span class="stamp-check">&#10003;</span> DUYỆT BỞI BAN GIÁM ĐỐC
                        </div>
                    </div>
                    <div class="sig-name">Nguyễn Văn An</div>
                    <div class="sig-license">Số Giấy chứng nhận ĐKHN: 0142/KTV-BTC</div>
                </div>
            </div>

            <!-- Footer văn bản -->
            <div class="document-footer">
                <div class="footer-left">Hãng Kiểm toán AASC &bull; Báo cáo được khởi tạo và lưu trữ trên hệ thống Bitrix24</div>
                <div class="footer-right">Trang 1/1 &bull; Mã bảo mật: <?=md5($requestId . 'AASC_SECRET_2026')?></div>
            </div>
        </div>
    <?php endif; ?>
</div>
