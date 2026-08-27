
import React, { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import { KeyRound } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/use-toast';
import { useAuth } from '@/hooks/useAuth';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getMemberApiBase } from '@/lib/backendConfig';
import { getMembershipBenefits } from '@/lib/membershipBenefits';
import { normalizeAccountInfo, TSHIRT_SIZE_OPTIONS, formatTShirtSizeLabel } from '@/lib/memberProfile';
import { isPartnerOrAboveMembershipTierId } from '@/lib/membershipTiers';
import { getPortalUiSettings } from '@/lib/portalSettings';

const ProfileSection = ({ title, children, className = '', titleClassName = '' }) => (
  <section className={`bg-white py-6 ${className}`}>
    {title ? (
      <div className={`mb-5 border-b-2 border-[#b71c1c] pb-4 ${titleClassName}`}>
        <h3 className="text-xl font-bold text-black">{title}</h3>
      </div>
    ) : null}
    {children}
  </section>
);

const SERVICE_COMPONENT_OPTIONS = [
  'Active',
  'Reserve',
  'Veteran',
  'Retired',
];
const AAC_PROFILE_FIELD_CLASS = 'mt-1 flex h-10 w-full rounded-md border border-[#d9d9d9] bg-white px-3 py-2 text-sm text-black';

const normalizeUniversitySearch = (value) => String(value || '')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, ' ')
  .trim();

const formatUniversityOption = (school) => {
  const name = String(school?.name || '').trim();
  const city = String(school?.city || '').trim();
  const state = String(school?.state || '').trim();
  const parent = String(school?.parent || '').trim();
  const location = [city, state].filter(Boolean).join(', ');
  const campusLabel = parent && parent !== name ? `${name} (${parent})` : name;

  return [campusLabel, location].filter(Boolean).join(' - ');
};

