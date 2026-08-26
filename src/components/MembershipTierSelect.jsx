import React from 'react';
import { Check } from 'lucide-react';
import { MEMBERSHIP_TIER_OPTIONS, isOneTimeMembershipTierId, isPublicMembershipTierId } from '@/lib/membershipTiers';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { cn } from '@/lib/utils';

function TierBenefitsList({ benefits, dense, maxItems }) {
  if (!benefits?.length) {
    return null;
  }
  const visibleBenefits = Number.isInteger(maxItems) ? benefits.slice(0, maxItems) : benefits;
  const hiddenCount = Math.max(0, benefits.length - visibleBenefits.length);
  return (
    <ul className={cn('mt-3 space-y-2 text-left', dense ? 'text-[13px] leading-5' : 'text-[13px] leading-5')}>
      {visibleBenefits.map((line) => (
        <li key={line} className="flex gap-2 text-[#514a40]">
          <Check
            className={cn('mt-0.5 shrink-0 text-[#9e1b1e]', dense ? 'h-3.5 w-3.5' : 'h-4 w-4')}
            strokeWidth={2.5}
            aria-hidden
          />
          <span className="min-w-0 flex-1 break-words [overflow-wrap:anywhere]">{line}</span>
        </li>
      ))}
      {hiddenCount > 0 ? (
        <li className="text-xs font-semibold uppercase tracking-[0.12em] text-[#8f877a]">
          + {hiddenCount} more benefits
        </li>
      ) : null}
    </ul>
  );
}

/**
 * @param {object} props
 * @param {string} props.selectedId
 * @param {(id: string) => void} props.onSelect
 * @param {'compact' | 'full'} [props.variant]
 */
export function MembershipTierSelect({ selectedId, onSelect, variant = 'compact' }) {
  const membershipLevelBenefits = getPortalUiSettings().content?.membershipLevelBenefits || {};
  const getTierBenefits = (tier) => (
    Object.prototype.hasOwnProperty.call(membershipLevelBenefits, tier.id) && Array.isArray(membershipLevelBenefits[tier.id])
      ? membershipLevelBenefits[tier.id]
      : tier.benefits
  );
  const visibleTiers = MEMBERSHIP_TIER_OPTIONS
    .filter((tier) => isPublicMembershipTierId(tier.id))
    .map((tier) => ({
      ...tier,
      benefits: getTierBenefits(tier),
    }));

  if (variant === 'full') {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" role="radiogroup" aria-label="Membership level">
        {visibleTiers.map((t) => {
          const selected = selectedId === t.id;
          const priceLabel =
            t.priceCents === 0
              ? 'Free'
              : `$${(t.priceCents / 100).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
          return (
            <button
              key={t.id}
              type="button"
              onClick={() => onSelect(t.id)}
              role="radio"
              aria-checked={selected}
              className={cn(
                'aac-membership-tier-card flex min-h-[320px] flex-col rounded-none border p-5 text-left shadow-sm transition',
                selected
                  ? 'border-[#ffc72c] bg-white ring-2 ring-[#ffc72c] ring-offset-0'
                  : 'border-[#ffc72c] bg-white hover:border-[#d6a300] hover:shadow-md',
              )}
            >
              <div className="flex flex-1 flex-col">
                <span className="text-lg font-bold text-stone-900">{t.label}</span>
                <span
                  className={cn(
                    'aac-membership-tier-card__price mt-2 w-fit text-[26px] font-semibold leading-8 tracking-tight',
                    selected ? 'bg-[#ffc72c] px-3 py-1.5 text-black' : 'text-[#8f1515]',
                  )}
                >
                  {priceLabel}
                  {t.priceCents === 0 ? null : isOneTimeMembershipTierId(t.id) ? (
                    <span className={cn('text-sm font-medium', selected ? 'text-black' : 'text-stone-500')}> one-time</span>
                  ) : (
                    <span className={cn('text-sm font-medium', selected ? 'text-black' : 'text-stone-500')}>/yr</span>
                  )}
                </span>
                <p className="mt-2.5 text-[13px] leading-5 text-stone-600">{t.blurb}</p>
                <TierBenefitsList benefits={t.benefits} />
                {selected ? (
                  <span className="mt-4 text-xs font-semibold uppercase tracking-wide text-[#8f1515]">Selected</span>
                ) : (
                  <span className="mt-4 text-xs font-medium text-stone-400">Tap to select</span>
                )}
              </div>
            </button>
          );
        })}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {visibleTiers.map((t) => (
        <button
          key={t.id}
          type="button"
          onClick={() => onSelect(t.id)}
          className={cn(
            'rounded-xl border p-4 text-left transition',
            selectedId === t.id
              ? 'border-[#c8a43a] bg-[rgba(200,164,58,0.15)] ring-1 ring-[#c8a43a]'
              : 'border-stone-200 bg-stone-50 hover:border-stone-300',
          )}
        >
          <div className="flex items-baseline justify-between gap-2">
            <span className="font-semibold text-black">{t.label}</span>
            <span className="text-sm font-medium text-[#c8a43a]">
              {t.priceCents === 0 ? 'Free' : `$${(t.priceCents / 100).toFixed(0)}`}
            </span>
          </div>
          <p className="mt-1 text-xs text-black/65">{t.blurb}</p>
          <TierBenefitsList benefits={t.benefits} dense />
        </button>
      ))}
    </div>
  );
}
