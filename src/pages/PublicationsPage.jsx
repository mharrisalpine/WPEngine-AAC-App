import React from 'react';
import { motion } from 'framer-motion';
import { BookOpen, Download, ExternalLink, Headphones, Newspaper, Search } from 'lucide-react';
import { Link } from 'react-router-dom';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { getPublicationLibraryItems } from '@/lib/publications';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { isPartnerOrAboveMembershipTierId } from '@/lib/membershipTiers';
import { normalizeAccountInfo, normalizePrintDigitalPreference } from '@/lib/memberProfile';

const PUBLICATION_PREFERENCE_LABELS = {
  aaj: 'American Alpine Journal',
  anac: 'Accidents in North American Climbing',
  acj: 'American Climbing Journal',
  guidebook: 'Guidebook to Membership',
};

const PODCASTS = [
  {
    title: 'American Alpine Club Podcast',
    description: 'Conversations from the AAC community, with stories from climbers, writers, rescuers, and advocates.',
    spotifyEmbedUrl: 'https://open.spotify.com/embed/show/2Et22IIRdSjG94OEQXyofi?utm_source=generator&theme=0',
    listenUrl: 'https://americanalpineclub.org/podcast',
    learnUrl: 'https://americanalpineclub.org/podcast',
  },
  {
    title: 'Cutting Edge Podcast',
    description: 'A deeper look at recent climbs, expedition reports, and the stories behind the American Alpine Journal.',
    spotifyEmbedUrl: 'https://open.spotify.com/embed/show/6BUZB5EQyaDLlrdHig6Ssq?utm_source=generator&theme=0',
    listenUrl: 'https://americanalpineclub.org/cutting-edge',
    learnUrl: 'https://americanalpineclub.org/cutting-edge',
  },
];

const MORE_STORIES = [
  {
    title: 'The Line',
    imageUrl: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=800&q=80',
    url: 'https://americanalpineclub.org/news',
  },
  {
    title: 'AAC News',
    imageUrl: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80',
    url: 'https://americanalpineclub.org/news',
  },
  {
    title: 'Climbing Stories',
    imageUrl: 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=800&q=80',
    url: 'https://americanalpineclub.org/stories',
  },
  {
    title: 'Member Stories',
    imageUrl: 'https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=800&q=80',
    url: 'https://americanalpineclub.org/news',
  },
];

const getPublicationSelectionForItem = (accountInfo, itemId) => {
  const preferenceMap = {
    aaj: accountInfo?.aaj_pref,
    anac: accountInfo?.anac_pref,
    acj: accountInfo?.acj_pref,
    guidebook: accountInfo?.guidebook_pref,
  };

  return normalizePrintDigitalPreference(preferenceMap[itemId], 'Print');
};

const SectionHeader = ({ icon: Icon, eyebrow, title, description }) => (
  <div className="border-b-2 border-[#b71c1c] pb-4">
    <div className="flex items-start gap-3">
      <div className="pt-1 text-[#b71c1c]">
        <Icon className="h-5 w-5" />
      </div>
      <div>
        {eyebrow ? (
          <p className="mb-2 text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#b71c1c]">
            {eyebrow}
          </p>
        ) : null}
        <h1 className="text-3xl font-bold text-stone-950 sm:text-4xl">{title}</h1>
        {description ? <p className="mt-2 max-w-3xl text-sm leading-6 text-stone-600">{description}</p> : null}
      </div>
    </div>
  </div>
);

