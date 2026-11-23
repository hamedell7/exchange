# قراردادهای توسعه Exchange

**وضعیت:** قرارداد جاری توسعه  
**دامنه:** همه سرویس‌های Monorepo، با جزئیات اجرایی Identity به‌عنوان اولین نمونه

این سند مبنای تصمیم‌های کدنویسی بعدی است. هدف ایجاد یک معماری یکنواخت و قابل نگهداری است، نه اجبار به ساختن کلاس‌ها و پوشه‌های بیشتر. در تعارض بین «الگوی ظاهری» و «سادگی همراه با حفظ invariant»، سادگی همراه با حفظ invariant اولویت دارد.

## ۱. اصول غیرقابل مذاکره

1. هر سرویس مالک داده، مدل دامنه، Migration و دیتابیس خودش است.
2. هیچ سرویس دیگری Entity، Eloquent Model یا جدول سرویس مالک را import یا مستقیماً query نمی‌کند.
3. وابستگی کد به سمت داخل است: Presentation به Application، Application به Domain؛ Infrastructure قراردادهای Application را پیاده‌سازی می‌کند.
4. Domain به Laravel، Eloquent، HTTP، Queue، Cache، Container و Driver دیتابیس وابسته نمی‌شود.
5. هر قانون باید یک مالک مشخص داشته باشد. یک قانون را در چند لایه با رفتارهای متفاوت بازنویسی نمی‌کنیم.
6. هیچ Pattern، Interface، DTO، Factory، Policy یا پوشه‌ای صرفاً برای «آماده بودن برای آینده» ساخته نمی‌شود.

## ۲. ساختار ماژول

ساختار پیشنهادی داخل هر سرویس:

```text
app/
├── Http/                         # concerns HTTP عمومی و واقعاً مشترک
├── Models/                       # Eloquentهای متعارف Laravel، در صورت نیاز
├── Providers/                    # Composition Root و اتصال Portها به Adapterها
└── Modules/
    └── <Context>/
        ├── Presentation/
        │   └── Http/
        │       ├── Controllers/
        │       ├── Requests/
        │       ├── Resources/
        │       └── routes.php
        ├── Domain/
        │   ├── Entities/
        │   ├── ValueObjects/
        │   └── Policies/
        ├── Application/
        │   ├── Commands/         # ورودی‌های immutable و تایپ‌شده
        │   ├── Actions/          # اجرای Use Caseها
        │   ├── Contracts/        # Portهای موردنیاز Application
        │   ├── Exceptions/
        │   └── Results/          # فقط در صورت نیاز واقعی
        └── Infrastructure/
            ├── Persistence/
            └── ...               # Adapterهای framework و سرویس بیرونی
```

- این درخت نقشه اجباری برای ساخت همه پوشه‌ها نیست؛ پوشه را هنگام نیاز واقعی ایجاد کنید.
- ماژول‌محوری بر نوع تکنولوژی اولویت دارد؛ کد یک قابلیت نباید بین پوشه‌های عمومی نامرتبط پخش شود.
- Presentation هر Context در خود ماژول نگهداری می‌شود؛ پوشه `app/Http` فقط برای اجزای واقعاً عمومی و مشترک است.
- Action اجرای یک Use Case است؛ برای یک عملیات، Action را با Service یا Handler هم‌مسئولیت تکرار نکنید.
- قرار داشتن Eloquent Model در `app/Models` به‌تنهایی نقض معماری نیست. ممنوعیت، وابستگی Domain به Eloquent است.
- نام‌گذاری و مسیرهای فعلی می‌توانند با رشد یک ماژول تکامل یابند؛ جابه‌جایی فایل بدون بهبود مرز یا فهم کد هدف نیست.

## ۳. قراردادهای Application

### Command و Use Case

