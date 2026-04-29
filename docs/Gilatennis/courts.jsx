// Featured Courts section — community hub
const COURTS = [
{
  name: "Memorial Park Tennis Center",
  city: "Houston, TX",
  surface: "Hard",
  rating: 4.8,
  reviews: 312,
  type: "Video Review",
  duration: "6:42",
  img: "court · houston · twilight",
  amenities: ["Lights", "Pro Shop", "Stringing"],
  excerpt: "24 lit hard courts, walk-on friendly, the gold standard for public play in Texas."
},
{
  name: "Riverside Clay Club",
  city: "Asheville, NC",
  surface: "Clay",
  rating: 4.9,
  reviews: 188,
  type: "Article",
  duration: "5 min",
  img: "court · red clay · mountain backdrop",
  amenities: ["Lights", "Locker Room", "Cafe"],
  excerpt: "Hand-rolled red clay courts maintained daily — a rarity in the Southeast."
},
{
  name: "Coastline Grass Pavilion",
  city: "Newport, RI",
  surface: "Grass",
  rating: 4.7,
  reviews: 94,
  type: "Video Review",
  duration: "8:10",
  img: "court · grass · seaside",
  amenities: ["Pro Shop", "Coaching", "Member Only"],
  excerpt: "Six championship-grade grass courts. We were skeptical of public access — pleasantly surprised."
},
{
  name: "Sunset Indoor Athletics",
  city: "Portland, OR",
  surface: "Hard",
  rating: 4.6,
  reviews: 256,
  type: "Article",
  duration: "7 min",
  img: "court · indoor · daylight roof",
  amenities: ["Indoor", "Lights", "Cafe", "Stringing"],
  excerpt: "Year-round indoor play under translucent roof panels. Best winter option on the West Coast."
}];


const COURT_GREEN = "oklch(64.7% .217 57.6)";

function FeaturedCourts() {
  return (
    <section id="courts" className="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950">
      <div className="max-w-[1400px] mx-auto px-6 py-16 lg:py-24">
        <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
          <div>
            <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] mb-3" style={{ color: COURT_GREEN }}>
              <IconMapPin size={14} /> Featured Courts
            </div>
            <h2 className="font-display font-black text-4xl lg:text-6xl tracking-[-0.03em] leading-[0.95] max-w-2xl">
              The places <em className="not-italic" style={{ color: COURT_GREEN }}>worth driving</em> for.
            </h2>
            <p className="mt-4 text-neutral-600 dark:text-neutral-400 max-w-xl">
              Court reviews from our staff and community — surface quality, lighting, amenities, and the vibe you'll actually feel when you show up.
            </p>
          </div>
          <a href="#" className="self-start inline-flex items-center gap-2 px-5 h-11 border text-xs uppercase tracking-[0.2em] font-bold hover:text-white transition-colors"
          style={{ borderColor: COURT_GREEN, color: COURT_GREEN }}
          onMouseEnter={(e) => {e.currentTarget.style.background = COURT_GREEN;e.currentTarget.style.color = 'white';}}
          onMouseLeave={(e) => {e.currentTarget.style.background = 'transparent';e.currentTarget.style.color = COURT_GREEN;}}>
            Submit a court <IconArrowUpRight size={14} />
          </a>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-12">
          {COURTS.map((c, i) => <CourtCard key={i} c={c} />)}
        </div>

        <div className="mt-14 flex justify-center">
          <button className="group inline-flex items-center gap-3 px-7 h-12 text-sm font-bold uppercase tracking-[0.2em] text-white transition-colors"
          style={{ backgroundColor: COURT_GREEN }}>
            Browse all 240+ courts
            <IconArrow size={16} className="group-hover:translate-x-1 transition-transform" />
          </button>
        </div>
      </div>
    </section>);

}

function CourtCard({ c }) {
  const isVideo = c.type === "Video Review";
  return (
    <a href="#" className="group flex flex-col">
      <div className="relative" style={{ borderTop: `2px solid ${COURT_GREEN}` }}>
        <Placeholder label={c.img} aspect="4/3" tone="dark" className="w-full">
          {/* Surface badge top-left */}
          <div className="absolute top-3 left-3 flex items-center gap-1.5">
            <span className="px-2 py-1 bg-white text-black text-[10px] font-bold uppercase tracking-[0.18em]">
              {c.surface}
            </span>
            <span className="px-2 py-1 text-white text-[10px] font-bold uppercase tracking-[0.18em]" style={{ backgroundColor: COURT_GREEN }}>
              {c.type === "Video Review" ? "Video" : "Read"}
            </span>
          </div>

          {/* Video play / duration */}
          {isVideo ?
          <div className="absolute inset-0 grid place-items-center pointer-events-none">
              <div className="h-14 w-14 rounded-full bg-white/90 grid place-items-center text-black group-hover:scale-110 transition-transform">
                <IconPlay size={20} />
              </div>
            </div> :
          null}
          <div className="absolute bottom-3 right-3 px-2 py-0.5 bg-black/60 backdrop-blur-sm text-white text-[10px] font-mono">
            {c.duration}
          </div>
        </Placeholder>
      </div>

      <div className="pt-5">
        <div className="flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-neutral-500">
          <IconMapPin size={11} /> {c.city}
        </div>
        <h3 className="mt-2 font-display font-bold text-xl leading-[1.1] tracking-[-0.015em] group-hover:text-coral transition-colors">
          {c.name}
        </h3>
        <p className="mt-2 text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed line-clamp-2">{c.excerpt}</p>

        {/* Amenity badges */}
        <div className="mt-4 flex flex-wrap gap-1.5">
          {c.amenities.map((a) =>
          <span key={a} className="px-2 py-1 text-[10px] font-bold uppercase tracking-[0.15em] border"
          style={{ borderColor: `color-mix(in oklch, ${COURT_GREEN} 40%, transparent)`, color: COURT_GREEN }}>
              {a}
            </span>
          )}
        </div>

        {/* Rating + CTA */}
        <div className="mt-5 flex items-center justify-between">
          <span className="flex items-center gap-1 text-[11px] text-neutral-500">
            <IconStar size={11} fill="currentColor" className="text-coral" />
            <span className="font-bold text-neutral-700 dark:text-neutral-300">{c.rating}</span>
            <span>· {c.reviews}</span>
          </span>
          <span className="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-coral group-hover:gap-2.5 transition-all">
            Read review <IconArrow size={12} />
          </span>
        </div>
      </div>
    </a>);

}

window.FeaturedCourts = FeaturedCourts;