# Postman — Exchange (Local)

این پوشه Collection و Environment آماده‌ی تست APIهای پیاده‌سازی‌شده Exchange را نگه می‌دارد.

## Import در Postman

1. از مسیر **Import** فایل `Exchange-Local.postman_environment.json` را وارد کنید.
2. فایل `Exchange-API.postman_collection.json` را هم Import کنید.
3. Environment با نام **Exchange - Local** را از بالای Postman انتخاب کنید.
4. ابتدا زیرساخت و سرویس Identity را اجرا کنید و سپس درخواست‌ها را بفرستید.

## اجرای Identity Service

از ریشه مخزن اجرا کنید:

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
docker compose --env-file identity-service/.env -f identity-service/compose.yaml up -d --build
docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan migrate --force
```

آدرس مستقیم Identity در محیط محلی: `http://localhost:8001`

Health check:
- `GET {{identityBaseUrl}}/up`

## ترتیب تست پیشنهادی

در فولدر **Identity Service**:

1. **Health Check** — باید 200 بگیرد.
2. **Register User** — باید 201 بگیرد. ایمیل منحصربه‌فرد به‌صورت خودکار در Collection Variable ذخیره می‌شود. حساب بلافاصله `active` می‌شود و ایمیل تأیید ارسال نمی‌شود.
3. **Register Duplicate Email (expect 409)** — باید تعارض 409 و خطای `errors.email` برگرداند.
4. **Register Invalid Payload** — باید 422 و خطاهای validation بگیرد.
5. **Login (email + password)** — با ایمیل و رمز ثبت‌نام ورود می‌کند. درخواست به‌صورت خودکار توکن Sanctum را در Collection Variable به نام `identityAccessToken` ذخیره می‌کند.
6. **Login With Wrong Password (expect 401)** — باید 401 با پیام عمومی اعتبارنامه نامعتبر برگرداند.
7. **Get Current User (Bearer)** — از توکن ذخیره‌شده استفاده می‌کند و پروفایل کاربر جاری را برمی‌گرداند.
8. **Logout (revoke current token)** — همان توکن را باطل می‌کند و متغیر توکن را پاک می‌کند.

بعد از Logout برای اجرای دوباره Get Current User باید Login را مجدداً اجرا کنید.

## متغیرهای مهم Collection

- `registrationEmail`: در اولین Register به‌صورت خودکار ساخته می‌شود. برای اجرای سناریوی ثبت‌نام با کاربر جدید، آن را پاک یا خالی کنید.
- `registrationPassword`: رمز تست محلی؛ حداقل ۱۵ و حداکثر ۱۲۸ کاراکتر.
- `identityAccessToken`: فقط بعد از Login موفق پر می‌شود و بعد از Logout پاک می‌شود.
- `identityAdminReadToken`: فقط برای درخواست server-to-server فهرست کاربران است؛ secret سمت سرور است و نباید به Frontend منتقل شود.

## قرارداد ورود

```http
POST {{identityBaseUrl}}/api/v1/identity/login
Content-Type: application/json
Accept: application/json

{
  "email": "{{registrationEmail}}",
  "password": "{{registrationPassword}}",
  "device_name": "Postman Local"
}
```

پاسخ موفق شامل `data.access_token` و `data.expires_at` است. در درخواست‌های محافظت‌شده، هدر زیر ارسال می‌شود:

```http
Authorization: Bearer <access_token>
```

توکن Sanctum opaque و مخصوص Identity است؛ Gateway، Wallet و Trading هنوز قرارداد اعتبارسنجی و انتشار هویت بین‌سرویسی آن را پیاده‌سازی نکرده‌اند.

## وضعیت سایر سرویس‌ها

در نسخه فعلی کد، برای Wallet و Trading هنوز routeهای API مورد نیاز Postman تعریف نشده‌اند؛ بنابراین درخواست صوری برای آن‌ها اضافه نشده است. متغیر `gatewayBaseUrl` برای تست‌های بعدی نگه داشته شده است.
