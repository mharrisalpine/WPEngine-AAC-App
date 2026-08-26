
import React, { useState, useEffect, useLayoutEffect } from 'react';
import { Helmet } from 'react-helmet';
import { Outlet, useLocation } from 'react-router-dom';
import { Toaster } from '@/components/ui/toaster';
import { toast } from '@/components/ui/use-toast';
import PortalSidebar from '@/components/PortalSidebar';
import { useAuth } from '@/hooks/useAuth';
import { Button } from '@/components/ui/button';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getExpirationWarningDetails, shouldPromptMembershipVerification } from '@/lib/membershipRenewal';
import HomePage from '@/pages/HomePage';
import LoginPage from '@/pages/LoginPage';
import MemberJoinPage from '@/pages/MemberJoinPage';
import { getAppRuntimeConfig } from '@/lib/backendConfig';
import PortalRouteErrorBoundary from '@/components/PortalRouteErrorBoundary';

const ExpirationBanner = ({ details, onRenew }) => {
  if (!details) {
    return null;
  }

  const message = details.isExpired
    ? 'Your membership has expired. Renew now to restore uninterrupted member access.'
    : details.daysUntilExpiration === 0
      ? `Your membership expires today${details.formattedDate ? `, ${details.formattedDate}` : ''}. Renew now to avoid a lapse in access.`
      : `Your membership expires in ${details.daysUntilExpiration} day${details.daysUntilExpiration === 1 ? '' : 's'}${details.formattedDate ? `, on ${details.formattedDate}` : ''}. Renew now to keep your member access active.`;

  return (
    <div className="border-b border-[#f8c235]/25 bg-[#8f1515] text-white">
      <div className="mx-auto flex max-w-[1600px] flex-col gap-3 px-4 py-3 md:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-[#f8c235]">Membership Expiration Notice</p>
          <p className="mt-1 text-sm leading-6 text-white">{message}</p>
        </div>
        <Button
          type="button"
          variant="secondary"
          className="shrink-0 border border-[#f8c235] bg-[#f8c235] px-5 text-sm font-semibold uppercase tracking-[0.14em] text-black hover:bg-[#ddb01d]"
          onClick={onRenew}
        >
          Renew Membership
        </Button>
      </div>
    </div>
  );
};

