import { Navigate, Route, Routes } from 'react-router-dom';
import AppShell from './components/AppShell.jsx';
import { GuestRoute, PrivateRoute } from './components/RouteGuards.jsx';
import {
  ForgotPasswordPage, LoginPage, RegisterPage, ResetPasswordPage,
} from './pages/AuthPages.jsx';
import AccountPage from './pages/AccountPage.jsx';
import ComingSoonPage from './pages/ComingSoonPage.jsx';
import DashboardPage from './pages/DashboardPage.jsx';
import SecurityPage from './pages/SecurityPage.jsx';

function NotFoundPage() {
  return (
    <div className="not-found">
      <strong>404</strong>
      <h1>این صفحه پیدا نشد</h1>
      <p>نشانی واردشده معتبر نیست یا این صفحه وجود ندارد.</p>
      <a href="/dashboard">بازگشت به پیشخوان</a>
    </div>
  );
}

export default function App() {
  return (
    <Routes>
      <Route element={<GuestRoute />}>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
      </Route>

      <Route element={<PrivateRoute />}>
        <Route element={<AppShell />}>
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/profile" element={<AccountPage />} />
          <Route path="/security" element={<SecurityPage />} />
          <Route path="/wallet" element={<ComingSoonPage />} />
          <Route path="/trading" element={<ComingSoonPage />} />
          <Route path="/activity" element={<ComingSoonPage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
