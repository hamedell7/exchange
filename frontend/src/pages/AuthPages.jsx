import { useMemo, useState } from 'react';
import {
  ArrowLeft, KeyRound, LockKeyhole, Mail, UserRound,
} from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { apiRequest, getApiErrorMessage, getFieldError } from '../api/client.js';
import { useAuth } from '../auth/AuthContext.jsx';
import AuthLayout from '../components/AuthLayout.jsx';
import { Field } from '../components/Field.jsx';
import { FormNotice, LoadingButton } from '../components/Feedback.jsx';

function AuthLink({ to, children }) {
  return <Link className="auth-link" to={to}>{children} <ArrowLeft size={15} /></Link>;
}

export function LoginPage() {
  const { signIn } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setLoading(true);

    try {
      await signIn(form.email, form.password);
      navigate(location.state?.from?.pathname || '/dashboard', { replace: true });
    } catch (requestError) {
      setError(getApiErrorMessage(requestError, 'ورود انجام نشد. اطلاعات را بررسی کن.'));
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthLayout
      eyebrow="ورود به حساب"
      title="خوش برگشتی!"
      description="برای ورود به فضای کاربری، اطلاعات حساب خودت را وارد کن."
      footer={<>حساب کاربری نداری؟ <AuthLink to="/register">ساخت حساب جدید</AuthLink></>}
    >
      {location.state?.message ? <FormNotice tone="success">{location.state.message}</FormNotice> : null}
      <FormNotice>{error}</FormNotice>
      <form className="auth-form" onSubmit={handleSubmit}>
        <Field
          id="login-email"
          label="ایمیل"
          type="email"
          icon={Mail}
          placeholder="name@example.com"
          autoComplete="email"
          value={form.email}
          onChange={(event) => setForm({ ...form, email: event.target.value })}
          required
          maxLength={254}
        />
        <div className="field-stack">
          <div className="field-row">
            <label className="field-label" htmlFor="login-password">رمز عبور</label>
            <AuthLink to="/forgot-password">رمز عبور را فراموش کردی؟</AuthLink>
          </div>
          <Field
            id="login-password"
            type="password"
            icon={LockKeyhole}
            placeholder="رمز عبور حساب"
            autoComplete="current-password"
            value={form.password}
            onChange={(event) => setForm({ ...form, password: event.target.value })}
            required
            maxLength={128}
          />
        </div>
        <LoadingButton className="primary-button auth-submit" type="submit" loading={loading}>
          ورود به حساب <ArrowLeft size={17} />
        </LoadingButton>
      </form>
      <div className="auth-assurance"><LockKeyhole size={15} /> اطلاعات ورود به‌صورت امن ارسال می‌شود.</div>
    </AuthLayout>
  );
}