function App() {
  const { user, profile, loading, authReady } = useAuth();
  const runtimeConfig = getAppRuntimeConfig();
  const isSignupEmbed = runtimeConfig.embedMode === 'signup';
  const isLoginEmbed = runtimeConfig.embedMode === 'login';
  const location = useLocation();
  const [activeTab, setActiveTab] = useState('profile');
  const fullBleedContentRoutes = new Set([]);
  const isFullBleedContentRoute = fullBleedContentRoutes.has(location.pathname);
  const flushTopMemberRoutes = new Set(['/profile']);
  const isFlushTopMemberRoute = flushTopMemberRoutes.has(location.pathname);
  const fullWidthContentRoutes = new Set(['/discounts', '/membership', '/membership/upgrade']);
  const isFullWidthContentRoute = fullWidthContentRoutes.has(location.pathname);
  const publicOutletPaths = new Set(['/login', '/linked-accounts', '/home', '/join']);
  const showPublicOutlet = publicOutletPaths.has(location.pathname);
  const { openMembershipAction } = useMembershipActions();
  const expirationWarning = getExpirationWarningDetails(profile);

  useLayoutEffect(() => {
    if (location.pathname !== '/join') {
      return undefined;
    }

    const resetSignupScroll = () => {
      const portalRoot = document.getElementById('aac-member-portal-root') || document.getElementById('root');
      const publicShellScroller = portalRoot?.querySelector('.topo-lines > main');

      [document.scrollingElement, portalRoot, publicShellScroller].forEach((scrollTarget) => {
        if (scrollTarget) {
          scrollTarget.scrollTop = 0;
          scrollTarget.scrollLeft = 0;
        }
      });
    };

    resetSignupScroll();
    const animationFrame = window.requestAnimationFrame(resetSignupScroll);
    const resetTimers = [0, 100, 500].map((delay) => window.setTimeout(resetSignupScroll, delay));

    return () => {
      window.cancelAnimationFrame(animationFrame);
      resetTimers.forEach((timer) => window.clearTimeout(timer));
    };
  }, [location.pathname]);

  useEffect(() => {
    // This reminder should feel helpful, not cursed, so we only show it once per
    // browser session when a member is nearing expiration without auto-renew.
    if (!user?.id || !profile) {
      return;
    }
    const key = `aac_renewal_modal_${user.id}`;
    if (sessionStorage.getItem(key)) {
      return;
    }
    if (shouldPromptMembershipVerification(profile)) {
      sessionStorage.setItem(key, '1');
      toast({
        title: 'Membership renewal reminder',
        description: 'Your membership is nearing expiration. Use Renew Membership to continue through PMPro checkout.',
      });
    }
  }, [user?.id, profile]);

  if (isSignupEmbed) {
    return (
      <div className="aac-signup-embed-surface">
        <PortalRouteErrorBoundary>
          <MemberJoinPage />
        </PortalRouteErrorBoundary>
        <Toaster />
      </div>
    );
  }

  if (isLoginEmbed) {
    return (
      <div className="aac-login-embed-surface">
        <LoginPage />
        <Toaster />
      </div>
    );
  }

  // Public routes must not depend on the member-session request. On a logged-out
  // visit that request can be delayed or blocked by caching/security middleware;
  // gating /join behind it leaves the app on the initial loading screen forever.
  if (!authReady && !showPublicOutlet) {
    return (
      <div className="min-h-screen member-app-surface flex items-center justify-center text-stone-800">
        Loading...
      </div>
    );
  }

  const protectedMemberPaths = new Set([
    '/',
    '/profile',
    '/change-password',
    '/discounts',
    '/publications',
    '/rescue',
    '/membership',
    '/membership/upgrade',
    '/contact',
    '/account',
  ]);
  const showProtectedLogin = !user && protectedMemberPaths.has(location.pathname);
  const shouldRenderPublicShell = (!user && !showProtectedLogin) || showPublicOutlet || showProtectedLogin;
  const useDocumentScroll = location.pathname === '/join';
  if (shouldRenderPublicShell) {
    return (
      <div className="topo-lines flex min-h-screen flex-col">
        <main
          className={useDocumentScroll ? 'min-w-0 overflow-visible' : 'min-h-0 min-w-0 flex-1 overflow-y-auto'}
          style={{ paddingTop: '0px' }}
        >
          <PortalRouteErrorBoundary key={location.pathname}>
            {showProtectedLogin ? <LoginPage /> : showPublicOutlet ? <Outlet context={{ activeTab, setActiveTab }} /> : <HomePage />}
          </PortalRouteErrorBoundary>
        </main>
        <Toaster />
      </div>
    );
  }
  
  return (
    <>
      <Helmet>
        <title>American Alpine Club - Member Portal</title>
        <meta name="description" content="Access your AAC membership card, partner discounts, publications, and account settings." />
      </Helmet>
      
      <div
        className="member-app-surface flex min-h-screen flex-col overflow-visible"
        style={{
          '--aac-portal-header-height': '0px',
          paddingTop: '0.75rem',
        }}
      >
        <ExpirationBanner
          details={expirationWarning}
          onRenew={() => void openMembershipAction('renew', { targetTier: profile?.profile_info?.tier || 'Partner' })}
        />
        <PortalSidebar />
        <div className="flex min-h-0 flex-1 flex-col overflow-visible">
          <main
            className={`portal-main-surface mx-auto min-h-0 min-w-0 flex-1 overflow-visible ${isFullWidthContentRoute ? 'w-full max-w-none !px-[clamp(1.25rem,2.5vw,3rem)]' : ''} ${isFullBleedContentRoute ? 'px-0 py-0' : isFlushTopMemberRoute ? 'px-4 pb-6 pt-8 md:pb-8' : 'px-4 py-8 md:pb-8'}`}
            style={{ paddingBottom: isFullBleedContentRoute ? 'env(safe-area-inset-bottom, 0px)' : 'calc(1.5rem + env(safe-area-inset-bottom, 0px))' }}
          >
            <div className={isFullWidthContentRoute ? 'w-full max-w-none' : 'mx-auto max-w-7xl'}>
              <PortalRouteErrorBoundary key={location.pathname}>
                <Outlet context={{ activeTab, setActiveTab }} />
              </PortalRouteErrorBoundary>
            </div>
          </main>
        </div>
        <Toaster />
      </div>
    </>
  );
}

export default App;
