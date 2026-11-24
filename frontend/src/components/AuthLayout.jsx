import { ArrowLeft, ShieldCheck, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function AuthLayout({ eyebrow, title, description, children, footer }) {
  return (
    <main className="auth-page">
      <section className="auth-visual" aria-label="معرفی اکسچنج">
        <div className="auth-visual-grid" />
        <Link to="/login" className="brand brand-light">
          <span className="brand-symbol">E</span>
          <span className="brand-copy"><strong>EXCHANGE</strong><small>فضای کاربری</small></span>
        </Link>

        <div className="auth-visual-copy">
          <span className="visual-kicker"><Sparkles size={15} /> یک تجربه یکپارچه</span>
          <h2>حساب تو؛<br /><span>همیشه در کنترل تو.</span></h2>
          <p>به فضای شخصی اکسچنج خوش آمدی. حساب و دسترسی‌هایت را در محیطی ساده، شفاف و امن مدیریت کن.</p>
        </div>

        <div className="auth-visual-bottom">
          <div className="visual-security-icon"><ShieldCheck size={21} /></div>
          <div><strong>ورود امن و شفاف</strong><span>حفاظت از دسترسی، از اولین قدم</span></div>
          <span className="visual-bottom-arrow"><ArrowLeft size={18} /></span>
        </div>
        <div className="visual-orb orb-one" />
        <div className="visual-orb orb-two" />
      </section>

      <section className="auth-form-side">
        <div className="auth-form-wrap">
          <div className="auth-mobile-brand"><span className="brand-symbol">E</span><strong>EXCHANGE</strong></div>
          <header className="auth-heading">
            <span className="eyebrow">{eyebrow}</span>
            <h1>{title}</h1>
            <p>{description}</p>
          </header>
          {children}
          {footer ? <div className="auth-form-footer">{footer}</div> : null}
          <p className="auth-copyright">© {new Date().getFullYear()} Exchange · تمامی حقوق محفوظ است.</p>
        </div>
      </section>
    </main>
  );
}
