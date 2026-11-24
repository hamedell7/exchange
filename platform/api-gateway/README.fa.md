# درگاه API (API Gateway)

این مؤلفه لایه ورودی پلتفرم Exchange است و با Nginx پیاده‌سازی شده است. Gateway بدون وضعیت (Stateless) است و مالک کاربر، موجودی، سفارش، مجوز دامنه‌ای یا منطق تجاری نیست.

## اجرای Gateway

Gateway بخشی از Compose مرکزی Platform است؛ Compose جداگانه یا اجرای دستی `docker run` لازم نیست.

از ریشه مخزن در PowerShell:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}

docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build api-gateway
```

برای اجرای همه مؤلفه‌های Platform (PostgreSQL، Redis، RabbitMQ و Gateway)، سرویس مشخص نکن:

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build
```

Gateway روی پورت میزبان `8080` منتشر می‌شود. برای تغییر آن، `API_GATEWAY_PORT` را در `platform/infrastructure/.env` تنظیم کن.

بررسی سلامت:

```powershell
Invoke-RestMethod http://127.0.0.1:8080/healthz
```

این Endpoint فقط اجرای Nginx را بررسی می‌کند و سلامت سرویس‌های مقصد را تضمین نمی‌کند.

## مسیرهای فعلی

| مسیر عمومی | سرویس مقصد |
|---|---|
| `/api/v1/identity/*` | Identity Service |
| `/api/v1/trading/*` | Trading Service |
| `/api/v1/wallet/*` | Wallet Service |
| `/healthz` | سلامت پردازش Gateway |

URI اصلی هنگام ارسال درخواست به سرویس مقصد حفظ می‌شود. برنامه هر سرویس باید همین پیشوندها را ارائه کند؛ در غیر این صورت، مسیرهای Gateway باید با قرارداد API هماهنگ شوند.

## شبکه و DNS

Gateway و سه Microservice روی شبکه مشترک Docker با نام `exchange-network` قرار می‌گیرند. Gateway از نام‌های زیر برای دسترسی به کانتینرها استفاده می‌کند:

- `identity-service:8000`
- `trading-service:8000`
- `wallet-service:8000`

Nginx از DNS داخلی Docker برای resolve کردن نام‌ها در زمان درخواست استفاده می‌کند؛ بنابراین لازم نیست سرویس‌های Laravel هنگام شروع Gateway از قبل اجرا شده باشند. اگر کانتینر مقصد هنوز به شبکه متصل نشده باشد، درخواست آن مسیر موقتاً خطای اتصال می‌گیرد.

## قابلیت‌های پیاده‌سازی‌شده

- محدودسازی نرخ درخواست و تعداد اتصال هم‌زمان به‌ازای IP.
- محدودیت اندازه بدنه درخواست و Timeoutهای کلاینت.
- Timeout مستقل برای اتصال، ارسال و دریافت از سرویس‌های مقصد.
- تولید Request ID در Gateway و ارسال آن به سرویس مقصد.
- هدرهای امنیتی پایه و مخفی‌کردن نسخه Nginx.
- Endpoint سلامت محلی.

مقادیر Rate Limit فعلی صرفاً پیش‌فرض شروع توسعه‌اند و باید با تست بار و الگوی ترافیک واقعی تنظیم شوند.

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
