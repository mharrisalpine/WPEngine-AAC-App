import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { BadgePercent, User, Settings, Tag, Mail, PenSquare, BookOpen, LogOut } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { getPortalPageUrl } from '@/lib/backendConfig';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { isPartnerOrAboveMembershipTierId } from '@/lib/membershipTiers';
import { cn } from '@/lib/utils';

export function PortalNavLinks({ onNavigate, className }) {
  const { pathname } = useLocation();
  const { profile } = useAuth();
  const portalSections = getPortalUiSettings().navigation.sidebarSections;
  const canAccessPublications = isPartnerOrAboveMembershipTierId(profile?.profile_info?.tier);
  const iconRegistry = {
    user: User,
    settings: Settings,
    tag: Tag,
    'badge-percent': BadgePercent,
    mail: Mail,
    pen: PenSquare,
    book: BookOpen,
  };

  const isItemActive = (to, itemId) => {
    if (itemId === 'member_profile') {
      return pathname === '/' || pathname === '/profile' || pathname === '';
    }
    if (itemId === 'account') {
      return pathname === '/account' || pathname === '/change-password';
    }
    if (itemId === 'publications') {
      return pathname === '/publications';
    }
    if (itemId === 'discounts') {
      return pathname === '/discounts' || pathname === '/rescue';
    }
    if (itemId === 'manage') {
      return pathname === '/membership' || pathname.startsWith('/membership/');
    }
    return pathname === to;
  };

  return (
    <nav className={cn('portal-sidebar-nav flex flex-col gap-6 px-4 py-4', className)} aria-label="Member portal">
      {portalSections.map((section) => (
        <div key={section.title}>
          <p className="portal-sidebar-section-title mb-3 px-3 text-[0.92rem] font-semibold uppercase tracking-[0.24em] text-white/80">{section.title}</p>
          <ul className="space-y-1">
            {section.items.filter((item) => {
              if (item.id === 'publications' && !canAccessPublications) {
                return false;
              }

              return true;
            }).map((item) => {
              const active = isItemActive(item.to, item.id);
              const Icon = iconRegistry[item.icon] || User;
              const itemClasses = cn(
                'portal-sidebar-link group relative flex items-center gap-3 border-b px-3 py-3.5 text-[1.05rem] font-medium text-white transition-all',
                active ? 'portal-sidebar-link--active' : '',
              );
              const icon = <Icon className="h-5 w-5 shrink-0 transition-colors" />;

              if (item.href) {
                return (
                  <li key={item.href + item.label}>
                    <a href={item.href} onClick={onNavigate} className={itemClasses}>
                      {icon}
                      <span className="portal-sidebar-link__label">{item.label}</span>
                    </a>
                  </li>
                );
              }

              return (
                <li key={item.to + item.label}>
                  <Link
                    to={item.to}
                    onClick={onNavigate}
                    className={itemClasses}
                  >
                    {icon}
                    <span className="portal-sidebar-link__label">{item.label}</span>
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      ))}
    </nav>
  );
}

const SidebarSignOut = ({ onSignedOut, horizontal = false }) => {
  const { signOut } = useAuth();

  const handleSignOut = async () => {
    const result = await signOut();
    if (!result?.error) {
      try {
        Object.keys(sessionStorage)
          .filter((key) => key.startsWith('aac_renewal_modal_'))
          .forEach((key) => sessionStorage.removeItem(key));
      } catch (error) {
        // Session storage cleanup should never block logout.
      }
      onSignedOut?.();
      const loginUrl = new URL(getPortalPageUrl(), window.location.origin);
      loginUrl.searchParams.set('aac_logged_out', Date.now().toString());
      loginUrl.hash = '/login';
      window.location.replace(loginUrl.toString());
    }
  };

  return (
    <div className={horizontal ? 'portal-sidebar-signout portal-sidebar-signout--horizontal shrink-0 px-4 py-3' : 'mt-auto border-t border-white/15 px-4 py-5'}>
      <button
        type="button"
        onClick={handleSignOut}
        className="portal-sidebar-link group flex w-full items-center gap-3 border-b px-3 py-3.5 text-left text-[1.05rem] font-medium text-white transition-all hover:bg-white/10"
      >
        <LogOut className="h-5 w-5 shrink-0" />
        <span className="portal-sidebar-link__label">Sign Out</span>
      </button>
    </div>
  );
};

const PortalSidebar = () => {
  const portalUiSettings = getPortalUiSettings();
  const design = portalUiSettings.design;

  const sidebarSurfaceStyle = {
    '--portal-sidebar-accent': design.sidebarAccentColor || '#b71c1c',
  };

  return (
    <header
      className="portal-sidebar-surface portal-horizontal-nav aac-member-mobile-nav shrink-0 border-y border-black/8"
      style={sidebarSurfaceStyle}
      aria-label="Member portal navigation"
    >
      <div className="portal-horizontal-nav__inner">
        <PortalNavLinks />
        <SidebarSignOut horizontal />
      </div>
    </header>
  );
};

export default PortalSidebar;
