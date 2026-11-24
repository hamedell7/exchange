# درگاه API (API Gateway)

این مؤلفه لایه ورودی پلتفرم Exchange است و با Nginx پیاده‌سازی شده است. Gateway بدون وضعیت (Stateless) است و مالک منطق تجاری یا داده سرویس‌ها نیست.

## اجرای Gateway

Gateway بخشی از Compose مرکزی Platform است. از ریشه مخزن اجرا کن:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d --build api-gateway
```

## مسیرهای فعلی

| مسیر عمومی | سرویس مقصد |
|---|---|
| /api/v1/identity/* | Identity Service |
| /api/v1/trading/* | Trading Service |
| /api/v1/wallet/* | Wallet Service |
| /api/v1/market/* | Market Data Service |
| /healthz | سلامت پردازش Gateway |

URI اصلی هنگام ارسال درخواست به سرویس مقصد حفظ می‌شود. هر سرویس باید همین پیشوند را ارائه کند.

## شبکه و امنیت

Gateway از DNS داخلی Docker برای resolve کردن نام سرویس‌ها در زمان درخواست استفاده می‌کند. Gateway جایگزین احراز هویت و مجوزدهی در سرویس مالک دامنه نیست. Secretهای Production نباید در Git ثبت شوند.
