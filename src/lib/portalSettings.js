import { getAppRuntimeConfig } from '@/lib/backendConfig';

export const JOIN_PAGE_URL = '/signup/';

// These defaults are the app's emergency backpack: they keep the frontend usable
// outside WordPress and catch missing runtime settings before the UI face-plants.
const DEFAULT_SETTINGS = {
  content: {
    home_hero_kicker: 'Home',
    home_hero_title: 'United\nWe Climb.',
    home_hero_description:
      'Explore AAC membership, publications, discounts, and account resources through the same member-focused experience that powers the portal.',
    home_primary_cta_label: 'Join',
    home_primary_cta_url: JOIN_PAGE_URL,
    home_secondary_cta_label: 'Renew',
    home_secondary_cta_url: 'https://membership.americanalpineclub.org/renew',
    home_tertiary_cta_label: 'Learn More About Membership',
    home_tertiary_cta_url: 'https://americanalpine.wpenginepowered.com/learn-more/',
    home_membership_chip_kicker: 'Membership',
    home_membership_chip_description:
      'Climbing advocacy, publications, discounts, and member resources all live here.',
    home_intro_kicker: 'Since 1902',
    home_intro_title: 'Built for climbers.',
    home_intro_description:
      'Founded in 1902, the American Alpine Club is a nonprofit that champions climbing knowledge, inspiration, advocacy, and community support for people who care deeply about the mountains.',
    home_intro_secondary_description:
      'From member publications to account tools and partner discounts, the Club keeps building practical resources that help climbers stay connected and better supported.',
    home_intro_button_label: 'Learn More About The AAC',
    home_intro_button_url: 'https://americanalpine.wpenginepowered.com/learn-more/',
    home_involvement_kicker: 'Explore',
    home_involvement_title: 'How To Get Involved',
    home_involvement_button_label: 'Join the Club',
    home_involvement_button_url: JOIN_PAGE_URL,
    home_publications_kicker: 'Library',
    home_publications_title: 'Our Publications',
    home_publications_button_label: 'All Publications',
    home_publications_button_url: 'https://americanalpine.wpenginepowered.com/publications/',
    home_partners_kicker: 'Network',
    home_partners_title: 'Our Partners',
    home_partners_description:
      'Partner brands and community collaborators help AAC extend member value across climbing gear, publications, and advocacy work.',
    homeInvolvementCards: [
      {
        title: 'Join the Club',
        description:
          'Membership supports AAC advocacy, climbing knowledge, and the wider climbing community.',
        buttonLabel: 'Join Now',
        buttonUrl: JOIN_PAGE_URL,
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
        accentStyle: 'gold',
      },
    ],
    homePublicationCards: [
      {
        title: 'American Alpine Journal',
        description:
          'Long-form reporting on major climbs around the world, presented in AAC’s flagship publication.',
        buttonLabel: 'View Publication',
        buttonUrl: 'https://americanalpine.wpenginepowered.com/publications/aaj/',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
        accentColor: '#f8c235',
      },
      {
        title: 'Accidents in North American Climbing',
        description:
          'Annual accident analysis and takeaways that help climbers learn from the year’s most important incidents.',
        buttonLabel: 'View Publication',
        buttonUrl: 'https://americanalpine.wpenginepowered.com/publications/accidents/',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
        accentColor: '#b20710',
      },
    ],
    homePartnerLogos: [
      {
        name: 'American Alpine Club',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/dark-header-logo.svg',
        linkUrl: 'https://americanalpine.wpenginepowered.com/',
      },
      {
        name: 'Backcountry',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-2.png',
        linkUrl: '',
      },
      {
        name: 'Black Diamond',
        imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-1.png',
        linkUrl: '',
      },
    ],
    memberProfileCardSections: {
      membership_card: { label: 'Membership Card', visible: 1 },
      profile_information: { label: 'Profile Information', visible: 1 },
      membership_snapshot: { label: 'Membership Snapshot', visible: 1 },
      redpoint_benefits: { label: 'Redpoint Benefits', visible: 1 },
      linked_accounts: { label: 'Linked Accounts', visible: 1 },
      custom_blocks: { label: 'Custom Member Profile Blocks', visible: 1 },
    },
    account_settings_title: 'Account Settings',
    contactIssueTypes: [
      'Membership Issues',
      'Cancellation',
      'Discount Codes',
    ],
    profile_information_title: 'Profile Information',
    profile_information_description:
      'Primary contact and profile information used across the AAC portal. You may update your details and preferences in Account Settings.',
    update_profile_button_label: 'Update Profile Information',
    membership_snapshot_title: 'Membership Snapshot',
    membership_snapshot_description:
      'Live membership and benefit details coming from WordPress and Paid Memberships Pro.',
    linked_accounts_title: 'Linked Accounts',
    linked_accounts_description:
      'Manage household members connected to this AAC membership and redeem invite codes for child accounts.',
    memberProfileBlocks: [],
    member_details_description:
      'Members receive a free T-shirt and books with the purchase of their membership.',
    publications_title: 'Books & Media',
    publications_description:
      'Access AAC digital publications, podcasts, and member stories from the member portal.',
    publications_locked_title: 'Books & Media Unlocks at Partner',
    publications_locked_description:
      'The AAC publication library is available to Partner members and above. Upgrade your membership to open digital issues and manage your publication preferences.',
    publications_upgrade_button_label: 'Upgrade Membership',
    publicationViewUrls: {
      aaj: 'https://aac-publications.s3.us-east-1.amazonaws.com/aaj/AAJ+2025.pdf',
      anac: 'https://aac-publications.s3.us-east-1.amazonaws.com/ANAC+2025+Book_Digital_reduced.pdf',
      acj: 'https://americanalpineclub.org/publications/',
      guidebook: 'https://www.flipsnack.com/americanalpineclub/guidebook-xv/full-view.html',
    },
    join_hero_kicker: 'Membership',
    join_hero_title: 'United\nWe Climb.',
    join_hero_description:
      'Join the American Alpine Club to support climbing advocacy, publications, member benefits, and a member experience built for the people who keep showing up for the mountains.',
    join_primary_cta_label: 'Join Now',
    join_benefits_cta_label: 'Member Benefits',
    join_application_kicker: '',
    join_application_title: 'Choose your membership and complete checkout.',
    join_application_description:
      'Select a membership level above, then complete the real AAC checkout form below.',
    join_redeem_code_button_label: 'Redeem Family Member Invite',
    signupBenefitsMatrixImageUrl: '',
    membershipLevelBenefits: {
      Supporter: [
        'Support for AAC Advocacy, Education, & Member Services',
        'AAC T-shirt',
        'Discounts: Gear, Gym, & Guide Services',
        'AAC Library',
      ],
      Partner: [
        'Support for AAC Advocacy, Education, & Member Services',
        'AAC T-shirt',
        'Discounts: Gear, Gym, & Guide Services',
        'AAC Library',
        'Rescue Coverage',
        'Medical Expense Coverage',
        'Mortal Remains Transport',
        'AAC Publications (AAJ+Accidents+Guidebook+ACJ)',
      ],
      Leader: [
        'Support for AAC Advocacy, Education, & Member Services',
        'AAC T-shirt',
        'Discounts: Gear, Gym, & Guide Services',
        'AAC Library',
        'Rescue Coverage',
        'Medical Expense Coverage',
        'Mortal Remains Transport',
        'AAC Publications (AAJ+Accidents+Guidebook+ACJ)',
      ],
      Advocate: [
        'Support for AAC Advocacy, Education, & Member Services',
        'AAC T-shirt',
        'Discounts: Gear, Gym, & Guide Services',
        'AAC Library',
        'Rescue Coverage',
        'Medical Expense Coverage',
        'Mortal Remains Transport',
        'AAC Publications (AAJ+Accidents+Guidebook+ACJ)',
        'Limited Edition Hardcover AAJ',
      ],
    },
    login_hero_kicker: 'Member access',
    login_hero_title: 'United\nWe Climb.',
    login_hero_description:
      'Access your membership details, discounts, publications, and account settings in one place.',
    login_form_kicker: 'Login',
    login_form_title: 'Welcome back.',
    login_submit_label: 'Sign in',
    login_forgot_password_label: 'Forgot your password?',
    login_join_link_label: 'Need to join?',
    login_purchase_success_message: 'Purchase successful. Please sign in to access your member profile.',
    linked_accounts_page_title: 'Linked Accounts',
    linked_accounts_page_description:
      'Enter a family invite code to create or claim a connected household account. If the email already has an AAC account, we will link that existing account after verifying the password.',
    linked_accounts_lookup_button_label: 'Check Code',
    linked_accounts_redeem_button_label: 'Redeem Invite Code',
    linked_accounts_success_message: 'Invite redeemed successfully. Redirecting to your member profile...',
    discounts_title: 'Member Benefits',
    discounts_locked_title: 'Discounts Locked',
    discounts_locked_description:
      'Discounts are available to active members only. Renew or rejoin your membership to unlock partner offers.',
    discounts_free_locked_description:
      'Free memberships include portal preview access and promo emails, but partner discounts unlock with a paid membership.',
    discounts_upgrade_hint:
      'Upgrade from Free to Supporter or above whenever you are ready.',
    discounts_button_label: 'Visit Website',
    benefitsGalleryItems: [
      {
        id: 'discounts',
        title: 'Discounts',
        imageUrl: 'https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=1200&q=82',
        url: '',
        actionLabel: 'Explore Discounts',
        description:
          'Climbing can be expensive. AAC members get deep discounts from 300+ outdoor brands, as well as savings at AAC lodging facilities, partner climbing gyms, and guide services across the country. Discounts may vary by membership level.',
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
    ],
    discountCards: [
      {
        brand: 'Patagonia',
        category: 'discount-brands',
        discount_percent: '20%',
        discount_code_text: 'Use your AAC Patagonia member code at checkout.',
        discount_percent_supporter: '15%',
        discount_percent_partner: '20%',
        discount_percent_leader: '25%',
        discount_percent_advocate: '25%',
        display_text: 'Premium outdoor clothing and gear for climbers and adventurers.',
        button_url: 'https://www.patagonia.com',
        image_url: 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=900&q=80',
      },
      {
        brand: 'The North Face',
        category: 'discount-brands',
        discount_percent: '15%',
        discount_code_text: 'Use your AAC The North Face member code at checkout.',
        discount_percent_supporter: '10%',
        discount_percent_partner: '15%',
        discount_percent_leader: '18%',
        discount_percent_advocate: '20%',
        display_text: 'High-performance outdoor apparel and equipment.',
        button_url: 'https://www.thenorthface.com',
        image_url: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=900&q=80',
      },
      {
        brand: 'Black Diamond',
        category: 'discount-brands',
        discount_percent: '25%',
        discount_code_text: 'Use your AAC Black Diamond member code at checkout.',
        discount_percent_supporter: '15%',
        discount_percent_partner: '20%',
        discount_percent_leader: '25%',
        discount_percent_advocate: '30%',
        display_text: 'Premium climbing gear, harnesses, and safety equipment.',
        button_url: 'https://www.blackdiamondequipment.com',
        image_url: 'https://images.unsplash.com/photo-1526491109672-74740652b963?auto=format&fit=crop&w=900&q=80',
      },
    ],
    portal_preferences_title: 'Portal Preferences',
    portal_preferences_description:
      'Settings the portal is currently storing for your member record.',
    quick_actions_title: 'Quick Actions',
    quick_actions_description:
      'Jump straight into the next member task.',
    confirmation_letter_format: 'standard',
    confirmation_letter_body: `To Whom it May Concern,

This letter confirms that **{member_name}** is a member of The American Alpine Club.
{benefit_sentence}

{reimbursement_sentence}

In case of rescue contact Redpoint Travel Protection: +01-628-251-1510

Please contact the American Alpine Club with any questions at 303-384-0110 or email us at info@americanalpineclub.org.

Regards,

The American Alpine Club
710 Tenth Street Suite 100
Golden, CO 80401 USA`,
  },
  design: {
    sidebarBackgroundUrl: '/sidebar-topo-v2.svg',
    sidebarOverlayStart: '0.72',
    sidebarOverlayEnd: '0.82',
    sidebarButtonBackground: '#000000',
    sidebarButtonHoverBackground: '#111111',
    sidebarButtonActiveBackground: '#000000',
    sidebarAccentColor: '#f8c235',
    primaryActionBackground: '#8f1515',
    primaryActionText: '#ffffff',
    secondaryActionBackground: '#f8c235',
    secondaryActionText: '#000000',
    pageBackground: '#ffffff',
    panelBackground: '#ffffff',
    panelBorderColor: '#d6d3d1',
    heroPanelBackground: 'rgba(0,0,0,0.34)',
    heroPanelBorderColor: 'rgba(255,255,255,0.14)',
    heroChipBackground: 'rgba(0,0,0,0.38)',
    heroChipBorderColor: 'rgba(255,255,255,0.18)',
    loginFormBackground: 'rgba(247,241,232,0.94)',
    loginOverlay: 'linear-gradient(180deg,rgba(3,0,0,0.24),rgba(3,0,0,0.72)),radial-gradient(circle_at_top,rgba(248,194,53,0.12),transparent 24%)',
    homeHeroOverlay: 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
    homeHeroTintOverlay: 'linear-gradient(to top, rgba(3,0,0,0.5), transparent, rgba(3,0,0,0.16))',
    joinHeroOverlay: 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
    joinHeroTintOverlay: 'linear-gradient(to top, rgba(3,0,0,0.56), transparent, rgba(3,0,0,0.18))',
    navBackground: '#030000',
    navTextColor: '#ffffff',
    navHoverTextColor: '#f8c235',
    navIconColor: '#f8c235',
    navDropdownBackground: 'rgba(11,9,8,0.95)',
    navDropdownTextColor: '#f4efe7',
    joinHeroImageUrl: '/assets/join-hero-static-image.jpg',
    homeHeroVideoUrl:
      'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0',
    joinHeroVideoUrl: '',
    loginBackgroundImageUrl: '/assets/join-hero-static-image.jpg',
    homeIntroImageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
    homeIntroAccentImageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-3.jpg',
    publicationTileImages: {
      aaj: '',
      anac: '',
      acj: '',
      guidebook: '',
    },
  },
  navigation: {
    topNavSections: [
      {
        id: 'get_involved',
        label: 'Get Involved',
        href: '/get-involved',
        children: [
          { label: 'Volunteer', href: '/volunteer' },
          { label: 'Donate', href: 'https://membership.americanalpineclub.org/donate', external: true },
          { label: 'Sign Up', href: JOIN_PAGE_URL, external: true },
        ],
      },
      {
        id: 'membership',
        label: 'Membership',
        href: '/membership',
        children: [
          { label: 'Benefits', href: '/benefits' },
          { label: 'Join', href: JOIN_PAGE_URL, external: true },
          { label: 'Sign In', href: '/login' },
          { label: 'Renew', href: 'https://membership.americanalpineclub.org/renew', external: true },
        ],
      },
      {
        id: 'stories_news',
        label: 'Stories & News',
        href: '/stories',
        children: [
          { label: 'Articles & News', href: '/stories' },
          { label: 'The Prescription', href: '/prescription' },
          { label: 'The Line', href: '/line-archive' },
        ],
      },
      {
        id: 'publications',
        label: 'Publications',
        href: '/publications',
        children: [
          { label: 'AAJ', href: '/publications/aaj' },
          { label: 'Accidents', href: '/publications/accidents' },
        ],
      },
      {
        id: 'our_work',
        label: 'Our Work',
        href: '/our-work',
        children: [
          { label: "Gov't Affairs", href: '/advocacy' },
          { label: 'Grief Fund', href: '/grieffund' },
          { label: 'Library', href: '/library' },
          { label: 'Chapters', href: '/chapters' },
        ],
      },
    ],
    sidebarSections: [
      {
        id: 'your_portal',
        title: 'Member',
        items: [
          { id: 'member_profile', label: 'Member Profile', to: '/profile', icon: 'user', order: 1 },
          { id: 'account', label: 'Settings', to: '/account', icon: 'pen', order: 2 },
          {
            id: 'manage',
            label: 'Billing',
            to: '/membership',
            icon: 'settings',
            order: 3,
          },
          { id: 'discounts', label: 'Benefits', to: '/discounts', icon: 'badge-percent', order: 4 },
          { id: 'contact', label: 'Contact Us', to: '/contact', icon: 'mail', order: 5 },
        ],
      },
    ],
  },
  layout: {
    homeSections: [
      { id: 'hero', label: 'Hero', order: 1, visible: 1 },
      { id: 'intro', label: 'Intro', order: 2, visible: 1 },
      { id: 'involvement', label: 'Get Involved', order: 3, visible: 1 },
      { id: 'publications', label: 'Publications', order: 4, visible: 1 },
      { id: 'partners', label: 'Partners', order: 5, visible: 1 },
    ],
  },
};

export const getPortalUiSettings = () => {
  const runtimeSettings = getAppRuntimeConfig().portalSettings || {};

  // WordPress hands us a very PHP-shaped settings blob. We translate and merge it
  // here so React gets the camelCase structure it expects and nobody has to turn
  // each component into its own tiny interpreter.
  return {
    content: {
      ...DEFAULT_SETTINGS.content,
      ...(runtimeSettings.content || {}),
      publicationViewUrls: {
        ...DEFAULT_SETTINGS.content.publicationViewUrls,
        ...(runtimeSettings.content?.publicationViewUrls || {}),
      },
      discountCards:
        Array.isArray(runtimeSettings.content?.discountCards) && runtimeSettings.content.discountCards.length
          ? runtimeSettings.content.discountCards
          : DEFAULT_SETTINGS.content.discountCards,
      benefitsGalleryItems:
        Array.isArray(runtimeSettings.content?.benefitsGalleryItems) && runtimeSettings.content.benefitsGalleryItems.length
          ? runtimeSettings.content.benefitsGalleryItems
          : DEFAULT_SETTINGS.content.benefitsGalleryItems,
      contactIssueTypes:
        Array.isArray(runtimeSettings.content?.contactIssueTypes) && runtimeSettings.content.contactIssueTypes.length
          ? runtimeSettings.content.contactIssueTypes
          : DEFAULT_SETTINGS.content.contactIssueTypes,
      homeInvolvementCards:
        Array.isArray(runtimeSettings.content?.homeInvolvementCards) && runtimeSettings.content.homeInvolvementCards.length
          ? runtimeSettings.content.homeInvolvementCards
          : DEFAULT_SETTINGS.content.homeInvolvementCards,
      homePublicationCards:
        Array.isArray(runtimeSettings.content?.homePublicationCards) && runtimeSettings.content.homePublicationCards.length
          ? runtimeSettings.content.homePublicationCards
          : DEFAULT_SETTINGS.content.homePublicationCards,
      homePartnerLogos:
        Array.isArray(runtimeSettings.content?.homePartnerLogos) && runtimeSettings.content.homePartnerLogos.length
          ? runtimeSettings.content.homePartnerLogos
          : DEFAULT_SETTINGS.content.homePartnerLogos,
      membershipLevelBenefits:
        runtimeSettings.content?.membershipLevelBenefits && typeof runtimeSettings.content.membershipLevelBenefits === 'object'
          ? runtimeSettings.content.membershipLevelBenefits
          : DEFAULT_SETTINGS.content.membershipLevelBenefits,
      memberProfileBlocks:
        Array.isArray(runtimeSettings.content?.memberProfileBlocks) && runtimeSettings.content.memberProfileBlocks.length
          ? runtimeSettings.content.memberProfileBlocks
          : DEFAULT_SETTINGS.content.memberProfileBlocks,
    },
    design: {
      ...DEFAULT_SETTINGS.design,
      ...(runtimeSettings.design || {}),
      publicationTileImages: {
        ...DEFAULT_SETTINGS.design.publicationTileImages,
        ...(runtimeSettings.design?.publicationTileImages || {}),
      },
    },
    navigation: {
      topNavSections:
        runtimeSettings.navigation?.topNavSections || DEFAULT_SETTINGS.navigation.topNavSections,
      sidebarSections:
        runtimeSettings.navigation?.sidebarSections || DEFAULT_SETTINGS.navigation.sidebarSections,
    },
    layout: {
      homeSections:
        runtimeSettings.layout?.homeSections || DEFAULT_SETTINGS.layout.homeSections,
    },
  };
};
