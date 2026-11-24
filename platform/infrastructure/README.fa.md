# Exchange Platform (Development)

این Compose نقطه ورود واحد Platform برای توسعه محلی است و اجزای مشترک زیر را اجرا می‌کند:

- PostgreSQL مرکزی
- Redis
- RabbitMQ
- Adminer برای مشاهده دیتابیس‌ها
- API Gateway مبتنی بر Nginx

اپلیکیشن‌های Laravel شامل Identity، Wallet، Trading و Market Data همچنان Compose مستقل خودشان را دارند و فقط از شبکه مشترک exchange-network استفاده می‌کنند.

## راه‌اندازی

از ریشه مخزن در PowerShell:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml config --quiet
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml pull postgres redis rabbitmq adminer
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml ps
```

Adminer فقط روی 127.0.0.1:8081 منتشر شده است: http://127.0.0.1:8081. برای اتصال، System را PostgreSQL و Server را postgres انتخاب کن.

Gateway روی 127.0.0.1:8080 منتشر می‌شود.

پس از بالا آمدن PostgreSQL، دیتابیس‌ها و Roleهای اختصاصی سرویس‌ها را ایجاد کن (اجرای مجدد این دستور امن است):

```cmd
platform\infrastructure\scripts\bootstrap-databases.cmd
```

## Market Data Service

فایل محیطی را ایجاد و مقدار CMC_API_KEY را در آن تنظیم کن:

```powershell
if (-not (Test-Path .\market-data-service\.env)) {
    Copy-Item .\market-data-service\.env.example .\market-data-service\.env
}
```

سپس سرویس را اجرا کن:

```powershell
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan key:generate
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250
```

راهنمای کامل در market-data-service/README.md قرار دارد.

## پورت‌ها

- API Gateway: 8080
- PostgreSQL: 5432
- Redis: 6379
- RabbitMQ AMQP: 5672
- RabbitMQ Management: 15672
- Adminer: 8081

## نکات معماری و امنیت

- رمزهای نمونه فقط برای توسعه محلی‌اند.
- Adminer فقط برای توسعه محلی است و نباید مستقیماً در اینترنت منتشر شود.
- هر سرویس دیتابیس و Role اختصاصی دارد.
- ارتباط سرویس‌ها از API یا Event است، نه Query مستقیم به دیتابیس سرویس دیگر.
- Gateway فقط درخواست HTTP را مسیریابی می‌کند.
- کلید CMC فقط در market-data-service/.env محلی قرار می‌گیرد و نباید در Git ثبت شود.
- از docker compose down -v برای خاموش کردن معمولی زیرساخت استفاده نکن.
