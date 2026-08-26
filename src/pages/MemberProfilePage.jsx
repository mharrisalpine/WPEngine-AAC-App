import React from 'react';
import { motion } from 'framer-motion';
import { AlertTriangle, Calendar, CheckCircle2, CreditCard, HeartPulse, PhoneCall, Receipt, Shield, User, Users } from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import MembershipCard from '@/components/MembershipCard';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/hooks/useAuth';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { useToast } from '@/components/ui/use-toast';
import { createLinkedAccount, scheduleLinkedAccountRemoval } from '@/lib/memberApi';
import { formatTShirtSizeLabel, normalizePrintDigitalPreference } from '@/lib/memberProfile';
import { getExpirationWarningDetails, RENEWAL_PROMPT_DAYS } from '@/lib/membershipRenewal';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { isMembershipActive } from '@/lib/membershipStatus';
import { cn } from '@/lib/utils';

// Keep the family-member purchase flow intact while hiding its profile controls.
// Set this back to true when AAC is ready to offer the option again.
const SHOW_ADD_FAMILY_MEMBER_CONTROLS = false;
const SHOW_FAMILY_REDEEM_INVITE_BUTTON = false;

const DetailRow = ({ label, value }) => (
  <div className="flex items-start justify-between gap-4 border-b border-stone-200/80 py-3 last:border-b-0 last:pb-0">
    <span className="text-sm font-medium uppercase tracking-[0.18em] text-stone-500">{label}</span>
    <span className="text-right text-sm text-stone-900">{value || 'Not provided'}</span>
  </div>
);

const InfoCard = ({ icon: Icon, title, description, children }) => (
  <section className="bg-white py-6">
    <div className="mb-5 border-b-2 border-[#b71c1c] pb-4">
      <div className="flex items-start gap-3">
        <div className="pt-1 text-[#b71c1c]">
        <Icon className="h-5 w-5" />
        </div>
        <div>
          <h2 className="text-xl font-bold text-stone-900">{title}</h2>
          {description ? <p className="mt-1 text-sm text-stone-600">{description}</p> : null}
        </div>
      </div>
    </div>
    {children}
  </section>
);

const CUSTOM_BLOCK_ICONS = {
  receipt: Receipt,
  user: User,
  shield: Shield,
  users: Users,
  heart: HeartPulse,
  'credit-card': CreditCard,
  calendar: Calendar,
};

const formatAddress = (accountInfo = {}) => {
  const parts = [
    accountInfo.street,
    accountInfo.address2,
    [accountInfo.city, accountInfo.state].filter(Boolean).join(', '),
    [accountInfo.zip, accountInfo.country].filter(Boolean).join(' '),
  ].filter(Boolean);

  return parts.join(', ');
};

