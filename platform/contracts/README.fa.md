# Exchange Contracts

این پوشه مرجع قراردادهای بیرونی بین سرویس‌هاست؛ پیاده‌سازی داخلی سرویس‌ها اینجا قرار نمی‌گیرد.

## ساختار

- `openapi/identity/`: قرارداد REST سرویس Identity
- `openapi/wallet/`: قرارداد REST سرویس Wallet
- `openapi/trading/`: قرارداد REST سرویس Trading
- `events/`: Schema پیام‌ها و Integration Eventهای RabbitMQ

## قرارداد موجود

- [Identity API v1](openapi/identity/identity-v1.yaml): شامل ثبت‌نام، شکل خطاها، تعارض ایمیل و محدودیت نرخ فعلی.

## قواعد تغییر

1. OpenAPI مرجع رسمی درخواست، پاسخ، Status Code و Schema عمومی HTTP است. تغییر Endpoint باید همزمان در کد و OpenAPI ثبت شود.
2. تغییر ناسازگار در قرارداد منتشرشده بدون نسخه جدید یا برنامه سازگاری مجاز نیست.
3. از ثبت Secret، داده شخصی واقعی، Token یا اطلاعات حساس در قراردادها خودداری کنید.
4. Eventها دارای `event_id`، `event_type`، `schema_version`، `occurred_at` و `correlation_id` هستند.
5. مصرف‌کننده Event باید در برابر تحویل تکراری Idempotent باشد.
6. Event وابسته به تغییر DB فقط پس از طراحی Transactional Outbox قابل اتکا منتشر می‌شود.
7. قرارداد نباید Endpoint یا رفتار فرضی برای قابلیت‌هایی که هنوز طراحی نشده‌اند تعریف کند.

برای قواعد معماری و فرمت پیاده‌سازی به [قراردادهای توسعه Exchange](../docs/architecture/development-contracts.fa.md) مراجعه کنید.
