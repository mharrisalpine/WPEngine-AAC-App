/**
 * Prompt non-auto-renewing members when expiration is within this many days (or already past).
 */
export const RENEWAL_PROMPT_DAYS = 90;

export function getDaysUntilMembershipDate(dateStr) {
  if (!dateStr || typeof dateStr !== 'string') {
    return null;
  }

  const trimmed = dateStr.trim();
  const end = trimmed.includes('T')
    ? new Date(trimmed)
    : new Date(`${trimmed}T23:59:59`);

  if (Number.isNaN(end.getTime())) {
    return null;
  }

  const now = new Date();
  return Math.ceil((end.getTime() - now.getTime()) / 86400000);
}

/** @param {object | null | undefined} profile */
export function shouldPromptMembershipVerification(profile) {
  const autoRenew = Boolean(profile?.account_info?.auto_renew);
  if (autoRenew) {
    return false;
  }

  const expirationDate = profile?.profile_info?.expiration_date;
  const days = getDaysUntilMembershipDate(expirationDate);
  if (days === null) {
    return false;
  }

  const status = String(profile?.profile_info?.status || '').toLowerCase();
  if (status === 'active' && days < 0) {
    return false;
  }

  return days <= RENEWAL_PROMPT_DAYS;
}

export function getExpirationWarningDetails(profile) {
  const autoRenew = Boolean(profile?.account_info?.auto_renew);
  if (autoRenew) {
    return null;
  }

  const expirationDate = profile?.profile_info?.expiration_date;
  const daysUntilExpiration = getDaysUntilMembershipDate(expirationDate);
  if (daysUntilExpiration === null || daysUntilExpiration > RENEWAL_PROMPT_DAYS) {
    return null;
  }

  const status = String(profile?.profile_info?.status || '').toLowerCase();
  if (status === 'active' && daysUntilExpiration < 0) {
    return null;
  }

  const parsedDate = expirationDate.includes('T')
    ? new Date(expirationDate)
    : new Date(`${expirationDate}T23:59:59`);

  const formattedDate = Number.isNaN(parsedDate.getTime())
    ? ''
    : new Intl.DateTimeFormat(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
      }).format(parsedDate);

  return {
    daysUntilExpiration,
    formattedDate,
    isExpired: daysUntilExpiration < 0,
  };
}
