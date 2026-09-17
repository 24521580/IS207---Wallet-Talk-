# Ví Nói

Personal finance web app for Vietnamese users. You type one natural sentence, AI splits it into structured transactions, you review them, then save.

## Product overview

Typical flow:

1. Login
2. Open Dashboard
3. Click **Thêm bằng AI**
4. Type: `Hôm nay ăn sáng 30k, đổ xăng 100k, chiều mua sách 150k`
5. Click **Phân tích bằng AI**
6. Review / edit / delete / add rows
7. **Xác nhận & Lưu**
8. History and Dashboard update

## Features

- Register, login, logout, session auth
- Natural-language multi-transaction parsing
- Human verification before save
- Transaction history, search, filters, pagination
- Edit / delete own transactions
- Dashboard cards and Chart.js charts
- Reports by week / month / quarter / custom range
- Admin category management
- Demo AI fallback when no API key is configured
- Loading, empty, validation, and error states

## Tech stack

- PHP 8.3
- Laravel 13 (MVC)
- MySQL / MariaDB
- Blade + Tailwind CSS v4
- Chart.js (bundle qua Vite, không dùng CDN nên demo offline vẫn chạy)
- Client-side JavaScript only
- Gemini or OpenAI (optional)

## Screens & routes

| Screen | Route | Controller |
| --- | --- | --- |
| Landing (guest) | `GET /` | closure → `welcome` view |
| Login / Register | `GET/POST /login`, `/register` | `Auth\LoginController`, `Auth\RegisterController` |
| Dashboard | `GET /dashboard` | `DashboardController` + `DashboardService` |
| Add with AI | `GET /giao-dich/them` | `TransactionController@create` |
| AI parsing (JSON) | `POST /giao-dich/ai` | `TransactionController@parse` (throttle 15/phút) |
| Confirm & save many | `POST /giao-dich/xac-nhan` | `TransactionController@confirm` |
| History + filters | `GET /giao-dich` | `TransactionController@index` |
| Edit / delete | `PUT/DELETE /giao-dich/{transaction}` | `TransactionController@update/destroy` |
| Reports | `GET /bao-cao` | `ReportController` + `ReportService` |
| Profile | `GET/PUT /ho-so` | `ProfileController` |
| Admin categories | `GET/POST/PUT/DELETE /admin/danh-muc` | `Admin\CategoryController` + `CategoryService` |

## Architecture

```
Browser
  → Laravel Routes
    → Controller
      → Service / Business Logic
        → Model
          → MySQL

AI path:
Browser → TransactionController → ExpenseParserService
  → Live AI API or DemoAiParser
    → JSON
      → AiResponseValidator
        → Preview UI
          → User confirm
            → TransactionService → MySQL
```

Responsibilities:

- Controllers: HTTP only
- Services: business rules and AI
- Form Requests: validation
- Policies / middleware: authorization
- Blade: UI

## Folder structure

```
app/Http/Controllers
app/Http/Requests
app/Http/Middleware
app/Models
app/Policies
app/Services
app/Services/Ai
database/migrations
database/seeders
resources/views
resources/js
routes/web.php
```

## Environment variables

Copy `.env.example` to `.env` and generate a key:

```bash
cp .env.example .env
php artisan key:generate
```

Important variables:

| Key | Meaning |
| --- | --- |
| `DB_*` | MySQL connection |
| `DEMO_AI_MODE` | `true` uses deterministic demo parser |
| `AI_PROVIDER` | `gemini` or `openai` |
| `GEMINI_API_KEY` / `OPENAI_API_KEY` | server-side only |
| `AI_TIMEOUT` | HTTP timeout in seconds |
| `APP_DEBUG` | keep `false` on demo machines so users never see stack traces |

Never put API keys in Blade, JavaScript, or git.

## Database setup

Create an empty database:

```sql
CREATE DATABASE vinoi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
php artisan migrate
php artisan db:seed
```

Use `php artisan migrate:fresh --seed` only on local demo data.

## Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| User | demo@vinoi.com | Demo123@ |
| Admin | admin@vinoi.com | Admin123@ |

The demo user is seeded with ~28 realistic transactions.

## How to run locally

Requirements: PHP 8.3+, Composer, Node.js, MySQL/MariaDB.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open http://localhost:8000

For Tailwind/JS hot reload: `npm run dev` in another terminal.

## How to enable Demo AI Mode

In `.env`:

```
DEMO_AI_MODE=true
```

The add-transaction screen shows **Demo AI Mode**. Results are deterministic for the assignment examples.

To use a live API:

```
DEMO_AI_MODE=false
AI_PROVIDER=gemini
GEMINI_API_KEY=your_key_here
```

If the live API fails, the app falls back to the demo parser and still labels the result as demo.

## Security notes

- CSRF on all form posts (including the fetch call from the AI screen)
- Passwords hashed by Laravel (`hashed` cast), không lưu plaintext
- Eloquent / bound parameters, no raw SQL concatenation
- Blade escaping by default; chart payloads xuất bằng `@js` (escape `<`, `>`, `&`, `'`, `"`)
- Auth middleware on app routes; `admin` middleware + policies cho khu quản trị
- Users can only access their own transactions (policy + `scopeOwnedBy`)
- API keys stay in `.env` (không truyền ra view/JS), `.env` bị `.gitignore`
- Rate limit 15 request/phút cho endpoint AI
- Validation message tiếng Việt ở `lang/vi/validation.php` (không lộ message tiếng Anh)
- Friendly error pages, no stack traces when `APP_DEBUG=false`; JSON error handler cho request AJAX

## Test scenarios

| Case | Input | Expected | Automated test |
| --- | --- | --- | --- |
| 1 | Hôm nay ăn sáng 30k | 1 expense, 30.000, today | `Unit\DemoAiParserTest::test_case_1_single_breakfast` |
| 2 | Hôm nay ăn sáng 30k, uống cà phê 25k, đổ xăng 100k | 3 transactions | `test_case_2_three_transactions` |
| 3 | Hôm qua mua sách 150k | date = yesterday | `test_case_3_yesterday` |
| 4 | Nhận lương 10 triệu | income 10.000.000 | `test_case_4_salary` |
| 5 | mua đồ 100k | Mua sắm or Khác, editable | `test_case_5_ambiguous_shopping` |
| 6 | empty | validation, no API call | `Feature\TransactionFlowTest::test_empty_ai_input_is_validated` |
| 7 | AI API lỗi/timeout | fallback demo + thông báo rõ, không lưu DB | `Feature\AiFallbackTest` (2 test) |
| 8 | amount âm / không hợp lệ | server reject | `test_negative_amount_is_rejected`, `Unit\AiResponseValidatorTest` |
| 9 | truy cập giao dịch của user khác | 403 | `test_user_cannot_edit_another_users_transaction`, `test_user_cannot_delete_another_users_transaction` |

Extra suites:

- `Feature\TransactionHistoryTest` – search, filter theo ngày/danh mục/loại, pagination, empty state, edit/delete, category type mismatch.
- `Feature\DashboardReportTest` – tổng thu/chi/số dư, chart data theo danh mục, preset tuần/tháng/custom, % danh mục, tenant isolation.
- `Feature\AdminCategoryTest` – thống kê admin, CRUD danh mục, chặn xóa danh mục đang dùng, chặn non-admin.
- `Feature\AuthenticationTest` – register + hash mật khẩu, mật khẩu yếu bị từ chối, login sai, logout, route bảo vệ, landing page.
- `Unit\AiResponseValidatorTest` – số tiền âm/không hợp lệ → unresolved, type sai, danh mục lạ → fallback, ngày sai → today, các định dạng tiền Việt (`100k`, `2,5 triệu`, `3tr`, `2tr5`, `15.000`, `150000`).

Run everything:

```bash
php artisan test
```



