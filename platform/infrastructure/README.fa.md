# Exchange Platform (Development)

این Compose نقطه ورود واحد Platform برای توسعه محلی است و اجزای مشترک زیر را اجرا می‌کند:

- PostgreSQL مرکزی
- Redis
- RabbitMQ
- Adminer برای مشاهده دیتابیس‌ها
- API Gateway مبتنی بر Nginx

چهار اپلیکیشن Laravel یعنی Identity، Wallet، Trading و Market Data همچنان Compose مستقل خودشان را دارند و فقط از شبکه مشترک `exchange-network` استفاده می‌کنند.

## پیش‌نیاز و راه‌اندازی

Docker Desktop و Docker Compose v2 باید فعال باشند. از ریشه مخزن در PowerShell اجرا کن:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}

docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml config --quiet
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml pull postgres redis rabbitmq adminer
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml ps
```

این یک دستور Compose برای تمام مؤلفه‌های Platform است؛ Gateway هم همراه زیرساخت Build و اجرا می‌شود و پورت محلی `8080` را در اختیار می‌گیرد. رابط Adminer نیز روی `http://127.0.0.1:8081` در دسترس است و فقط روی loopback منتشر می‌شود.

پس از بالا آمدن PostgreSQL، دیتابیس‌ها و Roleهای اختصاصی سرویس‌ها را ایجاد کن (اجرای مجدد این دستور امن است):

```cmd
platform\infrastructure\scripts\bootstrap-databases.cmd
```

این مرحله را پیش از اجرای اپلیکیشن‌ها انجام بده. هر سه اپلیکیشن از میزبان `postgres:5432` روی شبکه `exchange-network` استفاده می‌کنند.

## ترتیب اجرای اپلیکیشن‌ها

Compose مرکزی، PostgreSQL و شبکه را ایجاد می‌کند. سپس اپلیکیشن‌های مستقل را اجرا کن:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml up -d --build
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml up -d --build
docker compose --env-file trading-service/.env -f trading-service/compose.yaml up -d --build
```

Gateway از DNS داخلی Docker برای resolve کردن نام سرویس‌ها استفاده می‌کند؛ بنابراین لازم نیست کانتینرهای Laravel هنگام شروع Gateway از قبل روشن باشند. وقتی سرویس مقصد به شبکه متصل شد، درخواست‌های آن مسیر به مقصد هدایت می‌شوند.

## متغیرهای محیطی

فایل `platform/infrastructure/.env.example` نمونه تنظیمات Platform است. آن را به `.env` کپی کن و مقادیر توسعه را در همان فایل نگه دار.

متغیرهای Gateway:

- `API_GATEWAY_PORT`: پورت میزبان؛ پیش‌فرض `8080`
- `IDENTITY_SERVICE_HOST` و `IDENTITY_SERVICE_PORT`
- `WALLET_SERVICE_HOST` و `WALLET_SERVICE_PORT`
- `TRADING_SERVICE_HOST` و `TRADING_SERVICE_PORT`
- `MARKET_DATA_SERVICE_HOST` و `MARKET_DATA_SERVICE_PORT`

مقادیر Host پیش‌فرض با نام کانتینرهای سرویس‌ها هماهنگ هستند. مقادیر DB Roleها و رمزهایشان نیز باید با `DB_USERNAME`، `DB_DATABASE` و `DB_PASSWORD` در فایل `.env` هر سرویس یکسان باشند.

## Market Data Service

سرویس Market Data کاتالوگ ارزهای برتر بازار جهانی را نگهداری می‌کند. Compose و دیتابیس آن مستقل است.

اگر فایل محیطی این سرویس را نداری، از نمونه بساز و مقدار کلید CMC را فقط در فایل محلی وارد کن:

```powershell
if (-not (Test-Path .\\market-data-service\\.env)) {
    Copy-Item .\\market-data-service\\.env.example .\\market-data-service\\.env
}
```

در فایل `market-data-service/.env` مقدار `CMC_API_KEY` را قرار بده. سپس زیرساخت و دیتابیس‌های سرویس را آماده کن و خود سرویس را اجرا کن:

```powershell
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan key:generate
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250
```

فرمان `market:sync-assets` همگام‌سازی کاتالوگ را فقط هنگام اجرای دستی انجام می‌دهد و هنوز زمان‌بندی خودکار یا WebSocket ندارد. راهنمای سرویس: [market-data-service/README.md](../../market-data-service/README.md).

اگر `platform/infrastructure/.env` از قبل وجود دارد، سه متغیر `MARKET_DATA_DB_USERNAME`، `MARKET_DATA_DB_DATABASE` و `MARKET_DATA_DB_PASSWORD` اختیاری‌اند؛ اسکریپت bootstrap در صورت نبود آن‌ها از مقادیر محلی نمونه استفاده می‌کند. این مقادیر باید با `DB_USERNAME`، `DB_DATABASE` و `DB_PASSWORD` در فایل محلی Market Data هماهنگ باشند.

## پورت‌ها

تمام پورت‌ها فقط روی `127.0.0.1` منتشر می‌شوند:

- API Gateway: `8080` (قابل تغییر با `API_GATEWAY_PORT`)
- Adminer: `8081` (فقط روی `127.0.0.1`)
- PostgreSQL: `5432`
- Redis: `6379`
- RabbitMQ AMQP: `5672`
- RabbitMQ Management: `15672`

سلامت Gateway:

```powershell
Invoke-RestMethod http://127.0.0.1:8080/healthz
```

این Endpoint فقط اجرای Nginx را بررسی می‌کند و سلامت سرویس‌های مقصد را تضمین نمی‌کند.

## اگر زیرساخت قبلی را اجرا کرده‌ای

نام پروژه Compose همچنان `exchange-platform` است تا Volumeهای فعلی و چرخه منابع قبلی حفظ شوند. نام شبکه نیز `exchange-network` است و سه Compose سرویس‌های Laravel آن را به‌صورت `external` مصرف می‌کنند. زیرساخت را پیش از اپلیکیشن‌ها اجرا کن و دیگر `docker network create exchange-network` اجرا نکن.

در نسخه قبلی، Gateway به‌صورت دستی با `docker run` اجرا می‌شد. اگر کانتینر قدیمی با نام `exchange-api-gateway` هنوز وجود دارد، پیش از اجرای Compose جدید یک‌بار آن را حذف کن:

```powershell
docker rm -f exchange-api-gateway
```

این دستور فقط کانتینر قدیمی Gateway را حذف می‌کند و به Volumeهای داده دست نمی‌زند.

اگر قبلاً شبکه `exchange-network` را دستی ساخته‌ای، پیش از اجرای Compose وضعیت آن را با `docker network inspect exchange-network` بررسی کن. شبکه‌ای که دستی ساخته شده ممکن است با شبکه تحت مدیریت Compose تداخل داشته باشد؛ آن را بدون بررسی کانتینرهای متصل حذف نکن.

## انتقال از Composeهای قدیمی سرویس‌ها

در نسخه قبلی هر سرویس کانتینر PostgreSQL خودش را داشت. برای اینکه کانتینرهای دیتابیس قدیمی به‌عنوان orphan روشن نمانند، بعد از دریافت تغییرات، برای هر سرویس یک‌بار دستور متناظر را اجرا کن:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml down --remove-orphans
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml down --remove-orphans
docker compose --env-file trading-service/.env -f trading-service/compose.yaml down --remove-orphans
```

