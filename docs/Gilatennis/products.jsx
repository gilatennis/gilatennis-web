// Trending Gear product grid + Trust bar
const PRODUCTS = [
  { name: "Aero Pro X 100", cat: "Racket", price: 249, was: 279, badge: "Hot", rating: 4.8, count: 412, img: "racket · aero" },
  { name: "Court Speed 5", cat: "Shoes", price: 139, badge: "New", rating: 4.6, count: 188, img: "shoe · court speed" },
  { name: "Hyper-G Soft 1.25", cat: "String", price: 18, was: 22, rating: 4.9, count: 1840, img: "string reel" },
  { name: "Tour Bag 12-Pack", cat: "Bag", price: 159, badge: "Bestseller", rating: 4.7, count: 622, img: "bag · 12-pack" },
  { name: "Pro Staff 97 v14", cat: "Racket", price: 259, rating: 4.7, count: 301, img: "racket · pro staff" },
  { name: "Mirage Clay Pro", cat: "Shoes", price: 139, badge: "Editor's Pick", rating: 4.9, count: 245, img: "shoe · clay" },
  { name: "Polyester Hex 1.20", cat: "String", price: 16, was: 19, rating: 4.5, count: 980, img: "string · hex" },
  { name: "Performance Polo", cat: "Apparel", price: 65, rating: 4.6, count: 410, img: "polo · navy" },
];

function TrendingGear() {
  const [tab, setTab] = React.useState("all");
  const cats = ["all", "Racket", "Shoes", "String", "Apparel"];
  const list = tab === "all" ? PRODUCTS : PRODUCTS.filter(p => p.cat === tab);

  return (
    <section className="bg-neutral-100 dark:bg-neutral-900 border-b border-black/10 dark:border-white/10">
      <div className="max-w-[1400px] mx-auto px-6 py-16 lg:py-24">
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
          <div>
            <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-3">
              <IconFire size={14}/> Trending This Week
            </div>
            <h2 className="font-display font-black text-4xl lg:text-5xl tracking-[-0.03em] leading-[0.95]">
              Gear our community is buying.
            </h2>
          </div>
          <div className="flex items-center gap-1.5 overflow-x-auto -mx-6 px-6 md:mx-0 md:px-0">
            {cats.map(c => (
              <button
                key={c}
                onClick={() => setTab(c)}
                className={`shrink-0 px-4 h-9 text-xs uppercase tracking-[0.18em] font-bold transition-colors ${
                  tab === c
                    ? "bg-black text-white dark:bg-white dark:text-black"
                    : "text-neutral-600 dark:text-neutral-400 hover:text-coral"
                }`}
              >
                {c === "all" ? "All Gear" : c}
              </button>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
          {list.map((p, i) => <ProductCard key={i} p={p}/>)}
        </div>
      </div>
    </section>
  );
}

function ProductCard({ p }) {
  const [hover, setHover] = React.useState(false);
  return (
    <div
      className="group bg-white dark:bg-neutral-950 flex flex-col"
      onMouseEnter={()=>setHover(true)}
      onMouseLeave={()=>setHover(false)}
    >
      <div className="relative">
        <Placeholder label={p.img} aspect="1/1" tone="light" className="w-full"/>
        {p.badge && (
          <span className="absolute top-3 left-3 px-2 py-1 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.18em]">
            {p.badge}
          </span>
        )}
        <button className="absolute top-3 right-3 h-9 w-9 grid place-items-center bg-white/90 dark:bg-neutral-950/90 hover:bg-coral hover:text-white transition-colors">
          <IconHeart size={15}/>
        </button>
        <button className={`absolute inset-x-3 bottom-3 h-10 bg-black text-white text-xs font-bold uppercase tracking-[0.2em] flex items-center justify-center gap-2 transition-all ${hover ? "translate-y-0 opacity-100" : "translate-y-2 opacity-0 pointer-events-none"}`}>
          Quick add <IconBag size={14}/>
        </button>
      </div>
      <div className="p-4">
        <div className="text-[10px] uppercase tracking-[0.2em] text-neutral-500">{p.cat}</div>
        <div className="mt-1 font-display font-bold text-[15px] leading-tight">{p.name}</div>
        <div className="mt-2 flex items-center gap-1.5 text-[11px] text-neutral-500">
          <span className="flex items-center gap-0.5 text-coral">
            <IconStar size={11} fill="currentColor"/> <span className="text-neutral-700 dark:text-neutral-300 font-bold">{p.rating}</span>
          </span>
          <span>· {p.count} reviews</span>
        </div>
        <div className="mt-3 flex items-baseline gap-2">
          <span className="font-display font-black text-lg">${p.price}</span>
          {p.was && <span className="text-xs line-through text-neutral-400">${p.was}</span>}
        </div>
      </div>
    </div>
  );
}

// Marquee partner brands trust bar
function TrustBar() {
  const brands = ["WILSON", "BABOLAT", "HEAD", "YONEX", "ASICS", "PRINCE", "DUNLOP", "TECNIFIBRE", "LUXILON", "K-SWISS"];
  return (
    <section className="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950">
      <div className="max-w-[1400px] mx-auto px-6 py-10">
        <div className="flex items-center gap-4 mb-6">
          <span className="text-[11px] uppercase tracking-[0.25em] text-neutral-500">Authorized retailer for</span>
          <span className="flex-1 h-px bg-black/10 dark:bg-white/10"/>
        </div>
      </div>
      <div className="overflow-hidden">
        <div className="flex gap-12 lg:gap-16 marquee py-2 pb-10">
          {[...brands, ...brands].map((b, i) => (
            <span key={i} className="font-display font-black text-2xl lg:text-3xl tracking-[0.05em] text-neutral-400 dark:text-neutral-600 shrink-0 whitespace-nowrap">
              {b}
            </span>
          ))}
        </div>
      </div>
    </section>
  );
}

window.TrendingGear = TrendingGear;
window.TrustBar = TrustBar;
