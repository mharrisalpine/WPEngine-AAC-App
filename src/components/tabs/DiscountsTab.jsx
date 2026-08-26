import React, { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { ChevronDown, ExternalLink, Plus, Tag } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { canAccessDiscounts, getMembershipTier, isFreeMembershipTier } from '@/lib/membershipAccess';
import { getMembershipStatus } from '@/lib/membershipStatus';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { openExternalUrl } from '@/lib/mobileNavigation';
import { getTierDisplayLabel } from '@/lib/membershipTiers';

const DISCOUNT_TIER_KEYS = ['supporter', 'partner', 'leader', 'advocate'];
const DISCOUNT_CATEGORIES = [
  { id: 'discount-brands', label: 'Discount Brands' },
  { id: 'expertvoice', label: 'ExpertVoice' },
  { id: 'climbing-guides', label: 'Climbing Guides' },
  { id: 'climbing-gyms', label: 'Climbing Gym Discounts' },
  { id: 'lodging', label: 'Lodging' },
];
const DISCOUNT_CATEGORY_IDS = DISCOUNT_CATEGORIES.map((category) => category.id);
const DISCOUNT_BRAND_CATEGORY = 'discount-brands';
const DISCOUNT_BRAND_TIERS = [
  { id: 'top' },
  { id: 'middle' },
  { id: 'lower' },
];
const EXPERTVOICE_URL = 'https://www.expertvoice.com/home';
const EXPERTVOICE_BANNER_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2026/01/ExpertVoice-banner.png';
const BRAND_HEADER_IMAGE_URL = 'https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=1800&q=82';
const GYM_HEADER_IMAGE_URL = 'https://images.unsplash.com/photo-1546016365-9b38a1b97164?auto=format&fit=crop&w=1600&q=80';
const GUIDE_HEADER_IMAGE_URL = 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=1600&q=80';
const LODGING_HEADER_IMAGE_URL = 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=1800&q=82';
const LODGING_REGIONS = [
  {
    name: 'Alaska',
    offers: [
      { name: 'AAC Snowbird Hut', details: 'A first-come, first-served backcountry basecamp in the southern Talkeetna Mountains. The insulated and heated hut sits at 4,820 feet north of Palmer.', url: 'https://americanalpineclub.org/snowbird-hut' },
    ],
  },
  {
    name: 'California',
    offers: [
      { name: "Hans's Basecamp", discount: '15% off', details: 'Yosemite basecamp sleeping 10+ with access to the Rostrum, El Capitan Meadow, and Badger Pass.', url: 'http://www.hansbasecamp.com/' },
    ],
  },
  {
    name: 'Colorado',
    offers: [
      { name: 'A-Lodge', discount: '20% off', details: 'Save on private rooms, hostel stays, and car or tent camping at the Boulder and Lyons locations. Email info@americanalpineclub.org for the member code.', url: 'https://a-lodge.com/' },
      { name: 'Colorado Mountain School Lodge', discount: 'CMS client rate', details: 'Stay in Estes Park near Rocky Mountain National Park. Call 1-800-836-4008 for reservations.', url: 'https://coloradomountainschool.com/about-colorado-mountain-school/lodging/' },
      { name: 'The Inn at Raspberry Ridge', discount: '25% off', details: 'Four-bedroom inn in Marble, Colorado. The member rate lowers the published $90 nightly rate to $70. Contact raspberryridge@yahoo.com.', url: 'http://www.mcrchamber.org/lodging-cabins-camping/inn-raspberry-ridge' },
    ],
  },
  {
    name: 'New England',
    offers: [
      { name: 'AAC Campground in Rumney', details: 'AAC campground across from the main parking area and crags at Rumney Rocks, New Hampshire.', url: 'https://americanalpineclub.org/rumney-rattlesnake-campground' },
      { name: 'Appalachian Mountain Club Facilities', discount: 'AMC member rates', details: 'Show an AAC membership card or proof-of-membership letter at AMC huts and lodges, including the High Mountain Huts in New Hampshire.', url: 'http://www.outdoors.org/' },
      { name: 'Greenmont Farms', discount: '10% off', details: "Restored post-and-beam barn near Smugglers' Notch ice climbing in Vermont, available for individuals and groups.", url: 'http://greenmontfarms.com/pricing/' },
      { name: 'The Notch Hostel', discount: '10% off', details: 'Shared and private rooms in North Woodstock, New Hampshire, convenient to Rumney, Cannon, and the White Mountains.', url: 'http://www.notchhostel.com/' },
      { name: 'Tenney Mountain Basecamp', discount: '10% off', details: 'Private, family-friendly lodging about 10 minutes from Rumney Rocks. Book directly to avoid third-party platform fees and show proof of AAC membership.', url: 'https://www.vibebasecamps.com/' },
    ],
  },
  {
    name: 'New York',
    offers: [
      { name: 'AAC Campground in the Gunks', details: 'AAC campground within walking distance of the Shawangunk Ridge.', url: 'https://americanalpineclub.org/gunks-campground' },
      { name: 'High Peaks Mountain Guides - Guides House', discount: '10% off peak stays', details: 'Discounted lodging varies by date, with additional midweek rates. Call for availability.', url: 'http://hpmountainguides.com/' },
      { name: 'The Keene Farm', discount: 'ACC member rates', details: 'AAC members pay Alpine Club of Canada member rates: $20 for the hut or $10 for camping. Select ACC Member and include your AAC member number.', url: 'https://www.alpineclubofcanada.ca/huts/keene-farm/' },
    ],
  },
  {
    name: 'Oregon',
    offers: [
      { name: 'Mazama Lodge', discount: 'From $20/night', details: 'Show an AAC membership card or other proof of membership at the Mount Hood lodge.', url: 'http://mazamas.org/lodge/' },
    ],
  },
  {
    name: 'Tennessee',
    offers: [
      { name: 'The Crash Pad', discount: '10% off', details: 'Member lodging discount at the LEED Platinum hostel in Chattanooga. Present your AAC membership card.', url: 'http://www.crashpadchattanooga.com/' },
    ],
  },
  {
    name: 'Texas',
    offers: [
      { name: 'Hueco Rock Ranch', details: 'AAC lodging three miles from Hueco Tanks.', url: 'https://americanalpineclub.org/hueco-rock-ranch' },
    ],
  },
  {
    name: 'Utah',
    offers: [
      { name: 'Field Station Moab', discount: 'Up to 25% off', details: 'Savings on room rates plus discounted merchandise, food, and beverages. Email info@americanalpineclub.org for the booking code. Minimum stays and blackout dates may apply.', url: 'https://www.fieldstation.com/' },
    ],
  },
  {
    name: 'West Virginia',
    offers: [
      { name: 'AAC Campground in the New River Gorge', details: 'AAC campground on 40 acres in Fayetteville, adjacent to National Park land and within walking distance of popular crags.', url: 'https://americanalpineclub.org/new-river-gorge-campground' },
      { name: 'Wexler Hut', discount: '20% off', details: 'Discounted hut lodging in Seneca Rocks. Call 304-567-2115 to reserve.', url: 'https://www.facebook.com/pages/The-Wexler-Hut-3045672115/1439916819599738?ref_type=bookmark' },
    ],
  },
  {
    name: 'Wyoming',
    offers: [
      { name: 'Bentwood Inn', discount: '10-15% off', details: '15% off October-May; 10% off June-September, holidays, and Presidents Day week.', url: 'http://www.bentwoodinn.com/' },
      { name: 'The Alpine House', discount: '10-15% off', details: '15% off September-June; 10% off July-August and holidays.', url: 'http://alpinehouse.com/' },
      { name: 'Double Diamond X Ranch', discount: '10% off', details: 'Member lodging discount near Cody and the region’s ice climbing.', url: 'http://www.ddxranchwyoming.com/' },
      { name: "Grand Teton Climbers' Ranch", details: 'Affordable AAC lodging south of Jenny Lake in Grand Teton National Park.', url: 'https://americanalpineclub.org/grand-teton-climbers-ranch' },
    ],
  },
  {
    name: 'International',
    offers: [
      { name: 'Sorcerer Lodge - British Columbia', discount: '25% off', details: 'Discount for self-guided groups during shoulder seasons; helicopter transportation is not included. Call (250) 344-2804.', url: 'http://www.sorcererlodge.com/' },
      { name: 'Refugio Cochamo - Chile', discount: 'Member rates', details: 'Bunk room: $15 nightly instead of $20. Camping: $4.50 per person nightly from January 15 through February, or $3 during low season.', url: 'http://www.cochamo.com/lodging/' },
    ],
  },
];
const GUIDE_MEDIA_IMAGES = [
  'https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=900&q=80',
  'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80',
  'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?auto=format&fit=crop&w=900&q=80',
];
const BENEFIT_GALLERY_ITEMS = [
  {
    id: 'discounts',
    title: 'Discounts',
    imageUrl: 'https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=1200&q=82',
    description:
      'Climbing can be expensive. AAC members get deep discounts from 300+ outdoor brands, as well as savings at AAC lodging facilities, partner climbing gyms, and guide services across the country. Discounts may vary by membership level.',
    actionLabel: 'Explore Discounts',
  },
  {
    id: 'rescue',
    title: 'Rescue',
    imageUrl: 'https://images.unsplash.com/photo-1534621107955-b06bbc17b043?auto=format&fit=crop&w=1200&q=82',
    url: '/rescue',
    actionLabel: 'Open Rescue Benefits',
    description:
      'With your newly enhanced rescue and medical expense coverage, you can tie in a little easier knowing the Club has got your back. As a Leader level member, you receive a $300,000 rescue benefit and $5,000 in medical expense coverage.',
  },
  {
    id: 'publications',
    title: 'Books & Media',
    imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
    url: '/publications',
    actionLabel: 'Open Books & Media',
    description:
      'Download digital publications, explore AAC podcasts, and catch recent climbing stories from the Club.',
  },
  {
    id: 'member-store',
    title: 'Member Store',
    imageUrl: 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=1200&q=82',
    url: 'https://americanalpineclub.myshopify.com/',
    actionLabel: 'Open Member Store',
    description:
      'Shop AAC apparel, books, gifts, and member merchandise from the American Alpine Club store.',
  },
  {
    id: 'library',
    title: 'Library',
    imageUrl: 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=1200&q=82',
    url: 'https://americanalpine.wpenginepowered.com/library/',
    actionLabel: 'Open Library',
    description:
      'For climbing bibliophiles. The Club has one of the most extensive climbing libraries in the world. The library is housed in Golden, CO, but we’ll ship you whatever you want to read, for free!',
  },
  {
    id: 'lodging',
    title: 'Lodging',
    imageUrl: 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=1200&q=82',
    url: 'https://americanalpine.wpenginepowered.com/lodging/',
    actionLabel: 'Open Lodging',
    description:
      'Leader level members receive deep discounts at AAC lodging facilities across the country, as well as huts and affiliates throughout the globe.',
  },
  {
    id: 'grants',
    title: 'Grants',
    imageUrl: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=82',
    url: 'https://americanalpine.wpenginepowered.com/grants/',
    actionLabel: 'Open Grants',
    description:
      'Cash for climbing. The Club has a storied legacy of funding climbing-related projects—from expeditions to research—in support of our mission. With more than $175,000 in annual awards, you don’t have to be a pro to get your climbing dream funded.',
  },
];
const normalizeBenefitGalleryItems = (items = []) => {
  const inputItems = Array.isArray(items) ? items : [];

  return BENEFIT_GALLERY_ITEMS.map((fallback) => {
    const raw = inputItems.find((item) => item?.id === fallback.id) || {};
    const isBooksMedia = fallback.id === 'publications';
    const rawTitle = String(raw.title || '').trim();
    const rawUrl = String(raw.url || '').trim();
    return {
      id: fallback.id,
      title: isBooksMedia && (!rawTitle || rawTitle === 'Publications') ? fallback.title : String(raw.title || fallback.title || '').trim(),
      imageUrl: String(raw.imageUrl || raw.image_url || fallback.imageUrl || '').trim(),
      url: isBooksMedia && (!rawUrl || rawUrl.includes('/publications/')) ? fallback.url : String(raw.url || fallback.url || '').trim(),
      actionLabel: isBooksMedia && String(raw.actionLabel || raw.action_label || '').trim() === 'Open Publications'
        ? fallback.actionLabel
        : String(raw.actionLabel || raw.action_label || fallback.actionLabel || '').trim(),
      description: isBooksMedia && String(raw.description || '').includes('digital copies of Accidents')
        ? fallback.description
        : String(raw.description || fallback.description || '').trim(),
    };
  }).filter((item) => item.title);
};
const getGymStateName = (brand) =>
  String(brand || '')
    .replace(/\s+Gym\s+Discounts\s*$/i, '')
    .trim();

const normalizeDiscountCategory = (category) => {
  const normalized = String(category || '').trim().toLowerCase().replace(/_/g, '-');
  if (['brands', 'brand-discounts', 'discounts', 'discount-brands'].includes(normalized)) {
    return 'discount-brands';
  }
  if (['expertvoice', 'expert-voice'].includes(normalized)) {
    return 'expertvoice';
  }
  if (['guides', 'climbing-guides', 'guide-discounts'].includes(normalized)) {
    return 'climbing-guides';
  }
  if (['gyms', 'climbing-gyms', 'gym-discounts', 'climbing-gym-discounts'].includes(normalized)) {
    return 'climbing-gyms';
  }
  return DISCOUNT_CATEGORY_IDS.includes(normalized) ? normalized : DISCOUNT_BRAND_CATEGORY;
};

const isTierSpecificDiscountCategory = (category) => normalizeDiscountCategory(category) === DISCOUNT_BRAND_CATEGORY;

const normalizeBrandTier = (brandTier) => {
  const normalized = String(brandTier || '').trim().toLowerCase().replace(/_/g, '-');
  if (['top', 'top-brand', 'featured', 'primary'].includes(normalized)) {
    return 'top';
  }
  if (['lower', 'lower-brand', 'secondary'].includes(normalized)) {
    return 'lower';
  }
  return 'middle';
};

const getDiscountTierKey = (membershipTier) => {
  switch (membershipTier) {
    case 'Supporter':
      return 'supporter';
    case 'Partner':
      return 'partner';
    case 'Leader':
      return 'leader';
    case 'Advocate':
    case 'GRF':
    case 'Lifetime':
      return 'advocate';
    default:
      return '';
  }
};

const normalizeVisibleTiers = (visibleTiers) => {
  if (!visibleTiers || typeof visibleTiers !== 'object') {
    return DISCOUNT_TIER_KEYS.reduce((tiers, tierKey) => ({ ...tiers, [tierKey]: true }), {});
  }

  return DISCOUNT_TIER_KEYS.reduce(
    (tiers, tierKey) => ({
      ...tiers,
      [tierKey]: visibleTiers[tierKey] === true || visibleTiers[tierKey] === 1 || visibleTiers[tierKey] === '1',
    }),
    {}
  );
};

const normalizeDiscountCards = (cards = []) =>
  (Array.isArray(cards) ? cards : [])
    .map((card, index) => ({
      id: `${card.brand || 'discount'}-${index}`,
      brand: String(card.brand || '').trim(),
      category: normalizeDiscountCategory(card.category),
      brandTier: normalizeBrandTier(card.brand_tier || card.brandTier),
      discountPercent: String(card.discount_percent || '').trim(),
      discountCodeText: String(card.discount_code_text || '').trim(),
      supporterCodeText: String(card.discount_code_text_supporter || '').trim(),
      partnerCodeText: String(card.discount_code_text_partner || '').trim(),
      leaderCodeText: String(card.discount_code_text_leader || '').trim(),
      advocateCodeText: String(card.discount_code_text_advocate || '').trim(),
      supporterPercent: String(card.discount_percent_supporter || card.discount_percent || '').trim(),
      partnerPercent: String(card.discount_percent_partner || card.discount_percent || '').trim(),
      leaderPercent: String(card.discount_percent_leader || card.discount_percent || '').trim(),
      advocatePercent: String(card.discount_percent_advocate || card.discount_percent || '').trim(),
      visibleTiers: normalizeVisibleTiers(card.visible_tiers || card.visibleTiers),
      displayText: String(card.display_text || '').trim(),
      buttonUrl: String(card.button_url || '').trim(),
      imageUrl: String(card.image_url || '').trim(),
    }))
    .filter(
      (card) =>
        card.brand ||
        card.displayText ||
        card.discountPercent ||
        card.discountCodeText ||
        card.supporterCodeText ||
        card.partnerCodeText ||
        card.leaderCodeText ||
        card.advocateCodeText ||
        card.supporterPercent ||
        card.partnerPercent ||
        card.leaderPercent ||
        card.advocatePercent ||
        card.buttonUrl ||
        card.imageUrl
    );

const resolveMembershipDiscountPercent = (card, membershipTier) => {
  if (!isTierSpecificDiscountCategory(card.category)) {
    return card.discountPercent || card.supporterPercent || card.partnerPercent || card.leaderPercent || card.advocatePercent || '';
  }

  switch (membershipTier) {
    case 'Supporter':
      return card.supporterPercent;
    case 'Partner':
      return card.partnerPercent;
    case 'Leader':
      return card.leaderPercent;
    case 'Advocate':
    case 'GRF':
    case 'Lifetime':
      return card.advocatePercent;
    default:
      return '';
  }
};

const isDiscountCardVisibleForTier = (card, membershipTier) => {
  if (!isTierSpecificDiscountCategory(card.category)) {
    return true;
  }

  const tierKey = getDiscountTierKey(membershipTier);
  if (!tierKey) {
    return false;
  }

  return card.visibleTiers?.[tierKey] !== false;
};

const resolveMembershipDiscountCodeText = (card, membershipTier) => {
  if (!isTierSpecificDiscountCategory(card.category)) {
    return card.discountCodeText || card.supporterCodeText || card.partnerCodeText || card.leaderCodeText || card.advocateCodeText || '';
  }

  switch (membershipTier) {
    case 'Supporter':
      return card.supporterCodeText || card.discountCodeText;
    case 'Partner':
      return card.partnerCodeText || card.discountCodeText;
    case 'Leader':
      return card.leaderCodeText || card.discountCodeText;
    case 'Advocate':
    case 'GRF':
    case 'Lifetime':
      return card.advocateCodeText || card.leaderCodeText || card.discountCodeText;
    default:
      return card.discountCodeText;
  }
};

const DiscountCard = ({ card, index, membershipPercent, membershipCodeText, membershipTierLabel, isTierSpecific, portalDesign, portalContent, handleVisitOffer }) => {
  const detailText = membershipCodeText || card.displayText;

  return (
  <motion.article
    key={card.id}
    initial={{ opacity: 0, y: 20 }}
    animate={{ opacity: 1, y: 0 }}
    transition={{ duration: 0.5, delay: index * 0.08 }}
    className="flex h-full flex-col overflow-hidden border-t-4 border-[#b71c1c] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
  >
    <div className="relative aspect-[1.28] overflow-hidden bg-white">
      {card.imageUrl ? (
        <div className="flex h-full w-full items-center justify-center bg-white p-3">
          <img
            src={card.imageUrl}
            alt={card.brand || 'AAC discount partner'}
            className="h-full w-full object-contain"
          />
        </div>
      ) : (
        <div className="flex h-full w-full items-center justify-center bg-white">
          <Tag className="h-10 w-10 text-stone-500" />
        </div>
      )}
    </div>

    <div className="flex flex-1 flex-col px-3 py-3">
      <div>
        <p className="text-[0.62rem] font-semibold uppercase tracking-[0.16em] text-[#8f1515]">Member Benefit</p>
        <h3 className="mt-2 text-lg font-bold leading-tight text-black">{card.brand || 'AAC Partner'}</h3>
        {membershipPercent ? (
          <p className="mt-1 text-sm font-bold uppercase tracking-[0.16em] text-[#8f1515]">
            {membershipPercent}
            {isTierSpecific ? <span className="text-[0.68rem] text-black/45"> {membershipTierLabel}</span> : null}
          </p>
        ) : null}
      </div>

      {(detailText || card.buttonUrl) ? (
        <details className="group mt-3 border border-stone-200/90 bg-white">
          <summary className="flex cursor-pointer list-none items-center justify-between px-3 py-2.5 text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-black/65 marker:hidden [&::-webkit-details-marker]:hidden">
            <span>More Details</span>
            <ChevronDown className="h-4 w-4 transition-transform group-open:rotate-180" />
          </summary>
          <div className="border-t border-stone-200/80 px-3 py-3">
            {detailText ? (
              <p className="whitespace-pre-line text-[0.82rem] font-medium leading-5 text-black/75">{detailText}</p>
            ) : null}
            {card.buttonUrl ? (
              <div className="pt-3">
                <Button
                  type="button"
                  className="w-full text-[0.72rem] uppercase tracking-[0.12em]"
                  style={{
                    backgroundColor: portalDesign.primaryActionBackground,
                    color: portalDesign.primaryActionText,
                  }}
                  onClick={() => void handleVisitOffer(card.buttonUrl)}
                >
                  {portalContent.discounts_button_label}
                  <ExternalLink className="ml-2 h-4 w-4" />
                </Button>
              </div>
            ) : null}
          </div>
        </details>
      ) : null}
    </div>
  </motion.article>
  );
};

const getCardDetails = (card, membershipTier) =>
  resolveMembershipDiscountCodeText(card, membershipTier) || card.displayText || '';

const SectionHero = ({ imageUrl, kicker, title, description, imagePosition = 'center' }) => (
  <div className="relative min-h-[18rem] overflow-hidden bg-black">
    <img
      src={imageUrl}
      alt=""
      className="absolute inset-0 h-full w-full object-cover opacity-80"
      style={{ objectPosition: imagePosition }}
    />
    <div className="absolute inset-0 bg-gradient-to-r from-black/85 via-black/50 to-black/20" />
    <div className="relative max-w-4xl p-6 text-white sm:p-8 lg:p-10">
      <p className="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#f8c235]">{kicker}</p>
      <h3 className="mt-3 text-3xl font-bold leading-tight sm:text-4xl">{title}</h3>
      {description ? <p className="mt-4 max-w-3xl text-base leading-7 text-white/82">{description}</p> : null}
    </div>
  </div>
);

const ExpertVoiceSection = ({ handleVisitOffer }) => {
  return (
    <section className="bg-white">
      <button
        type="button"
        className="block w-full overflow-hidden border-y-[3px] border-[#b71c1c] bg-white text-left transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[#b71c1c] focus:ring-offset-2"
        onClick={() => void handleVisitOffer(EXPERTVOICE_URL)}
        aria-label="Visit ExpertVoice"
      >
        <img
          src={EXPERTVOICE_BANNER_URL}
          alt="ExpertVoice"
          className="block w-full object-cover"
        />
      </button>
    </section>
  );
};

const LodgingSection = ({ handleVisitOffer }) => (
  <section className="space-y-6 bg-white">
    <SectionHero
      imageUrl={LODGING_HEADER_IMAGE_URL}
      kicker="Member Lodging"
      title="AAC lodging discounts"
      description="Explore AAC campgrounds, huts, hostels, lodges, and partner accommodations across the United States and abroad. Offer details may change; confirm current rates and restrictions directly with the property when booking."
    />

    <div className="space-y-7">
      {LODGING_REGIONS.map((region) => (
        <div key={region.name}>
          <h4 className="border-b-[3px] border-[#b71c1c] pb-2 text-2xl font-bold text-black">{region.name}</h4>
          <div className="grid gap-x-8 md:grid-cols-2">
            {region.offers.map((offer) => (
              <article key={offer.name} className="border-b border-stone-300 py-5">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                  <h5 className="text-xl font-bold leading-tight text-black">{offer.name}</h5>
                  {offer.discount ? (
                    <span className="shrink-0 text-[0.7rem] font-bold uppercase tracking-[0.14em] text-[#8f1515]">{offer.discount}</span>
                  ) : null}
                </div>
                <p className="mt-3 text-sm leading-6 text-black/70">{offer.details}</p>
                {offer.url ? (
                  <button
                    type="button"
                    className="mt-4 inline-flex items-center border-b-2 border-[#b71c1c] pb-1 text-[0.7rem] font-bold uppercase tracking-[0.14em] text-[#8f1515]"
                    onClick={() => void handleVisitOffer(offer.url)}
                  >
                    Property details
                    <ExternalLink className="ml-2 h-4 w-4" />
                  </button>
                ) : null}
              </article>
            ))}
          </div>
        </div>
      ))}
    </div>
  </section>
);

const ExpandableDirectorySection = ({ category, cards, membershipTier, handleVisitOffer }) => {
  const isGuide = category === 'climbing-guides';
  const directoryCards = useMemo(() => {
    if (isGuide) {
      return cards;
    }

    return [...cards].sort((firstCard, secondCard) =>
      String(firstCard.brand || '').localeCompare(String(secondCard.brand || ''), undefined, { sensitivity: 'base' })
    );
  }, [cards, isGuide]);
  const headerImage = isGuide ? GUIDE_HEADER_IMAGE_URL : GYM_HEADER_IMAGE_URL;
  const kicker = isGuide ? 'Guide Discounts' : 'Climbing Gym Discounts';
  const title = isGuide ? 'Guide services and outdoor instruction' : 'Gym discounts by state';
  const description = isGuide
    ? 'AAC guide partners offer discounts on instruction, guiding, avalanche education, wilderness medicine, and mountain programs. Expand a guide entry for discount details and booking notes.'
    : 'AAC partners with climbing gyms across the country to offer member discounts on day passes, memberships, punch passes, and initiation fees. Expand a state to review available gym offers, locations, and links where available.';

  return (
    <section className="space-y-4 bg-white">
      <div className="overflow-hidden bg-white">
        <SectionHero
          imageUrl={headerImage}
          kicker={kicker}
          title={title}
          description={description}
          imagePosition={isGuide ? 'center' : 'center'}
        />
      </div>

      {isGuide ? (
        <div className="grid gap-2 sm:grid-cols-3">
          {GUIDE_MEDIA_IMAGES.map((imageUrl, index) => (
            <div key={imageUrl} className="aspect-[1.55] overflow-hidden bg-stone-100">
              <img src={imageUrl} alt="" className="h-full w-full object-cover" loading={index === 0 ? 'eager' : 'lazy'} />
            </div>
          ))}
        </div>
      ) : null}

      <div
        className={
          isGuide
            ? 'grid gap-x-8 border-y-2 border-[#b71c1c] bg-white md:grid-cols-2'
            : 'grid gap-x-5 border-y-2 border-[#b71c1c] bg-white md:grid-cols-2'
        }
      >
        {directoryCards.map((card) => {
          const details = getCardDetails(card, membershipTier);
          const percent = isGuide ? resolveMembershipDiscountPercent(card, membershipTier) : '';
          const displayName = isGuide ? card.brand : getGymStateName(card.brand);
          return (
            <details key={card.id} className="group border-b-2 border-[#b71c1c] bg-white">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-1 py-3 marker:hidden [&::-webkit-details-marker]:hidden">
                <span className="flex min-w-0 items-center gap-3">
                  <span className="min-w-0">
                    <span className="block text-xl font-bold text-black">{displayName}</span>
                    {percent ? (
                      <span className="mt-1 block text-[0.72rem] font-bold uppercase tracking-[0.16em] text-[#8f1515]">{percent}</span>
                    ) : null}
                  </span>
                </span>
                <span className="flex h-9 w-9 shrink-0 items-center justify-center border border-[#b71c1c] text-[#b71c1c]">
                  <Plus className="h-5 w-5 transition-transform group-open:rotate-45" />
                </span>
              </summary>
              <div className="pb-3 pr-2">
                {details ? <p className="whitespace-pre-line text-sm leading-6 text-black/72">{details}</p> : null}
                {card.buttonUrl ? (
                  <button
                    type="button"
                    className="mt-4 inline-flex min-h-[2.75rem] items-center justify-center bg-[#8f1515] px-5 text-[0.72rem] font-bold uppercase tracking-[0.14em] text-white"
                    onClick={() => void handleVisitOffer(card.buttonUrl)}
                  >
                    Website
                    <ExternalLink className="ml-2 h-4 w-4" />
                  </button>
                ) : null}
              </div>
            </details>
          );
        })}
      </div>
    </section>
  );
};

const BenefitsGallery = ({ items, onOpenDiscounts, onOpenLodging, onOpenBooksMedia, onOpenInternal, onOpenExternal }) => (
  <div className="space-y-6 bg-white">
    <div className="border-b-[3px] border-[#b71c1c] pb-5">
      <p className="mb-2 text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#b71c1c]">
        Member Benefits
      </p>
      <h2 className="text-3xl font-bold text-black sm:text-4xl">AAC Benefits</h2>
      <p className="mt-3 max-w-3xl text-sm leading-7 text-black/68 sm:text-base">
        Explore membership benefits across discounts, rescue support, publications, library access, lodging, and grants.
      </p>
    </div>

    <div className="flex flex-wrap justify-center gap-5 bg-white">
      {items.map((item, index) => {
        const clickable = item.id === 'discounts' || item.id === 'lodging' || Boolean(item.url);
        const CardTag = clickable ? 'button' : 'article';
        const handleClick = () => {
          if (item.id === 'discounts') {
            onOpenDiscounts();
            return;
          }

          if (item.id === 'lodging') {
            onOpenLodging();
            return;
          }

          if (item.id === 'publications') {
            onOpenBooksMedia();
            return;
          }

          if (typeof item.url === 'string' && item.url.startsWith('/')) {
            onOpenInternal(item.url);
            return;
          }

          if (item.url) {
            onOpenExternal(item.url);
          }
        };
        return (
          <motion.div
            key={item.id}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: index * 0.05 }}
            className="flex w-full md:w-[calc(50%-0.625rem)] xl:w-[calc(25%-0.9375rem)]"
          >
            <CardTag
              type={clickable ? 'button' : undefined}
              onClick={clickable ? handleClick : undefined}
              className={`aac-benefit-gallery-card flex h-full min-h-[31rem] w-full flex-col border border-stone-300 bg-white text-left text-black md:h-[34rem] xl:h-[36rem] ${
                clickable ? 'transition hover:-translate-y-0.5 hover:border-[#b71c1c] hover:shadow-[0_18px_36px_rgba(0,0,0,0.08)]' : ''
              }`}
            >
              <div className="aac-benefit-gallery-media aspect-[4/3] w-full overflow-hidden bg-stone-100">
                <img
                  src={item.imageUrl}
                  alt=""
                  className="aac-benefit-gallery-image h-full w-full object-cover"
                  loading={index === 0 ? 'eager' : 'lazy'}
                />
              </div>
              <div className="flex flex-1 flex-col gap-3 p-5">
                <h3 className="text-2xl font-bold leading-tight text-black">{item.title}</h3>
                <p className="overflow-hidden text-sm leading-7 text-black/68 md:max-h-[11rem] xl:max-h-[12.25rem]">{item.description}</p>
                {item.actionLabel ? (
                  <span className="mt-auto inline-flex w-max items-center border-b-[3px] border-[#b71c1c] pb-1 text-[0.72rem] font-bold uppercase tracking-[0.14em] text-[#8f1515]">
                    {item.actionLabel}
                    {item.url ? <ExternalLink className="ml-2 h-4 w-4" /> : null}
                  </span>
                ) : null}
              </div>
            </CardTag>
          </motion.div>
        );
      })}
    </div>
  </div>
);

