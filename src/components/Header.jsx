import React, { useEffect, useRef, useState } from 'react';
import { DollarSign, LogIn, Menu, Plus, User, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { Link, useLocation } from 'react-router-dom';
import { MainSiteNavigation } from '@/components/MainSiteNavigation';
import { JOIN_PAGE_URL, getPortalUiSettings } from '@/lib/portalSettings';

const LIGHT_LOGO_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg';

const ACTION_H = 'h-11 min-h-[2.75rem]';
const UTILITY_LINK_CLASS = 'group inline-flex items-center gap-2 text-[0.88rem] font-semibold tracking-[0.01em] text-white transition-colors hover:text-white';
const UTILITY_ICON_CLASS = 'h-[1.2rem] w-[1.2rem] text-[#f8c235] transition-transform duration-200 group-hover:scale-105';

const HeaderActions = ({ showDonate, showLogin, showJoin, showProfile, className, compact = false, style }) => {
  const actionHeight = compact ? 'h-10 min-h-[2.5rem]' : ACTION_H;
  const actionPadding = compact ? 'px-3.5 text-[0.72rem] tracking-[0.12em]' : 'px-5 text-[0.95rem] tracking-[0.18em]';

  return (
    <div className={className} style={style}>
      {showDonate ? (
        <a
          href="https://americanalpineclub.org/donate"
          className={`inline-flex ${actionHeight} items-center justify-center rounded-none border border-[#8f1515] bg-[#8f1515] ${actionPadding} font-semibold uppercase text-white transition-colors hover:border-[#6b1010] hover:bg-[#6b1010]`}
        >
          Donate
        </a>
      ) : null}

      {showProfile ? (
        <Link
          to="/profile"
          className={`inline-flex ${actionHeight} items-center justify-center rounded-none border border-[#f8c235] bg-[#f8c235] ${actionPadding} font-semibold uppercase text-black transition-colors hover:bg-[#e1ae14]`}
        >
          My Profile
        </Link>
      ) : null}

      {showJoin ? (
        <a
          href={JOIN_PAGE_URL}
          className={`inline-flex ${actionHeight} items-center justify-center rounded-none border border-[#f8c235] bg-[#f8c235] ${actionPadding} font-semibold uppercase text-black transition-colors hover:bg-[#e1ae14]`}
        >
          Join
        </a>
      ) : null}

      {showLogin ? (
        <Link
          to="/login"
          className={`inline-flex ${actionHeight} items-center justify-center rounded-none border border-[#8f1515] bg-[#8f1515] ${actionPadding} font-semibold uppercase text-white transition-colors hover:border-[#6b1010] hover:bg-[#6b1010]`}
        >
          Sign In
        </Link>
      ) : null}

    </div>
  );
};

const PublicUtilityNav = ({ showProfile, className }) => (
  <div className={className}>
    {showProfile ? (
      <Link
        to="/profile"
        className={UTILITY_LINK_CLASS}
      >
        <User className={UTILITY_ICON_CLASS} />
        Account
      </Link>
    ) : (
      <>
        <Link
          to="/login"
          className={UTILITY_LINK_CLASS}
        >
          <LogIn className={UTILITY_ICON_CLASS} />
          Sign In
        </Link>
        <a
          href={JOIN_PAGE_URL}
          className={UTILITY_LINK_CLASS}
        >
          <Plus className={UTILITY_ICON_CLASS} />
          Join
        </a>
      </>
    )}
    <Link
      to="/rescue"
      className={UTILITY_LINK_CLASS}
    >
      Rescue
    </Link>
    <a
      href="https://americanalpineclub.org/donate"
      className={UTILITY_LINK_CLASS}
    >
      <DollarSign className={UTILITY_ICON_CLASS} />
      Donate
    </a>
    {showProfile ? (
      <a
        href={JOIN_PAGE_URL}
        className={UTILITY_LINK_CLASS}
      >
        Join
      </a>
    ) : null}
  </div>
);

/**
 * @param {object} props
 * @param {'portal' | 'public'} [props.variant] public = join page (no portal menu, no log out)
 */
const Header = ({ variant = 'portal', onOpenPortalMenu }) => {
  const portalUi = getPortalUiSettings();
  const design = portalUi.design;
  const { user } = useAuth();
  const isPublic = variant === 'public';
  const location = useLocation();
  const headerRef = useRef(null);
  const [mobileSiteNavOpen, setMobileSiteNavOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [isHeaderHidden, setIsHeaderHidden] = useState(false);
  const forceSolidPublicHeader = true;
  const hideTimerRef = useRef(null);
  const lastScrollYRef = useRef(0);
  const isLoginRoute = location.pathname === '/login';
  const isHeroOverlayRoute = isPublic && (location.pathname === '/home' || location.pathname === '/join');
  const usesTransparentPublicHeader = isPublic && !forceSolidPublicHeader;

  const showSolidPublicChrome = !usesTransparentPublicHeader || isScrolled;
  const showPublicProfileAction = isPublic && !!user;

  useEffect(() => {
    const headerNode = headerRef.current;
    if (!headerNode || typeof document === 'undefined') {
      return undefined;
    }

    const updateHeaderHeight = () => {
      document.documentElement.style.setProperty('--aac-portal-header-height', `${headerNode.offsetHeight}px`);
    };

    updateHeaderHeight();

    let observer;
    if (typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(updateHeaderHeight);
      observer.observe(headerNode);
    }

    window.addEventListener('resize', updateHeaderHeight);

    return () => {
      window.removeEventListener('resize', updateHeaderHeight);
      observer?.disconnect();
    };
  }, [variant, location.pathname]);

  useEffect(() => {
    setMobileSiteNavOpen(false);
  }, [location.pathname, variant]);

  useEffect(() => {
    setIsHeaderHidden(false);
    lastScrollYRef.current = window.scrollY;
    if (hideTimerRef.current) {
      window.clearTimeout(hideTimerRef.current);
      hideTimerRef.current = null;
    }
  }, [location.pathname]);

  useEffect(() => {
    lastScrollYRef.current = window.scrollY;

    if (forceSolidPublicHeader) {
      setIsScrolled(true);
      setIsHeaderHidden(false);
      if (hideTimerRef.current) {
        window.clearTimeout(hideTimerRef.current);
        hideTimerRef.current = null;
      }
      return undefined;
    }

    if (!usesTransparentPublicHeader) {
      setIsScrolled(true);
    } else {
      setIsScrolled(window.scrollY > 8);
    }

    const showHeader = () => {
      setIsHeaderHidden(false);
    };

    const scheduleHide = () => {
      if (hideTimerRef.current) {
        window.clearTimeout(hideTimerRef.current);
      }

      hideTimerRef.current = window.setTimeout(() => {
        if (window.scrollY > 32 && !mobileSiteNavOpen) {
          setIsHeaderHidden(true);
        }
      }, 1500);
    };

    const updateScrolledState = () => {
      const currentScrollY = window.scrollY;
      const previousScrollY = lastScrollYRef.current;
      const scrolledEnough = Math.abs(currentScrollY - previousScrollY) > 2;
      const nearTop = currentScrollY <= 32;

      setIsScrolled(currentScrollY > 8);

      if (nearTop || scrolledEnough) {
        showHeader();
      }

      scheduleHide();
      lastScrollYRef.current = currentScrollY;
    };

    updateScrolledState();
    window.addEventListener('scroll', updateScrolledState, { passive: true });

    return () => {
      window.removeEventListener('scroll', updateScrolledState);
      if (hideTimerRef.current) {
        window.clearTimeout(hideTimerRef.current);
        hideTimerRef.current = null;
      }
    };
  }, [location.pathname, usesTransparentPublicHeader, mobileSiteNavOpen, forceSolidPublicHeader]);

  useEffect(() => {
    if (forceSolidPublicHeader) {
      setIsHeaderHidden(false);
      if (hideTimerRef.current) {
        window.clearTimeout(hideTimerRef.current);
        hideTimerRef.current = null;
      }
      return undefined;
    }

    if (mobileSiteNavOpen) {
      setIsHeaderHidden(false);
      if (hideTimerRef.current) {
        window.clearTimeout(hideTimerRef.current);
        hideTimerRef.current = null;
      }
      return undefined;
    }

    if (window.scrollY <= 32) {
      setIsHeaderHidden(false);
      return undefined;
    }

    lastScrollYRef.current = window.scrollY;
    hideTimerRef.current = window.setTimeout(() => {
      setIsHeaderHidden(true);
    }, 1500);

    return () => {
      if (hideTimerRef.current) {
        window.clearTimeout(hideTimerRef.current);
        hideTimerRef.current = null;
      }
    };
  }, [mobileSiteNavOpen, forceSolidPublicHeader]);

  const headerBackground = usesTransparentPublicHeader && !isScrolled
    ? 'transparent'
    : design.navBackground;
  const headerBorderColor = usesTransparentPublicHeader && !isScrolled
    ? 'transparent'
    : 'rgba(255,255,255,0.1)';
  const headerBackdropFilter = 'none';
  const chromeDividerColor = showSolidPublicChrome ? 'rgba(255,255,255,0.1)' : 'transparent';

  return (
    <header
      ref={headerRef}
      className={`${usesTransparentPublicHeader ? 'fixed inset-x-0 top-0' : 'sticky top-0'} z-50 border-b text-white transition-[background-color,border-color,box-shadow,backdrop-filter,transform] duration-300 ${(forceSolidPublicHeader || !isHeaderHidden) ? 'translate-y-0' : '-translate-y-full'}`}
      style={{
        background: headerBackground,
        borderColor: headerBorderColor,
        boxShadow: usesTransparentPublicHeader && !isScrolled ? 'none' : '0 1px 0 rgba(255,255,255,0.04)',
        backdropFilter: headerBackdropFilter,
        WebkitBackdropFilter: headerBackdropFilter,
        paddingTop: 'env(safe-area-inset-top, 0px)',
      }}
    >
      <div className="w-full px-0">
        {!isPublic ? (
          <PublicUtilityNav
            showProfile={!!user}
            className="flex min-h-[2.75rem] flex-wrap items-center justify-end gap-x-6 gap-y-2 border-b border-white/10 px-4 py-2 md:px-6"
          />
        ) : null}
        <div className="flex flex-col gap-3 xl:hidden">
          <div className="flex items-center justify-between gap-3 px-4 py-3 md:px-6">
            <div className="flex min-w-0 items-center gap-2">
              {!isPublic ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  onClick={onOpenPortalMenu}
                  className="shrink-0 rounded-none border border-white/10 bg-white/[0.03] text-white hover:bg-white/10 hover:text-white md:hidden"
                  aria-label="Open member portal menu"
                >
                  <Menu className="h-6 w-6" />
                </Button>
              ) : null}
              <Link to="/home" className="flex shrink-0 items-center">
                <img
                  alt="American Alpine Club Logo"
                  className="h-10 w-auto sm:h-11"
                  src={LIGHT_LOGO_URL}
                />
              </Link>
            </div>

            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={() => setMobileSiteNavOpen((current) => !current)}
              className="shrink-0 rounded-none border border-white/10 bg-white/[0.03] text-white hover:bg-white/10 hover:text-white"
              aria-label={mobileSiteNavOpen ? 'Close site navigation' : 'Open site navigation'}
              aria-expanded={mobileSiteNavOpen}
            >
              {mobileSiteNavOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </Button>
          </div>

          {!isPublic ? (
            <HeaderActions
              showDonate={false}
              showLogin={!user}
              showJoin={!user}
              showProfile={false}
              className="flex flex-wrap items-center gap-1.5 border-t border-white/10 px-4 pt-3 md:px-6"
              compact
              style={{ borderColor: chromeDividerColor }}
            />
          ) : null}

          {isPublic ? (
            <HeaderActions
              showDonate={false}
              showLogin
              showJoin
              showProfile={false}
              className="flex flex-wrap items-center gap-1.5 border-t border-white/10 px-4 pt-3 md:px-6"
              compact
              style={{ borderColor: chromeDividerColor }}
            />
          ) : null}

          {mobileSiteNavOpen ? (
            <div className="space-y-3 border-t border-white/10 px-4 pb-4 pt-3 md:px-6" style={{ borderColor: chromeDividerColor }}>
              <MainSiteNavigation className="min-w-0" />
            </div>
          ) : null}
        </div>

        <div className="hidden xl:flex xl:min-h-[4.75rem] xl:items-stretch xl:gap-6">
          <Link to="/home" className="flex shrink-0 items-center border-r border-white/10 px-6" style={{ borderColor: chromeDividerColor }}>
            <img
              alt="American Alpine Club Logo"
              className="h-12 w-auto"
              src={LIGHT_LOGO_URL}
            />
          </Link>

          <div className="flex min-w-0 flex-1 flex-col justify-center">
            <div className="flex min-w-0 items-center gap-4 pr-6">
              <MainSiteNavigation className="min-w-0 flex-1 justify-start" />
              {!isPublic ? (
                <HeaderActions
                  showDonate={false}
                  showLogin={!user}
                  showJoin={!user}
                  showProfile={false}
                  className="flex shrink-0 items-center gap-2"
                  compact
                />
              ) : null}
              {isPublic ? (
                <HeaderActions
                  showDonate={false}
                  showLogin
                  showJoin
                  showProfile={false}
                  className="flex shrink-0 items-center gap-2"
                  compact
                />
              ) : null}
            </div>
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;
