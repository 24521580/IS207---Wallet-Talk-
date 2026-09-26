# Ví Nói

Ứng dụng quản lý chi tiêu cá nhân bằng tiếng Việt. Người dùng nhập một câu tự nhiên, AI tách thành các giao dịch có cấu trúc, người dùng xem lại rồi mới lưu.

## Luồng sản phẩm

1. Đăng nhập
2. Mở Dashboard
3. Bấm **Thêm bằng AI**
4. Nhập: `Hôm nay ăn sáng 30k, đổ xăng 100k, chiều mua sách 150k`
5. Bấm **Phân tích bằng AI**
6. Xem / sửa / xóa / thêm dòng
7. **Xác nhận & Lưu**
8. Lịch sử và Dashboard cập nhật

AI **không** ghi database trước khi người dùng xác nhận.

## Stack

- PHP 8.3, Laravel 13, MySQL
- Blade + Tailwind CSS v4
- Chart.js (bundle qua Vite)
- Gemini / OpenAI / Groq (server-side)

## Flow kỹ thuật

```
Browser
  → POST /giao-dich/ai (relative URL, CSRF, session)
    → TransactionController@parse
      → ExpenseParserService
        → DEMO_AI_MODE=true  ? DemoAiParser
        → DEMO_AI_MODE=false ? LiveAiClient (Gemini/OpenAI/Groq)
          → JSON
            → AiResponseValidator
              → Preview UI
                → User confirm
                  → POST /giao-dich/xac-nhan
                    → DB::transaction → MySQL
```

## Biến môi trường

Copy `.env.example` thành `.env` rồi chạy `php artisan key:generate`.

| Biến | Ý nghĩa |
| --- | --- |
| `DB_CONNECTION` | `mysql` trên production |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Kết nối MySQL |
| `DEMO_AI_MODE` | `true` = parser demo nội bộ; `false` = gọi AI thật |
| `AI_PROVIDER` | `gemini`, `openai` hoặc `groq` |
| `GEMINI_API_KEY` | Key Gemini (cũng nhận `GOOGLE_API_KEY`) |
| `OPENAI_API_KEY` | Key OpenAI |
| `GROQ_API_KEY` | Key Groq |
| `AI_TIMEOUT` | Timeout HTTP (giây) |
| `APP_URL` | URL public, phải là `https://...` trên Railway |
| `APP_DEBUG` | `false` trên production |

API key **chỉ** nằm trên server. Không đưa vào Blade, JavaScript, HTML, JSON response, log, hoặc git.

## Chạy local

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Mở http://localhost:8000

Tài khoản demo (sau `db:seed`):

| Role | Email | Password |
| --- | --- | --- |
| User | demo@vinoi.com | Demo123@ |
| Admin | admin@vinoi.com | Admin123@ |

## Demo AI Mode

Trong `.env` / Railway Variables:

```
DEMO_AI_MODE=true
```

- Không gọi API ngoài.
- UI hiện huy hiệu **Demo AI Mode**.
- Dùng dữ liệu deterministic cho các câu demo.

```
DEMO_AI_MODE=false
```

- Bắt buộc gọi AI thật.
- Nếu API lỗi: trả lỗi thật, **không** âm thầm chuyển sang demo.
- UI hiện **AI Connected** khi đã có key; hiện **AI chưa cấu hình** nếu thiếu key.

Sau khi đổi biến trên Railway **phải Redeploy**.

---

## Hướng dẫn triển khai Railway

### 1. Variable cần thêm (đúng service đang chạy web)

Vào Railway Dashboard → chọn **service Laravel / Ví Nói** (không phải service MySQL) → tab **Variables**.

