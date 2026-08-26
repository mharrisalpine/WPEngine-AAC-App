import { useMembershipActions } from '@/hooks/useMembershipActions';

export const useStripe = () => {
    const { openMembershipAction } = useMembershipActions();
    return { openCustomerPortal: openMembershipAction };
};
