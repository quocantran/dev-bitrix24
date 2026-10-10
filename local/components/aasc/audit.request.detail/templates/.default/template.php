<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 * @var CMain $APPLICATION
 */

$request = $arResult['REQUEST'];
$currentStep = (int)$arResult['CURRENT_STEP'];
$steps = $arResult['WORKFLOW_STEPS'];
$comments = $arResult['TIMELINE_COMMENTS'] ?? [];
$requestId = (int)$request['ID'];

// Tính tỷ lệ % hoàn thành thanh tiến độ 6 bước (0%, 20%, 40%, 60%, 80%, 100%)
$progressPercent = min(100, max(0, round(($currentStep - 1) / 5 * 100)));
?>
<div class="audit-detail-wrapper" id="auditDetailApp" data-request-id="<?=$requestId?>" data-current-step="<?=$currentStep?>">
    <!-- Nút điều hướng quay lại -->
    <div class="detail-top-nav">
        <a href="/portal/my-requests/" class="btn-back-link">&larr; Quay lại danh sách hồ sơ</a>
        <div class="request-badge">
            Hồ sơ #<strong><?=$requestId?></strong><?php if (!empty($request['DATE_CREATE'])): ?> &bull; <?=htmlspecialcharsbx($request['DATE_CREATE'])?><?php endif; ?>
        </div>
    </div>

    <!-- Khung tiêu đề hồ sơ -->
    <div class="detail-header-card">
        <div class="header-main-info">
            <h2 class="company-title"><?=htmlspecialcharsbx($request['COMPANY_NAME'])?></h2>
            <div class="company-sub-meta">
                <span>Mã số thuế: <strong><?=htmlspecialcharsbx($request['TAX_CODE'] ?: 'Chưa cập nhật')?></strong></span>
                <span class="meta-separator">&bull;</span>
                <span>Chuyên viên phụ trách: <strong><?=htmlspecialcharsbx($arResult['ASSIGNED_USER_NAME'])?></strong></span>
            </div>
        </div>
    </div>

    <!-- Khung Stepper Timeline Tiến Độ Xử Lý Thời Gian Thực (6 Bước Chuẩn VSA) -->
    <div class="detail-stepper-card">
        <div class="stepper-card-header">
            <h3>Tiến Độ Quy Trình Kiểm Toán Chuẩn VSA</h3>
            <span class="stepper-live-badge" id="liveStatusBadge">
                <span class="pulse-indicator"></span> Đang đồng bộ thời gian thực
            </span>
        </div>

        <div class="stepper-container">
            <div class="stepper-progress-track">
                <div class="stepper-progress-fill" id="stepperProgressBar" style="width: <?=$progressPercent?>%;"></div>
            </div>

            <div class="stepper-steps-list">
                <?php foreach ($steps as $stepNum => $stepData): ?>
                    <?php
                    $isCompleted = ($stepNum < $currentStep);
                    $isCurrent = ($stepNum === $currentStep);
                    $stepClass = $isCompleted ? 'step-completed' : ($isCurrent ? 'step-current' : 'step-upcoming');
                    ?>
                    <div class="stepper-item <?=$stepClass?>" id="stepItem<?=$stepNum?>" data-step="<?=$stepNum?>">
                        <div class="step-circle">
                            <?php if ($isCompleted): ?>
                                &#10003;
                            <?php else: ?>
                                <?=$stepNum?>
                            <?php endif; ?>
                        </div>
                        <div class="step-text">
                            <span class="step-title"><?=htmlspecialcharsbx($stepData['TITLE'])?></span>
                            <span class="step-desc"><?=htmlspecialcharsbx($stepData['DESC'])?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($arResult['HAS_FINAL_REPORT'])): ?>
        <!-- Khung bàn giao Báo cáo kiểm toán chính thức khi hoàn tất bước 6 -->
        <div class="report-delivery-card">
            <div class="report-delivery-icon">&#9989;</div>
            <div class="report-delivery-info">
                <div class="report-delivery-badge">Phát hành Báo cáo kiểm toán chính thức</div>
                <h3 class="report-delivery-title">Báo Cáo Kiểm Toán Độc Lập Số <?=$requestId?>/2026/BCKT-AASC</h3>
                <p class="report-delivery-desc">
                    Cuộc kiểm toán đã hoàn tất kiểm toán thực địa và thủ tục kiểm soát chất lượng độc lập (EQCR). Báo cáo kiểm toán độc lập theo Chuẩn mực Kiểm toán Việt Nam số 700 (VSA 700) đã được Ban Giám đốc Hãng Kiểm toán AASC ký phát hành chính thức.
                </p>
                <div class="report-delivery-action">
                    <a href="<?=$arResult['REPORT_URL']?>" target="_blank" class="btn-download-report">
                        Xem & Tải Báo Cáo Kiểm Toán Độc Lập (PDF / In) &rarr;
                    </a>
                </div>
            </div>
        </div>
    <?php elseif (!empty($arResult['CAN_SIGN_CONTRACT'])): ?>
        <!-- Khung chấp thuận báo giá & Ký hợp đồng cho khách hàng -->
        <div class="quotation-card" id="quotationActionCard">
            <div class="quotation-icon">&#128221;</div>
            <div class="quotation-info">
                <div class="quotation-badge">Dự toán phí dịch vụ đã được phê duyệt</div>
                <h3 class="quotation-title">Dự toán phí kiểm toán: <?=number_format($arResult['ESTIMATED_FEE'])?> VNĐ</h3>
                <p class="quotation-desc">
                    Phương án kiểm toán và biểu phí dịch vụ đã được phê duyệt bởi Trưởng phòng/Ban Giám đốc AASC. Quý khách vui lòng xác nhận chấp thuận để chính thức ký kết hợp đồng và khởi tạo đoàn kiểm toán thực địa.
                </p>
                <div class="quotation-action">
                    <button type="button" id="btnSignContract" class="btn-sign-contract">
                        Chấp thuận & Ký hợp đồng dịch vụ kiểm toán &rarr;
                    </button>
                    <span id="signContractStatus" class="sign-status-text"></span>
                </div>
            </div>
        </div>
    <?php elseif (!empty($arResult['IS_CONTRACT_SIGNED'])): ?>
        <!-- Khung xác nhận khách hàng đã ký hợp đồng -->
        <div class="quotation-card quotation-card-signed" id="contractSignedCard">
            <div class="quotation-icon quotation-icon-success">&#9989;</div>
            <div class="quotation-info">
                <div class="quotation-badge quotation-badge-success">Đã xác nhận ký hợp đồng dịch vụ</div>
                <h3 class="quotation-title quotation-title-success">Đã Chấp Thuận Báo Giá & Ký Kết Hợp Đồng</h3>
                <p class="quotation-desc quotation-desc-success">
                    Quý khách đã hoàn tất việc xác nhận đồng ý dự toán phí dịch vụ và ký kết hợp đồng kiểm toán. Đội ngũ Ban Giám đốc và Trưởng phòng kiểm toán AASC đang tiếp nhận, lập kế hoạch thực địa (VSA 300) và triển khai các thủ tục tiếp theo.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lưới thông tin chi tiết và Timeline trao đổi -->
    <div class="detail-columns-grid">
        <!-- Cột trái: Thông tin hợp đồng & hồ sơ -->
        <div class="column-info-card">
            <div class="card-title">Thông Tin Đăng Ký Kiểm Toán</div>
            <div class="info-table">
                <div class="info-row">
                    <span class="info-label">Dịch vụ kiểm toán:</span>
                    <span class="info-value font-medium"><?=htmlspecialcharsbx($request['SERVICE_NAME'] ?: ($request['AUDIT_TYPE'] ?: 'Kiểm toán Báo cáo tài chính'))?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Quy mô doanh thu:</span>
                    <span class="info-value font-medium"><?=htmlspecialcharsbx($request['ANNUAL_REVENUE_FORMATTED'] ?: ($request['REVENUE_SCALE'] ?: 'Chưa cung cấp'))?></span>
                </div>
                <?php if ($arResult['ESTIMATED_FEE'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Phí dịch vụ:</span>
                        <span class="info-value text-primary font-bold"><?=number_format($arResult['ESTIMATED_FEE'], 0, ',', '.')?> VNĐ</span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Người liên hệ:</span>
                    <span class="info-value"><?=htmlspecialcharsbx($request['CONTACT_NAME'] ?: 'Đại diện doanh nghiệp')?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số điện thoại:</span>
                    <span class="info-value"><?=htmlspecialcharsbx($request['PHONE'] ?: ($request['CONTACT_PHONE'] ?: 'Chưa cung cấp'))?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Địa chỉ email:</span>
                    <span class="info-value"><?=htmlspecialcharsbx($request['EMAIL'] ?: ($request['CONTACT_EMAIL'] ?: 'Chưa cung cấp'))?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mã Lead CRM:</span>
                    <span class="info-value font-mono"><?=(!empty($request['CRM_LEAD_ID']) && (int)$request['CRM_LEAD_ID'] > 0) ? '#' . htmlspecialcharsbx($request['CRM_LEAD_ID']) : '<span class="text-pending">Đang khởi tạo</span>'?></span>
                </div>
                <?php if (!empty($request['CRM_DEAL_ID']) && (int)$request['CRM_DEAL_ID'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Mã Hợp đồng (Deal):</span>
                        <span class="info-value font-mono text-primary font-bold">#<?=htmlspecialcharsbx($request['CRM_DEAL_ID'])?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cột phải: Trao đổi & Ghi chú Timeline với chuyên viên -->
        <div class="column-timeline-card">
            <div class="card-title">Lịch Sử Trao Đổi & Phản Hồi Trực Tiếp</div>
            
            <div class="timeline-messages-stream" id="timelineStream">
                <?php if (empty($comments)): ?>
                    <div class="timeline-empty" id="timelineEmptyNotice">
                        Chưa có phản hồi nào. Bạn có thể gửi câu hỏi hoặc ghi chú bổ sung tài liệu dưới đây.
                    </div>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="timeline-msg-item">
                            <div class="msg-header">
                                <span class="msg-author"><?=htmlspecialcharsbx($comment['AUTHOR_NAME'])?></span>
                                <span class="msg-time"><?=htmlspecialcharsbx($comment['CREATED'])?></span>
                            </div>
                            <div class="msg-body">
                                <?=nl2br(htmlspecialcharsbx($comment['TEXT']))?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Khung nhập phản hồi mới -->
            <form id="noteForm" class="timeline-input-form">
                <?=bitrix_sessid_post()?>
                <div class="form-group">
                    <label for="noteMessage">Gửi ghi chú bổ sung hoặc thắc mắc cho đoàn kiểm toán:</label>
                    <textarea id="noteMessage" name="message" rows="3" placeholder="Nhập nội dung trao đổi, yêu cầu giải đáp hoặc thông tin bổ sung..." required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" id="btnSubmitNote" class="btn-send-note">
                        <span>Gửi phản hồi &rarr;</span>
                    </button>
                    <span id="noteStatusMsg" class="note-status-msg"></span>
                </div>
            </form>
        </div>
    </div>
</div>

