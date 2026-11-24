import { useCallback, useEffect, useState } from 'react';
import { ArrowLeft, ArrowUpLeft, BarChart3, ChevronLeft, ChevronRight, CircleAlert, LoaderCircle, Search, ShieldCheck, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import { apiRequest } from '../api/client.js';

const number = new Intl.NumberFormat('fa-IR');
const usdtNumber = new Intl.NumberFormat('en-US', { maximumSignificantDigits: 8 });
const tomanNumber = new Intl.NumberFormat('fa-IR', { maximumSignificantDigits: 8 });
const percentNumber = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2, signDisplay: 'always' });

function formatPrice(value, formatter) {
  if (value === null || value === undefined || value === '') return '—';
  const parsed = Number(value);
  return Number.isFinite(parsed) ? formatter.format(parsed) : '—';
}

function formatPercent(value) {
  if (value === null || value === undefined || value === '') return '—';
  const parsed = Number(value);
  return Number.isFinite(parsed) ? percentNumber.format(parsed) + '٪' : '—';
}

function AssetLogo({ asset }) {
  const [failed, setFailed] = useState(false);
  if (failed || !asset.logo_url) {
    return <span className="market-asset-logo market-asset-logo-fallback" aria-hidden="true">{asset.symbol?.slice(0, 1) || '?'}</span>;
  }

  return <img className="market-asset-logo" src={asset.logo_url} alt="" loading="lazy" onError={() => setFailed(true)} />;
}

