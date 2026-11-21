# Exchange Platform

این مخزن یک **Monorepo با مرزهای اجرایی مستقل** است. هر Microservice چرخه اجرای Docker، تنظیمات محیطی، وابستگی‌ها و دیتابیس خودش را دارد؛ اجزای مشترک پلتفرم هم زیر `platform/` نگهداری می‌شوند.

## ساختار مخزن

```text
exchange/
├── identity-service/       # Laravel Identity service
├── wallet-service/         # Laravel Wallet service
├── trading-service/        # Laravel Trading service
└── platform/
    ├── api-gateway/         # Nginx API Gateway
    ├── infrastructure/     # PostgreSQL, Redis, RabbitMQ و شبکه مشترک
    ├── contracts/           # OpenAPI و Integration Event contracts
    └── docs/                # معماری، ADRها و Runbookها
```

## مرزهای Docker در توسعه محلی

- هر Laravel service با Compose مستقل اجرا می‌شود.
- سورس هر سرویس به‌صورت Bind Mount از پوشه همان سرویس به `/var/www/html` متصل است؛ ویرایش فایل‌ها روی Host بلافاصله داخل کانتینر دیده می‌شود.
- پوشه `vendor` هر سرویس Named Volume مستقل دارد تا Composer dependencyهای Linux با فایل‌های Host ویندوز مخلوط نشوند.
- دیتابیس هر سرویس و داده‌های زیرساخت در Named Volumeهای مستقل نگهداری می‌شوند.
- زیرساخت مالک شبکه مشترک با نام ثابت `exchange-network` است. سرویس‌ها و Gateway آن را مصرف می‌کنند؛ شبکه را دستی نسازید.
- Bind Mount سورس فقط برای Development است. Production باید از Image ساخته‌شده و بدون Mount سورس استفاده کند.

## راه‌اندازی در Windows PowerShell

ابتدا فایل‌های محیطی را فقط در صورت نبودن ایجاد کنید:

```powershell
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
```

زیرساخت را اجرا کنید:

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
```

سپس هر سرویس را مستقل Build و اجرا کنید. برای نمونه:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml up -d --build
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml up -d --build
docker compose --env-file trading-service/.env -f trading-service/compose.yaml up -d --build
```

برای ساخت APP_KEY هر سرویس، دستور زیر را با مسیر Compose و env همان سرویس اجرا کنید:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan key:generate
```

همین دستور برای Wallet و Trading نیز با مسیر مربوط به خودشان اجرا می‌شود. سپس Migration همان سرویس را اجرا کنید. راهنمای کامل هر سرویس در README همان پوشه قرار دارد.

## نگهداری داده‌ها

دستور `docker compose down` کانتینرها را متوقف و حذف می‌کند اما Named Volumeها را نگه می‌دارد. از `down -v` فقط زمانی استفاده کنید که عمداً قصد حذف داده دیتابیس و وابستگی‌ها را دارید.
