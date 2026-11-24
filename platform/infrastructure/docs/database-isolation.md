# Database isolation in local development

در این محیط فقط یک PostgreSQL Server برای توسعه اجرا می‌شود و هر سه سرویس Identity، Wallet و Trading از طریق شبکه مشترک `exchange-network` به آن متصل می‌شوند. جداسازی در سطح Database و Role است، نه در سطح Instance.

اسکریپت `scripts/bootstrap-databases.cmd`:

- سه Role مستقل می‌سازد یا تنظیم می‌کند.
- هر Role را `NOSUPERUSER`, `NOCREATEDB`, `NOCREATEROLE` و `NOREPLICATION` نگه می‌دارد.
- هر Database را به Role متناظر واگذار می‌کند.
- دسترسی `CONNECT` را از `PUBLIC` می‌گیرد و فقط به Role متناظر می‌دهد.

این مجوزها مانع اتصال اتفاقی Role یک سرویس به Database سرویس دیگر می‌شوند. کاربر مدیریتی PostgreSQL همچنان اختیار مدیریتی دارد و نباید در تنظیمات اپلیکیشن استفاده شود.

در Production ممکن است برای سطح ایزوله‌سازی، پشتیبان‌گیری، دسترس‌پذیری و محدودسازی منابع، PostgreSQL instanceهای جداگانه لازم باشند.


Compose هر سرویس Laravel کانتینر دیتابیس محلی ندارد و از `DB_HOST=postgres` استفاده می‌کند. ابتدا زیرساخت مرکزی را اجرا و `scripts/bootstrap-databases.cmd` را اجرا کن؛ سپس اپلیکیشن‌ها را بالا بیاور. رمز هر Role باید با `DB_PASSWORD` متناظر در فایل محیطی همان سرویس یکسان باشد.
