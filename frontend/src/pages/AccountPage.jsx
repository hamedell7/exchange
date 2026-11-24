import { useAuth } from '../auth/AuthContext.jsx';
import { ArrowUpLeft, CalendarDays, CheckCircle2, Mail, ShieldCheck, UserRound } from 'lucide-react';
import { Link } from 'react-router-dom';

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'long', timeStyle: 'short' }).format(date);
}

function statusText(status) {
  return ({
    active: 'فعال',
    pending_verification: 'در انتظار تأیید',
    suspended: 'محدودشده',
    closed: 'بسته‌شده',
  })[status] || 'نامشخص';
}

function DetailRow({ label, value, icon: Icon }) {
  return (
    <div className="detail-row">
      <span className="detail-icon"><Icon size={18} /></span>
      <div className="detail-label">{label}</div>
      <strong className="detail-value">{value || '—'}</strong>
    </div>
  );
}

export default function AccountPage() {
  const { user } = useAuth();

  return (
    <div className="inner-page">
      <div className="page-intro">
        <div><span className="section-eyebrow">ACCOUNT PROFILE</span><h1>اطلاعات حساب</h1><p>اطلاعات اصلی حساب کاربری تو در این بخش نمایش داده می‌شود.</p></div>
        <span className="intro-icon"><UserRound size={23} /></span>
      </div>

      <section className="profile-card">
        <div className="profile-card-header">
          <div className="profile-avatar"><UserRound size={28} /></div>
          <div className="profile-head-copy">
            <h2>{user?.name || 'کاربر اکسچنج'}</h2>
            <span>{user?.email || '—'}</span>
          </div>
          <span className={`status-pill ${user?.status === 'active' ? 'success' : 'warning'}`}><span className="status-dot" /> {statusText(user?.status)}</span>
        </div>
        <div className="profile-divider" />
        <div className="detail-list">
          <DetailRow label="نام و نام خانوادگی" value={user?.name} icon={UserRound} />
          <DetailRow label="آدرس ایمیل" value={user?.email} icon={Mail} />
          <DetailRow label="وضعیت حساب" value={statusText(user?.status)} icon={ShieldCheck} />
          <DetailRow label="تاریخ عضویت" value={formatDate(user?.registered_at)} icon={CalendarDays} />
          <DetailRow label="وضعیت تأیید ایمیل" value={user?.email_verified_at ? `تأیید شده · ${formatDate(user.email_verified_at)}` : 'در این نسخه الزامی نیست'} icon={CheckCircle2} />
        </div>
        <div className="profile-info-note">
          <span className="note-mark">i</span>
          <p>در حال حاضر ویرایش اطلاعات شخصی از طریق API فعال نشده است. اطلاعات این صفحه صرفاً از سرویس Identity خوانده می‌شود.</p>
        </div>
      </section>

      <div className="page-bottom-link"><Link to="/security">برای مدیریت دسترسی‌ها به بخش امنیت حساب برو <ArrowUpLeft size={16} /></Link></div>
    </div>
  );
}
