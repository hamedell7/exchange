import { useState } from 'react';
import {
  Activity, ArrowLeft, Bell, ChevronLeft, CircleHelp, LayoutDashboard,
  LogOut, Menu, ShieldCheck, UserRound, Wallet, X, CandlestickChart,
} from 'lucide-react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext.jsx';

const navigation = [
  { to: '/dashboard', label: 'پیشخوان', icon: LayoutDashboard, end: true },
  { to: '/wallet', label: 'کیف پول', icon: Wallet, disabled: true },
  { to: '/trading', label: 'بازارها و معامله', icon: CandlestickChart, disabled: true },
  { to: '/activity', label: 'فعالیت‌ها', icon: Activity, disabled: true },
];

const accountNavigation = [
  { to: '/profile', label: 'اطلاعات حساب', icon: UserRound },
  { to: '/security', label: 'امنیت حساب', icon: ShieldCheck },
];

const pageTitles = {
  '/dashboard': 'نمای کلی',
  '/profile': 'اطلاعات حساب',
  '/security': 'امنیت حساب',
  '/wallet': 'کیف پول',
  '/trading': 'بازارها و معامله',
  '/activity': 'فعالیت‌ها',
};

function initials(name = '') {
  return name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('') || 'EX';
}

export default function AppShell() {
  const { user, signOut } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();
  const pageTitle = pageTitles[location.pathname] || 'پنل کاربری';

  async function handleSignOut() {
    if (loggingOut) return;
    setLoggingOut(true);
    await signOut();
    navigate('/login', { replace: true });
  }

  const navLink = ({ to, label, icon: Icon, disabled, end }) => {
    if (disabled) {
      return (
        <div key={to} className="sidebar-link disabled" aria-disabled="true" title="این بخش در مرحله بعد فعال می‌شود">
          <Icon size={19} strokeWidth={1.8} />
          <span>{label}</span>
          <small>به‌زودی</small>
        </div>
      );
    }

    return (
      <NavLink
        key={to}
        to={to}
        end={end}
        className={({ isActive }) => `sidebar-link ${isActive ? 'active' : ''}`}
        onClick={() => setMobileOpen(false)}
      >
        <Icon size={19} strokeWidth={1.8} />
        <span>{label}</span>
        <ChevronLeft className="sidebar-chevron" size={15} />
      </NavLink>
    );
  };

  return (
    <div className="app-shell">
      {mobileOpen ? <button className="sidebar-backdrop" aria-label="بستن منو" onClick={() => setMobileOpen(false)} /> : null}

      <aside className={`sidebar ${mobileOpen ? 'sidebar-open' : ''}`}>
        <div className="sidebar-top">
          <NavLink to="/dashboard" className="brand" onClick={() => setMobileOpen(false)}>
            <span className="brand-symbol">E</span>
            <span className="brand-copy"><strong>EXCHANGE</strong><small>فضای کاربری</small></span>
          </NavLink>
          <button className="mobile-close icon-button" onClick={() => setMobileOpen(false)} aria-label="بستن منو"><X size={20} /></button>
        </div>

        <div className="sidebar-section-label">خدمات اکسچنج</div>
        <nav className="sidebar-nav" aria-label="خدمات اصلی">
          {navigation.map(navLink)}
        </nav>

        <div className="sidebar-section-label sidebar-section-account">مدیریت حساب</div>
        <nav className="sidebar-nav" aria-label="مدیریت حساب">
          {accountNavigation.map(navLink)}
        </nav>

        <div className="sidebar-growth-card">
          <span className="growth-icon"><CircleHelp size={19} /></span>
          <strong>به راهنمایی نیاز داری؟</strong>
          <p>راهنمای استفاده از فضای کاربری به‌زودی در دسترس قرار می‌گیرد.</p>
          <span className="growth-soon">به‌زودی</span>
        </div>

        <div className="sidebar-user">
          <span className="avatar avatar-small">{initials(user?.name)}</span>
          <div className="sidebar-user-copy"><strong>{user?.name || 'کاربر اکسچنج'}</strong><span>{user?.email || ''}</span></div>
          <button className="signout-icon" onClick={handleSignOut} disabled={loggingOut} aria-label="خروج از حساب" title="خروج">
            <LogOut size={17} />
          </button>
        </div>
      </aside>

      <div className="app-main">
        <header className="topbar">
          <div className="topbar-leading">
            <button className="mobile-menu icon-button" onClick={() => setMobileOpen(true)} aria-label="باز کردن منو"><Menu size={21} /></button>
            <div className="breadcrumbs"><span>فضای کاربری</span><ChevronLeft size={15} /><strong>{pageTitle}</strong></div>
          </div>
          <div className="topbar-trailing">
            <span className="topbar-date">{new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium' }).format(new Date())}</span>
            <span className="topbar-divider" />
            <div className="topbar-account">
              <span className="avatar avatar-top">{initials(user?.name)}</span>
              <div><strong>{user?.name || 'کاربر'}</strong><span>حساب شخصی</span></div>
            </div>
            <button className="topbar-help" onClick={() => navigate('/security')} title="امنیت حساب"><Bell size={18} /></button>
          </div>
        </header>

        <main className="page-content"><Outlet /></main>

        <footer className="app-footer">
          <span>© {new Date().getFullYear()} Exchange</span>
          <span>فضای امن مدیریت حساب کاربری</span>
          <button onClick={() => navigate('/security')}>امنیت حساب <ArrowLeft size={14} /></button>
        </footer>
      </div>
    </div>
  );
}
