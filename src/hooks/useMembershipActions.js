import { useCallback } from 'react';
import { toast } from '@/components/ui/use-toast';
import { useAuth } from '@/hooks/useAuth';
import { openExternalUrl } from '@/lib/mobileNavigation';

const isNativeShell = () =>
  import.meta.env.VITE_APP_RUNTIME === 'mobile' ||
  Boolean(window?.Capacitor);

export const useMembershipActions = () => {
  const { profile } = useAuth();

  const getMembershipActionUrl = useCallback((type, overrides = {}) => {
    const actions = profile?.membership_actions || {};
    const targetTier = overrides.targetTier || profile?.profile_info?.tier || '';

    if (type === 'manage') {
      return actions.account_url || '';
    }

    if (type === 'manage_payment') {
      return actions.billing_url || actions.account_url || '';
    }

    if (type === 'cancel') {
      return actions.cancel_url || '';
    }

    if (type === 'add_dependent') {
      return actions.add_dependent_checkout_url || '';
    }

    const targetAction = targetTier ? actions.levels?.[targetTier] : null;
    if (targetAction?.checkout_url) {
      return targetAction.checkout_url;
    }

    if (actions.current_level_checkout_url && (type === 'renew' || type === 'join')) {
      return actions.current_level_checkout_url;
    }

    return '';
  }, [profile?.membership_actions, profile?.profile_info?.tier]);

  const navigateToMembershipUrl = useCallback(async (url) => {
    if (!url) {
      return false;
    }

    if (isNativeShell()) {
      await openExternalUrl(url);
      return true;
    }

    window.location.assign(url);
    return true;
  }, []);

  const openMembershipAction = useCallback(async (type, overrides = {}) => {
    if (type === 'downgrade') {
      const targetTier = overrides.targetTier;
      if (!targetTier) {
        toast({
          variant: 'destructive',
          title: 'Choose a membership level',
          description: 'Select the lower membership level to schedule for renewal.',
        });
        return false;
      }

      const targetAction = profile?.membership_actions?.levels?.[targetTier];
      if (targetAction?.action_type === 'downgrade_unavailable') {
        toast({
          variant: 'destructive',
          title: 'Downgrade unavailable',
          description: 'Downgrades are only available for auto-renew members within 30 days of renewal.',
        });
        return false;
      }
    }

    const url = getMembershipActionUrl(type, overrides);

    if (url) {
      return navigateToMembershipUrl(url);
    }

    toast({
      variant: 'destructive',
      title: 'Membership checkout unavailable',
      description: 'This account is missing the PMPro checkout URL for that action. Please refresh or contact AAC membership support.',
    });

    return false;
  }, [getMembershipActionUrl, navigateToMembershipUrl]);

  return {
    getMembershipActionUrl,
    openMembershipAction,
    hasManagedMembershipUrls: Boolean(
      profile?.membership_actions?.account_url ||
      profile?.membership_actions?.billing_url ||
      profile?.membership_actions?.cancel_url ||
      Object.keys(profile?.membership_actions?.levels || {}).length
    ),
  };
};