- هر عملیات تغییردهنده وضعیت یک ورودی مشخص و تایپ‌شده دارد.
- برای عملیات‌های اصلی، یک Command immutable با propertyهای روشن استفاده می‌شود.
- انتقال `array $data`، آرایه associative بدون قرارداد یا `mixed` به Application ممنوع است.
- وجود ۲۰ فیلد در یک Command اشکالی ندارد؛ فقط زمانی فیلدها را داخل آبجکت دیگری گروه‌بندی کنید که یک مفهوم منسجم و مستقل را نشان دهند یا جداگانه استفاده شوند.
- فیلدهای صرفاً انتقالی مثل `password_confirmation` وارد Command نمی‌شوند.
- Use Case یک قابلیت را هماهنگ می‌کند؛ نباید تبدیل به محل جمع‌شدن تمام قواعد دامنه شود.
- یک Use Case می‌تواند Domain Aggregate، Result یا در عملیات بدون خروجی `void` برگرداند؛ نوع خروجی باید نیاز واقعی مصرف‌کننده را منعکس کند.
- Controller نباید Entity را مستقیم JSON کند. فقط فیلدهای عمومی و مجاز را صریحاً برمی‌گرداند.

### چه زمانی Interface بسازیم؟

Interface فقط وقتی ایجاد می‌شود که Application به توانایی بیرونی نیاز دارد و جداسازی آن ارزش دارد؛ مانند:
- ذخیره‌سازی Aggregate
- Hash یا Verify کردن Credential
- تولید شناسه
- ارتباط با سرویس یا Broker بیرونی
- Clock، فقط زمانی که زمان قابل‌کنترل واقعاً برای منطق/تست لازم شده باشد

برای هر Service داخلی Interface موازی نسازید. Interface برای هر کلاس Concrete، هدف معماری نیست.

### Repository

- Repository برای Aggregate Root ذخیره‌شونده است، نه هر جدول و هر Entity.
- قرارداد Repository کوچک و use-case-oriented می‌ماند؛ API عمومی Query Builder یا Eloquent را افشا نمی‌کند.
- Repository فقط برای جداکردن ORM از Domain و اجرای عملیات ذخیره‌سازی لازم است؛ برای Query ساده خواندنی، Query Service یا Query مستقیم در لایه مناسب می‌تواند کافی باشد.
- بررسی اولیه وجود رکورد تضمین هم‌زمانی نیست. قید Unique/Check/Foreign Key دیتابیس مرجع نهایی است.

### Value Object و Domain Model

- Value Object برای مفهوم دارای معنا، اعتبارسنجی یا رفتار مشخص ساخته می‌شود، نه برای هر ستون.
- Entity باید هویت و invariant داشته باشد؛ صرفاً تبدیل هر جدول به یک کلاس Domain، DDD نیست.
- Aggregate کوچک‌ترین مرز سازگاری تراکنشی لازم است؛ Aggregate معادل مجموعه جداول مرتبط نیست.
- قانون متعلق به یک Entity/Value Object در همان مدل قرار می‌گیرد. Domain Service فقط وقتی ساخته می‌شود که قانون طبیعیِ متعلق به هیچ‌یک از آن‌ها نباشد.
- Domain Event فقط برای واقعیت معنادار کسب‌وکار است، نه برای هر عملیات CRUD.

## ۴. قرارداد اعتبارسنجی و خطاها

### اعتبارسنجی

- Form Request شکل ورودی HTTP، فیلدهای الزامی، نوع، اندازه، تأییدیه و محدودیت‌های انتقال را بررسی می‌کند.
- Use Case همان محدودیت‌هایی را که برای تمام مسیرهای ورودی ضروری‌اند دوباره تضمین می‌کند؛ مسیر Queue یا Console نباید بتواند invariant را دور بزند.
- Domain invariantها را مستقل از HTTP محافظت می‌کند.
- یک قانون مشترک نباید دو مقدار hard-code متفاوت داشته باشد. مقدار مشترک باید در یک قرارداد/نوع مشخص تعریف و از همان‌جا مصرف شود.

### مالک خطا

| نوع خطا | مالک | نمونه |
|---|---|---|
| ورودی HTTP نامعتبر | Presentation / Form Request | فیلد جاافتاده، فرمت JSON یا تأییدیه رمز |
| ورودی نامعتبر Use Case | Application | نام یا رمز خارج از قرارداد ثبت‌نام |
| نقض invariant | Domain | مقدار یا Transition غیرمجاز |
| تعارض داده و قید دیتابیس | Infrastructure و سپس Application error | ایمیل تکراری |
| خطای غیرمنتظره | لایه اجرا/مانیتورینگ | قطع DB، باگ، خطای برنامه‌نویسی |

