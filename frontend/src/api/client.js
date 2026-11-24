const API_ORIGIN = (import.meta.env.VITE_API_ORIGIN || '').replace(/\/$/, '');
const API_PREFIXES = Object.freeze({
  identity: '/api/v1/identity',
  wallet: '/api/v1/wallet',
  trading: '/api/v1/trading',
});
const TOKEN_KEY = 'exchange.identity.access_token';
const EXPIRY_KEY = 'exchange.identity.expires_at';

export class ApiError extends Error {
  constructor(status, payload) {
    super(payload?.message || 'در ارتباط با سرویس مشکلی پیش آمد.');
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload || {};
  }
}

export function getAccessToken() {
  return sessionStorage.getItem(TOKEN_KEY);
}

export function getTokenExpiry() {
  return sessionStorage.getItem(EXPIRY_KEY);
}

export function saveSession(accessToken, expiresAt) {
  sessionStorage.setItem(TOKEN_KEY, accessToken);
  if (expiresAt) sessionStorage.setItem(EXPIRY_KEY, expiresAt);
  else sessionStorage.removeItem(EXPIRY_KEY);
}

export function clearSession() {
  sessionStorage.removeItem(TOKEN_KEY);
  sessionStorage.removeItem(EXPIRY_KEY);
}

/**
 * Shared API transport for every Exchange service.
 * Service-specific authentication contracts are intentionally not assumed:
 * only Identity requests currently use the Sanctum bearer token.
 */
export async function apiRequest(path, options = {}) {
  const {
    method = 'GET',
    body,
    token = getAccessToken(),
    auth = true,
    service = 'identity',
    signal,
  } = options;

  const prefix = API_PREFIXES[service];
  if (!prefix) throw new Error(`Unknown API service: ${service}`);

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const headers = new Headers({ Accept: 'application/json' });

  if (body !== undefined) headers.set('Content-Type', 'application/json');
  if (auth && token && service === 'identity') {
    headers.set('Authorization', `Bearer ${token}`);
  }

  let response;

  try {
    response = await fetch(`${API_ORIGIN}${prefix}${normalizedPath}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      credentials: 'omit',
      signal,
    });
  } catch {
    throw new Error('ارتباط با سرور برقرار نشد. روشن بودن API Gateway را بررسی کن.');
  }

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new ApiError(response.status, payload);

  return payload;
}

const labels = {
  name: 'نام',
  email: 'ایمیل',
  password: 'رمز عبور',
  password_confirmation: 'تکرار رمز عبور',
  token: 'لینک بازنشانی',
};

export function getFieldError(error, field) {
  const message = error?.payload?.errors?.[field]?.[0];
  if (!message) return '';

  if (/required/i.test(message)) return `${labels[field] || 'این فیلد'} را وارد کن.`;
  if (/valid email/i.test(message)) return 'فرمت ایمیل معتبر نیست.';
  if (/already been taken|already registered/i.test(message)) return 'این ایمیل قبلاً ثبت شده است.';
  if (/at least (\d+) characters/i.test(message)) return `این فیلد باید حداقل ${message.match(/at least (\d+) characters/i)?.[1] || 15} نویسه داشته باشد.`;
  if (/confirmation does not match/i.test(message)) return 'تکرار رمز عبور با رمز اصلی یکسان نیست.';
  if (/invalid or has expired/i.test(message)) return 'لینک نامعتبر است یا اعتبار آن به پایان رسیده است.';
  if (/not allowed/i.test(message)) return 'این مقدار در درخواست مجاز نیست.';

  return message;
}

export function getApiErrorMessage(error, fallback = 'عملیات انجام نشد. دوباره تلاش کن.') {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'ایمیل یا رمز عبور صحیح نیست، یا نشست شما اعتبار ندارد.';
    if (error.status === 403) return 'دسترسی به این بخش مجاز نیست.';
    if (error.status === 409) return 'این ایمیل قبلاً در اکسچنج ثبت شده است.';
    if (error.status === 422) {
      const field = Object.keys(error.payload?.errors || {})[0];
      return getFieldError(error, field) || 'اطلاعات واردشده معتبر نیست. فیلدها را بررسی کن.';
    }
    if (error.status === 429) return 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کن.';
    if (error.status >= 500) return 'سرویس فعلاً پاسخ‌گو نیست. کمی بعد دوباره تلاش کن.';
  }

  return error?.message || fallback;
}
