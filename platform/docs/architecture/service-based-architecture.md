# معماری Service-Based برای سرویس‌های Laravel

**وضعیت:** Accepted  
**دامنه:** ساختار داخلی سرویس‌های Laravel در Exchange

## ۱. تصمیم معماری

ساختار داخلی هر Laravel service بر پایه Service-Based Architecture با قراردادهای متعارف Laravel است. هدف، نگه‌داشتن منطق هر قابلیت در Serviceهای روشن و استفاده مستقیم از امکانات Laravel است؛ نه بازسازی Container، ORM، Transaction و Validation فریم‌ورک در قالب لایه‌های موازی.

جریان معمول درخواست:

    Route -> Controller -> Form Request -> Service
                                      Service -> Eloquent Model / Query Builder / Laravel Facades
    Controller -> API Resource -> JSON

این تصمیم، مرزبندی Microserviceها را تغییر نمی‌دهد. Identity، Wallet و Trading همچنان مالک دیتابیس، داده، API، استقرار و قواعد خود هستند. Service-Based فقط روش پیاده‌سازی داخل هر سرویس را ساده می‌کند.

## ۲. ساختار پیشنهادی

    app/
    ├── Http/
    │   ├── Controllers/       # کنترلرهای نازک
    │   ├── Requests/          # اعتبارسنجی ورودی HTTP
    │   ├── Resources/         # خروجی صریح و allow-list
    │   └── Middleware/        # احراز هویت، محدودسازی و concerns HTTP
    ├── Models/                # Eloquent Models
    ├── Services/
    │   └── <Capability>/      # عملیات منسجم هر قابلیت
    ├── Enums/                 # enumهای مشترک و معنی‌دار
    ├── Support/               # helperهای کوچک و واقعاً مشترک
    ├── Exceptions/            # خطاهای معنادار موردنیاز برنامه
    ├── Mail/                  # Mailableهای Laravel
    └── Providers/             # ثبت تنظیمات و سرویس‌های framework

این ساختار یک قالب اجباری برای ایجاد تمام پوشه‌ها نیست. هر مسیر فقط هنگام وجود کد واقعی ساخته می‌شود. نام پوشه‌ها و عمق namespace باید تا حد ممکن نزدیک به convention لاراول بماند.

## ۳. مسئولیت اجزا

### Controller

- ورودی معتبر را از Form Request می‌گیرد.
- Service مربوط به قابلیت را فراخوانی می‌کند.
- پاسخ را با API Resource یا پاسخ HTTP مناسب برمی‌گرداند.
- نباید محل اجرای queryهای پیچیده یا منطق چندمرحله‌ای کسب‌وکار باشد.

### Form Request

- شکل و نوع داده ورودی HTTP، فیلدهای الزامی، اندازه، تأیید رمز و allow-list فیلدها را اعتبارسنجی می‌کند.
- فقط نگرانی‌های ورودی HTTP را پوشش می‌دهد.
- فیلدهایی مانند password_confirmation را به Service منتقل نمی‌کند، مگر اینکه خود عملیات واقعاً به آن مقدار نیاز داشته باشد.

### Service

- یک قابلیت یا عملیات منسجم را هماهنگ می‌کند.
- می‌تواند مستقیماً از Eloquent، Query Builder، DB::transaction، Hash، Mail، Cache، Http و سایر امکانات Laravel استفاده کند.
- مالک orchestration، قواعد کاربردی، کنترل وضعیت و مرز تراکنش همان عملیات است.
- نباید یک Service عمومی و بزرگ برای تمام سیستم یا یک کلاس برای هر متد ساده ساخته شود.
- برای کارهای چندمرحله‌ای، متدهای خصوصی کوچک و نام‌گذاری روشن ترجیح دارند؛ استخراج کلاس فقط وقتی انجام می‌شود که مسئولیت مستقل و قابل استفاده مجدد وجود داشته باشد.

### Eloquent Model

- ساختار داده، casts، روابط، scopes و رفتارهایی را نگه می‌دارد که واقعاً به خود رکورد تعلق دارند.
- استفاده مستقیم Service از Model طبیعی است؛ برای دور زدن Eloquent یک Repository اجباری لازم نیست.
- فیلدهای حساس باید hidden باشند، اما API Resource همچنان باید خروجی را به‌صورت allow-list مشخص کند.
- قیدهای Unique، Foreign Key و Check در دیتابیس مرجع نهایی تضمین هم‌زمانی هستند.

### Queryهای خواندنی

- برای خواندن ساده، query در Service مناسب همان قابلیت نوشته می‌شود.
- برای query بزرگ یا قابل استفاده مجدد، متد خصوصی، Eloquent Scope یا Query Service محدود استفاده کنید.
- تفکیک Command/Query، Read Model و دیتابیس Read مستقل پیش‌فرض نیست.
- فقط وقتی نیاز واقعی به مدل خواندنی جدا، بار خواندن متفاوت یا استقلال چرخه خواندن/نوشتن وجود دارد، CQRS را بررسی کنید.

### API Resource

- فیلدهای عمومی پاسخ را صریحاً فهرست می‌کند.
- Eloquent Model نباید بدون کنترل به JSON عمومی تبدیل شود.
- Resource با DTO اجباری یا Read Model جدا برای هر endpoint جایگزین نمی‌شود.