- Application و Domain هیچ‌وقت Laravel `ValidationException`، `HttpException` یا Response نمی‌سازند.
- Infrastructure خطای تکنولوژی را فقط در صورت تشخیص دقیق به خطای قابل‌فهم Application تبدیل می‌کند. هر `QueryException` را به تعارض کسب‌وکار تبدیل نکنید.
- خطای قابل انتظار باید نوع و معنای مشخص داشته باشد. `InvalidArgumentException` عمومی قرارداد خطا برای یک Use Case نیست.
- خطای دیتابیس، SQL، Stack Trace، Secret یا جزئیات داخلی در پاسخ API افشا نمی‌شود.

### قرارداد HTTP

| وضعیت | معنا |
|---|---|
| 201 | منبع با موفقیت ایجاد شد |
| 400 | درخواست از نظر ساختار/پروتکل قابل پردازش نیست |
| 401 | احراز هویت لازم است یا معتبر نیست |
| 403 | هویت معتبر است اما مجوز ندارد |
| 404 | منبع وجود ندارد یا در دسترس نیست |
| 409 | تعارض با وضعیت فعلی منبع؛ مانند حساب موجود با همان شناسه ایمیل |
| 422 | ورودی قابل پردازش است اما اعتبارسنجی فیلدها را نمی‌گذراند |
| 429 | محدودیت نرخ درخواست |
| 500 | خطای غیرمنتظره، بدون جزئیات داخلی |

خطاهای اعتبارسنجی با قالب Laravel شامل `message` و `errors` هستند. خطای تعارض نیز همین ساختار عمومی را نگه می‌دارد، ولی Status آن 409 است. شکل دقیق هر API در OpenAPI همان سرویس ثبت می‌شود.

## ۵. قرارداد داده و دیتابیس

- PostgreSQL دیتابیس اصلی سرویس‌های Exchange است؛ تست SQLite مکمل سریع است، نه جایگزین تست رفتار PostgreSQL.
- قیدهای دیتابیس برای invariantهای concurrency مانند Unique و Foreign Key الزامی‌اند.
- خطای Unique فقط در صورت تطابق نام/هویت قید موردنظر به خطای Application ترجمه می‌شود.
- تراکنش برای چند تغییر وابسته‌ای استفاده می‌شود که باید با هم Commit/Rollback شوند؛ برای یک Insert، Unit of Work عمومی نسازید.
- Migration باید یک تغییر Schema منسجم را نمایش دهد و نام قیدهای مهم صریح و پایدار باشد.
- برای داده پولی از float استفاده نکنید؛ نوع و scale/واحد پول باید در قرارداد همان قابلیت روشن باشد.
- داده Ledger پس از ثبت، Append-only است؛ اصلاح با ثبت سند معکوس/اصلاحی انجام می‌شود، نه ویرایش بی‌ردپای رکورد مالی.

### سیاست ایمیل Identity

- مقدار اصلی Email برای نمایش و ارسال حفظ می‌شود؛ local-part ورودی به اجبار lowercase نمی‌شود.
- برای شناسه حساب، Exchange عمداً ایمیل را case-insensitive در نظر می‌گیرد.
- کلید مقایسه با lowercase کل آدرس ساخته می‌شود. دیتابیس آن را از مقدار اصلی تولید و روی آن Unique Index اعمال می‌کند؛ برنامه نباید تنها مرجع این قاعده باشد. Migration پیش از تغییر Schema وجود ایمیل‌های تکراری پس از نرمال‌سازی را بررسی می‌کند و در صورت وجود، بدون حذف/ادغام خودکار داده‌ها متوقف می‌شود.
- هر Use Case ورود/بازیابی/تغییر ایمیل باید از همین کلید مقایسه استفاده کند. در Login نباید از جست‌وجوی پیش‌فرض Eloquent روی ستون خام `email` استفاده شود؛ Provider/Repository احراز هویت باید از `email_normalized` استفاده کند.
- قبل از اعطای امکان واریز، برداشت یا معامله، تأیید مالکیت ایمیل و وضعیت حساب باید در سیاست دسترسی بررسی شود.

### سیاست رمز عبور Identity