این دستورات کانتینرهای قبلی را متوقف و حذف می‌کنند، اما Volumeهای PostgreSQL قدیمی را حذف نمی‌کنند. داده‌های آن Volumeها خودکار به PostgreSQL مرکزی منتقل نمی‌شوند؛ اگر داده‌ای لازم داری، قبل از کنار گذاشتن دیتابیس‌های قبلی از آن‌ها Backup بگیر. سپس زیرساخت مرکزی را بالا بیاور، bootstrap را اجرا کن و اپلیکیشن‌ها را دوباره اجرا کن. از `down -v` استفاده نکن.

## تست اتصال زیرساخت

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml exec postgres pg_isready -U exchange_admin -d postgres
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml exec redis sh -lc 'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli ping'
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml exec rabbitmq rabbitmq-diagnostics -q ping
```

انتظار Redis برابر `PONG` است.

## نکات امنیتی و معماری

- رمزهای Development ثابت و ساده‌اند؛ از آن‌ها در Production استفاده نکن.
- Adminer ابزار مدیریتی توسعه است و نباید در Production مستقیماً در اینترنت منتشر شود.
- کلید `CMC_API_KEY` فقط در فایل محلی `market-data-service/.env` قرار می‌گیرد و نباید در Git ثبت شود.
- کاربر `exchange_admin` فقط برای عملیات مدیریتی محلی است؛ اپلیکیشن‌ها نباید با آن وصل شوند.
- هر سرویس از Role و Database اختصاصی استفاده می‌کند.
- ارتباط داده‌ای بین سرویس‌ها از طریق API یا Event است؛ نه Query مستقیم به دیتابیس سرویس دیگر.
- این Compose تنها PostgreSQL محلی پروژه را اجرا می‌کند. Identity، Wallet و Trading هرکدام Database و Role اختصاصی خود را روی همین Server دارند و هیچ‌کدام کانتینر PostgreSQL جداگانه‌ای اجرا نمی‌کنند.
- Gateway فقط درخواست HTTP را مسیریابی می‌کند؛ مالک منطق تجاری یا دیتابیس سرویس‌ها نیست.
- Secretهای Production نباید در Git ثبت شوند.
- Volumeهای قدیمی PostgreSQL سرویس‌ها خودکار حذف یا منتقل نمی‌شوند. برای حفظ داده‌ها Backup بگیر و هرگز برای این جابه‌جایی `down -v` اجرا نکن.
- توپولوژی Production، Backup، HA و سیاست دسترسی باید جداگانه تعیین شود.
- پیش از استفاده عملیاتی از RabbitMQ و Redis، vhost/user و ACL اختصاصی هر سرویس را تنظیم کن.
