# Exchange Platform Repository

این مخزن فقط اجزای مشترک پلتفرم را نگهداری می‌کند؛ سورس سه Microservice در مخزن‌های مستقل قرار دارد.

## ساختار

| مسیر | مسئولیت |
|---|---|
| `api-gateway/` | Nginx/API Gateway و مسیریابی ورودی؛ مستقل از سرویس‌ها اجرا و Deploy می‌شود. |
| `infrastructure/` | Docker Compose توسعه محلی، PostgreSQL، Redis، RabbitMQ و اسکریپت‌های زیرساخت |
| `contracts/` | قراردادهای بین‌سرویسی، OpenAPI و Schema رویدادها |
| `docs/` | معماری کلان، ADRها، عملیات و مدل دسترسی |

## استقلال اجرایی

قرار گرفتن Gateway و Infrastructure در یک Git Repository به معنی اجرای یکپارچه آن‌ها نیست. Gateway، زیرساخت و هر Microservice همچنان Process/Container، تنظیمات، Health Check و چرخه انتشار مستقل خود را دارند. فایل Compose فعلی فقط زیرساخت محلی را اجرا می‌کند.

## تنظیم و اجرای زیرساخت محلی (Windows CMD)

در این مخزن:

```cmd
cd infrastructure
copy .env.example .env
notepad .env
```

مقادیر `.env.example` فقط برای توسعه محلی هستند. فایل `.env` در Git نادیده گرفته می‌شود.

راه‌اندازی:

```cmd
docker compose config --quiet
docker compose up -d
docker compose ps
```

ساخت نقش‌ها و دیتابیس‌های مستقل سرویس‌ها:

```cmd
scripts\bootstrap-databases.cmd
```

یا از مسیر `infrastructure`:

```cmd
scripts\bootstrap-databases.cmd
```

## دیتابیس‌ها

- `identity_db` با کاربر `identity_app`
- `wallet_db` با کاربر `wallet_app`
- `trading_db` با کاربر `trading_app`

هیچ سرویس اجازه دسترسی مستقیم به دیتابیس سرویس دیگر را ندارد.

## دسترسی توسعه‌دهندگان

توسعه‌دهنده هر سرویس باید فقط به مخزن سرویس مربوطه دسترسی Write داشته باشد. دسترسی Read به این مخزن مشترک می‌تواند برای مشاهده قراردادها و مستندات لازم داده شود؛ این دسترسی، سورس سرویس‌های Identity، Wallet یا Trading را افشا نمی‌کند، چون آن سورس‌ها در مخزن‌های جدا هستند. این مخزن نباید شامل Secretهای Production یا اطلاعات حساس عملیاتی باشد.

اگر در آینده لازم شد Gateway یا Infrastructure از دید محرمانگی هم از توسعه‌دهندگان سرویس‌ها پنهان بماند، می‌توان `contracts/` را به یک بسته/مخزن قابل‌انتشار مستقل تبدیل کرد؛ در نسخه فعلی عمداً تعداد مخزن‌ها را کم نگه می‌داریم.

## Laravel

هیچ پروژه Laravel در این بسته نصب نشده است. برنامه هر سرویس در فاز جداگانه داخل مسیر `application/` همان مخزن ساخته خواهد شد.