const DiscountsTab = ({ profile }) => {
  const navigate = useNavigate();
  const portalUi = getPortalUiSettings();
  const portalContent = portalUi.content;
  const portalDesign = portalUi.design;
  const [showGallery, setShowGallery] = useState(true);
  const [activeCategory, setActiveCategory] = useState(DISCOUNT_CATEGORIES[0].id);
  const membershipStatus = getMembershipStatus(profile?.profile_info);
  const membershipTier = getMembershipTier(profile?.profile_info);
  const membershipTierLabel = getTierDisplayLabel(membershipTier || 'Supporter', 'Member');
  const isFreeTier = isFreeMembershipTier(profile?.profile_info);
  const isLocked = !canAccessDiscounts(profile?.profile_info);
  const discountCards = useMemo(
    () => normalizeDiscountCards(portalContent.discountCards)
      .filter((card) => isDiscountCardVisibleForTier(card, membershipTier)),
    [membershipTier, portalContent.discountCards]
  );
  const benefitGalleryItems = useMemo(
    () => normalizeBenefitGalleryItems(portalContent.benefitsGalleryItems),
    [portalContent.benefitsGalleryItems]
  );
  const visibleCards = discountCards.filter((card) => card.category === activeCategory);
  const brandTierGroups = useMemo(
    () => DISCOUNT_BRAND_TIERS
      .map((brandTier) => ({
        ...brandTier,
        cards: visibleCards.filter((card) => card.brandTier === brandTier.id),
      }))
      .filter((brandTier) => brandTier.cards.length),
    [visibleCards]
  );
  const orderedBrandCards = useMemo(
    () => brandTierGroups.flatMap((brandTier) => brandTier.cards),
    [brandTierGroups]
  );
  const activeCategoryLabel = DISCOUNT_CATEGORIES.find((category) => category.id === activeCategory)?.label || 'Benefits';

  useEffect(() => {
    if (!showGallery) {
      window.requestAnimationFrame(() => window.scrollTo({ top: 0, behavior: 'auto' }));
    }
  }, [activeCategory, showGallery]);

  const handleVisitOffer = async (url) => {
    if (!url) {
      return;
    }

    await openExternalUrl(url);
  };

  return (
    <div className="bg-white py-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
      >
        {showGallery ? (
          <BenefitsGallery
            items={benefitGalleryItems}
            onOpenDiscounts={() => {
              setActiveCategory(DISCOUNT_BRAND_CATEGORY);
              setShowGallery(false);
            }}
            onOpenLodging={() => {
              setActiveCategory('lodging');
              setShowGallery(false);
            }}
            onOpenBooksMedia={() => navigate('/publications')}
            onOpenInternal={(path) => navigate(path)}
            onOpenExternal={handleVisitOffer}
          />
        ) : (
          <>
        <div className="mb-6 border-b-[3px] border-[#b71c1c] pb-5">
          <p className="mb-2 text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#b71c1c]">
            Member Profile
          </p>
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 className="text-3xl font-bold text-black">{portalContent.discounts_title}</h2>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-black/65">
                Browse AAC member benefits and partner offers for your membership level.
              </p>
            </div>
            <button
              type="button"
              className="inline-flex min-h-[2.75rem] items-center justify-center border border-[#8f1515] bg-white px-5 text-[0.72rem] font-bold uppercase tracking-[0.14em] text-[#8f1515] transition hover:bg-[#8f1515] hover:text-white"
              onClick={() => setShowGallery(true)}
            >
              Benefit Gallery
            </button>
          </div>
        </div>

        {!isLocked ? (
          <div className="mb-6 grid w-full grid-cols-1 gap-3 border-b border-stone-200 bg-white pb-4 sm:grid-cols-2 xl:grid-cols-5">
            {DISCOUNT_CATEGORIES.map((category) => {
              const active = category.id === activeCategory;
              return (
                <button
                  key={category.id}
                  type="button"
                  className={`w-full border px-6 py-3 text-center text-[0.72rem] font-bold uppercase tracking-[0.14em] transition ${
                    active
                      ? 'border-[#8f1515] bg-[#8f1515] text-white'
                      : 'border-stone-300 bg-white text-black hover:border-[#8f1515] hover:text-[#8f1515]'
                  }`}
                  onClick={() => setActiveCategory(category.id)}
                >
                  {category.label}
                </button>
              );
            })}
          </div>
        ) : null}

        {isLocked ? (
          <div className="max-w-2xl border-y-[3px] border-[#b71c1c] bg-white py-6 text-center">
            <p className="mb-2 text-xl font-bold text-black">{portalContent.discounts_locked_title}</p>
            <p className="text-black/75">
              {isFreeTier
                ? portalContent.discounts_free_locked_description
                : portalContent.discounts_locked_description}
            </p>
            {membershipStatus !== 'Active' && !isFreeTier ? null : (
              <p className="mt-3 text-sm text-black/55">
                {portalContent.discounts_upgrade_hint}
              </p>
            )}
          </div>
        ) : activeCategory === 'expertvoice' ? (
          <ExpertVoiceSection handleVisitOffer={handleVisitOffer} />
        ) : activeCategory === 'lodging' ? (
          <LodgingSection handleVisitOffer={handleVisitOffer} />
        ) : activeCategory === 'climbing-guides' || activeCategory === 'climbing-gyms' ? (
          visibleCards.length ? (
            <ExpandableDirectorySection
              category={activeCategory}
              cards={visibleCards}
              membershipTier={membershipTier}
              handleVisitOffer={handleVisitOffer}
            />
          ) : (
            <div className="max-w-2xl border-y-[3px] border-[#b71c1c] bg-white py-6">
              <p className="text-lg font-bold text-black">No {activeCategoryLabel} are currently listed.</p>
              <p className="mt-2 text-sm leading-6 text-black/65">
                Add entries for this category in AAC Portal Settings.
              </p>
            </div>
          )
        ) : orderedBrandCards.length ? (
          <div className="space-y-6 bg-white">
            <SectionHero
              imageUrl={BRAND_HEADER_IMAGE_URL}
              kicker="Discount Brands"
              title="Climbing gear and partner offers"
              description="Browse AAC member discounts on climbing gear, outdoor equipment, apparel, footwear, and partner products."
              imagePosition="center"
            />
            <div className="grid grid-cols-1 gap-3 bg-white sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
              {orderedBrandCards.map((card, index) => {
                const membershipPercent = resolveMembershipDiscountPercent(card, membershipTier);
                const membershipCodeText = resolveMembershipDiscountCodeText(card, membershipTier);
                const isTierSpecific = isTierSpecificDiscountCategory(card.category);

                return (
                  <DiscountCard
                    key={card.id}
                    card={card}
                    index={index}
                    membershipPercent={membershipPercent}
                    membershipCodeText={membershipCodeText}
                    membershipTierLabel={membershipTierLabel}
                    isTierSpecific={isTierSpecific}
                    portalDesign={portalDesign}
                    portalContent={portalContent}
                    handleVisitOffer={handleVisitOffer}
                  />
                );
              })}
            </div>
          </div>
        ) : (
          <div className="max-w-2xl border-y-[3px] border-[#b71c1c] bg-white py-6">
            <p className="text-lg font-bold text-black">No {activeCategoryLabel} are currently visible for {membershipTierLabel}.</p>
            <p className="mt-2 text-sm leading-6 text-black/65">
              Add or enable cards for this category and tier in AAC Portal Settings.
            </p>
          </div>
        )}
          </>
        )}
      </motion.div>
    </div>
  );
};

export default DiscountsTab;
