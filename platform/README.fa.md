# Platform Components

این پوشه اجزای مشترک پلتفرم Exchange را در همین Monorepo نگهداری می‌کند. سورس Microserviceها در پوشه‌های هم‌سطح `identity-service/`, `wallet-service/` و `trading-service/` قرار دارد.

## ساختار

| مسیر | مسئولیت |
|---|---|
| `api-gateway/` | Nginx/API Gateway و مسیریابی ورودی؛ مستقل از سرویس‌ها اجرا و Deploy می‌شود. |
| `infrastructure/` | Docker Compose توسعه محلی، PostgreSQL، Redis، RabbitMQ و شبکه مشترک |
| `contracts/` | قراردادهای بین‌سرویسی، OpenAPI و Schema رویدادها |
| `docs/` | معماری کلان، ADRها، عملیات و مدل دسترسی |

## استقلال اجرایی

قرار گرفتن این اجزا و سه Microservice در یک Git Repository به معنی اجرای یکپارچه آن‌ها نیست. Gateway، زیرساخت و هر Microservice همچنان Process/Container، تنظیمات، Health Check و چرخه انتشار مستقل خود را دارند. Compose زیرساخت فقط وابستگی‌های مشترک پلتفرم را اجرا می‌کند.

## تنظیم و اجرای زیرساخت محلی

از ریشه Monorepo در PowerShell:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml config --quiet
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
```

این Compose شبکه مشترک `exchange-network` را با نام ثابت ایجاد می‌کند. سه سرویس و Gateway به آن متصل می‌شوند؛ آن را به‌صورت دستی ایجاد نکنید.

برای ایجاد Roleها و Databaseهای توسعه در PostgreSQL زیرساخت:

```cmd
platform\infrastructure\scripts\bootstrap-databases.cmd
```

## دیتابیس و امنیت

- هر سرویس باید با Credential مختص خودش و فقط به دیتابیس مالک خود متصل شود.
- ارتباط داده‌ای بین سرویس‌ها از طریق API یا Event است؛ نه Query مستقیم به دیتابیس سرویس دیگر.
- رمزهای نمونه فقط برای توسعه محلی‌اند و نباید در Production استفاده شوند.
- Secretهای Production نباید در Git ثبت شوند.