const PublicationsPage = () => {
  const { profile } = useAuth();
  const portalUiSettings = getPortalUiSettings();
  const portalContent = portalUiSettings.content;
  const portalDesign = portalUiSettings.design;
  const publicationItems = getPublicationLibraryItems();
  const accountInfo = normalizeAccountInfo(profile?.account_info || {});
  const canAccessPublications = isPartnerOrAboveMembershipTierId(profile?.profile_info?.tier);

  if (!canAccessPublications) {
    return (
      <div className="bg-white py-6">
        <motion.section
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
          className="bg-white py-6 text-center"
        >
          <div className="mx-auto flex max-w-xl flex-col items-center border-y-2 border-[#b71c1c] py-8">
            <div className="rounded-none bg-[#c8a43a]/18 p-3 text-[#6b5310]">
              <BookOpen className="h-5 w-5" />
            </div>
            <h1 className="mt-4 text-2xl font-bold text-stone-900">{portalContent.publications_locked_title}</h1>
            <p className="mt-2 text-sm leading-6 text-stone-600">
              {portalContent.publications_locked_description}
            </p>
            <Button
              asChild
              className="mt-5 rounded-none"
              style={{
                backgroundColor: portalDesign.primaryActionBackground,
                color: portalDesign.primaryActionText,
              }}
            >
              <Link to="/membership">{portalContent.publications_upgrade_button_label}</Link>
            </Button>
          </div>
        </motion.section>
      </div>
    );
  }

  return (
    <div className="bg-white py-6">
      <motion.div
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="space-y-10 bg-white"
      >
        <SectionHeader
          icon={BookOpen}
          eyebrow="Benefits > Books & Media"
          title="Books & Media"
          description="Open AAC digital publications, podcasts, and recent climbing stories from one member benefit page."
        />

        <section className="space-y-5 bg-white">
          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            {publicationItems.map((item) => {
              const selection = getPublicationSelectionForItem(accountInfo, item.id);
              const selectionLabel = PUBLICATION_PREFERENCE_LABELS[item.id] || item.title;

              return (
                <article key={item.id} className="aac-publication-card flex h-full flex-col bg-white">
                  <div className="flex h-[18rem] w-full shrink-0 items-center justify-center overflow-hidden bg-white p-3">
                    {item.imageUrl ? (
                      <img
                        src={item.imageUrl}
                        alt={`${item.title} cover`}
                        className="h-full w-full object-contain object-center"
                      />
                    ) : (
                      <div className="flex h-full w-full items-center justify-center border border-stone-200 bg-white text-[#b71c1c]">
                        <BookOpen className="h-10 w-10" />
                      </div>
                    )}
                  </div>
                  <div className="flex flex-1 flex-col border-t-2 border-[#b71c1c] py-4">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-stone-500">
                      {item.eyebrow}
                    </p>
                    <h2 className="mt-2 min-h-[3.25rem] text-lg font-bold leading-tight text-stone-900">{item.title}</h2>
                    <p className="mt-3 min-h-[2.5rem] text-sm font-semibold leading-5 text-[#b71c1c]">
                      {selectionLabel}: {selection}
                    </p>
                    <p className="mt-3 text-sm leading-6 text-stone-600 line-clamp-4">{item.description}</p>
                    <div className="mt-auto space-y-3 pt-4">
                      <Button asChild className="min-h-[2.75rem] w-full rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]">
                        <a href={item.href} target="_blank" rel="noreferrer">
                          Download PDF
                          <Download className="ml-2 h-4 w-4" />
                        </a>
                      </Button>
                      <Button asChild variant="outline" className="min-h-[2.75rem] w-full rounded-none border-stone-300 text-black hover:bg-stone-100">
                        <a href="https://publications.americanalpineclub.org/" target="_blank" rel="noreferrer">
                          Search articles
                          <Search className="ml-2 h-4 w-4" />
                        </a>
                      </Button>
                      <Button asChild variant="outline" className="min-h-[2.75rem] w-full rounded-none border-stone-300 text-black hover:bg-stone-100">
                        <a href="https://americanalpineclub.org/publications/" target="_blank" rel="noreferrer">
                          Learn more
                          <ExternalLink className="ml-2 h-4 w-4" />
                        </a>
                      </Button>
                    </div>
                  </div>
                </article>
              );
            })}
          </div>

          <div className="flex justify-center">
            <Button asChild variant="outline" className="min-h-[3rem] rounded-none border-[#b71c1c] px-8 text-[#8f1515] hover:bg-red-50">
              <Link to="/account">Change mailing preferences</Link>
            </Button>
          </div>
        </section>

        <section className="space-y-5 bg-white">
          <SectionHeader
            icon={Headphones}
            eyebrow="Audio"
            title="AAC Podcasts"
            description="Listen to AAC audio storytelling and long-form conversations from the climbing community."
          />
          <div className="grid gap-5 lg:grid-cols-2">
            {PODCASTS.map((podcast) => (
              <article key={podcast.title} className="flex h-full flex-col bg-white py-5">
                <div className="flex flex-col">
                  <h2 className="text-2xl font-bold text-stone-950">{podcast.title}</h2>
                  <p className="mt-2 text-sm leading-6 text-stone-600">{podcast.description}</p>
                  <div className="mt-4 overflow-hidden rounded-[12px] bg-[#121212]">
                    <iframe
                      title={`${podcast.title} Spotify player`}
                      src={podcast.spotifyEmbedUrl}
                      width="100%"
                      height="352"
                      allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                      loading="lazy"
                      className="block w-full border-0"
                    />
                  </div>
                  <div className="mt-auto grid gap-3 pt-4 sm:grid-cols-2">
                    <Button asChild className="rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]">
                      <a href={podcast.listenUrl} target="_blank" rel="noreferrer">AAC podcast page</a>
                    </Button>
                    <Button asChild variant="outline" className="rounded-none border-stone-300 text-black hover:bg-stone-100">
                      <a href={podcast.learnUrl} target="_blank" rel="noreferrer">Open on AAC</a>
                    </Button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="space-y-5 bg-white">
          <SectionHeader
            icon={Newspaper}
            eyebrow="Stories"
            title="More Stories From the AAC"
            description="Recent features, news, and dispatches from AAC writers and members."
          />
          <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            {MORE_STORIES.map((story) => (
              <a
                key={story.title}
                href={story.url}
                target="_blank"
                rel="noreferrer"
                className="group block bg-white text-black no-underline"
              >
                <div className="aspect-[4/3] overflow-hidden bg-stone-100">
                  <img src={story.imageUrl} alt="" className="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" />
                </div>
                <div className="border-t-2 border-[#b71c1c] py-3">
                  <h2 className="text-lg font-bold text-stone-950">{story.title}</h2>
                  <span className="mt-2 inline-flex items-center text-[0.72rem] font-bold uppercase tracking-[0.14em] text-[#8f1515]">
                    Read story
                    <ExternalLink className="ml-2 h-4 w-4" />
                  </span>
                </div>
              </a>
            ))}
          </div>
        </section>
      </motion.div>
    </div>
  );
};

export default PublicationsPage;
