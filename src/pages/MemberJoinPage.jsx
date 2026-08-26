import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Helmet } from 'react-helmet';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { MembershipTierSelect } from '@/components/MembershipTierSelect';
import { getPmproLevelIdForTier, getTierById, normalizeTierId } from '@/lib/membershipTiers';
import { getAppRuntimeConfig } from '@/lib/backendConfig';
import { mainSiteHref } from '@/lib/mainWebsiteNav';
import { getPortalUiSettings } from '@/lib/portalSettings';

const CHECKOUT_EMBED_MESSAGE = 'aac-pmpro-checkout-height';
const CHECKOUT_SCROLL_MESSAGE = 'aac-pmpro-checkout-scroll';
const CHECKOUT_STEP_MESSAGE = 'aac-pmpro-checkout-step';
const CHECKOUT_MIN_EMBED_HEIGHT = 540;
const CHECKOUT_MAX_EMBED_HEIGHT = 5200;
const POST_PURCHASE_LOGIN_URL = mainSiteHref('/membership/#/login?purchase_success=1');
const MEMBERSHIP_GRID_PLANS = [
  { id: 'Supporter', price: '$45' },
  { id: 'Partner', price: '$65-100', eyebrow: 'Most Popular' },
  { id: 'Leader', price: '$250' },
  { id: 'Advocate', price: '$500' },
];
const MEMBERSHIP_GRID_ROWS = [
  { label: 'Support for AAC Advocacy, Education & Member Services', values: [{ check: true }, { check: true, suffix: '+' }, { check: true, suffix: '++' }, { check: true, suffix: '+++' }] },
  { label: 'AAC T-shirt', values: [{ check: true }, { check: true }, { check: true }, { check: true }] },
  { label: 'AAC Grant Access', values: [{ check: true }, { check: true }, { check: true }, { check: true }] },
  { label: 'Discounts: Gear, Gym, & Guide Services', values: [{ check: true }, { check: true }, { check: true }, { check: true }] },
  { label: 'AAC Library', values: [{ check: true }, { check: true }, { check: true }, { check: true }] },
  { label: 'Rescue Coverage', values: ['', '$7,500', '$300,000', '$300,000'] },
  { label: 'Medical Expense Coverage', values: ['', '$5,000', '$5,000', '$5,000'] },
  { label: 'AAC Publications (AAJ + Accidents + The Guidebook)', values: ['', { check: true }, { check: true }, { check: true }] },
  { label: 'AAC Lodging Facility Discounts', values: ['', { check: true }, { check: true }, { check: true }] },
  { label: 'Limited Edition Hardcover AAJ', values: ['', '', '', { check: true }] },
  { label: 'Summit Journal Annual Subscription', values: ['', '', '', { check: true }] },
];

const MembershipGridCheck = ({ suffix = '' }) => (
  <span className="inline-flex items-center justify-center gap-1" aria-label={suffix ? `Included ${suffix}` : 'Included'}>
    <svg aria-hidden="true" viewBox="0 0 24 24" className="h-6 w-6 fill-none stroke-current" strokeWidth="3.25" strokeLinecap="round" strokeLinejoin="round">
      <path d="m5 12.5 4.25 4.25L19 7" />
    </svg>
    {suffix ? <span className="text-lg font-bold">{suffix}</span> : null}
  </span>
);

