/**
 * Logic xử lý tiến độ thời gian thực cho Component aasc:audit.request.detail
 * Chuẩn Bitrix Component 2.0 template script (ES6+)
 */

document.addEventListener('DOMContentLoaded', () => {
    const appEl = document.getElementById('auditDetailApp');
    if (!appEl) {
        return;
    }

    const requestId = parseInt(appEl.dataset.requestId || '0', 10);
    let currentStepState = parseInt(appEl.dataset.currentStep || '1', 10);

    const noteForm = document.getElementById('noteForm');
    const noteInput = document.getElementById('noteMessage');
    const btnSubmit = document.getElementById('btnSubmitNote');
    const statusMsg = document.getElementById('noteStatusMsg');
    const timelineStream = document.getElementById('timelineStream');
    const timelineEmpty = document.getElementById('timelineEmptyNotice');

    // 1. Thêm tin nhắn mới vào danh sách Timeline trên giao diện
    const appendTimelineMessage = (author, time, text) => {
        if (timelineEmpty) {
            timelineEmpty.style.display = 'none';
        }
        const msgDiv = document.createElement('div');
        msgDiv.className = 'timeline-msg-item';
        const safeAuthor = (window.BX && BX.util && BX.util.htmlspecialchars) ? BX.util.htmlspecialchars(author) : author;
        const safeTime = (window.BX && BX.util && BX.util.htmlspecialchars) ? BX.util.htmlspecialchars(time) : time;
        const safeText = (window.BX && BX.util && BX.util.htmlspecialchars) ? BX.util.htmlspecialchars(text).replace(/\n/g, '<br>') : text;

        msgDiv.innerHTML = `
            <div class="msg-header">
                <span class="msg-author">${safeAuthor}</span>
                <span class="msg-time">${safeTime}</span>
            </div>
            <div class="msg-body">${safeText}</div>
        `;
        if (timelineStream) {
            timelineStream.appendChild(msgDiv);
            timelineStream.scrollTop = timelineStream.scrollHeight;
        }
    };

    // 2. Xử lý gửi phản hồi / ghi chú qua Bitrix AJAX Controller
    if (noteForm && noteInput && btnSubmit && statusMsg) {
        noteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = noteInput.value.trim();
            if (!message) return;

            btnSubmit.disabled = true;
            statusMsg.textContent = 'Đang gửi...';
            statusMsg.style.color = '#4a5568';

            const formData = new FormData();
            formData.append('requestId', requestId);
            formData.append('message', message);
            if (window.BX && BX.bitrix_sessid) {
                formData.append('sessid', BX.bitrix_sessid());
            }

            try {
                const response = await fetch('/bitrix/services/main/ajax.php?action=aasc:audit.controller.request.addNote', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
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
            } catch {
                btnSubmit.disabled = false;
                statusMsg.textContent = 'Lỗi kết nối máy chủ.';
                statusMsg.style.color = '#e53e3e';
            }
        });
    }

    // 3. Cập nhật giao diện thanh Stepper khi nhận trạng thái mới (6 bước)
    const updateStepperUI = (step) => {
        const percent = Math.min(100, Math.max(0, Math.round((step - 1) / 5 * 100)));
        const progressBar = document.getElementById('stepperProgressBar');
        if (progressBar) {
            progressBar.style.width = `${percent}%`;
        }

        for (let i = 1; i <= 6; i++) {
            const stepItem = document.getElementById(`stepItem${i}`);
            if (!stepItem) continue;

            const circle = stepItem.querySelector('.step-circle');
            stepItem.classList.remove('step-completed', 'step-current', 'step-upcoming');

            if (i < step) {
                stepItem.classList.add('step-completed');
                if (circle) circle.innerHTML = '&#10003;';
            } else if (i === step) {
                stepItem.classList.add('step-current');
                if (circle) circle.innerHTML = i;
            } else {
                stepItem.classList.add('step-upcoming');
                if (circle) circle.innerHTML = i;
            }
        }
    };

    // 4. Xử lý ký hợp đồng dịch vụ kiểm toán
    const btnSign = document.getElementById('btnSignContract');
    if (btnSign) {
        btnSign.addEventListener('click', async () => {
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
            if (window.BX && BX.bitrix_sessid) {
                fd.append('sessid', BX.bitrix_sessid());
            }

            try {
                const response = await fetch('/bitrix/services/main/ajax.php?action=aasc:audit.controller.request.signContract', {
                    method: 'POST',
                    body: fd
                });
                const res = await response.json();

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
            } catch {
                btnSign.disabled = false;
                if (signStatus) {
                    signStatus.textContent = 'Lỗi kết nối máy chủ.';
                    signStatus.style.color = '#e53e3e';
                }
            }
        });
    }

    // 5. Tự động đồng bộ và cập nhật giao diện thời gian thực (Real-time Live Sync)
    const applyLiveUpdate = (data) => {
        if (!data) return;

        const newStep = parseInt(data.currentStep, 10);
        if (newStep >= 1 && newStep <= 6 && newStep !== currentStepState) {
            currentStepState = newStep;
            updateStepperUI(newStep);

            // Tự động tải lại trang nếu chuyển sang các trạng thái có thay đổi lớn trên giao diện
            // (Bước 2: Báo giá xuất hiện, Bước 3: Đã ký hợp đồng, Bước 6: Xuất hiện nút tải báo cáo)
            const quotationCard = document.getElementById('quotationActionCard');
            const signedCard = document.getElementById('contractSignedCard');
            const reportCard = document.querySelector('.report-delivery-card');

            if (newStep === 2 && !quotationCard) {
                setTimeout(() => { window.location.reload(); }, 600);
            } else if (newStep === 3 && !signedCard) {
                setTimeout(() => { window.location.reload(); }, 600);
            } else if (newStep === 6 && !reportCard) {
                setTimeout(() => { window.location.reload(); }, 600);
            }
        }

        // Cập nhật giá trị phí dịch vụ nếu có thay đổi
        if (data.estimatedFeeFormatted && data.estimatedFee > 0) {
            const feeRows = document.querySelectorAll('.info-value.text-primary');
            feeRows.forEach((el) => {
                if (el.textContent !== data.estimatedFeeFormatted) {
                    el.textContent = data.estimatedFeeFormatted;
                }
            });
        }
    };

    // Đồng bộ trạng thái mới nhất từ server khi nhận tín hiệu từ WebSocket
    const fetchLiveStatus = async () => {
        try {
            const response = await fetch(`/bitrix/services/main/ajax.php?action=aasc:audit.controller.request.getStatus&requestId=${requestId}`);
            const res = await response.json();
            if (res.status === 'success' && res.data) {
                applyLiveUpdate(res.data);
            }
        } catch {
            // Không làm gián đoạn giao diện nếu lỗi mạng tạm thời
        }
    };

    // 6. Tích hợp trực tiếp Bitrix Push & Pull WebSocket thời gian thực (Zero-Polling)
    const initPullSubscription = () => {
        if (typeof BX === 'undefined' || !BX.PULL) {
            return;
        }

        BX.PULL.extendWatch(`AASC_AUDIT_REQUEST_${requestId}`);

        BX.PULL.subscribe({
            moduleId: 'aasc.audit',
            callback: (data) => {
                if (!data || !data.params) return;

                // Trường hợp 1: Nhận sự kiện chuyển bước trạng thái kiểm toán từ WebSocket
                if (data.command === 'request_status_updated' && parseInt(data.params.requestId, 10) === requestId) {
                    fetchLiveStatus();
                }

                // Trường hợp 2: Nhận tin nhắn trao đổi mới từ CRM Timeline
                if (data.command === 'new_request_note' && parseInt(data.params.requestId, 10) === requestId) {
                    appendTimelineMessage(
                        data.params.authorName || 'Chuyên viên AASC',
                        data.params.time || 'Vừa xong',
                        data.params.message || ''
                    );
                }
            }
        });
    };

    if (typeof BX !== 'undefined') {
        BX.ready(initPullSubscription);
    } else {
        initPullSubscription();
    }

    // Khi người dùng chuyển lại tab này, tự động đồng bộ nhẹ 1 lần
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            fetchLiveStatus();
        }
    });
});
