# ADR-0001: Four-Repository Workspace

- Status: Accepted
- Date: 2026-11-09

## Context

مجوزهای معمول Git در سطح Repository اعمال می‌شوند، نه در سطح پوشه. بنابراین برای محدود کردن دسترسی توسعه‌دهندگان به سورس هر Microservice، سرویس‌های دامنه‌ای باید مخزن مستقل داشته باشند. در عین حال، جدا کردن هر جزء مشترک به مخزن مستقل در شروع پروژه سربار نگهداری غیرضروری ایجاد می‌کند.

## Decision

چهار Git Repository خواهیم داشت:

1. `exchange-platform`: شامل API Gateway، Infrastructure، Contracts و مستندات مشترک.
2. `exchange-identity-service`: سورس و دارایی‌های اختصاصی Identity.
3. `exchange-wallet-service`: سورس و دارایی‌های اختصاصی Wallet.
4. `exchange-trading-service`: سورس و دارایی‌های اختصاصی Trading.

پوشه `exchange-platform-workspace` صرفاً Workspace محلی است و خودش Git Repository نیست.

## Consequences

- هر Microservice تاریخچه Git، CI، تست، Build و Deployment مستقل دارد.
- توسعه‌دهندگان هر سرویس به‌صورت پیش‌فرض فقط به مخزن سرویس خود دسترسی Write می‌گیرند.
- در صورت نیاز، دسترسی Read به `exchange-platform` برای مشاهده قراردادها و مستندات داده می‌شود؛ این دسترسی همچنین سورس Gateway و Infrastructure مشترک را قابل مشاهده می‌کند.
- سورس هیچ Microservice دیگر در `exchange-platform` قرار نمی‌گیرد.
- Gateway و Infrastructure با وجود قرار گرفتن در یک Repository همچنان مستقل اجرا و Deploy می‌شوند.
- اگر در آینده محرمانگی Gateway یا Infrastructure نسبت به توسعه‌دهندگان سرویس‌ها لازم شد، Contracts را می‌توان به بسته نسخه‌بندی‌شده یا مخزن مجزا منتقل کرد.
