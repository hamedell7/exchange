import { AlertCircle, CheckCircle2, LoaderCircle } from 'lucide-react';

export function FormNotice({ children, tone = 'error' }) {
  if (!children) return null;
  const Icon = tone === 'success' ? CheckCircle2 : AlertCircle;

  return (
    <div className={`form-notice ${tone}`} role={tone === 'error' ? 'alert' : 'status'}>
      <Icon size={18} aria-hidden="true" />
      <span>{children}</span>
    </div>
  );
}

export function LoadingButton({ children, loading, className = '', ...props }) {
  return (
    <button {...props} className={className} disabled={loading || props.disabled}>
      {loading ? <LoaderCircle size={18} className="spin" aria-hidden="true" /> : null}
      <span>{children}</span>
    </button>
  );
}

export function PageLoader() {
  return (
    <div className="page-loader" role="status" aria-label="در حال بارگذاری">
      <span className="loader-mark" />
      <span>در حال آماده‌سازی فضای کاربری…</span>
    </div>
  );
}
