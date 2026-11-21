# Database isolation in local development

در این محیط یک PostgreSQL Server برای راحتی توسعه اجرا می‌شود؛ جداسازی در سطح Database و Role است، نه در سطح Instance.

اسکریپت `scripts/bootstrap-databases.cmd`:

- سه Role مستقل می‌سازد یا تنظیم می‌کند.
- هر Role را `NOSUPERUSER`, `NOCREATEDB`, `NOCREATEROLE` و `NOREPLICATION` نگه می‌دارد.
- هر Database را به Role متناظر واگذار می‌کند.
- دسترسی `CONNECT` را از `PUBLIC` می‌گیرد و فقط به Role متناظر می‌دهد.

این مجوزها مانع اتصال اتفاقی Role یک سرویس به Database سرویس دیگر می‌شوند. کاربر مدیریتی PostgreSQL همچنان اختیار مدیریتی دارد و نباید در تنظیمات اپلیکیشن استفاده شود.

در Production ممکن است برای سطح ایزوله‌سازی، پشتیبان‌گیری، دسترس‌پذیری و محدودسازی منابع، PostgreSQL instanceهای جداگانه لازم باشند.
