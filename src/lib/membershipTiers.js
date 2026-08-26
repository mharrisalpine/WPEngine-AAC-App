/**
 * Display membership tiles for signup / renewal.
 * Legacy stored ids `Lifetime` and `Partner Family` map to current visible levels.
 */
export const MEMBERSHIP_TIER_OPTIONS = [
  {
    id: 'Free',
    label: 'Free',
    pmproLevelId: 1,
    publicSelectable: false,
    manualOnly: false,
    blurb: 'Create an account, preview the portal, and receive promotional offers.',
    priceCents: 0,
    benefits: [
      'Create your AAC portal account',
      'Preview your profile and account settings',
      'Receive AAC promotional emails and promo codes',
      'Upgrade anytime to unlock paid member benefits',
      'No partner discounts or rescue coverage',
    ],
  },
  {
    id: 'Supporter',
    label: 'Supporter',
    pmproLevelId: 2,
    publicSelectable: true,
    manualOnly: false,
    blurb: 'Core member benefits and community access.',
    priceCents: 4500,
    benefits: [
      'Digital member communications and club updates',
      'Access to partner discounts',
      'AAC community and member network access',
      'AAC email newsletter and climbing news',
      'Member benefit updates and club news',
      'Support for climbing conservation and access advocacy',
    ],
  },
  {
    id: 'Partner',
    label: 'Partner',
    pmproLevelId: 3,
    publicSelectable: true,
    manualOnly: false,
    blurb: 'Enhanced rescue & medical benefit levels.',
    priceCents: 10000,
    benefits: [
      '$7,500 in rescue coverage',
      '$5,000 in medical coverage',
      '$15,000 in mortal remains transport',
      'Redpoint rescue reimbursement process included',
      'Everything included in Supporter',
      'Additional Partner-level member benefits',
    ],
  },
  {
    id: 'Leader',
    label: 'Leader',
    pmproLevelId: 4,
    publicSelectable: true,
    manualOnly: false,
    blurb: 'Highest published annual membership-level benefit limits.',
    priceCents: 25000,
    benefits: [
      '$300,000 in rescue coverage',
      '$5,000 in medical coverage',
      '$15,000 in mortal remains transport',
      'Redpoint rescue reimbursement process included',
      'Everything included in Partner',
      'Priority consideration for select AAC programs',
    ],
  },
  {
    id: 'Advocate',
    label: 'Advocate',
    pmproLevelId: 5,
    publicSelectable: true,
    manualOnly: false,
    blurb: 'Highest level of annual member support.',
    priceCents: 50000,
    benefits: [
      'Annual Advocate membership',
      '$300,000 in rescue coverage',
      '$5,000 in medical coverage',
      '$15,000 in mortal remains transport',
      'Redpoint rescue reimbursement process included',
      'Everything included in Leader',
      'Expanded support for the AAC mission and climbing community',
      'Recognition as an Advocate-level member',
    ],
  },
  {
    id: 'GRF',
    label: 'GRF',
    pmproLevelId: null,
    publicSelectable: false,
    manualOnly: true,
    blurb: 'Manual donor level for members who have contributed $1,500 within a single year.',
    benefits: [
      'Manual donor recognition level',
      '$300,000 in rescue coverage',
      '$5,000 in medical coverage',
      '$15,000 in mortal remains transport',
      'Redpoint rescue reimbursement process included',
      'Everything included in Advocate',
      'Reserved for members added manually by AAC staff',
      'Not available through public checkout or self-service upgrades',
    ],
  },
];

export const DONATION_OPTIONS_USD = [
  { value: 0, label: 'No thanks' },
  { value: 5, label: '$5' },
  { value: 10, label: '$10' },
  { value: 15, label: '$15' },
  { value: 30, label: '$30' },
  { value: 50, label: '$50' },
  { value: 250, label: '$250' },
];

export const PHONE_TYPE_OPTIONS = [
  { value: 'mobile', label: 'Mobile' },
  { value: 'home', label: 'Home' },
  { value: 'work', label: 'Work' },
];

export const TSHIRT_SIZES = [
  'No T-shirt',
  'Unisex X-Small',
  'Unisex Small',
  'Unisex Medium',
  'Unisex Large',
  'Unisex X-Large',
  'Unisex XX-Large',
];

const MEMBERSHIP_ACCESS_RANK = {
  Free: 0,
  Supporter: 1,
  Partner: 2,
  Leader: 3,
  Advocate: 4,
  GRF: 5,
  Lifetime: 5,
};

/** Map legacy stored tier ids to a canonical option id. */
export function normalizeTierId(raw) {
  if (!raw || typeof raw !== 'string') {
    return 'Partner';
  }
  if (raw === 'Lifetime') {
    return 'Advocate';
  }
  if (raw === 'Partner Family') {
    return 'Partner';
  }
  return MEMBERSHIP_TIER_OPTIONS.some((t) => t.id === raw) ? raw : 'Partner';
}

export function getTierById(id) {
  const normalized = normalizeTierId(id);
  return MEMBERSHIP_TIER_OPTIONS.find((t) => t.id === normalized) || MEMBERSHIP_TIER_OPTIONS[0];
}

export function getTierDisplayLabel(id, fallback = 'Free') {
  if (!id || typeof id !== 'string') {
    return fallback;
  }

  if (id === 'Lifetime') {
    return 'Advocate';
  }
  if (id === 'Partner Family') {
    return 'Partner';
  }

  const exactMatch = MEMBERSHIP_TIER_OPTIONS.find((tier) => tier.id === id);
  return exactMatch ? exactMatch.label : id;
}

export function getPmproLevelIdForTier(id) {
  return getTierById(id).pmproLevelId;
}

export function isPublicMembershipTierId(id) {
  return Boolean(getTierById(id).publicSelectable);
}

export function isManualOnlyMembershipTierId(id) {
  return Boolean(getTierById(id).manualOnly);
}

export function isOneTimeMembershipTierId(id) {
  return false;
}

export function getMembershipAccessRank(id) {
  const normalized = normalizeTierId(id);
  return MEMBERSHIP_ACCESS_RANK[normalized] ?? 0;
}

export function isPartnerOrAboveMembershipTierId(id) {
  return getMembershipAccessRank(id) >= MEMBERSHIP_ACCESS_RANK.Partner;
}
