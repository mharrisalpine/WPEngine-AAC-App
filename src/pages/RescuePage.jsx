import React from 'react';
import { Helmet } from 'react-helmet';
import { ArrowLeft, CheckCircle2, ExternalLink, FileText, HeartPulse, PhoneCall, Shield } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { openExternalUrl } from '@/lib/mobileNavigation';
import { isMembershipActive } from '@/lib/membershipStatus';
import { getAppRuntimeConfig } from '@/lib/backendConfig';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { cn } from '@/lib/utils';

const REDPOINT_EMERGENCY_PHONE_DISPLAY = '+01-628-251-1510';
const REDPOINT_EMERGENCY_PHONE_LINK = '+16282511510';
const REDPOINT_EVACUATION_CLAIM_URL = 'https://aac-profile.s3.amazonaws.com/website_assets/MedicalEvacuationClaim_AAC_Redpoint.pdf';
const REDPOINT_MEDICAL_EXPENSE_CLAIM_URL = 'https://aac-profile.s3.amazonaws.com/website_assets/MedicalExpenseClaim_AAC_Redpoint.pdf';
const AAC_RESCUE_INFO_URL = 'https://americanalpineclub.org/rescue';
const RIPCORD_TRAVEL_INSURANCE_URL = 'https://redpointtravelprotection.com/partner/aac';

const formatCurrency = (amount) => {
  const numericAmount = Number(amount || 0);

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(numericAmount);
};

const RescueMetric = ({ label, value }) => (
  <div className="border-t border-white/16 pt-4">
    <p className="font-mono text-[0.66rem] uppercase tracking-[0.24em] text-white/48">{label}</p>
    <p className="mt-3 font-mono text-2xl font-bold tracking-[0.02em] text-[#f7f1e8]">{value}</p>
  </div>
);

const RescueActionButton = ({ href, icon: Icon, children, variant = 'primary' }) => {
  const isPrimary = variant === 'primary';

  return (
    <button
      type="button"
      className={`inline-flex min-h-[4.25rem] w-full items-center justify-center gap-3 px-5 text-center text-[0.78rem] font-bold uppercase tracking-[0.14em] transition sm:min-h-[4.5rem] ${
        isPrimary
          ? 'bg-[#b71c1c] text-white hover:bg-[#8f1515]'
          : 'border border-[#b71c1c] bg-white text-[#8f1515] hover:bg-[#fff5f5]'
      }`}
      onClick={() => void openExternalUrl(href)}
    >
      <Icon className="h-5 w-5 shrink-0" />
      <span>{children}</span>
      <ExternalLink className="h-4 w-4 shrink-0" />
    </button>
  );
};

