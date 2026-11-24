import { useCallback, useEffect, useState } from 'react';
import { ArrowLeft, ArrowUpLeft, BarChart3, ChevronLeft, ChevronRight, CircleAlert, LoaderCircle, Search, ShieldCheck, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import { apiRequest } from '../api/client.js';

const number = new Intl.NumberFormat('fa-IR');
const fallback = (symbol = '') => `https://ui-avatars.com/api/?name=${encodeURIComponent(symbol)}&background=edf3f7&color=29445b&bold=true&size=80`;

function AssetLogo({ asset }) {
  const [failed, setFailed] = useState(false);
  return (
    <img
      className="market-asset-logo"
      src={!failed && asset.logo_url ? asset.logo_url : fallback(asset.symbol)}
      alt=""
      loading="lazy"
      onError={() => setFailed(true)}
    />
  );
}

export default function MarketPage() {
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [assets, setAssets] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setPage(1);
      setQuery(search.trim());
    }, 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  const loadAssets = useCallback(async (signal) => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ per_page: '50', page: String(page) });
      if (query) params.set('search', query);
      const payload = await apiRequest(`/assets?${params.toString()}`, { service: 'market', auth: false, signal });
      setAssets(Array.isArray(payload?.data) ? payload.data : []);
      setMeta(payload?.meta || null);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'دریافت فهرست دارایی‌ها ناموفق بود.');
      }
    } finally {
      if (!signal.aborted) setLoading(false);
    }
  }, [page, query]);

  useEffect(() => {
    const controller = new AbortController();
    loadAssets(controller.signal);
    return () => controller.abort();
  }, [loadAssets]);

  const currentPage = meta?.current_page || page;
  const lastPage = meta?.last_page || 1;
  const total = meta?.total ?? assets.length;

  return (
    <div className="market-site">
      <header className="market-nav">
        <Link to="/markets" className="market-brand"><span className="market-brand-mark">E</span><span><strong>EXCHANGE</strong><small>بازار دارایی‌های دیجیتال</small></span></Link>
        <nav aria-label="ناوبری اصلی"><a className="market-nav-active" href="#markets">بازارها</a><a href="#about">درباره ما</a></nav>
        <div className="market-nav-actions"><Link className="market-login" to="/login">ورود</Link><Link className="market-register" to="/register">ایجاد حساب <ArrowUpLeft size={15}/></Link></div>
      </header>

      <main>
        <section className="market-hero">
          <div className="market-hero-glow market-glow-one"/><div className="market-hero-glow market-glow-two"/>
          <div className="market-hero-content">
            <span className="market-eyebrow"><Sparkles size={14}/> بازار رمزارزها در یک نگاه</span>
            <h1>دنیای دارایی‌های دیجیتال،<br/><span>شفاف و یکپارچه</span></h1>
            <p>فهرست دارایی‌های شناخته‌شده بازار را بررسی کن و برای شروع، حساب کاربری خودت را بساز.</p>
            <div className="market-hero-actions"><a href="#markets" className="market-hero-primary">مشاهده دارایی‌ها <ArrowLeft size={17}/></a><Link to="/register" className="market-hero-secondary">شروع فعالیت</Link></div>
            <div className="market-hero-trust"><ShieldCheck size={16}/> اطلاعات پایه دارایی‌ها از منبع داده بازار دریافت می‌شود</div>
          </div>
          <div className="market-hero-art" aria-hidden="true"><div className="market-art-ring ring-a"/><div className="market-art-ring ring-b"/><div className="market-art-core"><BarChart3 size={54}/></div><span className="market-art-orbit orbit-bitcoin">₿</span><span className="market-art-orbit orbit-ethereum">◆</span><span className="market-art-orbit orbit-usdt">₮</span></div>
        </section>

        <section id="markets" className="market-list-section">
          <div className="market-section-heading"><div><span className="market-section-kicker">ASSET DIRECTORY</span><h2>فهرست دارایی‌ها</h2><p>دارایی‌های فعال مرتب‌شده بر اساس رتبه بازار</p></div><div className="market-count"><span className="market-count-dot"/>{number.format(total)} دارایی</div></div>
          <div className="market-toolbar"><label className="market-search"><Search size={18}/><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="جست‌وجوی نام یا نماد دارایی..." aria-label="جست‌وجوی دارایی"/></label><span className="market-source-note">منبع: CoinMarketCap</span></div>

          <div className="market-table-wrap">
            <table className="market-table">
              <thead><tr><th>رتبه</th><th>دارایی</th><th>نماد</th><th>شناسه</th><th>اطلاعات</th></tr></thead>
              <tbody>
                {loading ? <tr><td colSpan="5"><div className="market-state"><LoaderCircle className="spin" size={23}/> در حال دریافت دارایی‌ها...</div></td></tr>
                  : error ? <tr><td colSpan="5"><div className="market-state market-error"><CircleAlert size={22}/><strong>دریافت اطلاعات انجام نشد</strong><span>{error}</span><button onClick={() => { const controller = new AbortController(); loadAssets(controller.signal); }}>تلاش دوباره</button></div></td></tr>
                    : assets.length === 0 ? <tr><td colSpan="5"><div className="market-state">دارایی مطابق جست‌وجوی شما پیدا نشد.</div></td></tr>
                      : assets.map((asset) => <tr key={asset.id}><td><span className="market-rank">{number.format(asset.rank || 0)}</span></td><td><div className="market-asset-cell"><AssetLogo asset={asset}/><div><strong>{asset.name}</strong><small>{asset.slug}</small></div></div></td><td><span className="market-symbol">{asset.symbol}</span></td><td><span className="market-id">{asset.id}</span></td><td>{asset.website_url ? <a className="market-info-link" href={asset.website_url} target="_blank" rel="noreferrer">وب‌سایت <ArrowUpLeft size={13}/></a> : <span className="market-muted">—</span>}</td></tr>)}
              </tbody>
            </table>
          </div>
          <div className="market-pagination"><span>صفحه {number.format(currentPage)} از {number.format(lastPage)}</span><div><button aria-label="صفحه قبل" disabled={loading || currentPage <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}><ChevronRight size={18}/></button><button aria-label="صفحه بعد" disabled={loading || currentPage >= lastPage} onClick={() => setPage((value) => value + 1)}><ChevronLeft size={18}/></button></div></div>
          <p className="market-disclaimer">این صفحه فقط اطلاعات پایه دارایی‌ها را نمایش می‌دهد. قیمت لحظه‌ای، تغییرات بازار و امکان معامله هنوز در این نسخه فعال نیستند.</p>
        </section>

        <section id="about" className="market-cta"><div><span>EXCHANGE ACCOUNT</span><h2>برای امکانات بعدی آماده‌ای؟</h2><p>حساب کاربری بساز تا با تکمیل سرویس‌ها، امکانات اکسچنج در دسترس تو قرار بگیرد.</p></div><Link to="/register">ایجاد حساب کاربری <ArrowUpLeft size={17}/></Link></section>
      </main>
      <footer className="market-footer"><Link to="/markets" className="market-footer-brand">EXCHANGE</Link><span>© {new Date().getFullYear()} Exchange</span><span>اطلاعات دارایی‌ها صرفاً جهت معرفی است.</span></footer>
    </div>
  );
}
