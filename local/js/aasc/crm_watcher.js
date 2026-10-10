/**
 * Script theo dõi và hiển thị thông báo lỗi quy trình trên giao diện CRM
 * Tự động bắt sự kiện CrmProgressControlAfterSaveSucces của Bitrix CRM
 */

(() => {
    const initAascCrmErrorWatcher = () => {
        if (window.BX && BX.addCustomEvent) {
            BX.addCustomEvent('CrmProgressControlAfterSaveSucces', (control, data) => {
                if (data && data.ERROR) {
                    if (window.BX && BX.UI && BX.UI.Notification && BX.UI.Notification.Center) {
                        BX.UI.Notification.Center.notify({
                            content: data.ERROR,
                            autoHideDelay: 6000
                        });
                    } else {
                        alert(data.ERROR);
                    }
                }
            });
        }
    };

    if (window.BX && BX.ready) {
        BX.ready(initAascCrmErrorWatcher);
    } else {
        document.addEventListener('DOMContentLoaded', initAascCrmErrorWatcher);
    }
})();
