import { ArrowRight, CandlestickChart, Clock3, Wallet } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';

const content = {
  '/wallet': {
    title: 'کیف پول',
    description: 'نمایش دارایی‌ها، موجودی، گردش حساب و عملیات کیف پول در این بخش قرار می‌گیرد.',
    icon: Wallet,
    label: 'Wallet Service',
  },
  '/trading': {
    title: 'بازارها و معامله',
    description: 'بازارها، ثبت سفارش و مدیریت معاملات از این صفحه در دسترس قرار خواهند گرفت.',
    icon: CandlestickChart,
    label: 'Trading Service',
  },
  '/activity': {
    title: 'فعالیت‌های حساب',
    description: 'تاریخچه رویدادهای حساب، تراکنش‌ها و سفارش‌ها در این بخش یکپارچه می‌شود.',
    icon: Clock3,
    label: 'Activity Center',
  },
};

export default function ComingSoonPage() {
  const location = useLocation();
  const page = content[location.pathname] || content['/wallet'];
  const Icon = page.icon;

  return (
    <div className="coming-page">
      <div className="coming-orbit"><div><Icon size={34} /></div></div>
      <span className="section-eyebrow">{page.label}</span>
      <h1>{page.title}</h1>
      <p>{page.description}</p>
      <span className="coming-badge"><Clock3 size={15} /> در حال آماده‌سازی</span>
      <Link to="/dashboard" className="subtle-link"><ArrowRight size={16} /> بازگشت به پیشخوان</Link>
    </div>
  );
}
