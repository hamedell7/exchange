# Exchange Infrastructure (Development)

این بخش زیرساخت محلی توسعه را مدیریت می‌کند. فعلاً PostgreSQL، Redis و RabbitMQ را اجرا می‌کند؛ سرویس‌های Laravel و API Gateway در این Compose قرار ندارند.

## پیش‌نیاز

- Docker Desktop روشن باشد.
- Docker Compose v2 در دسترس باشد.

## راه‌اندازی تازه

از PowerShell یا CMD در ریشه مخزن:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml config --quiet
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml pull
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml ps
```

در `.env` فقط مقادیر Development قرار بده. این فایل در Git ثبت نمی‌شود.

این Compose شبکه مشترک را با نام صریح `exchange-network` ایجاد و مدیریت می‌کند. Compose سه سرویس Identity، Wallet و Trading این شبکه را به‌صورت `external` مصرف می‌کنند. بنابراین زیرساخت را قبل از سرویس‌های اپلیکیشن بالا بیاور و دیگر `docker network create exchange-network` اجرا نکن.

## اگر زیرساخت قبلی را اجرا کرده‌ای

نام پروژه Compose همچنان `exchange-platform` است تا Volumeهای فعلی و چرخه منابع قبلی حفظ شوند. تغییر نام شبکه از نام خودکار `exchange-platform_exchange-network` به `exchange-network` باعث اتصال مجدد کانتینرهای زیرساخت به شبکه مشترک با نام ثابت می‌شود؛ Volumeهای PostgreSQL، Redis و RabbitMQ تغییر نام نمی‌دهند.

برای حفظ داده‌ها هرگز `docker compose down -v` اجرا نکن.

اگر قبلاً شبکه `exchange-network` را دستی ساخته‌ای، پیش از اجرای Compose جدید وضعیت آن را با `docker network inspect exchange-network` بررسی کن. شبکه‌ای که دستی ساخته شده ممکن است با شبکه تحت مدیریت Compose تداخل داشته باشد؛ آن را بدون بررسی کانتینرهای متصل حذف نکن.

## Database bootstrap

پس از سالم شدن PostgreSQL، در CMD از ریشه مخزن اجرا کن:

```cmd
platform\infrastructure\scripts\bootstrap-databases.cmd
```

این اسکریپت سه Role و سه Database ایجاد/همگام می‌کند و دسترسی CONNECT عمومی را از هر Database می‌گیرد. نام کاربری و رمزها را از `platform/infrastructure/.env` می‌خواند.

## تست اتصال

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml exec postgres pg_isready -U exchange_admin -d postgres
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml exec redis sh -lc 'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli ping'
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml exec rabbitmq rabbitmq-diagnostics -q ping
```

انتظار Redis برابر `PONG` است.

## پورت‌ها

تمام پورت‌ها فقط روی `127.0.0.1` منتشر می‌شوند:

- PostgreSQL: `5432`
- Redis: `6379`
- RabbitMQ AMQP: `5672`
- RabbitMQ Management: `15672`

پنل RabbitMQ فقط برای توسعه محلی است: [http://localhost:15672](http://localhost:15672).

## نکات امنیتی و معماری

- رمزهای Development ثابت و ساده‌اند؛ از آن‌ها در Production استفاده نکن.
- کاربر `exchange_admin` فقط برای عملیات مدیریتی محلی است؛ اپلیکیشن‌ها نباید با آن وصل شوند.
- هر سرویس از Role و Database اختصاصی استفاده می‌کند.
- این Compose یک PostgreSQL Server محلی با Databaseهای جدا فراهم می‌کند؛ سرویس‌های Compose مستقل نیز می‌توانند دیتابیس اختصاصی خود را نگه دارند. توپولوژی Production، Backup، HA و سیاست دسترسی باید جداگانه تعیین شود.
- پیش از استفاده عملیاتی از RabbitMQ و Redis، vhost/user و ACL اختصاصی هر سرویس را تنظیم کن.
