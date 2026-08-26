import React from 'react';
import { Plus } from 'lucide-react';
import { AAC_MAIN_NAV, mainSiteHref, resolveNavChildHref } from '@/lib/mainWebsiteNav';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { cn } from '@/lib/utils';

function NavLink({ href, external, className, children, style }) {
  return (
    <a
      href={href}
      className={className}
      style={style}
      {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
    >
      {children}
    </a>
  );
}

/**
 * Desktop + mobile navigation aligned with americanalpineclub.org (dark bar variant).
 */
export function MainSiteNavigation({ className }) {
  const portalUi = getPortalUiSettings();
  const navSections = portalUi.navigation.topNavSections || AAC_MAIN_NAV;
  const design = portalUi.design;
  const desktopLinkBase =
    'group inline-flex min-h-[3.35rem] whitespace-nowrap items-center justify-center gap-3 px-2 xl:px-3 2xl:px-4 text-[0.9rem] xl:text-[0.98rem] 2xl:text-[1.05rem] font-bold uppercase tracking-[0.15em] xl:tracking-[0.17em] 2xl:tracking-[0.19em] transition-colors';
  const dropdownPanel =
    'rounded-none border p-5 shadow-[0_28px_80px_rgba(0,0,0,0.45)] ring-1 ring-white/8 backdrop-blur';
  const dropdownLink =
    'block rounded-2xl px-4 py-3 text-sm font-medium transition-colors';

  return (
    <nav className={cn('flex items-center', className)} aria-label="American Alpine Club website">
      <ul className="hidden w-full items-stretch justify-between xl:flex">
        {navSections.map((section) => {
          if (section.type === 'link') {
            return (
              <li key={section.label} className="flex items-stretch">
                <NavLink href={section.href} external={section.external} className={desktopLinkBase} style={{ color: design.navTextColor }}>
                  <span className="relative pb-1">
                    {section.label}
                    <span
                      className="absolute inset-x-0 bottom-0 h-px origin-left scale-x-0 transition-transform duration-200 group-hover:scale-x-100"
                      style={{ backgroundColor: design.navHoverTextColor }}
                    />
                  </span>
                </NavLink>
              </li>
            );
          }

          return (
            <li key={section.label} className="group relative flex items-stretch">
              <a
                href={section.href || mainSiteHref(section.path)}
                className={cn(desktopLinkBase, 'relative')}
                style={{ color: design.navTextColor }}
              >
                <span className="relative pb-1">
                  {section.label}
                  <span
                    className="absolute inset-x-0 bottom-0 h-px origin-left scale-x-0 transition-transform duration-200 group-hover:scale-x-100 group-focus-within:scale-x-100"
                    style={{ backgroundColor: design.navHoverTextColor }}
                  />
                </span>
                  <Plus className="h-[1.2rem] w-[1.2rem] opacity-100" style={{ color: design.navIconColor }} aria-hidden />
              </a>
              <div
                className={cn(
                  'invisible absolute left-0 top-full z-[80] min-w-[18rem] max-w-[22rem] pt-3 opacity-0 transition-all duration-150',
                  'group-hover:visible group-hover:opacity-100',
                  'group-focus-within:visible group-focus-within:opacity-100',
                )}
              >
                <div className={dropdownPanel} style={{ background: design.navDropdownBackground, borderColor: 'rgba(255,255,255,0.12)' }}>
                  <span className="mb-3 block px-4 text-[0.68rem] font-semibold uppercase tracking-[0.25em]" style={{ color: design.navIconColor }}>
                    {section.label}
                  </span>
                  <ul className="space-y-1">
                    {section.children.map((child) => (
                      <li key={child.label}>
                        <NavLink
                          href={child.href || resolveNavChildHref(child)}
                          external={child.external}
                          className={dropdownLink}
                          style={{ color: design.navDropdownTextColor }}
                        >
                          {child.label}
                        </NavLink>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </li>
          );
        })}
      </ul>

      <div className="flex w-full flex-col gap-2 xl:hidden">
        {navSections.map((section) => {
          if (section.type === 'link') {
            return (
              <NavLink
                key={section.label}
                href={section.href}
                external={section.external}
                className="flex items-center justify-between rounded-[1.2rem] border border-white/10 bg-white/[0.03] px-4 py-3 text-sm font-semibold uppercase tracking-[0.16em] transition-colors hover:border-[#f8c235]/40"
                style={{ color: design.navTextColor }}
              >
                {section.label}
              </NavLink>
            );
          }
          return (
            <div key={section.label} className="relative">
              <details className="group overflow-hidden rounded-none border border-white/10 bg-white/[0.03]">
                <summary
                className="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-[0.95rem] font-semibold uppercase tracking-[0.16em] marker:hidden [&::-webkit-details-marker]:hidden"
                style={{ color: design.navTextColor }}
                >
                  <span>{section.label}</span>
                  <Plus className="h-5 w-5 opacity-100" style={{ color: design.navIconColor }} aria-hidden />
                </summary>
                <ul className="space-y-1 border-t border-white/8 px-2 py-2">
                  {section.children.map((child) => (
                    <li key={child.label}>
                        <NavLink
                          href={child.href || resolveNavChildHref(child)}
                          external={child.external}
                          className={dropdownLink}
                          style={{ color: design.navDropdownTextColor }}
                        >
                          {child.label}
                        </NavLink>
                    </li>
                  ))}
                </ul>
              </details>
            </div>
          );
        })}
      </div>
    </nav>
  );
}
