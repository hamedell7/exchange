# ADR-0003: Local Development Infrastructure

- Status: Accepted
- Date: 2026-11-09

## Decision

Docker Compose فقط زیرساخت محلی PostgreSQL، Redis و RabbitMQ را اجرا می‌کند. سرویس‌های Laravel و Gateway در مرحله‌های بعد و از Repositoryهای مستقل اضافه می‌شوند.

برای Development از `.env` محلی استفاده می‌شود. این فایل نباید وارد Git شود و مقادیر آن برای Production معتبر نیستند.

## Follow-up

پیش از اتصال اپلیکیشن‌ها، برای RabbitMQ vhost/user و permission مستقل و برای Redis ACL مستقل هر سرویس تعریف می‌شود. یک Password مشترک توسعه‌ای به‌تنهایی مرزبندی امنیتی سرویس‌ها را تأمین نمی‌کند.
