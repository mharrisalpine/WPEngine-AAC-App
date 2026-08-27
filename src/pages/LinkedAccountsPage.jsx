import React, { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { Link, useLocation } from 'react-router-dom';
import { CheckCircle2, Link2, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/useAuth';
import { getPortalPageUrl } from '@/lib/backendConfig';
import { validateInviteCode, redeemInviteCode } from '@/lib/memberApi';
import { getPortalUiSettings } from '@/lib/portalSettings';

const getInviteCodeFromSearch = (search) => new URLSearchParams(search || '').get('code') || '';

const redirectToProfile = () => {
  const portalPageUrl = getPortalPageUrl();
  window.location.assign(`${portalPageUrl}/#/profile?linked=1`);
};

const LinkedAccountsPage = () => {
  const location = useLocation();
  const { user, profile } = useAuth();
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const [inviteCode, setInviteCode] = useState(() => getInviteCodeFromSearch(location.search));
  const [inviteData, setInviteData] = useState(null);
  const [loadingInvite, setLoadingInvite] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [firstName, setFirstName] = useState(profile?.account_info?.first_name || '');
  const [lastName, setLastName] = useState(profile?.account_info?.last_name || '');
  const [email, setEmail] = useState(profile?.account_info?.email || user?.email || '');
  const [password, setPassword] = useState('');

  const normalizedCode = useMemo(() => String(inviteCode || '').trim().toUpperCase(), [inviteCode]);
  const currentMemberName = [profile?.account_info?.first_name, profile?.account_info?.last_name].filter(Boolean).join(' ').trim();

  const lookupInvite = async (codeToLookup) => {
    const code = String(codeToLookup || '').trim();
    if (!code) {
      setInviteData(null);
      setErrorMessage('Enter an invite code to continue.');
      return false;
    }

    setLoadingInvite(true);
    setErrorMessage('');
    setSuccessMessage('');

    try {
      const response = await validateInviteCode(code);
      setInviteData(response.invite || null);
      return true;
    } catch (error) {
      setInviteData(null);
      setErrorMessage(error.message || 'Invite code not found.');
      return false;
    } finally {
      setLoadingInvite(false);
    }
  };

  useEffect(() => {
    const queryCode = getInviteCodeFromSearch(location.search);
    if (queryCode) {
      setInviteCode(queryCode);
      void lookupInvite(queryCode);
    }
  }, [location.search]);

  const handleLookupSubmit = async (event) => {
    event.preventDefault();
    await lookupInvite(normalizedCode);
  };

  const handleRedeem = async (event) => {
    event.preventDefault();

    const hasInvite = inviteData || (await lookupInvite(normalizedCode));
    if (!hasInvite) {
      return;
    }

    setSubmitting(true);
    setErrorMessage('');
    setSuccessMessage('');

    try {
      await redeemInviteCode({
        invite_code: normalizedCode,
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim(),
        password,
      });

      setSuccessMessage(portalContent.linked_accounts_success_message);
      window.setTimeout(redirectToProfile, 300);
    } catch (error) {
      setErrorMessage(error.message || 'We could not redeem that invite code right now.');
    } finally {
      setSubmitting(false);
    }
  };

  const busy = loadingInvite || submitting;

  return (
    <>
      <Helmet>
        <title>{portalContent.linked_accounts_page_title} - American Alpine Club</title>
        <meta name="description" content="Redeem a family invite code and connect a linked AAC household account." />
      </Helmet>
      <div className="bg-white py-6">
        <motion.div
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
          className="mx-auto max-w-4xl space-y-6 bg-white"
        >
          <section className="bg-white py-6">
            <div className="flex items-start gap-3 border-b-2 border-[#b71c1c] pb-4">
              <div className="pt-1 text-[#b71c1c]">
                <Users className="h-5 w-5" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-stone-900">{portalContent.linked_accounts_page_title}</h1>
                <p className="mt-1 max-w-2xl text-sm leading-6 text-stone-600">
                  {portalContent.linked_accounts_page_description}
                </p>
              </div>
            </div>

            <form onSubmit={handleLookupSubmit} className="mt-6 border-b-2 border-[#b71c1c] pb-6">
              <div className="grid gap-4 md:grid-cols-[minmax(0,1fr),auto] md:items-end">
                <div>
                  <Label htmlFor="linked-account-invite-code" className="text-stone-900">Invite Code</Label>
                  <Input
                    id="linked-account-invite-code"
                    value={inviteCode}
                    onChange={(event) => {
                      setInviteCode(event.target.value.toUpperCase());
                      if (errorMessage) {
                        setErrorMessage('');
                      }
                    }}
                    placeholder="AACF-XXXXXXX"
                    className="mt-1 bg-white text-black"
                    autoCapitalize="characters"
                    autoComplete="off"
                  />
                </div>
                <Button
                  type="submit"
                  disabled={busy}
                  className="h-11 rounded-none border border-[#f8c235] bg-[#f8c235] font-semibold text-black hover:bg-[#dda914]"
                >
                  {loadingInvite ? 'Checking…' : portalContent.linked_accounts_lookup_button_label}
                </Button>
              </div>
            </form>

            {errorMessage ? (
              <div className="mt-5 border-l-4 border-[#8f1515] bg-red-50 px-4 py-3 text-sm font-medium text-[#8f1515]" role="alert">
                {errorMessage}
              </div>
            ) : null}

            {successMessage ? (
              <div className="mt-5 flex items-start gap-3 border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{successMessage}</span>
              </div>
            ) : null}

            {inviteData ? (
              <div className="mt-6 grid gap-6 xl:grid-cols-[0.88fr,1.12fr]">
                <section className="bg-white py-5">
                  <div className="border-b-2 border-[#b71c1c] pb-4">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-[#b71c1c]">Invite Details</p>
                  </div>
                  <div className="mt-4 space-y-3 text-sm text-stone-700">
                    <div className="border-t border-stone-200 pt-3">
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Connected To</p>
                      <p className="mt-1 font-semibold text-stone-900">{inviteData.parent_name}</p>
                    </div>
                    <div className="border-t border-stone-200 pt-3">
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Linked Role</p>
                      <p className="mt-1 text-stone-900">{inviteData.label}</p>
                    </div>
                    <div className="border-t border-stone-200 pt-3">
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Status</p>
                      <p className="mt-1 text-stone-900">{inviteData.status}</p>
                    </div>
                    <div className="border-t border-stone-200 pt-3">
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Invite Code</p>
                      <p className="mt-1 font-mono text-stone-900">{inviteData.code}</p>
                    </div>
                  </div>
                </section>

                <form onSubmit={handleRedeem} className="bg-white py-5">
                  <div className="flex items-start gap-3 border-b-2 border-[#b71c1c] pb-4">
                    <div className="pt-1 text-[#8f1515]">
                      <Link2 className="h-5 w-5" />
                    </div>
                    <div>
                      <h2 className="text-xl font-bold text-stone-900">
                        {user ? 'Claim With Current Account' : 'Create or Claim Account'}
                      </h2>
                      <p className="mt-1 text-sm leading-6 text-stone-600">
                        {user
                          ? `You are currently signed in as ${currentMemberName || email || 'this member account'}. Redeeming this code will link that account to the household membership.`
                          : 'Use your household member email and password below. If this email is new, a child account will be created. If it already exists, we’ll link the existing account after password verification.'}
                      </p>
                    </div>
                  </div>

                  {user ? (
                    <div className="mt-5 border-t border-stone-200 py-4 text-sm text-stone-700">
                      <p className="font-semibold text-stone-900">{currentMemberName || 'Current member account'}</p>
                      <p className="mt-1">{email || user.email}</p>
                    </div>
                  ) : (
                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                      <div>
                        <Label htmlFor="linked-account-first-name" className="text-stone-900">First Name</Label>
                        <Input
                          id="linked-account-first-name"
                          value={firstName}
                          onChange={(event) => setFirstName(event.target.value)}
                          className="mt-1 bg-white text-black"
                          autoComplete="given-name"
                        />
                      </div>
                      <div>
                        <Label htmlFor="linked-account-last-name" className="text-stone-900">Last Name</Label>
                        <Input
                          id="linked-account-last-name"
                          value={lastName}
                          onChange={(event) => setLastName(event.target.value)}
                          className="mt-1 bg-white text-black"
                          autoComplete="family-name"
                        />
                      </div>
                      <div>
                        <Label htmlFor="linked-account-email" className="text-stone-900">Email</Label>
                        <Input
                          id="linked-account-email"
                          type="email"
                          value={email}
                          onChange={(event) => setEmail(event.target.value)}
                          className="mt-1 bg-white text-black"
                          autoComplete="email"
                          required
                        />
                      </div>
                      <div>
                        <Label htmlFor="linked-account-password" className="text-stone-900">Password</Label>
                        <Input
                          id="linked-account-password"
                          type="password"
                          value={password}
                          onChange={(event) => setPassword(event.target.value)}
                          className="mt-1 bg-white text-black"
                          autoComplete="current-password"
                          required
                        />
                      </div>
                    </div>
                  )}

                  <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-stone-600">
                    Need a new AAC account first? You can also <Link to="/join" className="font-medium text-[#8f1515] hover:text-[#6f1010]">join here</Link>.
                    </p>
                    <Button type="submit" disabled={busy} className="rounded-none bg-[#f8c235] text-black hover:bg-[#dda914]">
                      {submitting ? 'Linking Account…' : portalContent.linked_accounts_redeem_button_label}
                    </Button>
                  </div>
                </form>
              </div>
            ) : null}
          </section>
        </motion.div>
      </div>
    </>
  );
};

export default LinkedAccountsPage;
