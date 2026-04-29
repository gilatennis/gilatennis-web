// Content-to-Commerce article grid. Gear Reviews carry a "Shop this product" overlay.
const ARTICLES = [
{
  type: "review",
  kicker: "Gear Review",
  title: "Wilson's new Pro Staff is a love letter to feel.",
  excerpt: "97 sq in, 315g, and a 16x19 pattern that punches above its weight on fast courts.",
  img: "racket · pro staff studio shot",
  author: "M. Alvarez",
  minutes: 7,
  product: { name: "Pro Staff 97 v14", price: "$259", strike: "$289" }
},
{
  type: "tip",
  kicker: "Tennis Tip",
  title: "The split-step timing trick that pros never miss.",
  excerpt: "Land milliseconds after your opponent's contact — here's the drill we teach to fix it.",
  img: "split step · slow-mo · court",
  author: "Coach Reyes",
  minutes: 5
},
{
  type: "review",
  kicker: "Gear Review",
  title: "Are these the most comfortable clay shoes ever?",
  excerpt: "We put 200km on the new Mirage Clay Pro across red and green clay.",
  img: "shoe · sole detail · clay dust",
  author: "L. Singh",
  minutes: 9,
  product: { name: "Mirage Clay Pro", price: "$139" }
},
{
  type: "tip",
  kicker: "Strategy",
  title: "When to come to net vs. stay back: a decision tree.",
  excerpt: "Court position is a math problem. Here's the framework that wins more points.",
  img: "court overhead · tactics diagram",
  author: "J. Park",
  minutes: 6
},
{
  type: "review",
  kicker: "Gear Review",
  title: "Polyester strings, ranked: who actually feels different?",
  excerpt: "We strung 14 reels at identical tension and let club players blind-test.",
  img: "string reel · color array",
  author: "Gear Desk",
  minutes: 11,
  product: { name: "Luxilon ALU Power", price: "$22", strike: "$26" }
},
{
  type: "tip",
  kicker: "Fitness",
  title: "Tennis-specific mobility in 12 minutes a day.",
  excerpt: "The shoulder, hip and ankle routine our touring physios actually use.",
  img: "athlete · mobility drill",
  author: "Dr. K. Tan",
  minutes: 8
}];


function ArticlesGrid() {
  const [filter, setFilter] = React.useState("all");
  const tabs = [
  { id: "all", label: "All Stories" },
  { id: "tip", label: "Tennis Tips" },
  { id: "review", label: "Gear Reviews" },
  { id: "strategy", label: "Strategy" }];

  const filtered = filter === "all" ? ARTICLES : ARTICLES.filter((a) => a.type === filter || a.kicker.toLowerCase() === filter);

  return (
    <section id="articles" className="border-b border-black/10 dark:border-white/10">
      <div className="max-w-[1400px] mx-auto px-6 py-16 lg:py-24">
        <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
          <div>
            <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-3">The Field Notes</div>
            <h2 className="font-display font-black text-4xl lg:text-6xl tracking-[-0.03em] leading-[0.95] max-w-2xl" style={{ fontSize: "48px" }}>
              In-depth reviews. <br />On-court tests. Your next advantage.
            </h2>
          </div>
          <div className="flex flex-wrap gap-1.5">
            {tabs.map((t) =>
            <button
              key={t.id}
              onClick={() => setFilter(t.id)}
              className={`px-4 h-10 text-xs uppercase tracking-[0.18em] font-bold border transition-colors ${
              filter === t.id ?
              "bg-black text-white border-black dark:bg-white dark:text-black dark:border-white" :
              "border-black/15 dark:border-white/15 hover:border-coral hover:text-coral"}`
              }>
              
                {t.label}
              </button>
            )}
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-12">
          {filtered.map((a, i) => <ArticleCard key={i} a={a} />)}
        </div>

        <div className="mt-14 flex justify-center">
          <button className="group inline-flex items-center gap-3 px-7 h-12 border border-black dark:border-white text-sm font-bold uppercase tracking-[0.2em] hover:bg-coral hover:border-coral hover:text-white transition-colors">
            Browse all 1,200+ stories
            <IconArrow size={16} className="group-hover:translate-x-1 transition-transform" />
          </button>
        </div>
      </div>
    </section>);

}

function ArticleCard({ a }) {
  const isReview = a.type === "review";
  return (
    <a href="#" className="group flex flex-col">
      <div className={`relative ${isReview ? "border-t-2 border-coral" : ""}`}>
        <Placeholder label={a.img} aspect="4/3" tone="dark" className="w-full">
          <div className="absolute top-3 left-3 px-2 py-1 bg-white text-black text-[10px] font-bold uppercase tracking-[0.18em]">
            {a.kicker}
          </div>
          {isReview && a.product &&
          <div className="absolute right-3 bottom-3 left-3 sm:left-auto sm:max-w-[200px] bg-white dark:bg-neutral-950 shadow-xl">
              <div className="px-3 py-2.5 flex items-center gap-3">
                <div className="flex-1 min-w-0">
                  <div className="text-[10px] uppercase tracking-[0.18em] text-neutral-500">Shop this</div>
                  <div className="font-display font-bold text-[13px] truncate">{a.product.name}</div>
                </div>
                <div className="text-right shrink-0">
                  <div className="font-display font-black text-sm">{a.product.price}</div>
                  {a.product.strike && <div className="text-[10px] line-through text-neutral-400">{a.product.strike}</div>}
                </div>
              </div>
              <button className="w-full h-9 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.2em] flex items-center justify-center gap-1.5 hover:bg-black hover:dark:bg-white hover:dark:text-black">
                Add to cart <IconArrow size={12} />
              </button>
            </div>
          }
        </Placeholder>
      </div>
      <div className="pt-5">
        <h3 className="font-display font-bold text-xl lg:text-[22px] leading-[1.1] tracking-[-0.015em] group-hover:text-coral transition-colors">
          {a.title}
        </h3>
        <p className="mt-3 text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed line-clamp-2">{a.excerpt}</p>
        <div className="mt-4 flex items-center gap-3 text-[11px] uppercase tracking-[0.18em] text-neutral-500">
          <span>{a.author}</span>
          <span className="h-1 w-1 rounded-full bg-neutral-400" />
          <span className="flex items-center gap-1.5"><IconClock size={11} /> {a.minutes} min</span>
        </div>
      </div>
    </a>);

}

window.ArticlesGrid = ArticlesGrid;