import {
  ArrowLeft, ArrowUpLeft, BadgeCheck, CalendarDays, CandlestickChart, CircleCheck,
  Clock3, KeyRound, ShieldCheck, UserRound, Wallet,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext.jsx';

function dateLabel(value, options = { dateStyle: 'long' }) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return new Intl.DateTimeFormat('fa-IR', options).format(date);
}

function shortId(value = '') {
  return value ? `${value.slice(0, 8)}…${value.slice(-4)}` : '—';
}

function greeting() {
  const hour = new Date().getHours();
  if (hour < 11) return 'صبح بخیر';
  if (hour < 17) return 'روز بخیر';
  return 'عصر بخیر';
}

function ModuleCard({ icon: Icon, title, text, label, tone = 'blue' }) {
  return (
    <article className={`module-card ${tone}`}>
      <span className="module-icon"><Icon size={21} /></span>
      <div className="module-copy"><strong>{title}</strong><p>{text}</p></div>
      <span className="module-label">{label}</span>
    </article>
  );
}

export default function DashboardPage() {
  const { user } = useAuth();
  const isActive = user?.status === 'active';
  const displayName = user?.name?.trim().split(/\s+/)[0] || 'دوست من';

  return (
    <div className="dashboard-page">
      <section className="welcome-banner">
        <div className="welcome-content">
          <span className="welcome-kicker"><span className="welcome-kicker-dot" /> فضای شخصی اکسچنج</span>
          <h1>{greeting()}، {displayName} <span className="wave">✦</span></h1>
          <p>به پنل کاربری خودت خوش آمدی. همه چیز برای مدیریت حساب در یک نگاه آماده است.</p>
          <div className="welcome-meta">
            <span><CalendarDays size={15} /> {dateLabel(new Date(), { dateStyle: 'full' })}</span>
            <span className="welcome-meta-divider" />
            <span className="welcome-account-id">شناسه: {shortId(user?.id)}</span>
          </div>
        </div>
        <div className="welcome-ornament" aria-hidden="true">
          <div className="ornament-ring ring-one" /><div className="ornament-ring ring-two" />
          <div className="ornament-center"><span>E</span></div>
          <span className="ornament-spark spark-one">✦</span>
          <span className="ornament-spark spark-two">✧</span>
        </div>
      </section>

      <div className="section-heading">
        <div><span className="section-eyebrow">ACCOUNT OVERVIEW</span><h2>وضعیت حساب تو</h2></div>
        <Link to="/profile" className="subtle-link">مشاهده اطلاعات حساب <ArrowLeft size={15} /></Link>
      </div>

      <section className="overview-grid">
        <article className="overview-card account-status-card">
          <div className="overview-card-top">
            <span className="overview-icon green"><ShieldCheck size={20} /></span>
            <span className={`status-pill ${isActive ? 'success' : 'warning'}`}>
              <span className="status-dot" /> {isActive ? 'فعال' : user?.status === 'suspended' ? 'محدودشده' : user?.status === 'closed' ? 'بسته‌شده' : 'در انتظار بررسی'}
            </span>
          </div>
          <span className="overview-label">وضعیت حساب کاربری</span>
          <strong className="overview-value">{isActive ? 'حساب تو فعال است' : 'وضعیت حساب نیاز به توجه دارد'}</strong>
          <span className="overview-note">{isActive ? 'امکان ورود به فضای کاربری فعال است.' : 'برای اطلاعات بیشتر با پشتیبانی ارتباط بگیر.'}</span>
        </article>

        <article className="overview-card">
          <div className="overview-card-top"><span className="overview-icon blue"><UserRound size={20} /></span><span className="overview-mini-label">پروفایل</span></div>
          <span className="overview-label">نام حساب</span>
          <strong className="overview-value">{user?.name || '—'}</strong>
          <span className="overview-note">{user?.email || 'ایمیل ثبت نشده'}</span>
        </article>

        <article className="overview-card">
          <div className="overview-card-top"><span className="overview-icon purple"><BadgeCheck size={20} /></span><span className="overview-mini-label">از زمان عضویت</span></div>
          <span className="overview-label">تاریخ ایجاد حساب</span>
          <strong className="overview-value">{dateLabel(user?.registered_at, { year: 'numeric', month: 'long', day: 'numeric' })}</strong>
          <span className="overview-note">عضویت در اکسچنج</span>
        </article>
      </section>

      <div className="dashboard-columns">
        <section className="dashboard-panel services-panel">
          <div className="panel-heading">
            <div><span className="section-eyebrow">YOUR SERVICES</span><h2>خدمات اکسچنج</h2></div>
            <span className="panel-counter">۲ خدمت در حال آماده‌سازی</span>
          </div>
          <div className="module-list">
            <ModuleCard
              icon={Wallet}
              title="کیف پول"
              text="مدیریت دارایی‌ها، موجودی و گردش حساب"
              label="به‌زودی"
              tone="green"
            />
            <ModuleCard
              icon={CandlestickChart}
              title="بازارها و معامله"
              text="مشاهده بازارها و مدیریت سفارش‌های معاملاتی"
              label="به‌زودی"
              tone="purple"
            />
          </div>
          <div className="module-footnote"><Clock3 size={15} /> این بخش‌ها پس از اتصال API سرویس‌های مربوطه فعال می‌شوند.</div>
        </section>

        <section className="dashboard-panel security-panel">
          <div className="panel-heading">
            <div><span className="section-eyebrow">ACCOUNT SECURITY</span><h2>امنیت حساب</h2></div>
            <span className="security-shield"><ShieldCheck size={19} /></span>
          </div>
          <div className="security-list">
            <div className="security-row">
              <span className="security-row-icon"><KeyRound size={17} /></span>
              <div><strong>ورود با رمز عبور</strong><span>احراز هویت حساب فعال است</span></div>
              <CircleCheck className="security-check" size={18} />
            </div>
            <div className="security-row">
              <span className="security-row-icon"><BadgeCheck size={17} /></span>
              <div><strong>وضعیت ایمیل</strong><span>{user?.email_verified_at ? 'ایمیل تأیید شده است' : 'تأیید ایمیل در حال حاضر الزامی نیست'}</span></div>
              <CircleCheck className="security-check" size={18} />
            </div>
          </div>
          <Link to="/security" className="security-action">مدیریت امنیت حساب <ArrowUpLeft size={16} /></Link>
        </section>
      </div>

      <section className="activity-note">
        <span className="activity-note-icon"><Clock3 size={19} /></span>
        <div><strong>فعالیت‌های حساب</strong><p>تاریخچه ورودها، تراکنش‌ها و سفارش‌ها پس از اتصال سرویس‌های مربوطه در این قسمت نمایش داده می‌شود.</p></div>
        <span className="activity-note-badge">در حال آماده‌سازی</span>
      </section>
    </div>
  );
}
