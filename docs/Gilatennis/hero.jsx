// Magazine-style hero with featured gear overlay
function Hero() {
  return (
    <section className="border-b border-black/10 dark:border-white/10">
      <div className="max-w-[1400px] mx-auto px-6 pt-8 pb-14 lg:pt-12 lg:pb-20">
        {/* Eyebrow row */}
        <div className="flex items-end justify-between mb-6">
          <div className="flex items-center gap-3 text-[11px] uppercase tracking-[0.25em]">
            <span className="px-2 py-1 bg-coral text-white font-bold">This Week</span>
            <span className="text-neutral-500">Issue No. 142 · April 26</span>
          </div>
          <a href="#" className="hidden md:flex items-center gap-2 text-[11px] uppercase tracking-[0.25em] hover:text-coral">
            View archive <IconArrowUpRight size={14}/>
          </a>
        </div>

        <div className="grid lg:grid-cols-12 gap-6 lg:gap-8">
          {/* Hero feature */}
          <article className="lg:col-span-8 group">
            <div className="relative">
              <Placeholder
                label="hero · alcaraz forehand at madrid · 16:10"
                aspect="16/10"
                tone="dark"
                className="w-full"
              >
                {/* Top tag */}
                <div className="absolute top-5 left-5 flex items-center gap-2">
                  <span className="px-2.5 py-1 bg-white text-black text-[10px] font-bold uppercase tracking-[0.2em]">Gear Review</span>
                  <span className="px-2.5 py-1 bg-black/40 text-white text-[10px] font-bold uppercase tracking-[0.2em] backdrop-blur-sm">8 min read</span>
                </div>

                {/* Title overlay */}
                <div className="absolute inset-x-0 bottom-0 p-5 sm:p-6 lg:p-10 bg-gradient-to-t from-black/90 via-black/50 to-transparent">
                  <h1 className="font-display font-black text-white text-[26px] sm:text-[44px] lg:text-[72px] leading-[0.95] sm:leading-[0.92] tracking-[-0.03em] max-w-3xl">
                    The racket that's <em className="not-italic text-coral">redefining</em> control on clay.
                  </h1>
                  <p className="mt-3 sm:mt-4 text-white/80 max-w-xl text-[13px] sm:text-[15px] leading-relaxed line-clamp-2 sm:line-clamp-none">
                    We strung, tested and beat up the new Aero Pro X for six weeks across three surfaces. Here's what we found — and who should actually buy it.
                  </p>
                  <div className="mt-4 sm:mt-6 flex items-center gap-3 sm:gap-4 text-white/70 text-[11px] sm:text-xs">
                    <span className="flex items-center gap-2">
                      <span className="h-7 w-7 rounded-full bg-white/20"/> by M. Alvarez
                    </span>
                    <span>·</span>
                    <span className="flex items-center gap-1.5"><IconClock size={13}/> 2h ago</span>
                  </div>
                </div>

                {/* Featured gear card overlay */}
                <div className="absolute right-5 top-5 w-[260px] hidden md:block">
                  <div className="bg-white dark:bg-neutral-950 shadow-2xl border-t-2 border-coral">
                    <div className="p-4">
                      <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-coral mb-2">Featured Gear</div>
                      <Placeholder label="racket" aspect="4/3" tone="light" className="mb-3"/>
                      <div className="font-display font-bold text-[15px] leading-tight">Aero Pro X 100 · 305g</div>
                      <div className="mt-1 text-xs text-neutral-500">Reviewed in this article</div>
                      <div className="mt-3 flex items-baseline justify-between">
                        <div>
                          <span className="font-display font-black text-xl">$249</span>
                          <span className="ml-2 text-xs line-through text-neutral-400">$279</span>
                        </div>
                        <span className="flex items-center gap-0.5 text-coral">
                          {[...Array(5)].map((_,i)=><IconStar key={i} size={11} fill="currentColor"/>)}
                        </span>
                      </div>
                      <button className="mt-3 w-full h-10 bg-black dark:bg-white text-white dark:text-black text-xs font-bold uppercase tracking-[0.18em] hover:bg-coral hover:dark:bg-coral hover:text-white flex items-center justify-center gap-2">
                        Shop now <IconArrow size={14}/>
                      </button>
                    </div>
                  </div>
                </div>
              </Placeholder>
            </div>
          </article>

          {/* Sidebar stack */}
          <aside className="lg:col-span-4 flex flex-col gap-6">
            <SideStory
              kicker="Tennis Tip"
              title="Five drills to fix a flat forehand in one practice."
              meta="Coach Reyes · 6 min"
            />
            <SideStory
              kicker="Pro Watch"
              title="Why Świątek's slice is suddenly her best shot."
              meta="The Editors · 4 min"
              tone="light"
            />
            <SideStory
              kicker="Buyer's Guide"
              title="Best tennis shoes for wide feet in 2026."
              meta="Gear desk · 9 min"
            />
          </aside>
        </div>

        {/* Scroll-cue stat strip */}
        <div className="mt-12 grid grid-cols-2 md:grid-cols-4 border-t border-black/10 dark:border-white/10 pt-8">
          {[
            ["1,200+", "Gear reviews"],
            ["340", "Active tournaments"],
            ["48k", "Community members"],
            ["Free", "Returns to 60 days"],
          ].map(([n, l]) => (
            <div key={l} className="px-2">
              <div className="font-display font-black text-3xl tracking-[-0.02em]">{n}</div>
              <div className="text-xs uppercase tracking-[0.2em] text-neutral-500 mt-1">{l}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function SideStory({ kicker, title, meta, tone = "dark" }) {
  return (
    <a href="#" className="group flex gap-4 items-start">
      <Placeholder label="image" aspect="1/1" tone={tone} className="w-28 shrink-0"/>
      <div className="flex-1 min-w-0">
        <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-coral">{kicker}</div>
        <h3 className="mt-1.5 font-display font-bold text-[17px] leading-[1.15] tracking-[-0.01em] group-hover:text-coral">
          {title}
        </h3>
        <div className="mt-2 text-[11px] uppercase tracking-[0.18em] text-neutral-500">{meta}</div>
      </div>
    </a>
  );
}

window.Hero = Hero;
