# Lop Fund – Laravel API
API cho ứng dụng quản lý quỹ lớp. Dùng Laravel + Sanctum.
## Yêu cầu
- PHP 8.2+
- Composer 2.x
- MySQL/MariaDB
- (tuỳ chọn) ngrok để expose API

## Cài đặt & chạy
```bash
# 1) Cài dependencies
composer install

# 2) Tạo file môi trường
cp .env.example .env

# 3) Tạo APP_KEY
php artisan key:generate

# 4) Cấu hình DB trong .env rồi migrate + seed
php artisan migrate --seed

# 5) Public storage (lưu ảnh chứng từ)
php artisan storage:link

# 6) Chạy server
php artisan serve   # http://127.0.0.1:8000
```
Cấu hình .env mẫu
```bash
APP_NAME=LopFund
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lopfund
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```
- Thư mục/Controller chính
   - routes/api.php – Khai báo API
   - app/Http/Controllers/
       - AuthController – đăng ký/đăng nhập/hydrate
       - ClassController – tạo lớp, join lớp, members, chuyển owner
       - FeeCycleController – kỳ thu, tạo hoá đơn, báo cáo kỳ
       - PaymentController – nộp, duyệt phiếu nộp, danh sách đã duyệt
       - ExpenseController/ExpenseRequestController – khoản chi, yêu cầu chi
       - FundAccountController – thông tin tài khoản quỹ, summary tổng thu/chi/số dư
- Kiểm thử nhanh (Postman/cURL)
    - Tất cả endpoint ở prefix /api. Dùng header Authorization: Bearer <token> sau khi đăng nhập.
    - POST /api/register – đăng ký
    - POST /api/login – đăng nhập → nhận token
    - GET /api/classes – lớp đã tham gia
    - POST /api/classes – tạo lớp { name }
    - POST /api/classes/join – nhập mã lớp { code }
    - GET /api/classes/{class}/fund-account/summary – tổng thu/chi/số dư
    - GET /api/classes/{class}/payments/approved – danh sách phiếu đã duyệt
- Lệnh hữu ích
```bash
php artisan migrate:fresh --seed
php artisan tinker
php artisan route:list
```
Troubleshoot
Ảnh không hiển thị → thiếu php artisan storage:link
Mobile không gọi được localhost → Android dùng http://10.0.2.2:8000
CORS/Sanctum → đảm bảo APP_URL đúng và SANCTUM_STATEFUL_DOMAINS đã bao localhost

---