- تا وقتی احراز هویت چندعاملی برای حساب فعال و اجباری نشده، حداقل رمز ۱۵ کاراکتر است. سقف ورودی ۱۲۸ Unicode code point است تا Passphraseهای طولانی پذیرفته شوند.
- رمز باید با تمام کاراکترهای قابل‌چاپ ASCII، فاصله و Unicode کار کند؛ هر code point یک کاراکتر شمرده می‌شود.
- ترکیب اجباری حروف بزرگ/کوچک، عدد یا نماد اعمال نمی‌کنیم و تغییر دوره‌ای اجباری رمز نداریم.
- Hashing در Identity با Argon2id انجام می‌شود. Docker Build و CI باید وجود واقعی پشتیبانی Argon2id در PHP را بررسی کنند؛ در صورت نبود پشتیبانی، Build/Test باید fail شود و به الگوریتم دیگری fallback نکنند.
- انتخاب Argon2id تصمیم اجرایی Exchange است؛ دلیل مهم پرهیز از truncate خاموش رمز در bcrypt است که PHP آن را پس از ۷۲ بایت قطع می‌کند. توان/هزینه Argon2id باید با بار واقعی سرور و محدودیت نرخ درخواست تنظیم و پایش شود.
- پیش از انتشار عمومی Production، بررسی رمز در برابر فهرست معتبر رمزهای رایج/افشاشده باید طراحی و اضافه شود. یک فهرست کوتاه hard-code شده را معادل بررسی رمزهای افشاشده تلقی نکنید.
- این سیاست در Register، Login، Password Reset و Password Change باید یکسان اعمال شود.

## ۶. قرارداد سرویس‌به‌سرویس

- ارتباط بین سرویس‌ها فقط از API Gateway/HTTP داخلی یا Integration Event نسخه‌دار انجام می‌شود.
- خواندن دیتابیس سرویس دیگر، Cross-database query و Foreign Key بین دیتابیس‌ها ممنوع است.
- شناسه Aggregate سرویس مالک، مرجع خارجی است؛ مدل Domain یا Eloquent آن به سرویس مصرف‌کننده منتقل نمی‌شود.
- OpenAPI قرارداد HTTP و فایل‌های زیر `platform/contracts/openapi/<service>` مرجع شکل درخواست/پاسخ هستند.
- Eventها Schema نسخه‌دار دارند و حداقل شامل `event_id`، `event_type`، `schema_version`، `occurred_at`، `correlation_id` و payload مشخص‌اند.
- انتشار Event وابسته به تغییر دیتابیس باید با Transactional Outbox اتمیک شود.
- مصرف‌کننده Event باید Idempotent باشد؛ تحویل تکراری پیام نباید عملیات مالی یا تجاری را تکرار کند.
- تغییر ناسازگار API/Event باید نسخه یا دوره سازگاری و برنامه مهاجرت داشته باشد.

## ۷. قواعد ویژه Wallet و Trading

این اصول از ابتدا اجباری‌اند، اما مدل و Aggregate نهایی فقط پس از تعریف Use Case و invariantهای دقیق طراحی می‌شوند.

### Wallet
- Amount با integer در کوچک‌ترین واحد پول یا Decimal با precision/scale صریح نگهداری می‌شود؛ هرگز float.
- Ledger منبع حقیقت حرکت پول است؛ Balance مشتق‌شده یا materialized باید با Ledger قابل تطبیق باشد.
- هر عملیات مالی دارای شناسه Idempotency پایدار و قید دیتابیس متناسب است.
- رزرو، آزادسازی، برداشت و انتقال باید در برابر Race Condition و Double Spend محافظت شوند.
- چند ثبت وابسته باید در مرز تراکنش واحد انجام شوند؛ قفل‌گذاری/سطح Isolation براساس الگوی واقعی عملیات انتخاب می‌شود.
- Ledger entry و رویداد مالی منتشرشده بعد از ثبت، ویرایش/حذف بی‌ردپا نمی‌شوند.

