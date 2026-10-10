/**
 * Script đồng bộ danh sách hồ sơ kiểm toán thời gian thực qua WebSocket Push & Pull (ES6+)
 */

document.addEventListener('DOMContentLoaded', () => {
    const refreshTable = async () => {
        try {
            const res = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTbody = doc.querySelector('#myRequestsTableBody');
            const curTbody = document.querySelector('#myRequestsTableBody');

            if (newTbody && curTbody && newTbody.innerHTML.trim() !== curTbody.innerHTML.trim()) {
                curTbody.innerHTML = newTbody.innerHTML;
            }
        } catch {
            // Giữ nguyên giao diện nếu kết nối mạng tạm thời gián đoạn
        }
    };

    // Tích hợp trực tiếp Bitrix Push & Pull WebSocket (Zero-Polling)
    if (typeof BX !== 'undefined' && BX.PULL) {
        BX.PULL.subscribe({
            moduleId: 'aasc.audit',
            command: 'request_status_updated',
            callback: () => {
                refreshTable();
            }
        });
    }

    // Tự động đồng bộ 1 lần khi người dùng quay lại tab này
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshTable();
        }
    });
});