export function RegisterPage() {
  const navigate = useNavigate();
  const [form, setForm] = useState({
    name: '', email: '', password: '', password_confirmation: '',
  });
  const [errors, setErrors] = useState({});
  const [notice, setNotice] = useState('');
  const [loading, setLoading] = useState(false);

  function update(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: '' }));
    setNotice('');
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setErrors({});
    setNotice('');
    setLoading(true);

    try {
      await apiRequest('/register', {
        method: 'POST',
        auth: false,
        body: form,
      });

      navigate('/login', {
        replace: true,
        state: { message: 'حساب کاربری با موفقیت ساخته شد. حالا می‌توانی وارد شوی.' },
      });
    } catch (error) {
      setNotice(getApiErrorMessage(error, 'ثبت‌نام انجام نشد. دوباره تلاش کن.'));
      setErrors({
        name: getFieldError(error, 'name'),
        email: getFieldError(error, 'email'),
        password: getFieldError(error, 'password'),
        password_confirmation: getFieldError(error, 'password_confirmation'),
      });
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthLayout
      eyebrow="شروع با اکسچنج"
      title="حساب خودت را بساز"
      description="با ساخت حساب، به فضای شخصی و خدمات یکپارچه اکسچنج دسترسی خواهی داشت."
      footer={<>قبلاً ثبت‌نام کرده‌ای؟ <AuthLink to="/login">ورود به حساب</AuthLink></>}
    >
      <FormNotice>{notice}</FormNotice>
      <form className="auth-form compact-form" onSubmit={handleSubmit}>
        <Field
          id="register-name"
          label="نام و نام خانوادگی"
          icon={UserRound}
          placeholder="نامی که با آن شناخته می‌شوی"
          autoComplete="name"
          value={form.name}
          onChange={(event) => update('name', event.target.value)}
          error={errors.name}
          required
          minLength={2}
          maxLength={255}
        />
        <Field
          id="register-email"
          label="ایمیل"
          type="email"
          icon={Mail}
          placeholder="name@example.com"
          autoComplete="email"
          value={form.email}
          onChange={(event) => update('email', event.target.value)}
          error={errors.email}
          required
          maxLength={254}
        />
        <Field
          id="register-password"
          label="رمز عبور"
          type="password"
          icon={LockKeyhole}
          placeholder="حداقل ۱۵ نویسه"
          autoComplete="new-password"
          value={form.password}
          onChange={(event) => update('password', event.target.value)}
          error={errors.password}
          hint="برای امنیت بیشتر، از عبارت طولانی و منحصربه‌فرد استفاده کن."
          required
          minLength={15}
          maxLength={128}
        />
        <Field
          id="register-confirm-password"
          label="تکرار رمز عبور"
          type="password"
          icon={LockKeyhole}
          placeholder="رمز عبور را دوباره وارد کن"
          autoComplete="new-password"
          value={form.password_confirmation}
          onChange={(event) => update('password_confirmation', event.target.value)}
          error={errors.password_confirmation}
          required
          minLength={15}
          maxLength={128}
        />
        <LoadingButton className="primary-button auth-submit" type="submit" loading={loading}>
          ساخت حساب کاربری <ArrowLeft size={17} />
        </LoadingButton>
      </form>
      <div className="auth-assurance"><LockKeyhole size={15} /> اطلاعات حساب تو با رمزنگاری امن نگهداری می‌شود.</div>
    </AuthLayout>
  );
}

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      const response = await apiRequest('/password/forgot', {
        method: 'POST',
        auth: false,
        body: { email: email.trim() },
      });
      setSuccess(response?.message || 'در صورت وجود حساب، راهنمای بازنشانی رمز عبور برای ایمیل شما ارسال می‌شود.');
    } catch (requestError) {
      setError(getApiErrorMessage(requestError));
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthLayout
      eyebrow="بازیابی دسترسی"
      title="رمز عبورت یادت رفته؟"
      description="ایمیل حساب خودت را وارد کن تا در صورت وجود حساب، لینک بازنشانی برایت ارسال شود."
      footer={<>رمزت را به یاد آوردی؟ <AuthLink to="/login">بازگشت به ورود</AuthLink></>}
    >
      <FormNotice>{error}</FormNotice>
      <FormNotice tone="success">{success}</FormNotice>
      <form className="auth-form" onSubmit={handleSubmit}>
        <Field
          id="forgot-email"
          label="ایمیل حساب"
          type="email"
          icon={Mail}
          placeholder="name@example.com"
          autoComplete="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          maxLength={254}
        />
        <LoadingButton className="primary-button auth-submit" type="submit" loading={loading}>
          ارسال لینک بازیابی <ArrowLeft size={17} />
        </LoadingButton>
      </form>
    </AuthLayout>
  );
}

export function ResetPasswordPage() {
  const navigate = useNavigate();
  const token = useMemo(() => new URLSearchParams(window.location.hash.slice(1)).get('token') || '', []);
  const [form, setForm] = useState({ password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  function update(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: '' }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setSuccess('');
    setErrors({});
    setLoading(true);

    try {
      const response = await apiRequest('/password/reset', {
        method: 'POST',
        auth: false,
        body: { token, ...form },
      });

      setSuccess(response?.message || 'رمز عبور با موفقیت تغییر کرد.');
      window.history.replaceState({}, document.title, window.location.pathname);
      window.setTimeout(() => navigate('/login', { replace: true }), 1800);
    } catch (requestError) {
      setError(getApiErrorMessage(requestError));
      setErrors({
        token: getFieldError(requestError, 'token'),
        password: getFieldError(requestError, 'password'),
        password_confirmation: getFieldError(requestError, 'password_confirmation'),
      });
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthLayout
      eyebrow="بازیابی دسترسی"
      title="رمز عبور جدید"
      description="یک رمز عبور جدید و منحصربه‌فرد برای حساب خود انتخاب کن."
      footer={<>به مشکل خوردی؟ <AuthLink to="/forgot-password">درخواست لینک جدید</AuthLink></>}
    >
      {!token ? <FormNotice>توکن بازیابی در این لینک پیدا نشد. یک لینک جدید درخواست کن.</FormNotice> : null}
      <FormNotice>{error}</FormNotice>
      <FormNotice tone="success">{success}</FormNotice>
      <form className="auth-form" onSubmit={handleSubmit}>
        <Field
          id="reset-password"
          label="رمز عبور جدید"
          type="password"
          icon={KeyRound}
          placeholder="حداقل ۱۵ نویسه"
          autoComplete="new-password"
          value={form.password}
          onChange={(event) => update('password', event.target.value)}
          error={errors.password}
          required
          minLength={15}
          maxLength={128}
        />
        <Field
          id="reset-confirm-password"
          label="تکرار رمز عبور جدید"
          type="password"
          icon={LockKeyhole}
          placeholder="رمز عبور را دوباره وارد کن"
          autoComplete="new-password"
          value={form.password_confirmation}
          onChange={(event) => update('password_confirmation', event.target.value)}
          error={errors.password_confirmation}
          required
          minLength={15}
          maxLength={128}
        />
        <LoadingButton className="primary-button auth-submit" type="submit" loading={loading} disabled={!token}>
          ثبت رمز عبور جدید <ArrowLeft size={17} />
        </LoadingButton>
      </form>
    </AuthLayout>
  );
}
