
import React from 'react';
import { motion } from 'framer-motion';
import { CheckCircle2, CheckSquare2, FileText, Flag, GraduationCap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import ConfirmationLetterDialog from '@/components/ConfirmationLetterDialog';
import { getFullName, normalizeAccountInfo, normalizeMembershipDiscountType } from '@/lib/memberProfile';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { getTierDisplayLabel } from '@/lib/membershipTiers';
import { getAppRuntimeConfig, getPortalPageUrl } from '@/lib/backendConfig';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { cn } from '@/lib/utils';

const DISCOUNT_BADGE_CONTENT = {
  military: {
    label: 'Military',
    Icon: Flag,
  },
  student: {
    label: 'Student',
    Icon: GraduationCap,
  },
};

const AAC_LOGO_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg';

const parseMembershipDate = (value) => {
  const normalized = String(value || '').trim();
  if (!normalized) {
    return null;
  }

  const dateOnlyMatch = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (dateOnlyMatch) {
    const [, year, month, day] = dateOnlyMatch;
    const date = new Date(Number(year), Number(month) - 1, Number(day));
    return Number.isNaN(date.getTime()) ? null : date;
  }

  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? null : date;
};

const formatMemberCardYear = (value, fallback = 'N/A') => {
  const normalized = String(value || '').trim();
  if (/^\d{4}$/.test(normalized)) {
    return normalized;
  }

  const date = parseMembershipDate(value);
  if (!date) {
    return fallback;
  }

  return String(date.getFullYear());
};

const formatValidThru = (profileInfo = {}) => {
  const candidates = [
    profileInfo.valid_through_date,
    profileInfo.renewal_date,
    profileInfo.expiration_date,
  ]
    .map((value) => ({ value, date: parseMembershipDate(value) }))
    .filter((candidate) => candidate.date);
  const latest = candidates.reduce((selected, candidate) => {
    if (!selected || candidate.date.getTime() > selected.date.getTime()) {
      return candidate;
    }
    return selected;
  }, null);
  const value = latest?.value || '';
  if (!value) {
    return 'N/A';
  }

  const date = latest?.date || parseMembershipDate(value);
  if (!date) {
    return 'N/A';
  }

  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const formatMemberNumber = (memberId) => {
  const normalized = String(memberId || '').trim();
  return normalized || 'N/A';
};

const MembershipCard = ({ profile }) => {
  const [confirmationLetterOpen, setConfirmationLetterOpen] = React.useState(false);
  const portalDesign = getPortalUiSettings().design || {};
  const runtimeConfig = getAppRuntimeConfig();
  const assetBaseUrl = String(runtimeConfig.assetBaseUrl || '').replace(/\/$/, '');
  const appBaseUrl = assetBaseUrl.replace(/\/assets$/, '');
  const topoBackgroundUrl = portalDesign.sidebarBackgroundUrl && !portalDesign.sidebarBackgroundUrl.startsWith('/')
    ? portalDesign.sidebarBackgroundUrl
    : `${appBaseUrl || ''}/sidebar-topo-v2.svg`;

  const accountInfo = normalizeAccountInfo(profile?.account_info || {});
  const profileInfo = profile?.profile_info || {};

  const status = getMembershipStatus(profileInfo);
  const isMemberActive = status === 'Active';
  const discountType = normalizeMembershipDiscountType(accountInfo.membership_discount_type);
  const discountBadge = discountType ? DISCOUNT_BADGE_CONTENT[discountType] : null;
  const DiscountBadgeIcon = discountBadge?.Icon;
  const membershipTierLabel = getTierDisplayLabel(profileInfo?.tier, 'Free');
  const memberSinceLabel = formatMemberCardYear(profileInfo?.joined_date);
  const validThruLabel = formatValidThru(profileInfo);
  const hasAutoRenewal = Boolean(accountInfo.auto_renew || profile?.membership_actions?.current_subscription_id);
  const memberNumber = formatMemberNumber(profileInfo?.member_id);
  const memberName = getFullName(accountInfo);

  const handleDownloadConfirmationLetter = () => {
    setConfirmationLetterOpen(true);
  };

  const handleChangeMembership = () => {
    if (typeof window === 'undefined') {
      return;
    }

    const portalPageUrl = getPortalPageUrl();
    window.location.assign(`${portalPageUrl}/#/membership`);
  };

  const cardBackgroundStyle = {
    backgroundImage: `linear-gradient(180deg, rgba(3, 0, 0, 0.66), rgba(3, 0, 0, 0.58)), radial-gradient(circle at 18% 16%, rgba(183, 28, 28, 0.2), transparent 34%), url("${topoBackgroundUrl}")`,
    backgroundPosition: 'center center, center top',
    backgroundRepeat: 'no-repeat, no-repeat, repeat',
    backgroundSize: 'cover, cover, 760px auto',
  };

  return (
    <>
      <motion.div
      className="aac-membership-card-shell mx-auto w-full max-w-6xl bg-white py-4"
      initial={{ opacity: 0, y: 18 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45 }}
    >
      <div
        className="aac-membership-card relative aspect-[2.55/1] min-h-[280px] w-full overflow-hidden bg-[#030000] p-5 text-white sm:min-h-[295px] sm:p-6 lg:min-h-[310px] lg:p-7"
        style={cardBackgroundStyle}
      >
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 opacity-[0.24]"
          style={{
            backgroundImage:
              'radial-gradient(circle at 17% 22%, rgba(183, 28, 28, 0.36) 0 1px, transparent 2px 76px, rgba(183, 28, 28, 0.22) 77px 78px, transparent 79px 148px), linear-gradient(112deg, transparent 0 16%, rgba(248,194,53,0.13) 16.1%, transparent 16.25% 46%, rgba(183,28,28,0.24) 46.1%, transparent 46.3% 76%, rgba(255,255,255,0.08) 76.1%, transparent 76.3%)',
            backgroundSize: '100% 100%, 360px 360px',
          }}
        />
        <div aria-hidden className="absolute inset-y-0 left-0 w-2 bg-[#b71c1c]" />
        <div aria-hidden className="absolute inset-x-0 bottom-0 h-px bg-white/8" />

        <div className="relative flex h-full flex-col">
          <div className="flex items-start justify-between gap-5">
            <div className="flex min-w-0 items-center">
              <img
                src={AAC_LOGO_URL}
                alt="American Alpine Club"
                className="h-10 w-auto max-w-[13rem] object-contain sm:h-12"
              />
            </div>
            <div className="aac-membership-card-badges flex shrink-0 items-center gap-2">
              <span
                className={cn(
                  'inline-flex items-center gap-2 border px-3 py-2 text-[0.62rem] font-semibold uppercase tracking-[0.22em]',
                  isMemberActive ? 'border-emerald-400/45 text-emerald-200' : 'border-white/28 text-white/60',
                )}
              >
                {isMemberActive ? <CheckCircle2 className="h-3.5 w-3.5" strokeWidth={2.2} /> : null}
                {status}
              </span>
              <span
                className={cn(
                  'hidden rounded-none border px-3 py-2 text-[0.62rem] font-semibold uppercase tracking-[0.28em] sm:inline-flex',
                  isMemberActive ? 'border-[#d23a32] text-[#ff594f]' : 'border-white/28 text-white/60',
                )}
              >
                {membershipTierLabel}
              </span>
              {discountBadge ? (
                <span className="inline-flex items-center gap-2 border border-white/18 px-3 py-2 text-[0.62rem] font-semibold uppercase tracking-[0.2em] text-white/68">
                  {DiscountBadgeIcon ? <DiscountBadgeIcon className="h-3.5 w-3.5 text-[#d23a32]" strokeWidth={2.1} /> : null}
                  {discountBadge.label}
                </span>
              ) : null}
            </div>
          </div>

          <div className="aac-membership-card-identity mt-6 flex flex-1 items-center sm:mt-8">
            <div className="min-w-0 self-center">
              <p className="text-[0.66rem] font-medium uppercase tracking-[0.36em] text-[#ff8a80] sm:text-xs">
                {membershipTierLabel} Member
              </p>
              <h2 className="aac-membership-card-name mt-3 font-serif text-3xl leading-[1.08] tracking-normal text-[#f7f1e8] sm:text-5xl lg:text-6xl">
                {memberName}
              </h2>
              <p className="aac-membership-card-number mt-6 font-mono text-sm tracking-[0.18em] text-white/52 sm:text-lg">
                No. {memberNumber}
              </p>
            </div>
          </div>

          <div className="aac-membership-card-meta mt-auto flex flex-col gap-4 pt-5 sm:flex-row sm:items-end sm:justify-between">
            <div className="grid w-full grid-cols-2 gap-8 sm:gap-12">
              <div>
                <p className="font-mono text-[0.66rem] uppercase tracking-[0.3em] text-white/46 sm:text-xs">Valid Thru</p>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                  <p className="font-mono text-xl tracking-[0.06em] text-[#f7f1e8] sm:text-2xl">{validThruLabel}</p>
                  {hasAutoRenewal ? (
                    <span className="inline-flex items-center gap-1.5 border border-emerald-400/35 bg-emerald-500/10 px-2 py-1 text-[0.58rem] font-semibold uppercase tracking-[0.16em] text-emerald-200">
                      <CheckSquare2 className="h-3.5 w-3.5 text-emerald-300" strokeWidth={2.4} />
                      Auto-Renewal
                    </span>
                  ) : null}
                </div>
              </div>
              <div className="text-right">
                <p className="font-mono text-[0.66rem] uppercase tracking-[0.3em] text-white/46 sm:text-xs">Member Since</p>
                <p className="mt-2 font-mono text-xl tracking-[0.06em] text-[#f7f1e8] sm:text-2xl">{memberSinceLabel}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="aac-membership-actions-divider mt-4 border-t-2 border-[#b71c1c] pt-4">
        <div className="flex flex-col gap-2 lg:flex-row">
          <Button
            onClick={handleChangeMembership}
            className="flex min-h-[2.85rem] flex-1 items-center justify-center gap-2 rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            Renew
          </Button>
          <Button
            onClick={handleDownloadConfirmationLetter}
            className="flex min-h-[2.85rem] flex-1 items-center justify-center gap-2 rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
          >
            <FileText size={16} /> View Confirmation Letter
          </Button>
        </div>
      </div>
      </motion.div>
      <ConfirmationLetterDialog
        open={confirmationLetterOpen}
        onOpenChange={setConfirmationLetterOpen}
        profile={profile}
      />
    </>
  );
};

export default MembershipCard;
