# Exchange Platform

این مخزن یک **Monorepo با مرزهای اجرایی مستقل** است. هر Microservice چرخه اجرای Docker، تنظیمات محیطی، وابستگی‌ها و دیتابیس خودش را دارد؛ اجزای مشترک پلتفرم هم زیر platform/ نگهداری می‌شوند.

## ساختار مخزن

    exchange/
    ├── frontend/               # React application shared by all services
    ├── identity-service/       # Laravel Identity service
    ├── wallet-service/         # Laravel Wallet service
    ├── trading-service/        # Laravel Trading service
    ├── market-data-service/    # Laravel global market asset catalog
    └── platform/
        ├── api-gateway/         # Nginx API Gateway
        ├── infrastructure/     # PostgreSQL, Redis, RabbitMQ و شبکه مشترک
        ├── contracts/           # OpenAPI و Integration Event contracts
        └── docs/                # معماری، ADRها و Runbookها

## Frontend یکپارچه

پروژه React در پوشه مستقل `frontend/` قرار دارد و رابط کاربری مشترک Identity، Wallet، Trading و Market Data خواهد بود. این پروژه به کد داخلی Laravel وابسته نیست و درخواست‌ها را فقط از مسیر API Gateway ارسال می‌کند.

راهنمای اجرا و تنظیمات فرانت‌اند: [frontend/README.md](frontend/README.md)

## قراردادهای معماری و توسعه

- [قراردادهای توسعه Exchange](platform/docs/architecture/development-contracts.fa.md)
- [راهنمای معماری Service-Based](platform/docs/architecture/service-based-architecture.md)
- [مرزبندی سرویس‌ها](platform/docs/architecture/service-boundaries.md)
- [قراردادهای عمومی API و Event](platform/contracts/README.fa.md)

ساختار داخلی Laravel بر پایه Controller، Form Request، Service و Eloquent Model است. مرزهای مالکیت داده و ارتباط مستقل Microserviceها همچنان حفظ می‌شوند. پیش از اضافه‌کردن abstraction جدید، راهنمای معماری را بررسی کنید.

## مرزهای Docker در توسعه محلی

- هر Laravel service با Compose مستقل اجرا می‌شود.
- فرانت‌اند React مستقل است و در زمان توسعه با Vite اجرا می‌شود.
- درخواست‌های `/api/*` در Vite به API Gateway محلی روی پورت 8080 پروکسی می‌شوند.
- سورس هر سرویس به‌صورت Bind Mount از پوشه همان سرویس به /var/www/html متصل است؛ ویرایش فایل‌ها روی Host بلافاصله داخل کانتینر دیده می‌شود.
- پوشه vendor هر سرویس Named Volume مستقل دارد تا Composer dependencyهای Linux با فایل‌های Host ویندوز مخلوط نشوند.
- دیتابیس هر سرویس و داده‌های زیرساخت در Named Volumeهای مستقل نگهداری می‌شوند.
- زیرساخت مالک شبکه مشترک با نام ثابت exchange-network است. سرویس‌ها و Gateway آن را مصرف می‌کنند؛ شبکه را دستی نسازید.
- Bind Mount سورس فقط برای Development است. Production باید از Image ساخته‌شده و بدون Mount سورس استفاده کند.

## راه‌اندازی در Windows PowerShell

ابتدا فایل‌های محیطی را فقط در صورت نبودن ایجاد کنید:

    if (-not (Test-Path .\platform\infrastructure\.env)) {
        Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
    }
    if (-not (Test-Path .\identity-service\.env)) {
        Copy-Item .\identity-service\.env.example .\identity-service\.env
    }
    if (-not (Test-Path .\wallet-service\.env)) {
        Copy-Item .\wallet-service\.env.example .\wallet-service\.env
    }
    if (-not (Test-Path .\trading-service\.env)) {
        Copy-Item .\trading-service\.env.example .\trading-service\.env
    }

Platform را اجرا کنید:

    docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build

سپس هر سرویس Laravel را مستقل Build و اجرا کنید. برای نمونه:

    docker compose --env-file identity-service/.env -f identity-service/compose.yaml up -d --build
    docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml up -d --build
    docker compose --env-file trading-service/.env -f trading-service/compose.yaml up -d --build

برای Market Data، مقدار `CMC_API_KEY` را در فایل محلی `market-data-service/.env` وارد کن و پس از اجرای Platform و bootstrap دیتابیس‌ها، اجرا کن:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan key:generate
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250

Adminer برای مشاهده دیتابیس‌ها در http://127.0.0.1:8081 در دسترس است.

برای اجرای فرانت‌اند از ریشه مخزن:

    cd frontend
    Copy-Item .env.example .env
    npm install
    npm run dev

سپس `http://localhost:5173` را باز کنید. درخواست‌های API از طریق Gateway در `http://localhost:8080` ارسال می‌شوند.

## نگهداری داده‌ها

دستور docker compose down کانتینرها را متوقف و حذف می‌کند اما Named Volumeها را نگه می‌دارد. از down -v فقط زمانی استفاده کنید که عمداً قصد حذف داده دیتابیس و وابستگی‌ها را دارید.
