import { MEMBERSHIP_TIER_OPTIONS, normalizeTierId } from '@/lib/membershipTiers';

export const MEMBERSHIP_PLAN_ORDER = ['Free', 'Supporter', 'Partner', 'Leader', 'Advocate', 'GRF'];

export const MEMBERSHIP_PLAN_PRICES = MEMBERSHIP_TIER_OPTIONS.reduce((prices, tier) => {
  prices[tier.id] = Math.round((tier.priceCents || 0) / 100);
  return prices;
}, {});

export const MEMBERSHIP_PLAN_DETAILS = MEMBERSHIP_TIER_OPTIONS.reduce((details, tier) => {
  details[tier.id] = {
    summary: tier.blurb,
    bullets: tier.benefits || [],
  };
  return details;
}, {});

export const getMembershipBenefits = (tier) => {
  const normalizedTier = normalizeTierId(tier);
  const matrix = {
    Free: { rescue_amount: 0, medical_amount: 0, mortal_remains_amount: 0, rescue_reimbursement_process: false },
    Supporter: { rescue_amount: 0, medical_amount: 0, mortal_remains_amount: 0, rescue_reimbursement_process: false },
    Partner: { rescue_amount: 7500, medical_amount: 5000, mortal_remains_amount: 15000, rescue_reimbursement_process: true },
    Leader: { rescue_amount: 300000, medical_amount: 5000, mortal_remains_amount: 15000, rescue_reimbursement_process: true },
    Advocate: { rescue_amount: 300000, medical_amount: 5000, mortal_remains_amount: 15000, rescue_reimbursement_process: true },
    GRF: { rescue_amount: 300000, medical_amount: 5000, mortal_remains_amount: 15000, rescue_reimbursement_process: true },
  };

  return matrix[normalizedTier] || matrix.Supporter;
};

export const formatDollars = (amount) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount || 0);
