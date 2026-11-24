# Platform Components

این پوشه اجزای مشترک پلتفرم Exchange را در همین Monorepo نگهداری می‌کند. سورس Microserviceها در پوشه‌های هم‌سطح `identity-service/`، `wallet-service/` و `trading-service/` قرار دارد.

## ساختار

| مسیر | مسئولیت |
|---|---|
| `api-gateway/` | سورس Nginx/API Gateway و تنظیمات مسیریابی ورودی |
| `infrastructure/` | Compose مرکزی توسعه محلی برای PostgreSQL، Redis، RabbitMQ، API Gateway و شبکه مشترک |
| `contracts/` | قراردادهای بین‌سرویسی، OpenAPI و Schema رویدادها |
| `docs/` | معماری کلان، ADRها، عملیات و مدل دسترسی |

## استقلال اجرایی

قرار گرفتن این اجزا و سه Microservice در یک Git Repository به معنی ادغام منطق یا چرخه عمر آن‌ها نیست. Compose مرکزی، سرویس‌های مشترک Platform و کانتینر مستقل Gateway را راه‌اندازی می‌کند؛ هر Microservice همچنان Compose، تنظیمات، Health Check و چرخه انتشار خودش را دارد.

Gateway و هر سه Microservice فقط از طریق شبکه خصوصی Docker با نام `exchange-network` به هم دسترسی دارند. API Gateway به دیتابیس وصل نمی‌شود.

## تنظیم و اجرای Platform

از ریشه Monorepo در PowerShell:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}

docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml config --quiet
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build
```

با همین یک دستور، PostgreSQL، Redis، RabbitMQ و Gateway اجرا می‌شوند. ورودی HTTP برنامه‌ها از `http://localhost:8080` است.

برای ایجاد Roleها و Databaseهای توسعه در PostgreSQL زیرساخت:

```cmd
platform\infrastructure\scripts\bootstrap-databases.cmd
```

سپس Compose هر Microservice را مستقل اجرا کن.

## دیتابیس و امنیت

- هر سرویس باید با Credential مختص خودش و فقط به دیتابیس مالک خود متصل شود.
- ارتباط داده‌ای بین سرویس‌ها از طریق API یا Event است؛ نه Query مستقیم به دیتابیس سرویس دیگر.
- رمزهای نمونه فقط برای توسعه محلی‌اند و نباید در Production استفاده شوند.
- Secretهای Production نباید در Git ثبت شوند.