const RescuePage = () => {
  const { profile, loading } = useAuth();
  const portalDesign = getPortalUiSettings().design || {};
  const runtimeConfig = getAppRuntimeConfig();
  const assetBaseUrl = String(runtimeConfig.assetBaseUrl || '').replace(/\/$/, '');
  const appBaseUrl = assetBaseUrl.replace(/\/assets$/, '');
  const topoBackgroundUrl = portalDesign.sidebarBackgroundUrl && !portalDesign.sidebarBackgroundUrl.startsWith('/')
    ? portalDesign.sidebarBackgroundUrl
    : `${appBaseUrl || ''}/sidebar-topo-v2.svg`;

  if (loading || !profile) {
    return <div className="pt-10 text-center text-stone-800">Loading rescue benefits...</div>;
  }

  const profileInfo = profile.profile_info || {};
  const benefitsInfo = profile.benefits_info || {};
  const membershipActive = isMembershipActive(profileInfo);
  const hasRescueCoverage = Boolean(
    Number(benefitsInfo.rescue_amount || 0) > 0 ||
    Number(benefitsInfo.medical_amount || 0) > 0 ||
    Number(benefitsInfo.mortal_remains_amount || 0) > 0
  );
  const coverageStatus = membershipActive && hasRescueCoverage ? 'Active' : 'Not active';
  const rescueCardBackgroundStyle = {
    backgroundImage: `linear-gradient(180deg, rgba(3, 0, 0, 0.72), rgba(3, 0, 0, 0.62)), radial-gradient(circle at 82% 20%, rgba(183, 28, 28, 0.2), transparent 30%), url("${topoBackgroundUrl}")`,
    backgroundPosition: 'center center, center top',
    backgroundRepeat: 'no-repeat, no-repeat, repeat',
    backgroundSize: 'cover, cover, 760px auto',
  };

  return (
    <>
      <Helmet>
        <title>Rescue Benefits - American Alpine Club</title>
        <meta
          name="description"
          content="Review AAC Redpoint rescue, medical expense, and mortal remains transport benefits."
        />
      </Helmet>

      <div className="aac-rescue-page bg-white py-6 text-stone-950">
        <div className="mb-8">
          <Button
            asChild
            variant="ghost"
            className="rounded-none px-0 text-sm font-medium text-stone-800 hover:bg-transparent hover:text-[#8f1515]"
          >
            <Link to="/discounts">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Benefits
            </Link>
          </Button>
        </div>

        <section className="border-b-[3px] border-[#b71c1c] pb-5">
          <p className="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#b71c1c]">Benefits &gt; Rescue</p>
          <h1 className="mt-3 text-4xl font-bold leading-tight text-black sm:text-5xl">Rescue Benefits</h1>
          <p className="mt-3 max-w-3xl text-sm leading-7 text-black/68 sm:text-base">
            Review your current Redpoint rescue and medical expense coverage, file a claim, or open the AAC rescue benefit details.
          </p>
        </section>

        <section
          className="aac-rescue-benefits-card relative mt-8 overflow-hidden bg-[#030000] p-5 text-white sm:p-7 lg:p-8"
          style={rescueCardBackgroundStyle}
        >
          <div aria-hidden className="absolute inset-y-0 left-0 w-2 bg-[#b71c1c]" />
          <div
            aria-hidden
            className="pointer-events-none absolute inset-0 opacity-[0.2]"
            style={{
              backgroundImage:
                'radial-gradient(circle at 18% 18%, rgba(183, 28, 28, 0.34) 0 1px, transparent 2px 74px, rgba(183, 28, 28, 0.18) 75px 76px, transparent 77px 142px), linear-gradient(112deg, transparent 0 18%, rgba(248,194,53,0.11) 18.1%, transparent 18.3% 52%, rgba(255,255,255,0.08) 52.1%, transparent 52.3%)',
              backgroundSize: '100% 100%, 360px 360px',
            }}
          />
          <div className="relative">
            <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
              <div className="max-w-2xl">
                <div className="flex items-center gap-3 text-[#ff8a80]">
                  <HeartPulse className="h-5 w-5" />
                  <p className="text-[0.68rem] font-semibold uppercase tracking-[0.26em]">Medical & Rescue</p>
                </div>
                <h2 className="mt-3 text-3xl font-bold leading-tight text-[#f7f1e8] sm:text-4xl">Redpoint Benefits</h2>
                <p className="mt-3 max-w-xl text-sm leading-6 text-white/68">
                  Your current Redpoint rescue and evacuation coverage snapshot.
                </p>
              </div>

              <span
                className={cn(
                  'inline-flex w-fit items-center gap-2 border px-3 py-2 text-[0.62rem] font-semibold uppercase tracking-[0.22em]',
                  membershipActive && hasRescueCoverage ? 'border-emerald-400/45 text-emerald-200' : 'border-white/28 text-white/60',
                )}
              >
                {membershipActive && hasRescueCoverage ? <CheckCircle2 className="h-3.5 w-3.5" strokeWidth={2.2} /> : null}
                {coverageStatus}
              </span>
            </div>

            <div className="mt-8 grid gap-4 md:grid-cols-3">
              <RescueMetric label="Rescue Coverage" value={formatCurrency(benefitsInfo.rescue_amount)} />
              <RescueMetric label="Medical Coverage" value={formatCurrency(benefitsInfo.medical_amount)} />
              <RescueMetric label="Mortal Remains Transport" value={formatCurrency(benefitsInfo.mortal_remains_amount)} />
            </div>

            <div className="mt-8 border border-white/18 bg-black/18 p-5">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-3">
                  <PhoneCall className="mt-1 h-5 w-5 shrink-0 text-[#ff8a80]" />
                  <div>
                    <p className="font-mono text-[0.66rem] uppercase tracking-[0.24em] text-white/48">Emergency Contact</p>
                    <p className="mt-2 text-2xl font-bold text-[#f7f1e8]">
                      <a href={`tel:${REDPOINT_EMERGENCY_PHONE_LINK}`}>{REDPOINT_EMERGENCY_PHONE_DISPLAY}</a>
                    </p>
                    <p className="mt-2 text-sm leading-6 text-white/64">In case of rescue, contact Redpoint Travel Protection.</p>
                  </div>
                </div>
                <p className="max-w-sm text-sm leading-6 text-white/54 sm:text-right">
                  {membershipActive && hasRescueCoverage
                    ? 'Included with your current membership.'
                    : 'Upgrade or renew an eligible membership to restore coverage.'}
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <RescueActionButton href={REDPOINT_EVACUATION_CLAIM_URL} icon={FileText}>
            File Rescue Claim
          </RescueActionButton>
          <RescueActionButton href={REDPOINT_MEDICAL_EXPENSE_CLAIM_URL} icon={FileText}>
            File Medical Claim
          </RescueActionButton>
          <RescueActionButton href={AAC_RESCUE_INFO_URL} icon={Shield} variant="secondary">
            Learn More
          </RescueActionButton>
          <RescueActionButton href={AAC_RESCUE_INFO_URL} icon={FileText} variant="secondary">
            Rescue Terms and Conditions
          </RescueActionButton>
          <RescueActionButton href={AAC_RESCUE_INFO_URL} icon={FileText} variant="secondary">
            Medical Terms and Conditions
          </RescueActionButton>
          <RescueActionButton href={RIPCORD_TRAVEL_INSURANCE_URL} icon={Shield} variant="secondary">
            Purchase Additional Ripcord Travel Insurance
          </RescueActionButton>
        </section>
      </div>
    </>
  );
};

export default RescuePage;
