# Exchange Infrastructure (Development)

این Repository زیرساخت محلی توسعه را مدیریت می‌کند. فعلاً فقط PostgreSQL، Redis و RabbitMQ اجرا می‌شوند؛ هیچ Laravel Service یا Gateway در Compose نیست.

## پیش‌نیاز

- Docker Desktop روشن باشد.
- Docker Compose v2 در دسترس باشد.

## راه‌اندازی تازه

از CMD در همین پوشه:

```cmd
copy .env.example .env
notepad .env
docker compose config --quiet
docker compose pull
docker compose up -d
docker compose ps
```

در `.env` فقط مقادیر Development قرار بده. این فایل در Git ثبت نمی‌شود.

## اگر زیرساخت قبلی را اجرا کرده‌ای

در صورتی که قبلاً همین پروژه را با Compose و نام `exchange-platform` اجرا کرده‌ای، برای حفظ همان Volumeها، فایل `.env` قبلی را به این پوشه کپی کن و از همان مقادیر استفاده کن. قبل از اجرای Compose جدید، تنظیمات و نام پروژه را بررسی کن. این Compose نام پروژه `exchange-platform` و نام سرویس‌های قبلی را حفظ می‌کند تا بتواند با منابع قبلی کار کند.

برای حفظ داده‌ها هرگز `docker compose down -v` اجرا نکن.

## Database bootstrap

پس از بالا آمدن PostgreSQL، در CMD از همین پوشه اجرا کن:

```cmd
scripts\bootstrap-databases.cmd
```

این اسکریپت سه Role و سه Database ایجاد/همگام می‌کند و دسترسی CONNECT عمومی را از هر Database می‌گیرد. نام کاربری و رمزها را از `.env` می‌خواند.

سپس برای فهرست Databaseها:

```cmd
docker compose exec postgres psql -U exchange_admin -d postgres -c "SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname;"
```

## تست اتصال

```cmd
docker compose exec postgres pg_isready -U exchange_admin -d postgres
docker compose exec redis sh -lc "REDISCLI_AUTH=\"$REDIS_PASSWORD\" redis-cli ping"
docker compose exec rabbitmq rabbitmq-diagnostics -q ping
```

انتظار Redis برابر `PONG` است.

## پورت‌ها

تمام پورت‌ها فقط روی `127.0.0.1` منتشر می‌شوند:

- PostgreSQL: `5432`
- Redis: `6379`
- RabbitMQ AMQP: `5672`
- RabbitMQ Management: `15672`

پنل RabbitMQ فقط برای توسعه محلی است: `http://localhost:15672`.

## نکات امنیتی و معماری

- رمزهای Development ثابت و ساده‌اند؛ از آن‌ها در Production استفاده نکن.
- کاربر `exchange_admin` فقط برای عملیات مدیریتی محلی است؛ اپلیکیشن‌ها نباید با آن وصل شوند.
- هر سرویس از Role و Database اختصاصی استفاده می‌کند.
- این Compose فعلاً یک PostgreSQL Server مشترک با Databaseهای جدا دارد؛ در Production درباره Instanceهای مستقل، Backup، HA و سیاست‌های دسترسی تصمیم جداگانه می‌گیریم.
- پیش از اتصال سرویس‌ها، RabbitMQ vhost/user و Redis ACL اختصاصی هر سرویس را اضافه می‌کنیم.
