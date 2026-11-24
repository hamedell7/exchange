# Exchange Platform

این مخزن یک Monorepo با مرزهای اجرایی مستقل است. هر Microservice چرخه اجرای Docker، تنظیمات محیطی، وابستگی‌ها و دیتابیس خودش را دارد؛ اجزای مشترک پلتفرم هم زیر platform/ نگهداری می‌شوند.

## ساختار مخزن

    exchange/
    ├── frontend/               # React application shared by all services
    ├── identity-service/       # Laravel Identity service
    ├── wallet-service/         # Laravel Wallet service
    ├── trading-service/        # Laravel Trading service
    ├── market-data-service/    # Laravel global market asset catalog
    └── platform/
        ├── api-gateway/        # Nginx API Gateway
        ├── infrastructure/    # PostgreSQL, Redis, RabbitMQ, Adminer
        ├── contracts/          # OpenAPI و Integration Event contracts
        └── docs/               # معماری، ADRها و Runbookها

## Frontend یکپارچه

پروژه React در پوشه مستقل frontend/ قرار دارد و رابط کاربری مشترک سرویس‌ها خواهد بود. درخواست‌ها فقط از مسیر API Gateway ارسال می‌شوند.

راهنما: frontend/README.md

## قراردادهای معماری و توسعه

- platform/docs/architecture/development-contracts.fa.md
- platform/docs/architecture/service-based-architecture.md
- platform/docs/architecture/service-boundaries.md
- platform/contracts/README.fa.md

ساختار داخلی Laravel بر پایه Controller، Form Request، Service و Eloquent Model است. از اضافه‌کردن abstraction بدون نیاز واقعی پرهیز کنید.

## راه‌اندازی Market Data در Windows PowerShell

ابتدا فایل‌های محیطی را در صورت نبودن ایجاد کن:

    if (-not (Test-Path .\platform\infrastructure\.env)) {
        Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
    }
    if (-not (Test-Path .\market-data-service\.env)) {
        Copy-Item .\market-data-service\.env.example .\market-data-service\.env
    }

مقدار CMC_API_KEY را در market-data-service/.env قرار بده. سپس زیرساخت و دیتابیس‌های اختصاصی را آماده کن:

    docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build
    platform\infrastructure\scripts\bootstrap-databases.cmd

سرویس Market Data را بساز و Migrationها را اجرا کن:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan key:generate
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250

راهنمای کامل: market-data-service/README.md

## ابزارهای محلی

- API Gateway: http://localhost:8080
- React/Vite: http://localhost:5173
- Adminer: http://127.0.0.1:8081

Adminer فقط روی loopback منتشر می‌شود و برای مشاهده دیتابیس‌های مستقل سرویس‌ها از طریق PostgreSQL مرکزی استفاده می‌شود.

## مرزهای Docker و داده‌ها

- هر Laravel service با Compose مستقل اجرا می‌شود.
- زیرساخت مالک شبکه مشترک exchange-network است؛ آن را دستی ایجاد نکنید.
- هر سرویس دیتابیس و Role اختصاصی دارد.
- ارتباط سرویس‌ها از API یا Event است، نه Query مستقیم به دیتابیس سرویس دیگر.
- از docker compose down -v برای خاموش کردن معمولی زیرساخت استفاده نکنید.
- Secretهای Production نباید در Git ثبت شوند.
