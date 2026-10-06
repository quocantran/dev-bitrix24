# dev-bitrix24

Dự án thực hành phát triển tính năng tùy biến trên nền tảng Bitrix24 bằng chuẩn D7. Mã nguồn tùy biến nằm trong thư mục `local/` và giao diện cổng thông tin người dùng nằm trong `portal/`, tuân thủ nguyên tắc không sửa đổi thư mục lõi `/bitrix/`.

## Tính năng chính

- Nhận dữ liệu biểu mẫu từ cổng thông tin và tạo bản ghi tương ứng trong CRM (Lead, Contact, Company, Deal).
- Bắt sự kiện CRM qua Event Handler D7 và gửi tin nhắn real-time vào kênh Redis pub/sub.
- Quản lý cấu hình hệ thống bằng file `.env` qua thư viện `phpdotenv`.
- Lưu cache danh mục dịch vụ trên Redis.
- Tự động triển khai mã nguồn lên máy chủ thông qua GitHub Actions self-hosted runner khi cập nhật nhánh `master`.

## Cấu trúc thư mục

```text
dev-bitrix24/
├── .github/
│   └── workflows/
│       └── deploy.yml              # Pipeline CI/CD tự động chạy khi push vào master
├── local/                          # Thư mục mã nguồn tùy biến
│   ├── modules/
│   │   └── aasc.audit/             # Module D7 (controller, model, handler)
│   ├── components/                 # Component hiển thị và xử lý biểu mẫu
│   ├── templates/                  # Site template cho giao diện portal
│   └── php_interface/
│       ├── init.php                # Khởi tạo hệ thống và nạp biến môi trường
│       └── scripts/                # Kịch bản kiểm tra và khởi tạo dữ liệu mẫu
├── portal/                         # Giao diện cổng thông tin tiếp nhận yêu cầu
├── .env.example                    # File mẫu khai báo biến môi trường
├── .gitignore                      # Danh sách file loại trừ khỏi git
├── composer.json                   # Khai báo thư viện bên ngoài (phpdotenv, predis)
└── README.md
```

## Cấu hình môi trường

Dự án tách cấu hình khỏi mã nguồn. Khi cài đặt trên môi trường mới, sao chép file cấu hình mẫu:

```bash
cp .env.example .env
```

Các biến môi trường cần thiết:

| Biến | Ý nghĩa | Ví dụ |
| :--- | :--- | :--- |
| `APP_ENV` | Môi trường thực thi | `production` hoặc `local` |
| `REDIS_HOST` | Địa chỉ máy chủ Redis | `127.0.0.1` |
| `REDIS_PORT` | Cổng kết nối Redis | `6379` |
| `REDIS_PASSWORD` | Mật khẩu Redis | `mat_khau_redis` |
| `REDIS_CHANNEL` | Kênh pub/sub sự kiện | `bitrix:pull:events` |
| `N8N_WEBHOOK_URL` | Webhook n8n nhận dữ liệu | `http://127.0.0.1:5678/webhook/lead-created` |

File `local/php_interface/init.php` đọc các giá trị này khi ứng dụng nạp yêu cầu.

## Quy trình triển khai

Mã nguồn được đồng bộ tự động lên máy chủ web (`/var/www/html/`) qua GitHub Actions runner:

1. Tạo nhánh riêng để viết tính năng mới từ nhánh `master`.
2. Tạo Pull Request trên GitHub sau khi kiểm tra xong mã nguồn.
3. Khi merge PR vào nhánh `master`, workflow `deploy.yml` tự động thực hiện:
   - Cài đặt thư viện qua `composer install --no-dev`.
   - Đồng bộ các tệp trong `local/` và `portal/` vào `/var/www/html/`.
   - Phân quyền tệp tin cho người dùng web (`bitrix:nginx`).
   - Xóa cache Bitrix và nạp lại tiến trình `php-fpm`.

Tiến trình runner chạy bằng tài khoản chuyên dụng `actions-runner` và được giới hạn lệnh thực thi trong `sudoers`.