| Tên biến (khớp chính xác) | Giá trị | Bắt buộc |
| --- | --- | --- |
| `APP_NAME` | `Ví Nói` | Có |
| `APP_ENV` | `production` | Có |
| `APP_KEY` | `php artisan key:generate --show` (`base64:...`) | Có |
| `APP_DEBUG` | `false` | Có |
| `APP_URL` | Domain Railway, ví dụ `https://xxx.up.railway.app` | Có |
| `DEMO_AI_MODE` | `false` (AI thật) hoặc `true` (demo) | Có |
| `AI_PROVIDER` | `gemini` (hoặc `openai`, `groq`) | Có |
| `GEMINI_API_KEY` | Key từ Google AI Studio | Khi dùng Gemini |
| `GEMINI_MODEL` | `gemini-2.0-flash` | Không bắt buộc |
| `OPENAI_API_KEY` | `sk-...` | Khi dùng OpenAI |
| `OPENAI_MODEL` | `gpt-4o-mini` | Không bắt buộc |
| `AI_TIMEOUT` | `25` | Không bắt buộc |
| `DB_CONNECTION` | `mysql` | Có |

MySQL trên Railway: gắn plugin MySQL vào **cùng service web**. App đọc `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQL_URL`. Nên map tương minh:

```
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

**QUAN TRỌNG**: Key API phải đặt trên **đúng service đang chạy PHP/Laravel**, không đặt nhầm sang MySQL plugin.

### 2. Phải redeploy sau khi đổi variable

Railway không inject biến mới vào process đang chạy. Sau khi thêm/sửa `GEMINI_API_KEY`, `OPENAI_API_KEY`, `DEMO_AI_MODE`, `APP_URL` → **Redeploy**.

Start command đã có `config:clear` để Laravel đọc env lúc runtime (không dùng config cache cũ).

### 3. Kiểm tra app đang chạy live

1. `GET https://<domain>/up` → HTTP 200.
2. Đăng nhập → **Thêm giao dịch**:
   - `DEMO_AI_MODE=false` + có key → huy hiệu xanh **AI Connected**.
   - `DEMO_AI_MODE=true` → huy hiệu hồng phách **Demo AI Mode**.
   - Thiếu key và không bật demo → **AI chưa cấu hình**.
3. Admin test (không lọc key):
   - Web: Đăng nhập `admin@vinoi.com` rồi `GET https://<domain>/admin/ai-test`
   - CLI: `railway run php artisan ai:test`

Ví dụ JSON thành công:

```json
{
  "configured": true,
  "provider": "gemini",
  "model": "gemini-2.0-flash",
  "connection": "ok",
  "http_status": 200
}
```

Nếu lỗi xác thực:

```json
{
  "configured": true,
  "provider": "gemini",
  "connection": "failed",
  "error_type": "authentication"
}
```

### 4. Lệnh hữu ích sau deploy

Trên Railway start command đã chạy migrate + seed danh mục. Local:

```bash
php artisan migrate --force
php artisan db:seed --class=CategorySeeder --force
php artisan config:clear
php artisan ai:test
php artisan test
```

**QUAN TRỌNG**:
- Không commit API key vào Git.
- Không chạy `config:cache` trên Railway nếu vừa đổi env mà chưa redeploy.
- Project sử dụng `config/ai.php` để đọc AI configuration (ưu tiên hơn `config/services.php`). API key phải được đặt đúng tên biến trong Railway để Laravel đọc được.

## Test

```bash
php artisan test
```

| Case | Input | Kỳ vọng |
| --- | --- | --- |
| 1 | Hôm nay ăn sáng 30k | 1 giao dịch 30.000 |
| 2 | Hôm nay ăn sáng 30k, uống cà phê 25k, đổ xăng 100k | 3 giao dịch |
| 3 | Hôm qua mua sách 150k | Ngày hôm qua |
| 4 | Nhận lương 10 triệu | income 10.000.000 |
| 5 | mua đồ 100k | Mua sắm hoặc Khác |
| 6 | rỗng | Không gọi AI |
| 7 | timeout | Báo lỗi, không dùng demo ngầm |
| 8 | amount âm | Server reject |
| 9 | User A sửa giao dịch User B | 403 |
| 10 | parse → preview → sửa → lưu | MySQL + history + dashboard |
