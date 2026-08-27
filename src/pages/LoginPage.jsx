import React, { useRef, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { useLocation } from 'react-router-dom';
import { LockKeyhole, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/useAuth';
import { JOIN_PAGE_URL, getPortalUiSettings } from '@/lib/portalSettings';
import { getPmproSocialLoginHtml, getPortalPageUrl, getWordPressLostPasswordUrl } from '@/lib/backendConfig';
import joinHeroStaticImage from '@/assets/join-hero-static-image.jpg';

const LOGIN_HERO_TITLE = 'United\nWe Climb.';

const getPortalRedirectTarget = (locationSearch) => {
  const searchCandidates = [locationSearch];

  if (typeof window !== 'undefined') {
    searchCandidates.push(window.location.search);
  }

  for (const search of searchCandidates) {
    if (!search) {
      continue;
    }

    const redirectTo = new URLSearchParams(search).get('redirect_to');
    if (!redirectTo || typeof window === 'undefined') {
      continue;
    }

    try {
      const targetUrl = new URL(redirectTo, window.location.origin);
      const appLoginUrl = new URL(window.location.href);
      const normalizedTarget = `${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`;
      const normalizedAppLogin = `${appLoginUrl.pathname}${appLoginUrl.search}${appLoginUrl.hash}`;

      if (targetUrl.origin !== window.location.origin || normalizedTarget === normalizedAppLogin) {
        return null;
      }

      return normalizedTarget;
    } catch {
      return null;
    }
  }

  return null;
};

const cancelNativeSubmit = (event) => {
  event?.preventDefault?.();
  event?.stopPropagation?.();
  event?.nativeEvent?.stopImmediatePropagation?.();
};

const getMemberProfileRedirectUrl = () => {
  if (typeof window === 'undefined') {
    return '/profile';
  }

  const portalPageUrl = getPortalPageUrl();
  if (portalPageUrl) {
    return `${portalPageUrl}/#/profile`;
  }

  return `${window.location.origin}/member-profile/#/profile`;
};

const LoginPage = () => {
  const location = useLocation();
  const { user, signIn, loading } = useAuth();
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;
  const loginBackgroundImageUrl = portalDesign.loginBackgroundImageUrl || joinHeroStaticImage;
  const loginOverlayOpacity = 1;
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [forgotMode, setForgotMode] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [authMessage, setAuthMessage] = useState('');
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);
  const [passwordModalMessage, setPasswordModalMessage] = useState('Incorrect email or password. Please try again.');
  const passwordInputRef = useRef(null);
  const submitLockRef = useRef(false);
  const redirectTarget = getPortalRedirectTarget(location.search);
  const purchaseSuccess = new URLSearchParams(location.search).get('purchase_success') === '1';
  const pmproSocialLoginHtml = getPmproSocialLoginHtml();

 const handleSubmit = async (event) => {
    cancelNativeSubmit(event);
    if (submitLockRef.current || submitting || loading) {
      return;
    }

    submitLockRef.current = true;
    setSubmitting(true);
    setAuthMessage('');
    try {
      if (forgotMode) {
        window.location.assign(getWordPressLostPasswordUrl());
        return;
      }

      const { user: signedInUser, error } = await signIn(email.trim(), password, { suppressToast: true });
      if (error) {
        const nextMessage = error.status === 401
          ? 'Incorrect email or password. Please try again.'
          : (error.message || 'We could not sign you in right now.');

        if (error.status === 401) {
          setPasswordModalMessage(nextMessage);
          setPasswordModalOpen(true);
        } else {
          setAuthMessage(nextMessage);
        }

        if (error.status === 401) {
          window.requestAnimationFrame(() => {
            passwordInputRef.current?.focus();
          });
        }
        return;
      }

      if (redirectTarget) {
        window.location.assign(redirectTarget);
        return;
      }

      if (signedInUser?.adminUrl) {
        window.location.assign(signedInUser.adminUrl);
        return;
      }

      window.location.assign(getMemberProfileRedirectUrl());
    } finally {
      submitLockRef.current = false;
      setSubmitting(false);
    }
  };

  const handleForgotModeToggle = () => {
    setForgotMode((value) => !value);
    setAuthMessage('');
  };

  const handleEmailChange = (event) => {
    setEmail(event.target.value);
    if (authMessage) {
      setAuthMessage('');
    }
  };

  const handlePasswordChange = (event) => {
    setPassword(event.target.value);
    if (authMessage) {
      setAuthMessage('');
    }
  };

  const handleFieldKeyDown = (event) => {
    if (event.key === 'Enter') {
      cancelNativeSubmit(event);
      void handleSubmit(event);
    }
  };

  const busy = loading || submitting;

  return (
    <>
      <Helmet>
        <title>Login - American Alpine Club</title>
        <meta name="description" content={portalContent.login_hero_description} />
      </Helmet>
      <div className="relative min-h-screen overflow-hidden bg-[#030000] text-white">
        {passwordModalOpen ? (
          <div className="fixed inset-0 z-[220] flex items-center justify-center bg-black/55 px-4 backdrop-blur-sm">
            <div className="w-full max-w-md border border-white/18 bg-black/68 p-6 text-white shadow-[0_32px_80px_rgba(0,0,0,0.52)] backdrop-blur-md">
              <h2 className="text-2xl font-semibold text-[#f8c235]">Incorrect Email or Password</h2>
              <p className="mt-3 text-base leading-7 text-white/78">
                {passwordModalMessage}
              </p>
              <div className="mt-6 flex justify-end">
                <Button
                  type="button"
                  className="h-11 rounded-none bg-[#8f1515] px-5 text-white hover:bg-[#6f1010]"
                  onClick={() => {
                    setPasswordModalOpen(false);
                    window.requestAnimationFrame(() => {
                      passwordInputRef.current?.focus();
                    });
                  }}
                >
                  Try Again
                </Button>
              </div>
            </div>
          </div>
        ) : null}
        <img
          src={loginBackgroundImageUrl}
          alt=""
          aria-hidden="true"
          className="absolute inset-0 h-full w-full object-cover"
        />
        <div className="absolute inset-0" style={{ background: portalDesign.loginOverlay, opacity: loginOverlayOpacity }} />
        <div className="relative mx-auto flex min-h-screen max-w-6xl items-center px-4 pb-10 pt-[calc(var(--aac-portal-header-height)+1.5rem)] sm:px-6 sm:pb-14 sm:pt-[calc(var(--aac-portal-header-height)+2rem)] lg:px-8">
          <div className="grid w-full gap-8 lg:grid-cols-[0.95fr,0.75fr] lg:items-center">
          <motion.div
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="relative pt-8 lg:pt-0"
          >
            <div className="relative flex h-full items-center">
              <div className="max-w-[42rem] px-1 py-1 sm:px-0 sm:py-0 lg:-translate-y-6">
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">{portalContent.login_hero_kicker}</p>
                <h1 className="mt-3 max-w-[38rem] whitespace-pre-line text-[4.4rem] leading-[0.92] text-white sm:text-[5.4rem] lg:text-[6.6rem] xl:text-[7.2rem]">
                  {LOGIN_HERO_TITLE}
                </h1>
                <p className="mt-5 max-w-[38rem] text-lg leading-8 text-white/88 sm:text-[1.32rem]">
                  {portalContent.login_hero_description}
                </p>
                <div className="mt-6">
                  <a
                    href={JOIN_PAGE_URL}
                    className="inline-flex h-12 items-center justify-center border border-[#8f1515] bg-[#8f1515] px-6 text-sm font-semibold uppercase tracking-[0.14em] text-white transition-colors hover:bg-[#6f1010] hover:border-[#6f1010]"
                  >
                    Join Now
                  </a>
                </div>
              </div>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.08 }}
            className="self-center border border-white/18 p-6 text-white shadow-[0_32px_80px_rgba(0,0,0,0.52)] backdrop-blur-md sm:p-8 lg:-translate-y-6"
            style={{ backgroundColor: '#000000' }}
            data-aac-login-surface="true"
          >
            <div className="mb-6 flex items-start justify-between gap-4">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-[#f8c235]">
                  {forgotMode ? 'Reset password' : portalContent.login_form_kicker}
                </p>
                <h2 className="mt-2 text-[1.85rem] leading-tight text-white sm:text-[2.05rem]">
                  {forgotMode ? 'Send a reset link.' : portalContent.login_form_title}
                </h2>
              </div>
              <div className="rounded-2xl border border-white/12 bg-white/6 p-3 text-[#f8c235]">
                {forgotMode ? <Mail className="h-6 w-6" /> : <LockKeyhole className="h-6 w-6" />}
              </div>
            </div>

            {authMessage ? (
              <div
                className="mb-5 rounded-2xl border border-[#fca5a5]/40 bg-[#8f1515] px-4 py-3 text-sm font-medium text-white shadow-[0_14px_32px_rgba(143,21,21,0.28)]"
                role="alert"
                aria-live="polite"
              >
                {authMessage}
              </div>
            ) : null}

            {purchaseSuccess ? (
              <div
                className="mb-5 rounded-2xl border border-[#f8c235]/40 bg-[#1f1a08] px-4 py-3 text-sm font-medium text-white shadow-[0_14px_32px_rgba(31,26,8,0.28)]"
                role="status"
                aria-live="polite"
              >
                {portalContent.login_purchase_success_message}
              </div>
            ) : null}

            <form
              className="space-y-5"
              data-aac-login-form="true"
              onSubmit={cancelNativeSubmit}
              noValidate
            >
              <div>
                <Label htmlFor="login-email" className="text-white">Email</Label>
                <Input
                  id="login-email"
                  type="email"
                  value={email}
                  onChange={handleEmailChange}
                  onKeyDown={handleFieldKeyDown}
                  required
                  className="mt-1 bg-white text-black"
                  autoComplete="email"
                />
              </div>

              {!forgotMode ? (
                <div>
                  <Label htmlFor="login-password" className="text-white">Password</Label>
                  <div className="relative mt-1">
                    <Input
                      id="login-password"
                      type={showPassword ? 'text' : 'password'}
                      ref={passwordInputRef}
                      value={password}
                      onChange={handlePasswordChange}
                      onKeyDown={handleFieldKeyDown}
                      required
                      className="bg-white pr-20 text-black"
                    />
                    <button
                      type="button"
                      data-aac-password-visibility="true"
                      onClick={() => setShowPassword((value) => !value)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 border-0 bg-transparent p-0 text-xs font-semibold uppercase tracking-[0.14em] text-black shadow-none outline-none hover:text-black focus-visible:ring-0"
                    >
                      {showPassword ? 'Hide' : 'Show'}
                    </button>
                  </div>
                </div>
              ) : null}
              <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button
                  type="button"
                  onClick={handleForgotModeToggle}
                  className="text-left text-sm font-medium text-[#f8c235] transition-colors hover:text-[#ffd86a]"
                >
                  {forgotMode ? 'Back to sign in' : portalContent.login_forgot_password_label}
                </button>
              </div>

              <Button
                type="button"
                onClick={handleSubmit}
                disabled={busy}
                className="mt-8 h-12 w-full rounded-none text-base font-semibold text-black"
                style={{
                  backgroundColor: portalDesign.secondaryActionBackground,
                  color: '#000000',
                }}
              >
                {busy ? 'Please wait…' : forgotMode ? 'Send reset link' : portalContent.login_submit_label}
              </Button>
            </form>

            {!forgotMode && pmproSocialLoginHtml ? (
              <div className="mt-5 border-t border-white/12 pt-5">
                <p className="mb-3 text-center text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-white/68">
                  Or continue with
                </p>
                <div
                  className="aac-login-social text-black [&_.pmpro_btn]:h-11 [&_.pmpro_btn]:rounded-none [&_.pmpro_btn]:border [&_.pmpro_btn]:border-[#0c0a09]/12 [&_.pmpro_btn]:bg-white [&_.pmpro_btn]:px-4 [&_.pmpro_btn]:text-sm [&_.pmpro_btn]:font-semibold [&_.pmpro_btn]:text-[#030000] [&_.pmpro_btn:hover]:bg-stone-100 [&_.pmpro_login_wrap]:m-0 [&_.pmpro_login_wrap]:p-0 [&_.pmpro_login_wrap>hr]:hidden [&_.pmpro_social_login]:m-0 [&_.pmpro_social_login]:p-0"
                  dangerouslySetInnerHTML={{ __html: pmproSocialLoginHtml }}
                />
              </div>
            ) : null}
          </motion.div>
          </div>
        </div>
      </div>
    </>
  );
};

export default LoginPage;