## ۴. Repository، Interface و DTO چه زمانی لازم‌اند؟

هیچ‌کدام پیش‌فرض نیستند.

Repository زمانی توجیه دارد که جزئیات persistence واقعاً باید از منطق برنامه جدا شوند، چند روش ذخیره‌سازی واقعی وجود داشته باشد یا عملیات persistence پیچیده و تکرارشونده باشد. صرفاً wrap کردن User::query() ارزش کافی ایجاد نمی‌کند.

Interface زمانی مفید است که یک مرز واقعی برای جایگزینی/اتصال یک سیستم بیرونی یا پیاده‌سازی متفاوت وجود دارد. Interface یک‌به‌یک برای هر Service، Model یا Facade ایجاد نکنید.

DTO زمانی مفید است که قرارداد ورودی/خروجی مستقل، پیچیده یا بین چند مصرف‌کننده مشترک باشد. برای پاس‌دادن چند scalar از Controller به Service یک کلاس Command نسازید.

Value Object فقط برای مفهومی استفاده می‌شود که اعتبارسنجی، رفتار یا معنای مستقل دارد و این پیچیدگی را توجیه می‌کند؛ نه برای هر ستون یا primitive.

Domain Model مستقل از Eloquent الگوی پیش‌فرض نیست. در قابلیت‌های ساده‌تر، مدل Eloquent و Service کافی‌اند. اگر یک بخش کسب‌وکار واقعاً قواعد پیچیده و transitionهای زیاد داشت، می‌توان منطق خالص و غنی را به کلاس‌های کوچک تخصصی منتقل کرد؛ بدون اینکه کل سرویس را به چهار لایه تبدیل کنیم.

## ۵. تراکنش و یکپارچگی داده

- DB::transaction مستقیماً در Service استفاده می‌شود.
- چند write که باید با هم موفق یا rollback شوند، در یک تراکنش واحد قرار می‌گیرند.
- برای یک Insert ساده، Unit of Work یا abstraction تراکنش نسازید.
- عملیات حساس به هم‌زمانی باید علاوه بر validation برنامه، قید دیتابیس و در صورت نیاز row lock یا conditional update داشته باشد.
- ایمیل، HTTP call، Queue و سایر side effectها در صورت امکان بعد از commit انجام شوند.
- Event و Outbox فقط در صورت نیاز واقعی به integration قابل اتکا و بعد از تعریف قرارداد بین‌سرویسی اضافه می‌شوند.

## ۶. خطا و اعتبارسنجی

- خطای ورودی HTTP در Form Request تشخیص داده می‌شود.
- Service می‌تواند برای نتیجه نامعتبر عملیات از خطای مشخص برنامه یا Laravel ValidationException استفاده کند.
- Controller خطاهای شناخته‌شده را به status و response قرارداد API نگاشت می‌کند.
- فقط constraint مشخصی مثل unique ایمیل به خطای تعارض قابل فهم تبدیل می‌شود؛ همه QueryExceptionها را به 409 تبدیل نکنید.
- SQL، Stack Trace، Secret و جزئیات داخلی هرگز در پاسخ API افشا نمی‌شوند.

## ۷. مرزبندی کسب‌وکار و میکروسرویس

DDD تاکتیکی و لایه‌های مستقل آن اجباری نیستند؛ اما مالکیت و مرزهای کسب‌وکار همچنان مهم‌اند:

- Identity مالک کاربر، credential و چرخه عمر حساب است.
- Wallet مالک حساب دارایی، رزرو، ledger و مانده است.
- Trading مالک بازار، سفارش و معامله است.
- Gateway مالک مدل دامنه کاربر یا عملیات مالی نیست.
- هیچ سرویس دیگری دیتابیس یا Eloquent Model سرویس مالک را query یا import نمی‌کند.
- ارتباط سرویس‌ها از قرارداد API یا Event نسخه‌دار انجام می‌شود.

## ۸. چه چیزهایی پیش‌فرض نیستند؟

تا وقتی مسئله واقعی وجود ندارد، اضافه نکنید:

- لایه‌های Domain/Application/Infrastructure مجزا برای همه CRUDها
- Aggregate و Value Object برای هر جدول یا فیلد
- Generic Repository، Generic Service یا Base CRUD Framework
- Command، Query، Handler، Mediator یا Bus برای هر عملیات
- Read Model و دیتابیس Read جدا
- DTO ورودی و خروجی برای هر endpoint
- Interface برای هر کلاس یا هر Facade لاراول
- Factory، Manager، Processor یا Unit of Work بدون مسئولیت مستقل
- Event Sourcing یا Domain Event برای هر تغییر
- abstraction صرفاً برای تستی که Laravel Facades و Feature Testها به‌سادگی پوشش می‌دهند

**اصل تصمیم:** ابتدا ساده‌ترین پیاده‌سازی قابل‌فهم در Laravel را بنویسید. اگر تکرار، پیچیدگی، تست‌پذیری یا فشار عملیاتی مشکل واقعی ایجاد کرد، abstraction متناسب با همان مشکل را اضافه کنید.