### Trading
- Order چرخه وضعیت صریح و transitionهای مجاز دارد.
- قیمت، مقدار، Tick Size، Step Size و ارزش مالی با دقت عددی مشخص کار می‌کنند؛ float ممنوع.
- اجرای معامله و Execution Record تغییرناپذیر و قابل ردیابی است.
- ارتباط با Wallet از قرارداد رزرو/آزادسازی وجه می‌گذرد؛ Trading مستقیماً دیتابیس Wallet را تغییر نمی‌دهد.
- هر سفارش ورودی/فرمان خارجی باید شناسه idempotency یا client order id و رفتار تکرار دقیق داشته باشد.
- منطق Matching و ترتیب اجرای سفارش‌ها deterministic و قابل تست طراحی می‌شود.

## ۸. قرارداد تست و کیفیت

| لایه | تست اجباری |
|---|---|
| Domain | تست خالص PHP برای Value Object، Aggregate invariant و Transition |
| Application | تست Use Case با Mock/Fake برای Portها؛ بررسی عدم فراخوانی Infrastructure هنگام ورودی نامعتبر |
| Infrastructure | Integration Test روی PostgreSQL برای Mapping، Unique/Check constraint و Transaction |
| Presentation | Feature Test برای ورودی/خروجی، Status، Error shape، Authentication و Rate Limit |

- Test باید رفتار را مستقل و دقیق اثبات کند؛ سناریوهای مختلف را در یک تست مبهم ادغام نکنید.
- تست رد رمز کوتاه باید Confirmation صحیح داشته باشد؛ تست Confirmation نادرست باید رمز با طول صحیح داشته باشد.
- تست موفق ثبت‌نام باید نوع واقعی Hash را بررسی کند؛ CI و Docker Build پشتیبانی Argon2id را preflight می‌کنند.
- CI برای سرویس دارای PostgreSQL باید تست روی SQLite و PostgreSQL اجرا کند.
- تست نوشته‌شده با تست اجراشده و تست موفق سه وضعیت جدا هستند؛ گزارش وضعیت باید دقیق باشد.

## ۹. چه چیزهایی پیش‌فرض نیستند؟

تا وقتی مسئله واقعی و توجیه‌شده به وجود نیامده، اضافه نکنید:
- CQRS یا Read/Write Database جدا
- Mediator، Command Bus یا Handler جدا برای هر Command
- Generic Repository، Generic Service یا Base CRUD Framework
- Unit of Work عمومی برای یک عملیات ساده
- Event Sourcing
- Domain Event برای هر تغییر
- Outbox قبل از انتشار Event قابل اتکا
- Factory برای ساخت یک شیء ساده
- Interface برای هر کلاس
- DTO خروجی برای هر Use Case
- Value Object برای هر فیلد
- لایه Manager/Service/Processor موازی با Use Case بدون مسئولیت مستقل

## ۱۰. روند تغییر معماری

- تصمیم معماری مهم و پایدار در همین اسناد یا ADR ثبت می‌شود.
- تغییر API عمومی همزمان با OpenAPI به‌روز می‌شود.
- تغییر Event همزمان با Schema و نسخه آن انجام می‌شود.
- هر PR/Commit باید تست مرتبط و دلیل ایجاد abstractionهای جدید را مشخص کند.
- Commit مستقیم به `main` در این مخزن مطابق جریان فعلی توسعه است؛ اما تغییرات باید کوچک، قابل بازبینی و با تست/CI پشتیبانی شوند.

## منابع اجرایی

- [راهنمای DDD و مرزبندی لایه‌ها](domain-driven-design.md)
- [مرزبندی سرویس‌ها](service-boundaries.md)
- [قراردادهای API و Event](../../contracts/README.fa.md)
- [قرارداد OpenAPI ثبت‌نام Identity](../../contracts/openapi/identity/identity-v1.yaml)

### مبنای امنیت رمز

- [NIST SP 800-63B-4](https://pages.nist.gov/800-63-4/sp800-63b.html): حداقل ۱۵ کاراکتر برای رمز تک‌عاملی، پشتیبانی از حداقل ۶۴ کاراکتر، شمارش Unicode code point و منع Composition Rule.
- [Laravel 13 Hashing](https://laravel.com/docs/13.x/hashing): انتخاب driver از طریق `HASH_DRIVER` و پشتیبانی Argon2id.
- [PHP password_hash](https://www.php.net/manual/en/function.password-hash.php): محدودیت ۷۲ بایتی bcrypt و شرایط استفاده از Argon2id.
