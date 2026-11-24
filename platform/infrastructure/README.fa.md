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

پس از بالا آمدن PostgreSQL، دیتابیس‌ها و Roleهای اختصاصی سرویس‌ها را ایجاد کن (اجرای مجدد این دستور امن است):

```cmd
platform\infrastructure\scripts\bootstrap-databases.cmd
```

این مرحله را قبل از اجرای اپلیکیشن‌ها انجام بده. هر سه اپلیکیشن از میزبان `postgres:5432` روی شبکه `exchange-network` استفاده می‌کنند.

در `.env` فقط مقادیر Development قرار بده. این فایل در Git ثبت نمی‌شود.

این Compose شبکه مشترک را با نام صریح `exchange-network` ایجاد و مدیریت می‌کند. Compose سه سرویس Identity، Wallet و Trading این شبکه را به‌صورت `external` مصرف می‌کنند. بنابراین زیرساخت را قبل از سرویس‌های اپلیکیشن بالا بیاور و دیگر `docker network create exchange-network` اجرا نکن.

## اگر زیرساخت قبلی را اجرا کرده‌ای

نام پروژه Compose همچنان `exchange-platform` است تا Volumeهای فعلی و چرخه منابع قبلی حفظ شوند. تغییر نام شبکه از نام خودکار `exchange-platform_exchange-network` به `exchange-network` باعث اتصال مجدد کانتینرهای زیرساخت به شبکه مشترک با نام ثابت می‌شود؛ Volumeهای PostgreSQL، Redis و RabbitMQ تغییر نام نمی‌دهند.

برای حفظ داده‌ها هرگز `docker compose down -v` اجرا نکن.

اگر قبلاً شبکه `exchange-network` را دستی ساخته‌ای، پیش از اجرای Compose جدید وضعیت آن را با `docker network inspect exchange-network` بررسی کن. شبکه‌ای که دستی ساخته شده ممکن است با شبکه تحت مدیریت Compose تداخل داشته باشد؛ آن را بدون بررسی کانتینرهای متصل حذف نکن.

## انتقال از Composeهای قبلی سرویس‌ها

در نسخه قبلی هر سرویس کانتینر PostgreSQL خودش را داشت. برای اینکه کانتینرهای دیتابیس قدیمی به‌عنوان orphan روشن نمانند، بعد از دریافت تغییرات، برای هر سرویس یک‌بار دستور متناظر را اجرا کن:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml down --remove-orphans
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml down --remove-orphans
docker compose --env-file trading-service/.env -f trading-service/compose.yaml down --remove-orphans
```

این دستورات کانتینرهای قبلی را متوقف و حذف می‌کنند، اما Volumeهای PostgreSQL قدیمی را حذف نمی‌کنند. داده‌های آن Volumeها خودکار به PostgreSQL مرکزی منتقل نمی‌شوند؛ اگر داده‌ای لازم داری، قبل از کنار گذاشتن دیتابیس‌های قبلی از آن‌ها Backup بگیر. سپس زیرساخت مرکزی را بالا بیاور، bootstrap را اجرا کن و اپلیکیشن‌ها را دوباره اجرا کن. از `down -v` استفاده نکن.

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
- این Compose تنها PostgreSQL محلی پروژه را اجرا می‌کند. Identity، Wallet و Trading هرکدام Database و Role اختصاصی خود را روی همین Server دارند و هیچ‌کدام کانتینر PostgreSQL جداگانه‌ای اجرا نمی‌کنند.
- رمز هر Role در `platform/infrastructure/.env` باید با `DB_PASSWORD` متناظر در فایل `.env` همان سرویس یکسان باشد؛ مقادیر پیش‌فرض فایل‌های نمونه هماهنگ‌اند.
- اگر قبلاً PostgreSQLهای محلی سرویس‌ها را اجرا کرده‌ای، Volumeهای قدیمی خودکار حذف نمی‌شوند و داده‌هایشان نیز خودکار منتقل نمی‌شود. برای حفظ داده‌ها قبل از کنار گذاشتن آن‌ها Backup بگیر؛ هرگز برای این جابه‌جایی `down -v` اجرا نکن.
- توپولوژی Production، Backup، HA و سیاست دسترسی باید جداگانه تعیین شود.
- پیش از استفاده عملیاتی از RabbitMQ و Redis، vhost/user و ACL اختصاصی هر سرویس را تنظیم کن.
