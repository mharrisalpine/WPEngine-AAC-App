import { getPortalUiSettings } from '@/lib/portalSettings';

const DEFAULT_PUBLICATION_ITEMS = [
  {
    id: 'aaj',
    eyebrow: 'Annual',
    title: 'American Alpine Journal',
    href: 'https://aac-publications.s3.us-east-1.amazonaws.com/aaj/AAJ+2025.pdf',
    imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
    description:
      'Published since 1929, the American Alpine Journal (AAJ) is renowned as the most comprehensive worldwide source of information on long new climbs. AAC members receive a copy of the 368-page book in the fall. Members also can download a PDF copy at the link below. To opt out of receiving a print copy (and save resources), visit Account Preferences on your Member Profile.',
  },
  {
    id: 'anac',
    eyebrow: 'Annual',
    title: 'Accidents in North American Climbing',
    href: 'https://aac-publications.s3.us-east-1.amazonaws.com/ANAC+2025+Book_Digital_reduced.pdf',
    imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
    description:
      'Published annually since 1948, Accidents in North American Climbing documents the year’s most significant and teachable climbing accidents. AAC members will be mailed a copy of the book in the fall. Members also can download a PDF copy at the link below. To opt out of receiving a print copy (and save resources), visit Account Preferences on your Member Profile.',
  },
  {
    id: 'acj',
    eyebrow: 'Journal',
    title: 'American Climbing Journal',
    href: 'https://americanalpineclub.org/publications/',
    imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
    description:
      'American Climbing Journal access is being prepared for the member portal. Use the View button for the current placeholder destination while the final digital edition link is added.',
  },
  {
    id: 'guidebook',
    eyebrow: 'Quarterly',
    title: 'Guidebook to Membership',
    href: 'https://www.flipsnack.com/americanalpineclub/guidebook-xv/full-view.html',
    imageUrl: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
    description:
      "The Guidebook is a quarterly publication that features the human stories of the Club's work-how AAC community members are delving into accessibility, route development, advocacy, climbing expeditions, translating AAC books for international audiences, giving back to their local community, researching mountain ecosystems, and more! AAC Partner members receive print editions as well as digital access at the link below. Visit your settings to change your mailing preferences.",
  },
];

export function getPublicationLibraryItems() {
  const portalSettings = getPortalUiSettings();
  const publicationViewUrls = portalSettings.content?.publicationViewUrls || {};
  const publicationTileImages = portalSettings.design?.publicationTileImages || {};

  return DEFAULT_PUBLICATION_ITEMS.map((item) => ({
    ...item,
    href: publicationViewUrls[item.id] || item.href,
    imageUrl: publicationTileImages[item.id] || item.imageUrl || '',
  }));
}
