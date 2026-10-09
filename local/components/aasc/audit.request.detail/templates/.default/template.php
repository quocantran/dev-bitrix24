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
$lead = $arResult['LEAD'];
$comments = $arResult['TIMELINE_COMMENTS'] ?? [];
$requestId = (int)$request['ID'];

// Tính tỷ lệ % hoàn thành thanh tiến độ 6 bước (0%, 20%, 40%, 60%, 80%, 100%)
$progressPercent = min(100, max(0, round(($currentStep - 1) / 5 * 100)));
?>
<div class="audit-detail-wrapper" id="auditDetailApp" data-request-id="<?=$requestId?>">
    <!-- Nút điều hướng quay lại -->
    <div class="detail-top-nav">
        <a href="/portal/my-requests/" class="btn-back-link">&larr; Quay lại danh sách hồ sơ</a>
        <div class="request-badge">
            Hồ sơ #<strong><?=$requestId?></strong> &bull; <?=htmlspecialcharsbx($request['DATE_CREATE'])?>
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
    <?php endif; ?>

    <!-- Lưới thông tin chi tiết và Timeline trao đổi -->
    <div class="detail-columns-grid">
        <!-- Cột trái: Thông tin hợp đồng & hồ sơ -->
        <div class="column-info-card">
            <div class="card-title">Thông Tin Đăng Ký Kiểm Toán</div>
            <div class="info-table">
                <div class="info-row">
                    <span class="info-label">Dịch vụ kiểm toán:</span>
                    <span class="info-value font-medium"><?=htmlspecialcharsbx($request['AUDIT_TYPE'] ?: 'Kiểm toán báo cáo tài chính')?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Quy mô doanh thu:</span>
                    <span class="info-value font-medium"><?=htmlspecialcharsbx($request['REVENUE_SCALE'] ?: 'Chưa cung cấp')?></span>
                </div>
                <?php if ($arResult['ESTIMATED_FEE'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Phí dịch vụ:</span>
                        <span class="info-value text-primary font-bold"><?=number_format($arResult['ESTIMATED_FEE'])?> VNĐ</span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Người liên hệ:</span>
                    <span class="info-value"><?=htmlspecialcharsbx($request['CONTACT_NAME'] ?: 'Đại diện doanh nghiệp')?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số điện thoại:</span>
                    <span class="info-value"><?=htmlspecialcharsbx($request['CONTACT_PHONE'] ?: 'Chưa cung cấp')?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Địa chỉ email:</span>
                    <span class="info-value"><?=htmlspecialcharsbx($request['CONTACT_EMAIL'] ?: 'Chưa cung cấp')?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mã Lead CRM:</span>
                    <span class="info-value font-mono">#<?=htmlspecialcharsbx($request['CRM_LEAD_ID'])?></span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestId = <?=$requestId?>;
    const noteForm = document.getElementById('noteForm');
    const noteInput = document.getElementById('noteMessage');
    const btnSubmit = document.getElementById('btnSubmitNote');
    const statusMsg = document.getElementById('noteStatusMsg');
    const timelineStream = document.getElementById('timelineStream');
    const timelineEmpty = document.getElementById('timelineEmptyNotice');

    // 1. Xử lý gửi phản hồi / ghi chú qua Bitrix AJAX Controller
    if (noteForm) {
        noteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = noteInput.value.trim();
            if (!message) return;

            btnSubmit.disabled = true;
            statusMsg.textContent = 'Đang gửi...';
            statusMsg.style.color = '#4a5568';

            const formData = new FormData();
            formData.append('requestId', requestId);
            formData.append('message', message);
            formData.append('sessid', BX.bitrix_sessid());

            fetch('/bitrix/services/main/ajax.php?action=aasc:audit.controller.request.addNote', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                btnSubmit.disabled = false;
                if (res.status === 'success') {
                    noteInput.value = '';
                    statusMsg.textContent = 'Đã gửi thành công!';
                    statusMsg.style.color = '#38a169';
                    setTimeout(() => { statusMsg.textContent = ''; }, 3000);

                    // Thêm ngay tin nhắn vào giao diện nếu Push server chưa kịp phản hồi
                    appendTimelineMessage('Bạn', 'Vừa xong', message);
                } else {
                    const err = (res.errors && res.errors[0]) ? res.errors[0].message : 'Không thể gửi phản hồi.';
                    statusMsg.textContent = err;
                    statusMsg.style.color = '#e53e3e';
                }
            })
            .catch(() => {
                btnSubmit.disabled = false;
                statusMsg.textContent = 'Lỗi kết nối máy chủ.';
                statusMsg.style.color = '#e53e3e';
            });
        });
    }

    function appendTimelineMessage(author, time, text) {
        if (timelineEmpty) {
            timelineEmpty.style.display = 'none';
        }
        const msgDiv = document.createElement('div');
        msgDiv.className = 'timeline-msg-item';
        msgDiv.innerHTML = `
            <div class="msg-header">
                <span class="msg-author">${BX.util.htmlspecialchars(author)}</span>
                <span class="msg-time">${BX.util.htmlspecialchars(time)}</span>
            </div>
            <div class="msg-body">${BX.util.htmlspecialchars(text).replace(/\\n/g, '<br>')}</div>
        `;
        timelineStream.appendChild(msgDiv);
        timelineStream.scrollTop = timelineStream.scrollHeight;
    }

    // 2. Xử lý ký hợp đồng dịch vụ kiểm toán
    const btnSign = document.getElementById('btnSignContract');
    if (btnSign) {
        btnSign.addEventListener('click', function() {
            if (!confirm('Bạn có chắc chắn muốn xác nhận chấp thuận dự toán phí và chính thức ký kết hợp đồng dịch vụ kiểm toán?')) {
                return;
            }

            btnSign.disabled = true;
            const signStatus = document.getElementById('signContractStatus');
            if (signStatus) {
                signStatus.textContent = 'Đang tiến hành ký kết hợp đồng...';
                signStatus.style.color = '#4a5568';
            }

            const fd = new FormData();
            fd.append('requestId', requestId);
            fd.append('sessid', BX.bitrix_sessid());

            fetch('/bitrix/services/main/ajax.php?action=aasc:audit.controller.request.signContract', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    if (signStatus) {
                        signStatus.textContent = 'Ký hợp đồng thành công! Đang tải lại...';
                        signStatus.style.color = '#38a169';
                    }
                    updateStepperUI(3);
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    btnSign.disabled = false;
                    const err = (res.errors && res.errors[0]) ? res.errors[0].message : 'Có lỗi khi ký kết hợp đồng.';
                    if (signStatus) {
                        signStatus.textContent = err;
                        signStatus.style.color = '#e53e3e';
                    }
                }
            })
            .catch(() => {
                btnSign.disabled = false;
                if (signStatus) {
                    signStatus.textContent = 'Lỗi kết nối máy chủ.';
                    signStatus.style.color = '#e53e3e';
                }
            });
        });
    }

    // 3. Tích hợp Bitrix Push & Pull thời gian thực qua WebSocket
    if (typeof BX !== 'undefined' && BX.PULL) {
        // Đăng ký mở rộng kênh theo dõi hồ sơ này
        BX.PULL.extendWatch('AASC_AUDIT_REQUEST_' + requestId);

        BX.PULL.subscribe({
            moduleId: 'aasc.audit',
            callback: function(data) {
                if (!data || !data.params) return;

                // Trường hợp 1: Nhận sự kiện chuyển bước trạng thái kiểm toán
                if (data.command === 'request_status_updated' && parseInt(data.params.requestId) === requestId) {
                    const newStep = parseInt(data.params.stepIndex);
                    if (newStep >= 1 && newStep <= 6) {
                        updateStepperUI(newStep);
                        if (newStep === 3 || newStep === 6) {
                            setTimeout(() => { window.location.reload(); }, 1200);
                        }
                    }
                }

                // Trường hợp 2: Nhận tin nhắn trao đổi mới từ CRM Timeline
                if (data.command === 'new_request_note' && parseInt(data.params.requestId) === requestId) {
                    appendTimelineMessage(
                        data.params.authorName || 'Chuyên viên AASC',
                        data.params.time || 'Vừa xong',
                        data.params.message || ''
                    );
                }
            }
        });
    }

    // Cập nhật giao diện thanh Stepper khi nhận WebSocket (6 bước)
    function updateStepperUI(currentStep) {
        const percent = Math.min(100, Math.max(0, Math.round((currentStep - 1) / 5 * 100)));
        const progressBar = document.getElementById('stepperProgressBar');
        if (progressBar) {
            progressBar.style.width = percent + '%';
        }

        for (let i = 1; i <= 6; i++) {
            const stepItem = document.getElementById('stepItem' + i);
            if (!stepItem) continue;

            const circle = stepItem.querySelector('.step-circle');
            stepItem.classList.remove('step-completed', 'step-current', 'step-upcoming');

            if (i < currentStep) {
                stepItem.classList.add('step-completed');
                if (circle) circle.innerHTML = '&#10003;';
            } else if (i === currentStep) {
                stepItem.classList.add('step-current');
                if (circle) circle.innerHTML = i;
            } else {
                stepItem.classList.add('step-upcoming');
                if (circle) circle.innerHTML = i;
            }
        }
    }
});
</script>