const formatMembershipDate = (value, fallback = 'Not scheduled') => {
  if (!value) {
    return fallback;
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? fallback : parsed.toLocaleDateString();
};

const formatCurrency = (amount) => {
  const numericAmount = Number(amount || 0);

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(numericAmount);
};

const REDPOINT_EMERGENCY_PHONE_DISPLAY = '+01-628-251-1510';
const REDPOINT_EVACUATION_CLAIM_URL = 'https://aac-profile.s3.amazonaws.com/website_assets/MedicalEvacuationClaim_AAC_Redpoint.pdf';
const REDPOINT_MEDICAL_EXPENSE_CLAIM_URL = 'https://aac-profile.s3.amazonaws.com/website_assets/MedicalExpenseClaim_AAC_Redpoint.pdf';

const getRenewalPromptStorageKey = (profile, warningDetails) => {
  const memberKey =
    profile?.profile_info?.member_id ||
    profile?.account_info?.email ||
    profile?.account_info?.user_id ||
    'member';
  const expirationKey =
    profile?.profile_info?.expiration_date ||
    warningDetails?.formattedDate ||
    'unknown-expiration';

  return `aac-renewal-expiration-prompt:${memberKey}:${expirationKey}`;
};

const getExpirationPromptDescription = (warningDetails) => {
  if (!warningDetails) {
    return '';
  }

  const dateText = warningDetails.formattedDate ? ` on ${warningDetails.formattedDate}` : '';

  if (warningDetails.isExpired) {
    return `Your AAC membership expired${dateText}. Renew now to keep your member benefits active.`;
  }

  if (warningDetails.daysUntilExpiration === 0) {
    return `Your AAC membership expires today${dateText}. Renew now to avoid a lapse in benefits.`;
  }

  return `Your AAC membership expires in ${warningDetails.daysUntilExpiration} days${dateText}. Renew now to keep your benefits active.`;
};

const formatConnectedAccountPrice = (value) => {
  const amount = Number(value || 0);
  return amount > 0 ? `$${amount.toFixed(2)}/yr` : 'Included';
};

const formatLinkedAccountStatus = (status) => {
  if (status === 'removal_pending') {
    return 'Removing at renewal';
  }

  return status;
};

const MemberProfilePage = () => {
  const navigate = useNavigate();
  const { profile, loading, refreshProfile } = useAuth();
  const { openMembershipAction } = useMembershipActions();
  const { toast } = useToast();
  const portalUiSettings = getPortalUiSettings();
  const portalDesign = portalUiSettings.design || {};
  const portalContent = portalUiSettings.content;
  const location = useLocation();
  const [removingSlotId, setRemovingSlotId] = React.useState('');
  const [creatingSlotId, setCreatingSlotId] = React.useState('');
  const [linkedAccountDrafts, setLinkedAccountDrafts] = React.useState({});
  const [showExpirationPrompt, setShowExpirationPrompt] = React.useState(false);
  const expirationWarningDetails = React.useMemo(
    () => (profile ? getExpirationWarningDetails(profile) : null),
    [profile],
  );

  React.useEffect(() => {
    if (!profile || !expirationWarningDetails) {
      setShowExpirationPrompt(false);
      return;
    }

    const storageKey = getRenewalPromptStorageKey(profile, expirationWarningDetails);
    const wasDismissed = typeof window !== 'undefined'
      && window.sessionStorage?.getItem(storageKey) === 'dismissed';

    if (!wasDismissed) {
      setShowExpirationPrompt(true);
    }
  }, [profile, expirationWarningDetails]);

  const dismissExpirationPrompt = React.useCallback(() => {
    if (profile && expirationWarningDetails && typeof window !== 'undefined') {
      window.sessionStorage?.setItem(
        getRenewalPromptStorageKey(profile, expirationWarningDetails),
        'dismissed',
      );
    }

    setShowExpirationPrompt(false);
  }, [profile, expirationWarningDetails]);

  const handleRenewFromExpirationPrompt = React.useCallback(() => {
    dismissExpirationPrompt();
    void openMembershipAction('renew', { targetTier: profile?.profile_info?.tier || 'Partner' });
  }, [dismissExpirationPrompt, openMembershipAction, profile?.profile_info?.tier]);

  if (loading || !profile) {
    return <div className="pt-10 text-center text-stone-800">Loading member profile...</div>;
  }

  const accountInfo = profile.account_info || {};
  const profileInfo = profile.profile_info || {};
  const benefitsInfo = profile.benefits_info || {};
  const connectedAccounts = Array.isArray(profile.connected_accounts) ? profile.connected_accounts : [];
  const familyMembership = profile.family_membership || { mode: '', additional_adult: false, dependent_count: 0 };
  const linkedParentAccount = profile.linked_parent_account || null;
  const membershipActive = isMembershipActive(profileInfo);
  const linkedSuccess = new URLSearchParams(location.search).get('linked') === '1';
  const memberProfileBlocks = Array.isArray(portalContent.memberProfileBlocks)
    ? portalContent.memberProfileBlocks.filter((block) => block && (block.title || block.description || (Array.isArray(block.entries) && block.entries.length)))
    : [];
  const memberProfileCardSections = portalContent.memberProfileCardSections || {};
  const isCardVisible = (cardId) => {
    const cardSettings = memberProfileCardSections?.[cardId];
    if (!cardSettings || typeof cardSettings !== 'object') {
      return true;
    }

    return cardSettings.visible !== 0 && cardSettings.visible !== false;
  };
  const canManageConnectedAccounts = !linkedParentAccount;
  const profileTier = profile?.profile_info?.tier || '';
  const isFamilyModeMembership = familyMembership.mode === 'family';
  const hasPurchasedFamilyMembership = Boolean(
    isFamilyModeMembership &&
    (
      familyMembership.additional_adult ||
      (familyMembership.dependent_count || 0) > 0 ||
      connectedAccounts.length > 0
    ),
  );
  const addDependentCheckoutUrl = profile?.membership_actions?.add_dependent_checkout_url || '';
  const canAddDependent = Boolean(
    canManageConnectedAccounts &&
    membershipActive &&
    hasPurchasedFamilyMembership &&
    addDependentCheckoutUrl &&
    (familyMembership.dependent_count || 0) < 3,
  );
  const addDependentUnavailableReason = (() => {
    if (canAddDependent || !membershipActive || !hasPurchasedFamilyMembership) {
      return '';
    }

    if (!canManageConnectedAccounts) {
      const parentName = linkedParentAccount?.parent_name || linkedParentAccount?.name || 'the primary family account';
      return `This account is linked under ${parentName}. Add family members from the primary family account.`;
    }

    if ((familyMembership.dependent_count || 0) >= 3) {
      return 'This family membership already has the maximum number of dependents.';
    }

    if (!addDependentCheckoutUrl) {
      return 'Dependent checkout is not available for this account yet. Contact AAC support to add a family member.';
    }

    return '';
  })();
	const shouldShowFamilyManagement = Boolean(
	  SHOW_ADD_FAMILY_MEMBER_CONTROLS && (
	    canAddDependent ||
	    (membershipActive && hasPurchasedFamilyMembership)
	  )
	);
  const shouldShowLinkedAccounts = Boolean(
    linkedParentAccount ||
    hasPurchasedFamilyMembership ||
    connectedAccounts.length > 0 ||
    canAddDependent
  );
  const hasRedpointBenefits = Boolean(
    Number(benefitsInfo.rescue_amount || 0) > 0 ||
    Number(benefitsInfo.medical_amount || 0) > 0 ||
    Number(benefitsInfo.mortal_remains_amount || 0) > 0 ||
    benefitsInfo.rescue_reimbursement_process
  );
  const redpointCoverageLabel = membershipActive && hasRedpointBenefits ? 'Active' : 'Not active';

  const handleScheduleRemoval = async (slotId) => {
    if (!slotId) {
      return;
    }

    setRemovingSlotId(slotId);
    try {
      await scheduleLinkedAccountRemoval(slotId);
      await refreshProfile();
      toast({
        title: 'Family member scheduled for removal',
        description: 'This linked account will stay active through the current family plan end date.',
      });
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Unable to update family plan',
        description: error.message || 'We could not schedule this family member for removal right now.',
      });
    } finally {
      setRemovingSlotId('');
    }
  };

  const updateLinkedAccountDraft = (slotId, updates) => {
    setLinkedAccountDrafts((current) => ({
      ...current,
      [slotId]: {
        ...(current[slotId] || {}),
        ...updates,
      },
    }));
  };

  const handleCreateLinkedAccount = async (account) => {
    const slotId = account?.id || '';
    const draft = linkedAccountDrafts[slotId] || {};
    const firstName = String(draft.first_name || '').trim();
    const lastName = String(draft.last_name || '').trim();
    const email = String(draft.email || '').trim();

    if (!slotId || !firstName || !lastName || !email) {
      toast({
        variant: 'destructive',
        title: 'Family member details required',
        description: 'Enter first name, last name, and email before creating the linked account.',
      });
      return;
    }

    setCreatingSlotId(slotId);
    try {
      const result = await createLinkedAccount({
        slot_id: slotId,
        first_name: firstName,
        last_name: lastName,
        email,
      });
      await refreshProfile();
      setLinkedAccountDrafts((current) => {
        const next = { ...current };
        delete next[slotId];
        return next;
      });
      toast({
        title: 'Family account created',
        description: result?.email_sent
          ? 'A password setup link was sent to the family member.'
          : 'The account was created, but WordPress could not confirm the email was sent. You can resend a password reset link from WordPress Users.',
      });
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Unable to create family account',
        description: error.message || 'We could not create that linked account right now.',
      });
    } finally {
      setCreatingSlotId('');
    }
  };

  const handleAddDependent = async () => {
    const started = await openMembershipAction('add_dependent');
    if (!started) {
      toast({
        variant: 'destructive',
        title: 'Unable to start checkout',
        description: 'Please contact AAC support to add a dependent to this membership.',
      });
    }
  };

  return (
    <>
      <Dialog open={showExpirationPrompt} onOpenChange={(open) => {
        if (!open) {
          dismissExpirationPrompt();
        } else {
          setShowExpirationPrompt(true);
        }
      }}>
        <DialogContent className="w-[calc(100%-2rem)] rounded-none border-2 border-[#b71c1c] bg-white p-6 shadow-[0_26px_70px_rgba(0,0,0,0.22)] sm:max-w-xl sm:p-8">
          <DialogHeader className="pr-8 text-left">
            <div className="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-[#b71c1c]/10 text-[#b71c1c]">
              <AlertTriangle className="h-6 w-6" />
            </div>
            <DialogTitle className="text-2xl font-bold text-stone-950">Membership Expiring Soon</DialogTitle>
            <DialogDescription className="text-base leading-7 text-stone-700">
              {getExpirationPromptDescription(expirationWarningDetails)}
            </DialogDescription>
          </DialogHeader>
          <div className="border-t-2 border-[#b71c1c] pt-5">
            <p className="text-sm leading-6 text-stone-600">
              This alert appears because the expiration date is within the {RENEWAL_PROMPT_DAYS}-day renewal window and auto-renewal is not currently enabled.
            </p>
            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
              <Button
                type="button"
                variant="outline"
                className="min-h-[3rem] rounded-none border-stone-300 px-5 text-black hover:bg-stone-100"
                onClick={dismissExpirationPrompt}
              >
                Remind me later
              </Button>
              <Button
                type="button"
                className="min-h-[3rem] rounded-none bg-[#b71c1c] px-6 text-white hover:bg-[#8f1515]"
                onClick={handleRenewFromExpirationPrompt}
              >
                Renew membership
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <div className="aac-member-profile-page bg-white pb-6 pt-4 md:pt-6">
      <motion.div
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="mx-auto w-full max-w-6xl space-y-6"
      >
        {isCardVisible('membership_card') ? <MembershipCard profile={profile} /> : null}

        {shouldShowFamilyManagement ? (
          <section className="bg-white py-4 text-stone-900">
            <div className="flex flex-col gap-4 border-b-2 border-[#b71c1c] pb-5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-start gap-3">
                <div className="pt-1 text-[#b71c1c]">
                  <Users className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-[#b71c1c]">Family Membership</p>
                  <h2 className="mt-2 text-xl font-bold text-stone-900">Add Family Member</h2>
                  <p className="mt-1 text-sm leading-6 text-stone-600">
                    Add a dependent to this Partner membership and create an invite code after checkout.
                  </p>
			{SHOW_ADD_FAMILY_MEMBER_CONTROLS && addDependentUnavailableReason ? (
                    <p className="mt-2 text-sm font-medium text-[#8f1515]">{addDependentUnavailableReason}</p>
                  ) : null}
                </div>
              </div>
              <Button
                type="button"
                className="min-h-[3rem] shrink-0 rounded-none bg-[#b71c1c] px-8 text-white hover:bg-[#8f1515]"
                disabled={!canAddDependent}
                onClick={handleAddDependent}
              >
                Add Family Member
              </Button>
            </div>
          </section>
        ) : null}

        <section className="bg-white py-6 text-stone-900">
          <div className="aac-redpoint-heading-divider border-b-2 border-[#b71c1c] pb-4">
            <div className="flex items-start gap-3">
              <div className="pt-1 text-[#b71c1c]">
                <HeartPulse className="h-5 w-5" />
              </div>
              <div>
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-[#b71c1c]">Medical & Rescue</p>
                <h2 className="mt-2 text-2xl font-bold text-stone-900">Redpoint Benefits</h2>
                <p className="mt-1 text-sm text-stone-600">Your current Redpoint rescue and evacuation coverage snapshot.</p>
              </div>
            </div>
          </div>

          <div className="grid gap-4 py-6 md:grid-cols-2 xl:grid-cols-4">
            <div className="border-t border-stone-200 pt-4">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Coverage Status</p>
              <p className="mt-3 text-xl font-semibold text-stone-900">{redpointCoverageLabel}</p>
              <p className="mt-2 text-sm text-stone-600">
                {membershipActive && hasRedpointBenefits
                  ? 'Included with your current membership.'
                  : 'Upgrade or renew an eligible membership to restore coverage.'}
              </p>
            </div>
            <div className="border-t border-stone-200 pt-4">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Rescue Coverage</p>
              <p className="mt-3 text-2xl font-bold text-stone-900">{formatCurrency(benefitsInfo.rescue_amount)}</p>
            </div>
            <div className="border-t border-stone-200 pt-4">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Medical Coverage</p>
              <p className="mt-3 text-2xl font-bold text-stone-900">{formatCurrency(benefitsInfo.medical_amount)}</p>
            </div>
            <div className="border-t border-stone-200 pt-4">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Mortal Remains Transport</p>
              <p className="mt-3 text-2xl font-bold text-stone-900">{formatCurrency(benefitsInfo.mortal_remains_amount)}</p>
            </div>
          </div>

          <div className="aac-redpoint-emergency-divider border-t-2 border-[#b71c1c] pt-5">
            <div className="flex flex-col gap-5">
              <div className="flex flex-col items-center gap-3 text-center">
                <div className="text-[#b71c1c]">
                  <PhoneCall className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Emergency Contact</p>
                  <p className="mt-2 text-xl font-bold text-stone-900">{REDPOINT_EMERGENCY_PHONE_DISPLAY}</p>
                  <p className="mt-2 text-sm leading-6 text-stone-600">In case of rescue, contact Redpoint Travel Protection.</p>
                </div>
              </div>
              <div className="flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                <Button asChild type="button" className="min-h-[3rem] rounded-none bg-[#b71c1c] px-6 text-white hover:bg-[#8f1515]">
                  <a href={REDPOINT_EVACUATION_CLAIM_URL} target="_blank" rel="noreferrer">Medical Evacuation Claim</a>
                </Button>
                <Button asChild type="button" className="min-h-[3rem] rounded-none bg-[#b71c1c] px-6 text-white hover:bg-[#8f1515]">
                  <a href={REDPOINT_MEDICAL_EXPENSE_CLAIM_URL} target="_blank" rel="noreferrer">Medical Expense Claim</a>
                </Button>
              </div>
            </div>
          </div>
        </section>

        <div className="grid gap-6">
          {isCardVisible('profile_information') ? (
            <InfoCard
              icon={User}
              title={portalContent.profile_information_title}
              description={portalContent.profile_information_description}
            >
              <div className="space-y-1">
                <DetailRow label="Email" value={accountInfo.email} />
                <DetailRow label="Phone" value={accountInfo.phone} />
                <DetailRow label="Address" value={formatAddress(accountInfo)} />
                <DetailRow label="T-Shirt Size" value={formatTShirtSizeLabel(accountInfo.size)} />
                <DetailRow label="American Alpine Journal" value={normalizePrintDigitalPreference(accountInfo.aaj_pref)} />
                <DetailRow label="Accidents in North American Climbing" value={normalizePrintDigitalPreference(accountInfo.anac_pref)} />
                <DetailRow label="American Climbing Journal" value={normalizePrintDigitalPreference(accountInfo.acj_pref)} />
                <DetailRow label="Guidebook to Membership" value={normalizePrintDigitalPreference(accountInfo.guidebook_pref)} />
              </div>
              <div className="mt-5 flex justify-center">
                <Button
                  type="button"
                  className="min-h-[3.125rem] rounded-full bg-[#b71c1c] px-7 text-white shadow-sm hover:bg-[#8f1515]"
                  onClick={() => navigate('/account')}
                >
                  {portalContent.update_profile_button_label}
                </Button>
              </div>
            </InfoCard>
          ) : null}

        </div>

        {shouldShowLinkedAccounts && isCardVisible('linked_accounts') ? (
          <InfoCard
            icon={Users}
            title={portalContent.linked_accounts_title}
            description={portalContent.linked_accounts_description}
          >
            {linkedSuccess ? (
              <div className="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                <span>Linked account updated successfully.</span>
              </div>
            ) : null}

            {linkedParentAccount ? (
              <div className="mb-5 rounded-[20px] border border-stone-200 bg-stone-50/80 px-4 py-4">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Linked Parent Account</p>
                <div className="mt-3 grid gap-3 text-sm text-stone-700 sm:grid-cols-2">
                  <div className="rounded-2xl bg-white px-4 py-3">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Connected To</p>
                    <p className="mt-1 font-semibold text-stone-900">{linkedParentAccount.parent_name || 'AAC Parent Account'}</p>
                    {linkedParentAccount.parent_email ? <p className="mt-1 text-stone-600">{linkedParentAccount.parent_email}</p> : null}
                  </div>
                  <div className="rounded-2xl bg-white px-4 py-3">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Linked Role</p>
                    <p className="mt-1 font-semibold text-stone-900">{linkedParentAccount.label || 'Family member'}</p>
                    {linkedParentAccount.invite_code ? <p className="mt-1 font-mono text-stone-600">{linkedParentAccount.invite_code}</p> : null}
                    {linkedParentAccount.scheduled_removal_date ? (
                      <p className="mt-1 text-stone-600">
                        Access ends {formatMembershipDate(linkedParentAccount.scheduled_removal_date, 'Not scheduled')}
                      </p>
                    ) : null}
                  </div>
                </div>
              </div>
            ) : null}

            {familyMembership.mode === 'family' || connectedAccounts.length > 0 ? (
              <>
                <div className="space-y-1">
                  <DetailRow
                    label="Family Plan"
                    value={familyMembership.mode === 'family' ? 'Enabled' : 'Not enabled'}
                  />
                  <DetailRow
                    label="Additional Adult"
                    value={familyMembership.additional_adult ? 'Included' : 'Not included'}
                  />
                  <DetailRow
                    label="Dependents"
                    value={String(familyMembership.dependent_count || 0)}
                  />
                </div>
                {connectedAccounts.length ? (
                  <div className="mt-5 space-y-3">
                    {connectedAccounts.map((account) => (
                      <div key={account.id} className="rounded-[20px] border border-stone-200 bg-stone-50/80 px-4 py-4">
                        {(() => {
                          const draft = linkedAccountDrafts[account.id] || {};
                          const canCreateLinkedAccount = Boolean(
                            canManageConnectedAccounts &&
                            !account.child_user_id &&
                            account.status !== 'connected' &&
                            account.status !== 'removal_pending',
                          );

                          return (
                            <>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">
                              {account.type === 'adult' ? 'Additional Adult' : 'Dependent'}
                            </p>
                            <p className="mt-1 text-sm font-semibold text-stone-900">{account.label}</p>
                            <p className="mt-1 text-sm text-stone-600">
                              {account.child_name || 'Pending child account'}
                              {account.child_email ? ` • ${account.child_email}` : ''}
                            </p>
                            {account.scheduled_removal_date ? (
                              <p className="mt-1 text-sm text-stone-600">
                                Access ends {formatMembershipDate(account.scheduled_removal_date, 'Not scheduled')}
                              </p>
                            ) : null}
                          </div>
                          <span
                            className={cn(
                              'inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em]',
                              account.status === 'connected'
                                ? 'bg-emerald-50 text-emerald-800'
                                : account.status === 'removal_pending'
                                  ? 'bg-red-50 text-red-700'
                                  : 'bg-amber-50 text-amber-800',
                            )}
                          >
                            {formatLinkedAccountStatus(account.status)}
                          </span>
                        </div>
                        <div className="mt-3 grid gap-3 text-sm text-stone-700 sm:grid-cols-2">
                          <div className="rounded-2xl bg-white px-4 py-3">
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Invite Code</p>
                            <p className="mt-1 font-mono text-sm text-stone-900">{account.invite_code || 'Pending'}</p>
                          </div>
                          <div className="rounded-2xl bg-white px-4 py-3">
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Recurring Charge</p>
                            <p className="mt-1 text-sm font-semibold text-stone-900">{formatConnectedAccountPrice(account.price)}</p>
                          </div>
                        </div>
                        {canCreateLinkedAccount ? (
                          <form
                            className="mt-4 border-t border-stone-200 pt-4"
                            onSubmit={(event) => {
                              event.preventDefault();
                              void handleCreateLinkedAccount(account);
                            }}
                          >
                            <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">
                              Create Account
                            </p>
                            <p className="mt-1 text-sm text-stone-600">
                              Create this family member's account and email them a password setup link.
                            </p>
                            <div className="mt-3 grid gap-3 lg:grid-cols-3">
                              <Input
                                type="text"
                                value={draft.first_name || ''}
                                placeholder="First name"
                                autoComplete="given-name"
                                onChange={(event) => updateLinkedAccountDraft(account.id, { first_name: event.target.value })}
                              />
                              <Input
                                type="text"
                                value={draft.last_name || ''}
                                placeholder="Last name"
                                autoComplete="family-name"
                                onChange={(event) => updateLinkedAccountDraft(account.id, { last_name: event.target.value })}
                              />
                              <Input
                                type="email"
                                value={draft.email || ''}
                                placeholder="Email address"
                                autoComplete="email"
                                onChange={(event) => updateLinkedAccountDraft(account.id, { email: event.target.value })}
                              />
                            </div>
                            <div className="mt-3 flex justify-end">
                              <Button
                                type="submit"
                                className="min-h-[2.75rem] px-5"
                                disabled={creatingSlotId === account.id}
                              >
                                {creatingSlotId === account.id ? 'Creating…' : 'Create Account & Send Link'}
                              </Button>
                            </div>
                          </form>
                        ) : null}
                        {canManageConnectedAccounts && account.child_user_id > 0 ? (
                          <div className="mt-4 flex justify-end">
							<Button
							  type="button"
							  className="min-h-[2.75rem] bg-[#b71c1c] px-5 text-white hover:bg-[#8f1515] disabled:bg-[#b71c1c] disabled:text-white"
                              disabled={account.status === 'removal_pending' || removingSlotId === account.id}
                              onClick={() => void handleScheduleRemoval(account.id)}
                            >
                              {account.status === 'removal_pending'
                                ? 'Removal scheduled'
                                : removingSlotId === account.id
                                  ? 'Scheduling…'
                                  : 'Remove At Renewal'}
                            </Button>
                          </div>
                        ) : null}
                            </>
                          );
                        })()}
                      </div>
                    ))}
                  </div>
                ) : null}
              </>
            ) : null}

			{SHOW_ADD_FAMILY_MEMBER_CONTROLS && addDependentUnavailableReason ? (
              <p className="mt-5 text-center text-sm font-medium text-[#8f1515]">
                {addDependentUnavailableReason}
              </p>
            ) : null}

			{(SHOW_ADD_FAMILY_MEMBER_CONTROLS && canAddDependent) || SHOW_FAMILY_REDEEM_INVITE_BUTTON ? (
			  <div className="mt-5 flex flex-col justify-center gap-3 sm:flex-row">
				{SHOW_ADD_FAMILY_MEMBER_CONTROLS && canAddDependent ? (
                <Button
                  type="button"
                  className="rounded-full"
                  style={{
                    backgroundColor: portalDesign.primaryActionBackground,
                    color: portalDesign.primaryActionText,
                  }}
                  onClick={handleAddDependent}
                >
                  Add Family Member
                </Button>
              ) : null}
				{SHOW_FAMILY_REDEEM_INVITE_BUTTON ? (
				<Button
				  asChild
				  type="button"
				  className="rounded-full"
				  variant={SHOW_ADD_FAMILY_MEMBER_CONTROLS && canAddDependent ? 'outline' : 'default'}
				  style={SHOW_ADD_FAMILY_MEMBER_CONTROLS && canAddDependent ? undefined : {
					backgroundColor: portalDesign.primaryActionBackground,
					color: portalDesign.primaryActionText,
				  }}
				>
				  <Link to="/linked-accounts">{portalContent.linked_accounts_redeem_button_label}</Link>
				</Button>
				) : null}
			  </div>
			) : null}
          </InfoCard>
        ) : null}

        {memberProfileBlocks.length && isCardVisible('custom_blocks') ? (
          <div className="grid gap-6 xl:grid-cols-2">
            {memberProfileBlocks.map((block, index) => {
              const Icon = CUSTOM_BLOCK_ICONS[block.icon] || Receipt;
              const entries = Array.isArray(block.entries) ? block.entries.filter((entry) => entry && (entry.label || entry.value || entry.description)) : [];
              const buttonUrl = String(block.button_url || '').trim();
              const buttonLabel = String(block.button_label || '').trim();
              return (
                <InfoCard
                  key={`${block.title || 'custom-block'}-${index}`}
                  icon={Icon}
                  title={block.title || 'Member Profile Block'}
                  description={block.description}
                >
                  {entries.length ? (
                    <div className="space-y-1">
                      {entries.map((entry, entryIndex) => (
                        <div key={`${entry.label || 'entry'}-${entryIndex}`} className="border-b border-stone-200/80 py-3 last:border-b-0 last:pb-0">
                          <div className="flex items-start justify-between gap-4">
                            <span className="text-sm font-medium uppercase tracking-[0.18em] text-stone-500">
                              {entry.label || 'Entry'}
                            </span>
                            <span className="text-right text-sm text-stone-900">
                              {entry.value || 'Not provided'}
                            </span>
                          </div>
                          {entry.description ? (
                            <p className="mt-2 text-sm leading-6 text-stone-600">{entry.description}</p>
                          ) : null}
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/80 px-5 py-6 text-sm text-stone-600">
                      No entries have been added to this block yet.
                    </div>
                  )}
                  {buttonUrl && buttonLabel ? (
                    <div className="mt-5 flex justify-center">
                      {buttonUrl.startsWith('/') ? (
                        <Button asChild type="button" className="rounded-full" style={{ backgroundColor: portalDesign.primaryActionBackground, color: portalDesign.primaryActionText }}>
                          <Link to={buttonUrl}>{buttonLabel}</Link>
                        </Button>
                      ) : (
                        <Button asChild type="button" className="rounded-full" style={{ backgroundColor: portalDesign.primaryActionBackground, color: portalDesign.primaryActionText }}>
                          <a href={buttonUrl} target="_blank" rel="noreferrer">{buttonLabel}</a>
                        </Button>
                      )}
                    </div>
                  ) : null}
                </InfoCard>
              );
            })}
          </div>
        ) : null}

      </motion.div>
      </div>
    </>
  );
};

export default MemberProfilePage;
