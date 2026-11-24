import { useState } from 'react';
import { ArrowLeft, CheckCircle2, KeyRound, LockKeyhole, ShieldCheck } from 'lucide-react';
import { Link } from 'react-router-dom';
import { apiRequest, getApiErrorMessage, getFieldError } from '../api/client.js';
import { useAuth } from '../auth/AuthContext.jsx';
import { Field } from '../components/Field.jsx';
import { FormNotice, LoadingButton } from '../components/Feedback.jsx';

function expiryLabel(value) {
  if (!value) return 'در دسترس نیست';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'در دسترس نیست';
  return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'long', timeStyle: 'short' }).format(date);
}

export default function SecurityPage() {
  const { user, expiresAt } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  async function requestPasswordReset(event) {
    event.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      const response = await apiRequest('/password/forgot', {
        method: 'POST',
        auth: false,
        body: { email: user.email },
      });
      setSuccess(response?.message || 'در صورت امکان، لینک بازنشانی برای ایمیل حساب ارسال می‌شود.');
    } catch (requestError) {
      setError(getApiErrorMessage(requestError));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="inner-page">
      <div className="page-intro">
        <div><span className="section-eyebrow">SECURITY CENTER</span><h1>امنیت حساب</h1><p>وضعیت دسترسی و راه‌های محافظت از حساب خودت را مدیریت کن.</p></div>
        <span className="intro-icon"><ShieldCheck size={23} /></span>
      </div>

      <section className="security-summary-card">
        <div className="security-summary-graphic"><ShieldCheck size={35} /></div>
        <div className="security-summary-copy">
          <span className="security-summary-kicker">IDENTITY PROTECTION</span>
          <h2>دسترسی حساب تو محافظت می‌شود</h2>
          <p>ورود با توکن اختصاصی انجام می‌شود و امکان لغو نشست فعلی با خروج از حساب وجود دارد.</p>
        </div>
        <span className="status-pill success"><span className="status-dot" /> سرویس فعال</span>
      </section>

      <section className="settings-card">
        <div className="settings-card-heading"><div><span className="section-eyebrow">SIGN-IN DETAILS</span><h2>جزئیات ورود فعلی</h2></div><LockKeyhole size={20} /></div>
        <div className="security-detail-list">
          <div className="security-detail-row"><span>روش احراز هویت</span><strong>ایمیل و رمز عبور · توکن Bearer</strong></div>
          <div className="security-detail-row"><span>نام دستگاه</span><strong>Exchange Web</strong></div>
          <div className="security-detail-row"><span>اعتبار توکن فعلی تا</span><strong>{expiryLabel(expiresAt)}</strong></div>
          <div className="security-detail-row"><span>وضعیت حساب</span><strong className="inline-success"><CheckCircle2 size={16} /> {user?.status === 'active' ? 'فعال' : 'نیازمند بررسی'}</strong></div>
        </div>
        <p className="settings-footnote">توکن دسترسی در حافظه نشست مرورگر نگهداری می‌شود و با خروج از حساب، از مرورگر پاک و در Identity لغو می‌شود.</p>
      </section>

      <section className="settings-card password-card">
        <div className="password-card-icon"><KeyRound size={21} /></div>
        <div className="password-card-copy">
          <h2>تغییر رمز عبور</h2>
          <p>برای دریافت لینک امن بازنشانی رمز عبور به ایمیل ثبت‌شده حسابت، درخواست زیر را ارسال کن.</p>
          <FormNotice>{error}</FormNotice>
          <FormNotice tone="success">{success}</FormNotice>
          <form onSubmit={requestPasswordReset}>
            <div className="password-email"><span>ایمیل حساب</span><strong dir="ltr">{user?.email}</strong></div>
            <LoadingButton className="primary-button" type="submit" loading={loading}>ارسال لینک بازنشانی <ArrowLeft size={16} /></LoadingButton>
          </form>
        </div>
      </section>

      <div className="security-warning">
        <span className="warning-mark">!</span>
        <p>نمایش نشست‌های فعال روی دستگاه‌های دیگر و خروج از همه دستگاه‌ها هنوز در API پیاده‌سازی نشده است. توکن فعلی را در اختیار دیگران قرار نده.</p>
      </div>
    </div>
  );
}