const StudentUniversityField = ({ value, schoolId, onChange }) => {
  const [results, setResults] = useState([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const requestSequenceRef = useRef(0);
  const searchTimerRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => () => {
    window.clearTimeout(searchTimerRef.current);
  }, []);

  const searchUniversities = useCallback((query) => {
    const normalizedQuery = normalizeUniversitySearch(query);
    const requestId = ++requestSequenceRef.current;
    window.clearTimeout(searchTimerRef.current);

    if (normalizedQuery.length < 2) {
      setResults([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    searchTimerRef.current = window.setTimeout(async () => {
      try {
        const requestUrl = new URL(`${getMemberApiBase()}/universities`, window.location.origin);
        requestUrl.searchParams.set('q', query);
        requestUrl.searchParams.set('limit', '30');

        const response = await fetch(requestUrl.toString(), { credentials: 'same-origin' });
        const payload = response.ok ? await response.json() : null;
        const schools = Array.isArray(payload?.schools) ? payload.schools : [];
        if (requestId === requestSequenceRef.current) {
          setResults(schools);
        }
      } catch (_error) {
        if (requestId === requestSequenceRef.current) {
          setResults([]);
        }
      } finally {
        if (requestId === requestSequenceRef.current) {
          setLoading(false);
        }
      }
    }, 180);
  }, []);

  const selectSchool = useCallback((label, selectedSchoolId = '') => {
    onChange(label, selectedSchoolId);
    setOpen(false);
    setResults([]);
    inputRef.current?.focus();
  }, [onChange]);

  const renderedOptions = results
    .map((school) => ({
      id: String(school?.id || ''),
      label: formatUniversityOption(school),
    }))
    .filter((school) => school.label);
  const showDropdown = open && (normalizeUniversitySearch(value).length > 0 || renderedOptions.length > 0);

  return (
    <div className="relative">
      <Label htmlFor="student_university" className="text-black">School / University</Label>
      <Input
        ref={inputRef}
        id="student_university"
        name="university_or_school"
        autoComplete="off"
        placeholder="Start typing your university"
        value={value || ''}
        onChange={(e) => {
          onChange(e.target.value, '');
          setOpen(true);
          searchUniversities(e.target.value);
        }}
        onFocus={() => {
          setOpen(true);
          searchUniversities(value);
        }}
        onBlur={() => {
          window.setTimeout(() => setOpen(false), 140);
        }}
        onKeyDown={(e) => {
          if (e.key === 'Enter' && showDropdown) {
            e.preventDefault();
            const firstOption = renderedOptions[0];
            if (firstOption) {
              selectSchool(firstOption.label, firstOption.id);
            }
          }
        }}
        className="bg-white border-[#d9d9d9] text-black mt-1"
      />
      <input type="hidden" name="student_university_id" value={schoolId || ''} readOnly />
      {showDropdown ? (
        <div className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto border border-[#d9d9d9] bg-white text-sm text-black shadow-lg">
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-black hover:bg-[#f4f0ea] focus:bg-[#f4f0ea]"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => selectSchool('Other / not listed', '')}
          >
            Other / not listed
          </button>
          {renderedOptions.map((school) => (
            <button
              key={`${school.id}-${school.label}`}
              type="button"
              className="block w-full px-3 py-2 text-left text-black hover:bg-[#f4f0ea] focus:bg-[#f4f0ea]"
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => selectSchool(school.label, school.id)}
            >
              {school.label}
            </button>
          ))}
          {!loading && renderedOptions.length === 0 && normalizeUniversitySearch(value).length >= 2 ? (
            <div className="px-3 py-2 text-black/60">No matching schools. Choose Other / not listed if needed.</div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
};

const AccountTab = ({ profile }) => {
  const navigate = useNavigate();
  const { updateProfile } = useAuth();
  const [accountData, setAccountData] = useState(null);
  const accountDataRef = useRef(null);
  const [saving, setSaving] = useState(false);
  const [savingPreferences, setSavingPreferences] = useState(false);
  const { openMembershipAction, getMembershipActionUrl, hasManagedMembershipUrls } = useMembershipActions();
  const canManagePublicationPreferences = isPartnerOrAboveMembershipTierId(profile?.profile_info?.tier);

  useEffect(() => {
    if (profile && profile.account_info) {
      const normalizedProfileAccountInfo = normalizeAccountInfo(profile.account_info);
      accountDataRef.current = normalizedProfileAccountInfo;
      setAccountData(normalizedProfileAccountInfo);
    }
  }, [profile]);

  const patchAccountData = useCallback((patch) => {
    setAccountData((current) => {
      const currentValue = current || {};
      const nextValue = normalizeAccountInfo({
        ...currentValue,
        ...(typeof patch === 'function' ? patch(currentValue) : patch),
      });
      accountDataRef.current = nextValue;
      return nextValue;
    });
  }, []);

  const currentProfileAccountInfo = normalizeAccountInfo(profile?.account_info || {});
  const publicationFieldKeys = ['aaj_pref', 'anac_pref', 'acj_pref', 'guidebook_pref'];
  const emergencyRelationshipOptions = Array.isArray(accountData?.emergency_contact_relationship_options) && accountData.emergency_contact_relationship_options.length
    ? accountData.emergency_contact_relationship_options
    : [
        { value: 'Spouse / Partner', label: 'Spouse / Partner' },
        { value: 'Parent', label: 'Parent' },
        { value: 'Sibling', label: 'Sibling' },
        { value: 'Child', label: 'Child' },
        { value: 'Friend', label: 'Friend' },
        { value: 'Other', label: 'Other' },
      ];
  const publicationPreferencesDirty = canManagePublicationPreferences && publicationFieldKeys.some(
    (key) => (accountData?.[key] || '') !== (currentProfileAccountInfo?.[key] || '')
  );

  const handleSave = async () => {
    const nextAccountData = accountDataRef.current || accountData;
    const normalizedAccountData = normalizeAccountInfo(nextAccountData);
    const requiredFields = [
      ['first_name', 'First name'],
      ['last_name', 'Last name'],
      ['email', 'Email'],
      ['street', 'Street address'],
      ['city', 'City'],
      ['state', 'State'],
      ['zip', 'ZIP / Postal code'],
      ['country', 'Country'],
    ];
    const missingField = requiredFields.find(([key]) => !String(normalizedAccountData[key] || '').trim());
    if (missingField) {
      toast({
        variant: 'destructive',
        title: `${missingField[1]} is required`,
        description: 'Complete the required profile fields before saving changes.',
      });
      return;
    }

    setSaving(true);
    try {
      accountDataRef.current = normalizedAccountData;
      setAccountData(normalizedAccountData);
      await updateProfile({ account_info: normalizedAccountData });
      toast({
        title: "✅ Settings Saved!",
        description: "Your account settings have been updated.",
      });
    } finally {
      setSaving(false);
    }
  };

  const handlePublicationPreferencesSave = async () => {
    if (!accountData || !canManagePublicationPreferences) {
      return;
    }

    const publicationPreferencePayload = publicationFieldKeys.reduce((payload, key) => {
      payload[key] = accountData[key];
      return payload;
    }, {});

    const normalizedAccountData = normalizeAccountInfo({
      ...currentProfileAccountInfo,
      ...publicationPreferencePayload,
    });

      setSavingPreferences(true);
    try {
      const nextAccountData = normalizeAccountInfo({
        ...(accountDataRef.current || currentProfileAccountInfo),
        ...publicationPreferencePayload,
      });
      accountDataRef.current = nextAccountData;
      setAccountData(nextAccountData);
      await updateProfile({ account_info: normalizedAccountData });
      toast({
        title: 'Publication preferences saved',
        description: 'These updates were saved to your profile and queued for Salesforce sync.',
      });
    } finally {
      setSavingPreferences(false);
    }
  };

  const handleRenew = () => {
    void openMembershipAction('renew', { targetTier: profile?.profile_info?.tier || 'Partner' });
  };

  const handleCancel = async () => {
    if (hasManagedMembershipUrls) {
      if (getMembershipActionUrl('cancel')) {
        void openMembershipAction('cancel');
        return;
      }

      toast({
        variant: 'destructive',
        title: 'Cancellation unavailable',
        description: 'PMPro cancellation is not configured yet for this membership. Please open your membership account to continue.',
      });
      void openMembershipAction('manage');
      return;
    }

    if (getMembershipActionUrl('cancel')) {
      void openMembershipAction('cancel');
      return;
    }

    const nextAccountData = { ...accountData, auto_renew: false };
    accountDataRef.current = nextAccountData;
    setAccountData(nextAccountData);
    await updateProfile({
      account_info: nextAccountData,
      profile_info: {
        ...(profile?.profile_info || {}),
        tier: '',
        renewal_date: '',
        status: 'Inactive',
      },
      benefits_info: getMembershipBenefits('Supporter'),
    });
    toast({
      title: 'Membership canceled',
      description: 'Your membership is now inactive and Redpoint benefits have been removed.',
    });
  };

  const portalContent = getPortalUiSettings().content;

  const membershipExpirationDate = profile?.profile_info?.expiration_date || profile?.profile_info?.valid_through_date || '';
  const hasAutoRenewal = Boolean(currentProfileAccountInfo.auto_renew || profile?.membership_actions?.current_subscription_id);
  const canCancelMembership = hasAutoRenewal && Boolean(getMembershipActionUrl('cancel'));

  if (!accountData) return <div className="text-black text-center pt-10">Loading account details...</div>;

  return (
    <>
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
        >
          <div className="mx-auto max-w-7xl space-y-6 bg-white">
            {/* Personal Information */}
            <ProfileSection title="Personal Information">
              <div className="space-y-4">
                <div>
                  <Label htmlFor="first_name" className="text-black">First Name</Label>
                  <Input
                    id="first_name"
                    required
                    value={accountData.first_name || ''}
                    onChange={(e) => patchAccountData({ first_name: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="last_name" className="text-black">Last Name</Label>
                  <Input
                    id="last_name"
                    required
                    value={accountData.last_name || ''}
                    onChange={(e) => patchAccountData({ last_name: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="email" className="text-black">Email</Label>
                  <Input
                    id="email"
                    type="email"
                    required
                    value={accountData.email || ''}
                    onChange={(e) => patchAccountData({ email: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="phone" className="text-black">Phone</Label>
                  <Input
                    id="phone"
                    value={accountData.phone || ''}
                    onChange={(e) => patchAccountData({ phone: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="birthdate" className="text-black">Birthdate</Label>
                  <Input
                    id="birthdate"
                    type="date"
                    value={accountData.birthdate || ''}
                    onChange={(e) => patchAccountData({ birthdate: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="street" className="text-black">Street Address</Label>
                  <Input
                    id="street"
                    required
                    value={accountData.street || ''}
                    onChange={(e) => patchAccountData({ street: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="address2" className="text-black">Address Line 2</Label>
                  <Input
                    id="address2"
                    value={accountData.address2 || ''}
                    onChange={(e) => patchAccountData({ address2: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="city" className="text-black">City</Label>
                        <Input id="city" required value={accountData.city || ''} onChange={(e) => patchAccountData({ city: e.target.value })} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                    <div>
                        <Label htmlFor="state" className="text-black">State / Province</Label>
                        <Input id="state" required value={accountData.state || ''} onChange={(e) => patchAccountData({ state: e.target.value })} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="zip" className="text-black">ZIP / Postal Code</Label>
                        <Input id="zip" required value={accountData.zip || ''} onChange={(e) => patchAccountData({ zip: e.target.value })} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                    <div>
                        <Label htmlFor="country" className="text-black">Country</Label>
                        <Input id="country" required value={accountData.country || ''} onChange={(e) => patchAccountData({ country: e.target.value })} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                </div>

                <div>
                  <Label htmlFor="size" className="text-black">T-Shirt Size</Label>
                  <select
                    id="size"
                    value={accountData.size || 'No T-shirt'}
                    onChange={(e) => patchAccountData({ size: e.target.value })}
                    className={AAC_PROFILE_FIELD_CLASS}
                  >
                    {TSHIRT_SIZE_OPTIONS.map((size) => (
                      <option key={size} value={size}>
                        {formatTShirtSizeLabel(size)}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-4 border-t-2 border-[#b71c1c] pt-5">
                  <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                    <StudentUniversityField
                      value={accountData.student_university || ''}
                      schoolId={accountData.student_university_id || ''}
                      onChange={(student_university, student_university_id) => patchAccountData({
                        student_university,
                        student_university_id,
                      })}
                    />
                    <div>
                      <Label htmlFor="graduation_date" className="text-black">Graduation Date</Label>
                      <Input
                        id="graduation_date"
                        type="date"
                        value={accountData.graduation_date || ''}
                        onChange={(e) => patchAccountData({ graduation_date: e.target.value })}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                  </div>

                  <div>
                    <Label htmlFor="service_component" className="text-black">Service Component</Label>
                    <select
                      id="service_component"
                      value={accountData.service_component || ''}
                      onChange={(e) => patchAccountData({ service_component: e.target.value })}
                      className={AAC_PROFILE_FIELD_CLASS}
                    >
                      <option value="">Select service component</option>
                      {SERVICE_COMPONENT_OPTIONS.map((option) => (
                        <option key={option} value={option}>
                          {option}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="space-y-3 border-t-2 border-[#b71c1c] pt-5">
                  <div>
                    <h4 className="text-base font-semibold text-black">Emergency Contact</h4>
                    <p className="text-sm text-black/60">
                      This information comes from your PMPro Emergency Contact user fields and is saved with your member profile.
                    </p>
                  </div>

                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                      <Label htmlFor="emergency_contact_first_name" className="text-black">First Name</Label>
                      <Input
                        id="emergency_contact_first_name"
                        value={accountData.emergency_contact_first_name || ''}
                        onChange={(e) => patchAccountData({ emergency_contact_first_name: e.target.value })}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="emergency_contact_last_name" className="text-black">Last Name</Label>
                      <Input
                        id="emergency_contact_last_name"
                        value={accountData.emergency_contact_last_name || ''}
                        onChange={(e) => patchAccountData({ emergency_contact_last_name: e.target.value })}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                      <Label htmlFor="emergency_contact_phone" className="text-black">Phone Number</Label>
                      <Input
                        id="emergency_contact_phone"
                        value={accountData.emergency_contact_phone || ''}
                        onChange={(e) => patchAccountData({ emergency_contact_phone: e.target.value })}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="emergency_contact_email" className="text-black">Email</Label>
                      <Input
                        id="emergency_contact_email"
                        type="email"
                        value={accountData.emergency_contact_email || ''}
                        onChange={(e) => patchAccountData({ emergency_contact_email: e.target.value })}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                  </div>

                  <div>
                    <Label htmlFor="emergency_contact_relationship" className="text-black">Relationship</Label>
                    <select
                      id="emergency_contact_relationship"
                      value={accountData.emergency_contact_relationship || ''}
                      onChange={(e) => patchAccountData({ emergency_contact_relationship: e.target.value })}
                      className="mt-1 flex h-10 w-full rounded-md border border-[#d9d9d9] bg-white px-3 py-2 text-sm text-black"
                    >
                      <option value="">Select relationship</option>
                      {emergencyRelationshipOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <Button
                  onClick={handleSave}
                  disabled={saving}
                  className="h-12 w-full rounded-none bg-[#b71c1c] text-lg text-white hover:bg-[#8f1515]"
                >
                  {saving ? 'Saving...' : 'Save Changes'}
                </Button>
              </div>
            </ProfileSection>

            {canManagePublicationPreferences ? (
            <ProfileSection title="Preferences" className="py-3" titleClassName="!mb-2 !pb-2">
              <div className="space-y-1">
                <div className="flex items-center justify-between gap-4 border-t border-stone-200 py-2.5">
                  <div>
                    <p className="text-sm font-medium leading-tight text-black">American Alpine Journal</p>
                    <p className="mt-0.5 text-xs leading-snug text-black/60">Choose how you receive this annual publication</p>
                  </div>
                  <select
                    value={accountData.aaj_pref || 'Print'}
                    onChange={(e) => patchAccountData({ aaj_pref: e.target.value })}
                    className="h-9 rounded-md border border-[#d9d9d9] bg-white px-3 py-1 text-sm text-black"
                  >
                    <option value="Print">Print</option>
                    <option value="Digital">Digital</option>
                  </select>
                </div>

                <div className="flex items-center justify-between gap-4 border-t border-stone-200 py-2.5">
                  <div>
                    <p className="text-sm font-medium leading-tight text-black">Accidents in North American Climbing</p>
                    <p className="mt-0.5 text-xs leading-snug text-black/60">Choose how you receive this annual publication</p>
                  </div>
                  <select
                    value={accountData.anac_pref || 'Print'}
                    onChange={(e) => patchAccountData({ anac_pref: e.target.value })}
                    className="h-9 rounded-md border border-[#d9d9d9] bg-white px-3 py-1 text-sm text-black"
                  >
                    <option value="Print">Print</option>
                    <option value="Digital">Digital</option>
                  </select>
                </div>

                <div className="flex items-center justify-between gap-4 border-t border-stone-200 py-2.5">
                  <div>
                    <p className="text-sm font-medium leading-tight text-black">American Climbing Journal</p>
                    <p className="mt-0.5 text-xs leading-snug text-black/60">Choose how you receive this journal</p>
                  </div>
                  <select
                    value={accountData.acj_pref || 'Print'}
                    onChange={(e) => patchAccountData({ acj_pref: e.target.value })}
                    className="h-9 rounded-md border border-[#d9d9d9] bg-white px-3 py-1 text-sm text-black"
                  >
                    <option value="Print">Print</option>
                    <option value="Digital">Digital</option>
                  </select>
                </div>

                <div className="flex items-center justify-between gap-4 border-t border-stone-200 py-2.5">
                  <div>
                    <p className="text-sm font-medium leading-tight text-black">Guidebook to Membership</p>
                    <p className="mt-0.5 text-xs leading-snug text-black/60">Choose how you receive AAC guide content</p>
                  </div>
                  <select
                    value={accountData.guidebook_pref || 'Print'}
                    onChange={(e) => patchAccountData({ guidebook_pref: e.target.value })}
                    className="h-9 rounded-md border border-[#d9d9d9] bg-white px-3 py-1 text-sm text-black"
                  >
                    <option value="Print">Print</option>
                    <option value="Digital">Digital</option>
                  </select>
                </div>

                <div className="space-y-1.5 pt-1">
                  <Button
                    onClick={handlePublicationPreferencesSave}
                    disabled={savingPreferences || !publicationPreferencesDirty}
                    className="h-10 w-full rounded-none bg-[#b71c1c] text-base text-white hover:bg-[#8f1515]"
                  >
                    {savingPreferences ? 'Saving...' : 'Save Publication Preferences'}
                  </Button>
                  <p className="text-xs leading-snug text-black/60">
                    Saving here updates your member profile and triggers the outbound Salesforce field sync queue.
                  </p>
                </div>

              </div>
            </ProfileSection>
            ) : null}

            <ProfileSection title="Security" titleClassName="!mb-2 !pb-2">
              <div className="flex items-center justify-between gap-4 border-t border-stone-200 py-4">
                <div className="flex items-center gap-3">
                  <KeyRound className="w-6 h-6 text-[#B71C1C]" />
                  <div>
                    <p className="text-black font-medium">Password</p>
                    <p className="text-black/60 text-sm">Change your AAC portal password without leaving the app</p>
                  </div>
                </div>
                <Button
                  onClick={() => navigate('/change-password')}
                  variant="secondary"
                  className="text-black hover:bg-[#a07f21]"
                >
                  Change
                </Button>
              </div>
            </ProfileSection>

            {/* Action Buttons */}
            <div className="space-y-3">
              {!hasManagedMembershipUrls ? (
                <Button
                  onClick={handleRenew}
                  className="w-full bg-[#c8a43a] hover:bg-[#a07f21] text-black h-12 text-lg"
                >
                  Renew Membership
                </Button>
              ) : null}

              {canCancelMembership ? (
                <Button
                  onClick={handleCancel}
                  variant="outline"
                  className="w-full border-stone-400 text-black hover:bg-stone-100 h-12 text-lg"
                >
                  Turn Off Automatic Renewal
                </Button>
              ) : membershipExpirationDate ? (
                <div className="border-y-2 border-[#b71c1c] bg-white px-5 py-4 text-sm leading-6 text-black">
                  <p className="font-semibold">Automatic renewal is off.</p>
                  <p className="text-black/60">
                    Your membership is not cancelled today. It remains active through {membershipExpirationDate} and will end then unless you renew.
                  </p>
                </div>
              ) : null}
            </div>
          </div>
        </motion.div>
      </div>
    </>
  );
};

export default AccountTab;
