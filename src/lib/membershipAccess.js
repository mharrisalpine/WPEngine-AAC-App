import { getMembershipStatus } from '@/lib/membershipStatus';
import { normalizeTierId } from '@/lib/membershipTiers';

export const getMembershipTier = (profileInfo = {}) => {
  if (!profileInfo?.tier) {
    return '';
  }

  return normalizeTierId(profileInfo.tier);
};

export const isFreeMembershipTier = (profileInfo = {}) => getMembershipTier(profileInfo) === 'Free';

export const canAccessDiscounts = (profileInfo = {}) =>
  getMembershipStatus(profileInfo) === 'Active' && !isFreeMembershipTier(profileInfo);

export const canAccessRescue = (profileInfo = {}, benefitsInfo = {}) =>
  getMembershipStatus(profileInfo) === 'Active' &&
  !isFreeMembershipTier(profileInfo) &&
  (((benefitsInfo?.rescue_amount || 0) > 0) || ((benefitsInfo?.medical_amount || 0) > 0));
