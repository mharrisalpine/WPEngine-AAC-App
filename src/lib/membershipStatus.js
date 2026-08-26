export const getMembershipStatus = (profileInfo) => {
  if (profileInfo?.status) {
    return profileInfo.status;
  }

  if (!profileInfo?.tier) {
    return 'Non-Member';
  }

  if (!profileInfo?.renewal_date) {
    return 'Inactive';
  }

  const renewalDate = new Date(profileInfo.renewal_date);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  return renewalDate >= today ? 'Active' : 'Inactive';
};

export const isMembershipActive = (profileInfo) => getMembershipStatus(profileInfo) === 'Active';
