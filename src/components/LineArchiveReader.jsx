import React, { useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { ExternalLink, Newspaper } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { openExternalUrl } from '@/lib/mobileNavigation';

const stories = [
  {
    id: 'baspa',
    title: 'The Line — From Bozeman to the Baspa',
    date: 'February 18, 2026',
    excerpt: 'A Montana team heads to northern India and finds unclimbed peaks and varied alpine rock above Rakchham.',
    image: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=80',
    url: 'https://americanalpineclub.org/news/2026/2/18/the-linefrom-bozeman-to-the-baspa',
  },
  {
    id: 'kei',
    title: 'The Line: A Climb for Kei Taniguchi',
    date: 'August 21, 2025',
    excerpt: 'A tribute ascent on Pandra in Nepal honors Kei Taniguchi through steep ice, bivouacs, and unfinished dreams.',
    image: 'https://images.unsplash.com/photo-1517825738774-7de9363ef735?auto=format&fit=crop&w=1200&q=80',
    url: 'https://americanalpineclub.org/news/2025/8/12/the-line',
  },
  {
    id: 'karakoram',
    title: 'The Line: News From the Cascades to the Karakoram',
    date: 'February 27, 2025',
    excerpt: 'Winter Sloan Peak conditions, bold route choices, and a sharp snapshot of fast-moving alpine progress.',
    image: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80',
    url: 'https://americanalpineclub.org/news/2025/2/25/the-line-from-the-cascades-to-the-karakoram',
  },
  {
    id: 'global-ambition',
    title: 'The Line: Global Ambition',
    date: 'August 21, 2024',
    excerpt: 'A geographic mystery in Uzbekistan turns into a first ascent with expedition strategy and mountain history in the mix.',
    image: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=80',
    url: 'https://americanalpineclub.org/news/2024/8/19/the-line-the-monthly-newsletter-of-the-aaj',
  },
  {
    id: 'kichatna',
    title: 'The Line — Kichatna Special',
    date: 'March 11, 2024',
    excerpt: 'Four first ascents in Alaska’s Kichatna Mountains make for an expedition-heavy special issue from the AAJ team.',
    image: 'https://images.unsplash.com/photo-1464823063530-08f10ed1a2dd?auto=format&fit=crop&w=1200&q=80',
    url: 'https://americanalpineclub.org/news/2024/3/8/the-line-kichatna-special',
  },
];

const LineArchiveReader = ({ compactOnly = false }) => {
  const [activeStoryId, setActiveStoryId] = useState(stories[0].id);
  const activeStory = useMemo(
    () => stories.find((story) => story.id === activeStoryId) || stories[0],
    [activeStoryId]
  );

  return (
    <section className="space-y-6">
      <div className="flex items-center gap-3">
        <div className="rounded-full bg-[#c8a43a] p-3 text-black">
          <Newspaper className="w-5 h-5" />
        </div>
        <div>
          <h2 className="text-3xl font-bold text-black">The Line Reader</h2>
          <p className="text-black/75">A Flipboard-style reader featuring recent stories from the AAC’s Line archive.</p>
        </div>
      </div>

      <div className="grid gap-6 xl:grid-cols-[0.95fr,1.4fr]">
        <div className={`space-y-4 ${compactOnly ? 'xl:col-span-2' : ''}`}>
          {stories.map((story) => {
            const isActive = story.id === activeStory.id;
            return (
              <button
                key={story.id}
                type="button"
                onClick={() => setActiveStoryId(story.id)}
                className={`w-full text-left rounded-[24px] overflow-hidden border transition-all ${
                  isActive
                    ? 'border-[#c8a43a] bg-white shadow-[0_18px_40px_rgba(0,0,0,0.08)]'
                    : 'border-stone-200 bg-white/70 hover:bg-white'
                }`}
              >
                <div className="aspect-[16/9] overflow-hidden">
                  <img src={story.image} alt={story.title} className="w-full h-full object-cover" />
                </div>
                <div className="p-5">
                  <p className="text-xs uppercase tracking-[0.25em] text-[#c8a43a] mb-2">{story.date}</p>
                  <h3 className="text-lg font-bold text-black mb-2">{story.title}</h3>
                  <p className="text-sm text-black/70">{story.excerpt}</p>
                  <div className="mt-4">
                    <span
                      className="inline-flex items-center gap-2 text-sm font-medium text-[#6b5310]"
                      onClick={(event) => {
                        event.stopPropagation();
                        openExternalUrl(story.url);
                      }}
                    >
                      <ExternalLink className="w-4 h-4" />
                      Read Story
                    </span>
                  </div>
                </div>
              </button>
            );
          })}
        </div>

        {!compactOnly && (
          <motion.div
            key={activeStory.id}
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35 }}
            className="card-gradient rounded-[28px] border border-stone-200 p-5 md:p-6"
          >
            <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-5">
              <div>
                <p className="text-xs uppercase tracking-[0.25em] text-[#c8a43a] mb-2">{activeStory.date}</p>
                <h3 className="text-3xl font-bold text-black">{activeStory.title}</h3>
                <p className="text-black/75 mt-3 max-w-2xl">{activeStory.excerpt}</p>
              </div>
              <Button
                type="button"
                variant="secondary"
                className="shrink-0"
                onClick={() => openExternalUrl(activeStory.url)}
              >
                <ExternalLink className="w-4 h-4 mr-2" />
                Open Full Story
              </Button>
            </div>

            <div className="rounded-[24px] overflow-hidden border border-[rgba(255,255,255,0.12)] bg-black">
              <iframe
                src={activeStory.url}
                title={activeStory.title}
                className="w-full h-[720px] border-0"
                loading="lazy"
              />
            </div>
          </motion.div>
        )}
      </div>
    </section>
  );
};

export default LineArchiveReader;
