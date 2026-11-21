# Exchange Platform Workspace (4 Repositories)

این پوشه فقط Workspace محلی است و خودش Git Repository نیست. چهار مخزن مستقل درون آن قرار دارند.

## ساختار نهایی

```text
exchange-platform-workspace/
├── exchange-platform/             # Gateway + Infrastructure + Contracts + Docs
├── exchange-identity-service/     # Microservice مستقل Identity
├── exchange-wallet-service/       # Microservice مستقل Wallet
├── exchange-trading-service/      # Microservice مستقل Trading
└── scripts/                       # ابزارهای مدیریت Workspace (خارج از Git)
```

## ایجاد Git Repositoryهای محلی

از CMD در ریشه Workspace اجرا کنید:

```cmd
scripts\init-repositories.cmd
scripts\status-repositories.cmd
```

این دستورات برای هرکدام از چهار پوشه `git init` جداگانه اجرا می‌کنند؛ روی ریشه Workspace دستور `git init` اجرا نکنید.

## راه‌اندازی زیرساخت در صورت وجود محیط قبلی

اگر PostgreSQL/Redis/RabbitMQ قبلاً از مسیر `C:\xampp\htdocs\exchange-platform` اجرا شده‌اند، فایل `.env` را به مسیر جدید زیر کپی کنید تا رمز و Volumeهای فعلی حفظ شوند:

```cmd
copy C:\xampp\htdocs\exchange-platform\.env exchange-platform\infrastructure\.env
```

سپس از داخل مخزن Platform:

```cmd
cd exchange-platform\infrastructure
docker compose config --quiet
docker compose ps
```

نام پروژه Compose همچنان `exchange-platform` است تا با استک قبلی هم‌نام بماند. برای حفظ داده‌ها هرگز `docker compose down -v` اجرا نکنید.

اگر زیرساخت قبلی ندارید، از `exchange-platform\infrastructure\.env.example` یک `.env` بسازید و فقط برای Development مقادیر مناسب قرار دهید.

## ساخت دیتابیس‌های مستقل

بعد از اطمینان از سالم بودن PostgreSQL، از مسیر `exchange-platform\infrastructure` اجرا کنید:

```cmd
scripts\bootstrap-databases.cmd
```

این اسکریپت سه Role و سه Database مستقل می‌سازد: Identity، Wallet و Trading.

## Laravel

هیچ Laravel application در این Workspace نصب نشده است. هر برنامه بعداً داخل مسیر `application/` در مخزن سرویس خودش ساخته می‌شود.

## دسترسی تیم

- هر توسعه‌دهنده سرویس فقط به مخزن سرویس خودش Write می‌گیرد.
- در صورت نیاز به قراردادها و مستندات، به `exchange-platform` فقط Read داده می‌شود.
- دسترسی به Infrastructure/Production Secrets و منابع Runtime جداگانه کنترل می‌شود.
