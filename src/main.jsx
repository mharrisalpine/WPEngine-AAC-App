
import React from 'react';
import ReactDOM from 'react-dom/client';
import { Route, Routes } from 'react-router-dom';
import App from '@/App';
import '@/index.css';
import { Toaster } from '@/components/ui/toaster';
import { AuthProvider } from '@/contexts/AppAuthContext';
import { AppRouter } from '@/lib/router';
import MemberPortal from '@/pages/MemberPortal';
import ContactPage from '@/pages/ContactPage';
import MembershipManagementPage from '@/pages/MembershipManagementPage';
import LoginPage from '@/pages/LoginPage';
import MemberProfilePage from '@/pages/MemberProfilePage';
import ChangePasswordPage from '@/pages/ChangePasswordPage';
import PublicationsPage from '@/pages/PublicationsPage';
import RescuePage from '@/pages/RescuePage';
import LinkedAccountsPage from '@/pages/LinkedAccountsPage';
import MemberJoinPage from '@/pages/MemberJoinPage';
import HomePage from '@/pages/HomePage';
import { getAppRuntimeConfig } from '@/lib/backendConfig';
import PortalRouteErrorBoundary from '@/components/PortalRouteErrorBoundary';

const WP_PORTAL_MOUNT_ID = 'aac-member-portal-root';
const config = getAppRuntimeConfig();
const preferredId = config?.mountId || WP_PORTAL_MOUNT_ID;
const mountElement =
  document.getElementById(preferredId) ||
  document.getElementById(WP_PORTAL_MOUNT_ID) ||
  document.getElementById('root');
if (!mountElement) {
  throw new Error(
    `AAC Member Portal mount element not found (tried #${preferredId}, #${WP_PORTAL_MOUNT_ID}, #root).`
  );
}

ReactDOM.createRoot(mountElement).render(
  <PortalRouteErrorBoundary>
    <AppRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<App />}>
            <Route index element={<MemberProfilePage />} />
            <Route path="profile" element={<MemberProfilePage />} />
            <Route path="change-password" element={<ChangePasswordPage />} />
            <Route path="discounts" element={<MemberPortal portalTab="discounts" />} />
            <Route path="home" element={<HomePage />} />
            <Route path="join" element={<MemberJoinPage />} />
            <Route path="publications" element={<PublicationsPage />} />
            <Route path="rescue" element={<RescuePage />} />
            <Route path="linked-accounts" element={<LinkedAccountsPage />} />
            <Route path="membership" element={<MembershipManagementPage />} />
            <Route path="membership/upgrade" element={<MembershipManagementPage standaloneUpgrade />} />
            <Route path="contact" element={<ContactPage />} />
            <Route path="account" element={<MemberPortal portalTab="account" />} />
            <Route path="login" element={<LoginPage />} />
          </Route>
        </Routes>
        <Toaster />
      </AuthProvider>
    </AppRouter>
  </PortalRouteErrorBoundary>
);