export default function MarketPage() {
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [assets, setAssets] = useState([]);
  const [meta, setMeta] = useState(null);
  const [marketData, setMarketData] = useState(null);
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
      const payload = await apiRequest('/assets?' + params.toString(), { service: 'market', auth: false, signal });
      setAssets(Array.isArray(payload?.data) ? payload.data : []);
      setMeta(payload?.meta || null);
      setMarketData(payload?.market_data || null);
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

  useEffect(() => {
    const timer = window.setInterval(() => {
      const controller = new AbortController();
      loadAssets(controller.signal);
    }, 60000);

    return () => window.clearInterval(timer);
  }, [loadAssets]);

  const currentPage = meta?.current_page || page;
  const lastPage = meta?.last_page || 1;
  const total = meta?.total ?? assets.length;
  const rateUpdatedAt = marketData?.rate_updated_at
    ? new Date(marketData.rate_updated_at).toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' })
    : null;

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
            <p>قیمت دارایی‌های دیجیتال را با نرخ USDT و تومان، همراه با تغییرات ۲۴ ساعته بررسی کن.</p>
            <div className="market-hero-actions"><a href="#markets" className="market-hero-primary">مشاهده بازارها <ArrowLeft size={17}/></a><Link to="/register" className="market-hero-secondary">شروع فعالیت</Link></div>
            <div className="market-hero-trust"><ShieldCheck size={16}/> قیمت‌ها از منابع بازار دریافت و به‌صورت دوره‌ای تازه‌سازی می‌شوند</div>
          </div>
          <div className="market-hero-art" aria-hidden="true"><div className="market-art-ring ring-a"/><div className="market-art-ring ring-b"/><div className="market-art-core"><BarChart3 size={54}/></div><span className="market-art-orbit orbit-bitcoin">₿</span><span className="market-art-orbit orbit-ethereum">◆</span><span className="market-art-orbit orbit-usdt">₮</span></div>
        </section>

        <section id="markets" className="market-list-section">
          <div className="market-section-heading"><div><span className="market-section-kicker">LIVE MARKET DATA</span><h2>بازار دارایی‌ها</h2><p>قیمت دارایی‌های فعال به‌ترتیب رتبه بازار</p></div><div className="market-count"><span className="market-count-dot"/>{number.format(total)} دارایی</div></div>
          <div className="market-toolbar"><label className="market-search"><Search size={18}/><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="جست‌وجوی نام یا نماد دارایی..." aria-label="جست‌وجوی دارایی"/></label><span className="market-source-note">قیمت: CoinMarketCap · نرخ تتر: Nobitex{rateUpdatedAt ? ' · آخرین نرخ: ' + rateUpdatedAt : ''}</span></div>

          <div className="market-table-wrap">
            <table className="market-table">
              <thead><tr><th>رتبه</th><th>دارایی</th><th>قیمت (USDT)</th><th>قیمت (تومان)</th><th>تغییر ۲۴ ساعت</th><th>اطلاعات</th></tr></thead>
              <tbody>
                {loading && assets.length === 0 ? <tr><td colSpan="6"><div className="market-state"><LoaderCircle className="spin" size={23}/> در حال دریافت قیمت‌ها و دارایی‌ها...</div></td></tr>
                  : error && assets.length === 0 ? <tr><td colSpan="6"><div className="market-state market-error"><CircleAlert size={22}/><strong>دریافت اطلاعات انجام نشد</strong><span>{error}</span><button onClick={() => { const controller = new AbortController(); loadAssets(controller.signal); }}>تلاش دوباره</button></div></td></tr>
                    : assets.length === 0 ? <tr><td colSpan="6"><div className="market-state">دارایی مطابق جست‌وجوی شما پیدا نشد.</div></td></tr>
                      : assets.map((asset) => {
                        const change = asset.percent_change_24h === null || asset.percent_change_24h === undefined
                          ? null
                          : Number(asset.percent_change_24h);
                        const changeClass = change === null || !Number.isFinite(change)
                          ? ''
                          : change >= 0 ? 'market-change-positive' : 'market-change-negative';

                        return (
                          <tr key={asset.id}>
                            <td><span className="market-rank">{number.format(asset.rank || 0)}</span></td>
                            <td><div className="market-asset-cell"><AssetLogo asset={asset}/><div><strong>{asset.name}</strong><small>{asset.slug}</small></div></div></td>
                            <td><span className="market-price-value">{formatPrice(asset.price_usdt, usdtNumber)} <small>USDT</small></span></td>
                            <td><span className="market-price-value">{formatPrice(asset.price_toman, tomanNumber)} <small>تومان</small></span></td>
                            <td><span className={'market-change ' + changeClass}>{formatPercent(change)}</span></td>
                            <td>{asset.website_url ? <a className="market-info-link" href={asset.website_url} target="_blank" rel="noreferrer">وب‌سایت <ArrowUpLeft size={13}/></a> : <span className="market-muted">—</span>}</td>
                          </tr>
                        );
                      })}
              </tbody>
            </table>
          </div>
          <div className="market-pagination"><span>صفحه {number.format(currentPage)} از {number.format(lastPage)}</span><div><button aria-label="صفحه قبل" disabled={loading || currentPage <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}><ChevronRight size={18}/></button><button aria-label="صفحه بعد" disabled={loading || currentPage >= lastPage} onClick={() => setPage((value) => value + 1)}><ChevronLeft size={18}/></button></div></div>
          <p className="market-disclaimer">قیمت USDT از CoinMarketCap و نرخ تبدیل USDT به تومان از بازار نوبیتکس دریافت می‌شود. اطلاعات قیمت به‌صورت دوره‌ای به‌روزرسانی می‌شود و ممکن است با قیمت قابل معامله در صرافی‌های دیگر تفاوت داشته باشد.</p>
        </section>

        <section id="about" className="market-cta"><div><span>EXCHANGE ACCOUNT</span><h2>برای امکانات بعدی آماده‌ای؟</h2><p>حساب کاربری بساز تا با تکمیل سرویس‌ها، امکانات اکسچنج در دسترس تو قرار بگیرد.</p></div><Link to="/register">ایجاد حساب کاربری <ArrowUpLeft size={17}/></Link></section>
      </main>
      <footer className="market-footer"><Link to="/markets" className="market-footer-brand">EXCHANGE</Link><span>© {new Date().getFullYear()} Exchange</span><span>قیمت‌ها جهت اطلاع‌رسانی هستند.</span></footer>
    </div>
  );
}
