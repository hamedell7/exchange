# درگاه API (API Gateway)

این مؤلفه لایه ورودی پلتفرم Exchange است و با Nginx پیاده‌سازی شده است. Gateway بدون وضعیت (Stateless) است و مالک کاربر، موجودی، سفارش، مجوز دامنه‌ای یا منطق تجاری نیست.

## مسیرهای فعلی

| مسیر عمومی | سرویس مقصد |
|---|---|
| `/api/v1/identity/*` | Identity Service |
| `/api/v1/trading/*` | Trading Service |
| `/api/v1/wallet/*` | Wallet Service |
| `/healthz` | سلامت پردازش Gateway |

URI اصلی هنگام ارسال درخواست به سرویس مقصد حفظ می‌شود. برنامه هر سرویس باید همین پیشوندها را ارائه کند؛ در غیر این صورت، پس از نهایی‌شدن قرارداد API باید مسیرهای Gateway با آن قراردادها هماهنگ شوند.

## قابلیت‌های پیاده‌سازی‌شده

- محدودسازی نرخ درخواست و تعداد اتصال هم‌زمان به‌ازای IP.
- محدودیت اندازه بدنه درخواست و Timeoutهای کلاینت.
- Timeout مستقل برای اتصال، ارسال و دریافت از سرویس‌های مقصد.
- تولید Request ID در Gateway و ارسال آن به سرویس مقصد.
- هدرهای امنیتی پایه و مخفی‌کردن نسخه Nginx.
- Endpoint سلامت محلی.

مقادیر Rate Limit فعلی صرفاً پیش‌فرض شروع توسعه‌اند و باید با تست بار و الگوی ترافیک واقعی تنظیم شوند.

## ساخت Image

از ریشه مخزن اجرا کنید:

```powershell
docker build -t exchange-api-gateway:local ./platform/api-gateway
```

## اجرا روی شبکه مشترک

ابتدا زیرساخت را اجرا کنید تا شبکه Docker با نام دقیق `exchange-network` ساخته شود. سپس سرویس‌های مقصد را بالا بیاورید و Gateway را به همان شبکه متصل کنید؛ در غیر این صورت نام‌های میزبان سرویس‌ها از داخل Nginx resolve نمی‌شوند.

```powershell
Copy-Item platform/api-gateway/.env.example platform/api-gateway/.env

docker run --rm --name exchange-api-gateway --network exchange-network --env-file platform/api-gateway/.env -p 127.0.0.1:8080:8080 exchange-api-gateway:local
```

بررسی سلامت:

```powershell
Invoke-WebRequest http://127.0.0.1:8080/healthz
```

این Endpoint فقط اجرای Nginx را بررسی می‌کند و سلامت سرویس‌های مقصد را تضمین نمی‌کند.

## تنظیمات سرویس‌های مقصد

تمام متغیرهای زیر هنگام شروع کانتینر الزامی هستند:

- `IDENTITY_SERVICE_HOST` و `IDENTITY_SERVICE_PORT`
- `TRADING_SERVICE_HOST` و `TRADING_SERVICE_PORT`
- `WALLET_SERVICE_HOST` و `WALLET_SERVICE_PORT`

مقادیر نمونه در `.env.example` قرار دارند. هیچ Secret یا آدرس حساس Production را در Git ثبت نکنید.

## مرزهای امنیتی

- در Production، TLS باید در Load Balancer/Ingress مورد اعتماد خاتمه یابد یا TLS مستقیماً برای Gateway پیکربندی شود؛ Gateway فعلی فقط HTTP روی پورت 8080 ارائه می‌کند.
- هدرهای هویتی ارسالی از سمت Client قابل اعتماد نیستند. این Gateway هویت احراز‌شده کاربر را جعل یا تولید نمی‌کند.
- Gateway جایگزین اعتبارسنجی امضای JWT، Issuer، Audience یا مجوزدهی در سرویس مالک دامنه نیست.
- هر سرویس باید مجوز دسترسی به منبع و عملیات را مستقلاً بررسی کند.
- اگر Proxy دیگری جلوی Gateway قرار گرفت، فقط بازه IP پروکسی‌های مورد اعتماد را برای Forwarded Headers مجاز کنید.
- Gateway و سرویس‌ها باید در شبکه خصوصی باشند و پورت سرویس‌ها مستقیماً در اینترنت منتشر نشود.

## موارد عمداً خارج از این مرحله

اعتبارسنجی JWT/OIDC، سیاست CORS، گواهی TLS، Rate Limit توزیع‌شده، Circuit Breaker، Retry، تبدیل درخواست/پاسخ و مستندات اختصاصی API هنوز فعال نشده‌اند. این موارد به قرارداد Issuer/JWKS سرویس Identity، دامنه‌های مجاز Frontend، توپولوژی استقرار و قرارداد نهایی APIها نیاز دارند.

به‌ویژه برای درخواست‌های نوشتنی مالی Retry خودکار فعال نشده است؛ چون ممکن است عملیات non-idempotent را تکرار و باعث ثبت دوباره تراکنش شود.
