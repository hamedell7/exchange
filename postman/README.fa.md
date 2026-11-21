# Postman — Exchange (Local)

این پوشه Collection و Environment آماده‌ی تست APIهای موجود در Workspace را نگه می‌دارد.

## Import در Postman

1. از مسیر **Import** فایل `Exchange-Local.postman_environment.json` را وارد کنید.
2. فایل `Exchange-API.postman_collection.json` را هم Import کنید.
3. Environment با نام **Exchange - Local** را از بالای Postman انتخاب کنید.
4. ابتدا سرویس‌ها را اجرا کنید و سپس درخواست‌ها را بفرستید.

## اجرای Identity Service

از ریشه‌ی مخزن Workspace:

```bash
docker compose -f platform/infrastructure/compose.yaml up -d
docker compose -f identity-service/compose.yaml up -d --build
docker compose -f identity-service/compose.yaml exec app php artisan migrate --force
```

آدرس مستقیم Identity در محیط محلی: `http://localhost:8001`

Health check:
- `GET {{identityBaseUrl}}/up`

ثبت‌نام:
- `POST {{identityBaseUrl}}/api/v1/identity/register`
- Body شامل `name`، `email`، `password` و `password_confirmation` است.
- حداقل طول رمز عبور ۱۲ کاراکتر است.
- پاسخ موفق باید HTTP 201 باشد.

## ترتیب تست پیشنهادی

در فولدر **Identity Service**:

1. **Health Check** — باید 200 بگیرد.
2. **Register User** — باید 201 بگیرد. ایمیل منحصربه‌فرد به‌صورت خودکار در Collection Variable ذخیره می‌شود.
3. **Register Duplicate Email** — بعد از ثبت موفق مرحله قبل اجرا شود؛ باید 422 و خطای `errors.email` بگیرد.
4. **Register Invalid Payload** — باید 422 و خطاهای validation بگیرد.

برای اجرای دوباره‌ی سناریوی ثبت‌نام، مقدار Collection Variable با نام `registrationEmail` را پاک کنید یا خالی بگذارید تا ایمیل جدید ساخته شود.

## وضعیت سایر سرویس‌ها

در نسخه فعلی کد، برای Wallet و Trading هنوز routeهای API تعریف نشده‌اند؛ بنابراین درخواست صوری برای آن‌ها اضافه نشده است. متغیر `gatewayBaseUrl` صرفاً برای آماده‌سازی تست‌های بعدی است؛ Gateway باید جداگانه اجرا و routeهای متناظر پیاده‌سازی شده باشند.
