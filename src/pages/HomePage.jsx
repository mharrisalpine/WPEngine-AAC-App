import React from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { ArrowRight, BookOpen, CalendarDays, HeartHandshake, Mountain, ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { getPortalUiSettings } from '@/lib/portalSettings';
import grandTetonHero from '@/assets/grand-teton-hero.jpg';

const accentClassMap = {
  gold: 'bg-[#f8c235] text-black',
  light: 'bg-white text-black border border-stone-200',
  sand: 'bg-[#efe5d4] text-black border border-stone-200',
  dark: 'bg-black text-white',
};

const iconRegistry = [HeartHandshake, CalendarDays, Mountain];

const isInternalHref = (href) => typeof href === 'string' && href.startsWith('/') && !href.startsWith('//');

const HOME_HERO_TITLE = 'United\nWe Climb.';
const HomePage = () => {
  const portalUi = getPortalUiSettings();
  const portalContent = portalUi.content;
  const portalDesign = portalUi.design;
  const homeHeroVideoUrl = portalDesign.homeHeroVideoUrl;
  const homeSections = (portalUi.layout?.homeSections || []).map((section) => section.id);
  const involvementCards = portalContent.homeInvolvementCards || [];
  const publicationCards = portalContent.homePublicationCards || [];
  const partnerLogos = portalContent.homePartnerLogos || [];


  return (
    <>
      <Helmet>
        <title>Home - American Alpine Club</title>
        <meta
          name="description"
          content="Explore AAC membership, publications, store highlights, and ways to get involved from the member portal home page."
        />
      </Helmet>

      <div className="pb-6 pt-0">
        {homeSections.includes('hero') ? (
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="relative min-h-[100svh] overflow-hidden bg-[#030000] text-white"
          >
            {homeHeroVideoUrl ? (
              <div className="absolute inset-0">
                <iframe
                  title="AAC homepage hero video"
                  src={homeHeroVideoUrl}
                  className="pointer-events-none absolute inset-0 h-full w-full scale-[1.32] transform-gpu"
                  frameBorder="0"
                  allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
                  referrerPolicy="strict-origin-when-cross-origin"
                  allowFullScreen
                />
              </div>
            ) : (
              <img
                src={grandTetonHero}
                alt=""
                aria-hidden="true"
                className="absolute inset-0 h-full w-full object-cover object-top"
              />
            )}
            <div className="absolute inset-0" style={{ background: portalDesign.homeHeroOverlay }} />
            <div className="absolute inset-0" style={{ background: portalDesign.homeHeroTintOverlay }} />

            <div className="relative flex min-h-[100svh] items-end px-4 pb-12 pt-[calc(var(--aac-portal-header-height)+1.5rem)] sm:px-6 sm:pb-16 sm:pt-[calc(var(--aac-portal-header-height)+2rem)] lg:px-10 xl:px-14 xl:pb-20 xl:pt-[calc(var(--aac-portal-header-height)+2.5rem)]">
              <div className="flex w-full items-end">
                <div className="w-full max-w-4xl">
                  <div
                    className="max-w-[42rem] px-1 py-1 sm:px-0 sm:py-0"
                  >
                    <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">{portalContent.home_hero_kicker}</p>
                    <h1 className="mt-3 max-w-[38rem] whitespace-pre-line text-[4.4rem] leading-[0.92] text-white sm:text-[5.4rem] lg:text-[6.6rem] xl:text-[7.2rem]">
                      {HOME_HERO_TITLE}
                    </h1>
                    <p className="mt-5 max-w-[38rem] text-lg leading-8 text-white/88 sm:text-[1.32rem]">
                      {portalContent.home_hero_description}
                    </p>

                    <div className="mt-8 flex flex-wrap gap-3">
                      <Button
                        asChild
                        className="min-h-[3rem] rounded-none px-6 text-sm font-semibold uppercase tracking-[0.16em]"
                        style={{
                          backgroundColor: portalDesign.secondaryActionBackground,
                          color: portalDesign.secondaryActionText,
                        }}
                      >
                        {isInternalHref(portalContent.home_primary_cta_url) ? (
                          <Link to={portalContent.home_primary_cta_url}>{portalContent.home_primary_cta_label}</Link>
                        ) : (
                          <a href={portalContent.home_primary_cta_url}>{portalContent.home_primary_cta_label}</a>
                        )}
                      </Button>
                      <a
                        href={portalContent.home_secondary_cta_url}
                        className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-white px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-white hover:text-black"
                      >
                        {portalContent.home_secondary_cta_label}
                      </a>
                      <a
                        href={portalContent.home_tertiary_cta_url}
                        className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-white/18 bg-white/[0.04] px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:border-[#f8c235] hover:text-[#f8c235]"
                      >
                        {portalContent.home_tertiary_cta_label}
                      </a>
                    </div>
                  </div>

                  <div className="mt-5 max-w-sm px-0 py-0">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.26em] text-[#f8c235]">
                      {portalContent.home_membership_chip_kicker}
                    </p>
                    <p className="mt-2 text-sm leading-6 text-white/84">
                      {portalContent.home_membership_chip_description}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </motion.section>
        ) : null}

        <div className="mx-auto max-w-7xl space-y-8">
          {homeSections.includes('intro') ? (
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.04 }}
            className="grid gap-6 lg:grid-cols-[minmax(0,0.95fr),minmax(0,1.05fr)]"
          >
            <div className="relative overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,0.10)]">
              <img src={portalDesign.homeIntroImageUrl} alt="AAC climbers" className="h-full w-full object-cover" />
              <div className="absolute bottom-4 right-4 hidden w-40 overflow-hidden rounded-[1.25rem] border border-white/60 shadow-xl lg:block">
                <img src={portalDesign.homeIntroAccentImageUrl} alt="AAC climber silhouette" className="h-full w-full object-cover" />
              </div>
            </div>
            <div className="card-gradient rounded-[2rem] border border-stone-200/80 p-6 sm:p-8">
              <div className="flex items-start gap-3">
                <div className="rounded-2xl bg-[#f8c235]/18 p-3 text-[#6b5310]">
                  <Mountain className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">{portalContent.home_intro_kicker}</p>
                  <h2 className="mt-2 text-3xl font-bold text-stone-900">{portalContent.home_intro_title}</h2>
                </div>
              </div>
              <p className="mt-5 text-base leading-7 text-stone-700">
                {portalContent.home_intro_description}
              </p>
              <p className="mt-4 text-base leading-7 text-stone-700">
                {portalContent.home_intro_secondary_description}
              </p>
              <div className="mt-6">
                <Button
                  asChild
                  className="rounded-none px-6"
                  style={{
                    backgroundColor: portalDesign.secondaryActionBackground,
                    color: portalDesign.secondaryActionText,
                  }}
                >
                  <a href={portalContent.home_intro_button_url}>
                    {portalContent.home_intro_button_label}
                    <ArrowRight className="ml-2 h-4 w-4" />
                  </a>
                </Button>
              </div>
            </div>
          </motion.section>
          ) : null}

          {homeSections.includes('involvement') ? (
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.08 }}
            className="rounded-[2rem] border border-stone-200/80 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">{portalContent.home_involvement_kicker}</p>
                <h2 className="mt-2 text-3xl font-bold text-stone-900">{portalContent.home_involvement_title}</h2>
              </div>
              <Button
                asChild
                className="rounded-none px-6"
                style={{
                  backgroundColor: portalDesign.primaryActionBackground,
                  color: portalDesign.primaryActionText,
                }}
              >
                {isInternalHref(portalContent.home_involvement_button_url) ? (
                  <Link to={portalContent.home_involvement_button_url}>{portalContent.home_involvement_button_label}</Link>
                ) : (
                  <a href={portalContent.home_involvement_button_url}>{portalContent.home_involvement_button_label}</a>
                )}
              </Button>
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-3">
              {involvementCards.map((card, index) => {
                const Icon = iconRegistry[index] || HeartHandshake;
                const cta = isInternalHref(card.buttonUrl) ? (
                  <Button asChild className="w-full rounded-none px-5">
                    <Link to={card.buttonUrl}>{card.buttonLabel}</Link>
                  </Button>
                ) : (
                  <a
                    href={card.buttonUrl}
                    className={`inline-flex w-full min-h-[3rem] items-center justify-center rounded-none px-5 text-sm font-semibold uppercase tracking-[0.16em] transition-colors ${accentClassMap[card.accentStyle] || accentClassMap.gold}`}
                  >
                    {card.buttonLabel}
                  </a>
                );

                return (
                  <article
                    key={`${card.title}-${index}`}
                    className="flex h-full flex-col overflow-hidden rounded-[1.7rem] border border-stone-200 bg-[#f8f4eb]"
                  >
                    {card.imageUrl ? (
                      <div className="aspect-[1.25] overflow-hidden bg-stone-200">
                        <img src={card.imageUrl} alt={card.title} className="h-full w-full object-cover" />
                      </div>
                    ) : null}
                    <div className="flex flex-1 flex-col p-5">
                      <div className="rounded-2xl bg-black/5 p-3 text-stone-900">
                        <Icon className="h-5 w-5" />
                      </div>
                      <h3 className="mt-4 text-2xl font-bold text-stone-900">{card.title}</h3>
                      <p className="mt-3 flex-1 text-sm leading-6 text-stone-700">{card.description}</p>
                      <div className="mt-5">{cta}</div>
                    </div>
                  </article>
                );
              })}
            </div>
          </motion.section>
          ) : null}

          {homeSections.includes('publications') ? (
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.12 }}
            className="rounded-[2rem] border border-stone-200/80 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8"
          >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">{portalContent.home_publications_kicker}</p>
                <h2 className="mt-2 text-3xl font-bold text-stone-900">{portalContent.home_publications_title}</h2>
              </div>
              <a
                href={portalContent.home_publications_button_url}
                className="inline-flex min-h-[3rem] items-center justify-center rounded-none border border-[#f8c235] bg-[#f8c235] px-6 text-sm font-semibold uppercase tracking-[0.16em] text-black transition-colors hover:bg-[#ddb01d]"
              >
                {portalContent.home_publications_button_label}
              </a>
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-2">
              {publicationCards.map((card) => (
                <a
                  key={card.title}
                  href={card.buttonUrl}
                  className="group grid overflow-hidden rounded-[1.7rem] border border-stone-200 bg-[#0d0a09] text-white shadow-[0_20px_48px_rgba(3,0,0,0.18)] md:grid-cols-[minmax(0,1fr),220px]"
                >
                  <div className="flex flex-col justify-center p-6">
                    <div
                      className="inline-flex w-fit items-center rounded-none px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.2em]"
                      style={{ backgroundColor: `${card.accentColor}22`, color: card.accentColor }}
                    >
                      <BookOpen className="mr-2 h-3.5 w-3.5" />
                      Publication
                    </div>
                    <h3 className="mt-4 text-2xl font-bold text-white">{card.title}</h3>
                    <p className="mt-3 text-sm leading-6 text-white/72">{card.description}</p>
                    <span className="mt-5 inline-flex items-center text-sm font-semibold uppercase tracking-[0.16em]" style={{ color: card.accentColor }}>
                      {card.buttonLabel}
                      <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </span>
                  </div>
                  <div className="min-h-[240px] overflow-hidden bg-stone-900">
                    <img src={card.imageUrl} alt={card.title} className="h-full w-full object-cover" />
                  </div>
                </a>
              ))}
            </div>
          </motion.section>
          ) : null}

          {(homeSections.includes('store') || homeSections.includes('partners')) ? (
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.16 }}
            className="grid gap-6 lg:grid-cols-[minmax(0,0.85fr),minmax(0,1.15fr)]"
          >
            {homeSections.includes('store') ? (
            <article className="overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,0.08)]">
              <div className="aspect-[1.05] overflow-hidden">
                <img src={portalDesign.homeStoreImageUrl} alt="AAC Store featured product" className="h-full w-full object-cover" />
              </div>
              <div className="p-6">
                <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">{portalContent.home_store_kicker}</p>
                <h2 className="mt-2 text-3xl font-bold text-stone-900">{portalContent.home_store_title}</h2>
                <p className="mt-4 text-sm leading-6 text-stone-700">
                  {portalContent.home_store_description}
                </p>
                <div className="mt-6">
                  <a
                    href={portalContent.home_store_button_url}
                    className="inline-flex min-h-[3rem] items-center justify-center rounded-none bg-black px-6 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-[#1f1a18]"
                  >
                    <ShoppingBag className="mr-2 h-4 w-4" />
                    {portalContent.home_store_button_label}
                  </a>
                </div>
              </div>
            </article>
            ) : <div />}

            {homeSections.includes('partners') ? (
            <article className="card-gradient rounded-[2rem] border border-stone-200/80 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-8">
              <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-[#8f1515]">{portalContent.home_partners_kicker}</p>
              <h2 className="mt-2 text-3xl font-bold text-stone-900">{portalContent.home_partners_title}</h2>
              <p className="mt-4 max-w-2xl text-sm leading-6 text-stone-700">
                {portalContent.home_partners_description}
              </p>

              <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {partnerLogos.map((logo, index) => (
                  logo.linkUrl ? (
                    <a
                      key={`${logo.name}-${index}`}
                      href={logo.linkUrl}
                      className="flex min-h-[120px] items-center justify-center rounded-[1.4rem] border border-stone-200 bg-white px-6 py-5"
                    >
                      <img src={logo.imageUrl} alt={logo.name} className="max-h-12 w-auto object-contain" />
                    </a>
                  ) : (
                    <div
                      key={`${logo.name}-${index}`}
                      className="flex min-h-[120px] items-center justify-center rounded-[1.4rem] border border-stone-200 bg-white px-6 py-5"
                    >
                      <img src={logo.imageUrl} alt={logo.name} className="max-h-12 w-auto object-contain" />
                    </div>
                  )
                ))}
              </div>
            </article>
            ) : <div />}
          </motion.section>
          ) : null}
        </div>
      </div>
    </>
  );
};

export default HomePage;