const MembershipBenefitsGrid = ({ selectedTierId, onSelect }) => (
  <div data-aac-benefits-grid className="aac-signup-benefits-matrix mb-6">
    <div className="overflow-x-auto pb-2">
      <table className="w-full min-w-[920px] table-fixed border-collapse text-[#050505]">
        <caption className="sr-only">American Alpine Club annual membership benefits by plan</caption>
        <colgroup>
          <col className="w-[28%]" />
          {MEMBERSHIP_GRID_PLANS.map((plan) => <col key={plan.id} className="w-[18%]" />)}
        </colgroup>
        <thead>
          <tr>
            <th className="border border-black bg-black px-4 py-3 text-left text-white" scope="col">
              <span className="sr-only">Benefit</span>
            </th>
            {MEMBERSHIP_GRID_PLANS.map((plan) => {
              const isPartner = plan.id === 'Partner';
              const isSelected = selectedTierId === plan.id;
              return (
                <th key={plan.id} scope="col" className={`border border-black p-0 ${isPartner ? 'bg-[#ffc72c] text-black' : 'bg-black text-white'}`}>
                  <button
                    type="button"
                    onClick={() => onSelect(plan.id)}
                    aria-pressed={isSelected}
                    className={`flex min-h-[68px] w-full flex-col items-center justify-center px-3 py-2 text-center transition-shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-[#b71c1c] ${isSelected ? 'shadow-[inset_0_-4px_0_#b71c1c]' : ''}`}
                  >
                    {plan.eyebrow ? <span className="mb-1 text-[11px] font-extrabold uppercase tracking-[0.08em]">{plan.eyebrow}</span> : null}
                    <span className="text-xl font-extrabold sm:text-2xl">{plan.id}</span>
                  </button>
                </th>
              );
            })}
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row" className="border border-[#cecece] bg-white px-4 py-2.5 text-left text-base font-extrabold">Benefits</th>
            {MEMBERSHIP_GRID_PLANS.map((plan) => (
              <td key={plan.id} className={`border border-[#cecece] px-3 py-2.5 text-center text-xl font-medium ${plan.id === 'Partner' ? 'bg-[#ffc72c]' : 'bg-white'}`}>{plan.price}</td>
            ))}
          </tr>
          {MEMBERSHIP_GRID_ROWS.map((row) => (
            <tr key={row.label}>
              <th scope="row" className="border border-[#cecece] bg-white px-4 py-2.5 text-left text-[15px] font-medium leading-5">{row.label}</th>
              {row.values.map((value, index) => (
                <td key={MEMBERSHIP_GRID_PLANS[index].id} className={`border border-[#cecece] px-3 py-2.5 text-center text-base font-medium ${MEMBERSHIP_GRID_PLANS[index].id === 'Partner' ? 'bg-[#ffc72c]' : 'bg-white'}`}>
                  {typeof value === 'object' && value.check ? <MembershipGridCheck suffix={value.suffix} /> : value}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
    <p className="mt-2 text-xs leading-5 text-[#6e675d]">*Student, military, and family membership discounts are available for the Partner membership level.</p>
  </div>
);
const buildEmbeddedCheckoutUrl = (tierId, wizardStep = 'account') => {
  const normalizedTier = normalizeTierId(tierId);
  const runtimeConfig = getAppRuntimeConfig();
  const configuredLevelIds = runtimeConfig.pmproLevelIds || {};
  const levelId = Number(configuredLevelIds[normalizedTier] || getPmproLevelIdForTier(normalizedTier));
  const checkoutBase = runtimeConfig.pmproCheckoutUrl || mainSiteHref('/membership-checkout/');
  const checkoutUrl = new URL(checkoutBase, window.location.origin);

  checkoutUrl.searchParams.set('level', String(levelId));
  checkoutUrl.searchParams.set('aac_embed', '1');
  checkoutUrl.searchParams.set('aac_signup', '1');
  checkoutUrl.searchParams.set('aac_wizard_step', wizardStep);
  checkoutUrl.searchParams.set('aac_rev', 'full-width-phone-shirt-490');

  return checkoutUrl.toString();
};

const MemberJoinPage = () => {
  const [selectedTierId, setSelectedTierId] = useState('Partner');
  const [embedHeight, setEmbedHeight] = useState(CHECKOUT_MIN_EMBED_HEIGHT);
  const checkoutFrameRef = useRef(null);
  const checkoutDraftRef = useRef([]);
  const currentWizardStepRef = useRef('account');
  const heightFrameRef = useRef(0);
  const pendingHeightRef = useRef(CHECKOUT_MIN_EMBED_HEIGHT);
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const redeemInviteButtonLabel =
    portalContent.join_redeem_code_button_label === 'Redeem Membership Code'
      ? 'Redeem Family Member Invite'
      : portalContent.join_redeem_code_button_label;
  const signupSurfaceColor = '#ffffff';
  const signupFormStyle = {
    paddingTop: 0,
  };

  const selectedTier = useMemo(() => getTierById(selectedTierId), [selectedTierId]);
  const checkoutUrl = useMemo(
    () => buildEmbeddedCheckoutUrl(selectedTierId, currentWizardStepRef.current),
    [selectedTierId],
  );

  useEffect(() => {
    let lastScrollY = window.scrollY;
    const syncHeaderState = () => {
      const nextScrollY = Math.max(0, window.scrollY);
      const delta = nextScrollY - lastScrollY;
      document.body.classList.toggle('aac-signup-header-scrolled', nextScrollY > 24);

      if (nextScrollY <= 24 || delta < -4) {
        document.body.classList.remove('aac-signup-header-hidden');
      } else if (delta > 4) {
        document.body.classList.add('aac-signup-header-hidden');
      }

      lastScrollY = nextScrollY;
    };

    document.body.classList.add('aac-signup-header-managed');
    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });
    return () => {
      window.removeEventListener('scroll', syncHeaderState);
      document.body.classList.remove('aac-signup-header-managed', 'aac-signup-header-scrolled', 'aac-signup-header-hidden');
    };
  }, []);

  useEffect(() => {
    const handleMessage = (event) => {
      if (event.origin !== window.location.origin) {
        return;
      }

      if (event.data?.type === CHECKOUT_EMBED_MESSAGE) {
        const nextHeight = Number(event.data.height);
        if (Number.isFinite(nextHeight) && nextHeight > 0) {
          pendingHeightRef.current = Math.min(Math.max(nextHeight, CHECKOUT_MIN_EMBED_HEIGHT), CHECKOUT_MAX_EMBED_HEIGHT);
          if (!heightFrameRef.current) {
            heightFrameRef.current = window.requestAnimationFrame(() => {
              heightFrameRef.current = 0;
              setEmbedHeight((currentHeight) => {
                const delta = pendingHeightRef.current - currentHeight;
                const shouldResize = delta >= 24 || delta <= -120;
                return shouldResize ? pendingHeightRef.current : currentHeight;
              });
            });
          }
        }
      }

      if (event.data?.type === CHECKOUT_STEP_MESSAGE) {
        const label = String(event.data.stepLabel || '').toLowerCase();
        currentWizardStepRef.current = label.includes('payment')
          ? 'payment'
          : label.includes('publication')
            ? 'publications'
            : label.includes('detail') || label.includes('contact') || label.includes('member')
              ? 'details'
              : 'account';
        }

      if (event.data?.type === CHECKOUT_SCROLL_MESSAGE) {
        const deltaY = Number(event.data.deltaY);
        if (Number.isFinite(deltaY) && deltaY !== 0) {
          const documentScroller = document.scrollingElement;
          const portalRoot = document.getElementById('aac-member-portal-root') || document.getElementById('root');
          const publicShellScroller = document.querySelector('.topo-lines > main');
          const activeScroller = [portalRoot, publicShellScroller, documentScroller].find(
            (candidate) => candidate && candidate.scrollHeight > candidate.clientHeight + 1
          );

          if (activeScroller && activeScroller !== documentScroller) {
            activeScroller.scrollBy({ top: deltaY, behavior: 'auto' });
          } else {
            window.scrollBy({ top: deltaY, behavior: 'auto' });
          }
        }
      }
    };

    window.addEventListener('message', handleMessage);
    return () => {
      window.removeEventListener('message', handleMessage);
      if (heightFrameRef.current) {
        window.cancelAnimationFrame(heightFrameRef.current);
        heightFrameRef.current = 0;
      }
    };
  }, []);

  const preserveCheckoutDraft = () => {
    try {
      const frameDocument = checkoutFrameRef.current?.contentDocument;
      const activeStepLabel = String(
        frameDocument?.querySelector('.aac-checkout-wizard__step[aria-current="step"] .aac-checkout-wizard__step-label')?.textContent || '',
      ).toLowerCase();
      if (activeStepLabel) {
        currentWizardStepRef.current = activeStepLabel.includes('payment')
          ? 'payment'
          : activeStepLabel.includes('publication')
            ? 'publications'
            : activeStepLabel.includes('detail') || activeStepLabel.includes('contact') || activeStepLabel.includes('member')
              ? 'details'
              : 'account';
      }

      const controls = Array.from(frameDocument?.querySelectorAll('form input, form select, form textarea') || []);
      checkoutDraftRef.current = controls
        .filter((control) => control.name && !['file', 'submit', 'button', 'hidden'].includes(control.type) && control.name !== 'level')
        .map((control) => ({
          name: control.name,
          type: control.type,
          value: control.value,
          checked: Boolean(control.checked),
          selectedValues: control.multiple ? Array.from(control.selectedOptions).map((option) => option.value) : null,
        }));
    } catch (error) {
      // Changing levels still works if this browser blocks frame access.
    }
  };

  const restoreCheckoutDraft = () => {
    if (!checkoutDraftRef.current.length) {
      return;
    }
    try {
      const frameDocument = checkoutFrameRef.current?.contentDocument;
      checkoutDraftRef.current.forEach((entry) => {
        const selectorName = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(entry.name) : entry.name.replace(/["\\]/g, '\\$&');
        const matchingControls = Array.from(frameDocument?.querySelectorAll(`[name="${selectorName}"]`) || []);
        const controls = entry.type === 'checkbox' || entry.type === 'radio'
          ? matchingControls.filter((control) => control.value === entry.value)
          : matchingControls;
        controls.forEach((control) => {
          if (entry.type === 'checkbox' || entry.type === 'radio') {
            control.checked = entry.checked && control.value === entry.value;
          } else if (control.multiple && entry.selectedValues) {
            Array.from(control.options).forEach((option) => {
              option.selected = entry.selectedValues.includes(option.value);
            });
          } else {
            control.value = entry.value;
          }
          control.dispatchEvent(new Event('input', { bubbles: true }));
          control.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
    } catch (error) {
      // Leave the newly loaded checkout usable if restoration is unavailable.
    }
  };

  const handleTierSelect = (tierId) => {
    if (tierId === selectedTierId) {
      return;
    }
    preserveCheckoutDraft();
    setSelectedTierId(tierId);
  };

  const handleCheckoutFrameLoad = () => {
    const frameWindow = checkoutFrameRef.current?.contentWindow;
    if (!frameWindow) {
      return;
    }

    try {
      window.requestAnimationFrame(() => {
        restoreCheckoutDraft();
        window.setTimeout(restoreCheckoutDraft, 180);
      });
      const frameUrl = new URL(frameWindow.location.href);
      const postPurchaseUrl = new URL(POST_PURCHASE_LOGIN_URL);
      const runtimeConfirmationUrl = getAppRuntimeConfig().pmproConfirmationUrl;
      const confirmationPath = runtimeConfirmationUrl ? new URL(runtimeConfirmationUrl, window.location.origin).pathname : '/membership-checkout/membership-confirmation';
      const isConfirmationPath = frameUrl.pathname === confirmationPath || frameUrl.pathname.includes('/membership-checkout/membership-confirmation');
      const isFramedProfile =
        frameUrl.pathname === postPurchaseUrl.pathname &&
        (
          frameUrl.hash === '#/login' ||
          frameUrl.hash.startsWith('#/login?') ||
          frameUrl.hash === '#/profile' ||
          frameUrl.hash.startsWith('#/profile?')
        );

	      if (isConfirmationPath || isFramedProfile) {
	        window.location.assign(postPurchaseUrl.toString());
	      }

    } catch (error) {
      // Ignore cross-document timing errors and leave the iframe in place.
    }
  };

  return (
    <>
      <Helmet>
        <title>Join - American Alpine Club</title>
        <meta
          name="description"
          content={portalContent.join_hero_description}
        />
      </Helmet>
      <div
        className="bg-white font-sans text-[#16130f]"
        style={{ backgroundColor: signupSurfaceColor, overflowAnchor: 'none' }}
      >
        <div className="aac-join-layout grid w-full max-w-none gap-0 bg-white">
          <main className="aac-join-main min-w-0 bg-white px-4 py-0 sm:px-7 lg:px-9 xl:px-12 2xl:px-14">
            <div id="membership-form" className="mx-auto w-full max-w-[1440px] text-[#16130f]" style={signupFormStyle}>
              <section
                className="grid content-start bg-white p-0 pb-6 sm:pb-8"
              >
                <div className="aac-signup-form-intro mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                  <div>
                    <h2 className="text-3xl font-extrabold leading-tight tracking-tight text-[#16130f] sm:text-4xl">Select your plan</h2>
                    <p className="mt-3 text-base leading-7 text-[#6e675d] sm:text-lg">Choose the annual membership that fits your climbing life.</p>
                  </div>
                </div>
	                <MembershipTierSelect
	                  variant="full"
	                  selectedId={selectedTierId}
	                  onSelect={handleTierSelect}
	                />
	                <div className="mt-6 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                  <Button
                    asChild
                    type="button"
                    variant="outline"
                    className="min-h-[1.75rem] rounded-[6px] border-[#e4dfd6] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.06em] text-[#6e675d] hover:border-[#d7cfbf] hover:text-[#16130f]"
                  >
                    <Link to="/linked-accounts">{redeemInviteButtonLabel}</Link>
                  </Button>
                </div>
              </section>

              <div
                  className="aac-checkout-iframe-wrap block"
                  style={{
                    position: 'static',
                    height: 'auto',
                    overflow: 'visible',
                    overscrollBehavior: 'auto',
                    '--aac-checkout-iframe-height': `${embedHeight}px`,
                  }}
                >
                  <iframe
                    ref={checkoutFrameRef}
                    title={`${selectedTier.label} membership checkout`}
                    src={checkoutUrl}
                    onLoad={handleCheckoutFrameLoad}
                    scrolling="no"
                    className="block w-full bg-transparent"
	                    style={{
	                      position: 'static',
	                      inset: 'auto',
	                      display: 'block',
	                      width: '100%',
	                      maxWidth: '100%',
	                      height: 'var(--aac-checkout-iframe-height)',
	                      minHeight: `${CHECKOUT_MIN_EMBED_HEIGHT}px`,
	                      overflow: 'hidden',
	                      border: 0,
	                    }}
                  />
                </div>
            </div>
          </main>
        </div>
      </div>
    </>
  );
};

export default MemberJoinPage;
