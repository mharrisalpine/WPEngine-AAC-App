import { apiRequest, setAuthToken, setRestNonce } from '@/lib/apiClient';
import { fakeAuthDb, shouldUseFakeMemberDb } from '@/lib/fakeMemberDb';

const withOptionalFakeBackend = async (remoteCall, fallbackCall) => {
  if (shouldUseFakeMemberDb()) {
    return fallbackCall();
  }

  return remoteCall();
};

export const getCurrentMember = () =>
  withOptionalFakeBackend(
    () => apiRequest('/me'),
    () => fakeAuthDb.getCurrentMember()
  );

export async function loginMember(email, password) {
  setAuthToken(null);
  setRestNonce(null);

  const data = await withOptionalFakeBackend(
    () => apiRequest('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),
    () => fakeAuthDb.loginMember(email, password)
  );

  setAuthToken(data.token || null);
  setRestNonce(data.restNonce || null);
  return data;
}

export async function registerMember(email, password, options = {}) {
  const data = await withOptionalFakeBackend(
    () => apiRequest('/register', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        first_name: options?.data?.first_name || '',
        last_name: options?.data?.last_name || '',
      }),
    }),
    () => fakeAuthDb.registerMember(email, password, options)
  );

  setAuthToken(data.token || null);
  setRestNonce(data.restNonce || null);
  return data;
}

export async function logoutMember() {
  const data = await withOptionalFakeBackend(
    () => apiRequest('/logout', { method: 'POST' }),
    () => fakeAuthDb.logoutMember()
  );

  // Only discard local credentials after WordPress confirms that its session
  // cookie was cleared. Otherwise a failed request can make the UI appear
  // signed out while the browser remains authenticated on the server.
  setAuthToken(null);
  setRestNonce(null);
  return data;
}

export async function changeMemberPassword(currentPassword, newPassword, confirmPassword) {
  const data = await withOptionalFakeBackend(
    () => apiRequest('/change-password', {
      method: 'POST',
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword,
      }),
    }),
    () => fakeAuthDb.changePassword(currentPassword, newPassword, confirmPassword)
  );

  if (data?.restNonce) {
    setRestNonce(data.restNonce);
  }

  return data;
}

export const updateMemberProfile = (updates) =>
  withOptionalFakeBackend(
    () => apiRequest('/profile', {
      method: 'PATCH',
      body: JSON.stringify(updates),
    }),
    () => fakeAuthDb.updateMemberProfile(updates)
  );

export const submitContactMessage = ({ name, email, issueType, message }) =>
  withOptionalFakeBackend(
    () => apiRequest('/contact', {
      method: 'POST',
      body: JSON.stringify({ name, email, issue_type: issueType, message }),
    }),
    () => fakeAuthDb.submitContactMessage({ name, email, issueType, message })
  );

export const getMemberTransactions = () =>
  withOptionalFakeBackend(
    () => apiRequest('/transactions'),
    () => fakeAuthDb.getMemberTransactions()
  );

export const scheduleMembershipDowngrade = (targetTier) =>
  withOptionalFakeBackend(
    () => apiRequest('/membership/downgrade', {
      method: 'POST',
      body: JSON.stringify({ target_tier: targetTier }),
    }),
    async () => {
      throw new Error('Scheduled membership downgrades are not available in demo mode.');
    }
  );

export const validateInviteCode = (code) =>
  withOptionalFakeBackend(
    () => apiRequest(`/invite-code?code=${encodeURIComponent(code)}`),
    () => fakeAuthDb.validateInviteCode(code)
  );

export async function redeemInviteCode(payload) {
  const data = await withOptionalFakeBackend(
    () => apiRequest('/redeem-invite', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
    () => fakeAuthDb.redeemInviteCode(payload)
  );

  setAuthToken(data.token || null);
  setRestNonce(data.restNonce || null);
  return data;
}

export const scheduleLinkedAccountRemoval = (slotId) =>
  withOptionalFakeBackend(
    () => apiRequest('/linked-accounts/remove', {
      method: 'POST',
      body: JSON.stringify({ slot_id: slotId }),
    }),
    async () => {
      throw new Error('Linked account renewal removal is not available in the demo mode.');
    }
  );

export const createLinkedAccount = (payload) =>
  withOptionalFakeBackend(
    () => apiRequest('/linked-accounts/create', {
      method: 'POST',
      body: JSON.stringify(payload || {}),
      timeoutMs: 60000,
    }),
    async () => {
      throw new Error('Linked account creation is not available in the demo mode.');
    }
  );
