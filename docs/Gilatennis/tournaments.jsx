// Tournament board + Newsletter + Footer
const TOURNAMENTS = [
  {
    name: "NorCal Spring Open",
    date: "May 3 – 5, 2026",
    city: "San Jose, CA",
    surface: "Hard",
    levels: "3.0 – 4.5",
    spots: 12,
    total: 64,
    fee: "$45",
    featured: true,
    starts: "Sat 8:00 AM",
  },
  {
    name: "Sunset Doubles Classic",
    date: "May 10, 2026",
    city: "Santa Monica, CA",
    surface: "Hard",
    levels: "3.5 – 5.0",
    spots: 4,
    total: 32,
    fee: "$60",
    starts: "Sun 9:00 AM",
  },
  {
    name: "Red Clay Challenge",
    date: "May 16 – 17, 2026",
    city: "Austin, TX",
    surface: "Clay",
    levels: "4.0 – 5.0",
    spots: 18,
    total: 48,
    fee: "$55",
    starts: "Sat 7:30 AM",
  },
  {
    name: "Junior Champions Cup",
    date: "May 22 – 24, 2026",
    city: "Boston, MA",
    surface: "Hard",
    levels: "U14 – U18",
    spots: 22,
    total: 96,
    fee: "$40",
    starts: "Fri 4:00 PM",
  },
];

function Tournaments() {
  const [selected, setSelected] = React.useState(0);
  return (
    <section id="tournaments" className="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950 relative overflow-hidden">
      {/* Decorative coral half-disc */}
      <div className="absolute -right-32 -top-32 h-[420px] w-[420px] rounded-full bg-coral/10 dark:bg-coral/15 pointer-events-none"/>
      <div className="max-w-[1400px] mx-auto px-6 py-16 lg:py-24 relative">
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
          <div>
            <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-3">
              <IconTrophy size={14}/> Tournament Board
            </div>
            <h2 className="font-display font-black text-4xl lg:text-5xl tracking-[-0.03em] leading-[0.95] max-w-2xl">
              Find a draw. Pay your fee. Show up and play.
            </h2>
            <p className="mt-4 text-neutral-600 dark:text-neutral-400 max-w-xl">
              Sanctioned & rec-league events from coaches and clubs in our network. Filter by skill level — register in under 60 seconds.
            </p>
          </div>
          <div className="flex gap-2">
            <button className="px-4 h-10 border border-black/15 dark:border-white/15 text-xs uppercase tracking-[0.18em] font-bold flex items-center gap-2 hover:border-coral hover:text-coral">
              <IconMapPin size={14}/> Near me
            </button>
            <button className="px-4 h-10 border border-black/15 dark:border-white/15 text-xs uppercase tracking-[0.18em] font-bold flex items-center gap-2 hover:border-coral hover:text-coral">
              All filters <IconChevronDown size={14}/>
            </button>
          </div>
        </div>

        <div className="grid lg:grid-cols-12 gap-6">
          {/* Tournament list */}
          <div className="lg:col-span-7 flex flex-col">
            {TOURNAMENTS.map((t, i) => (
              <TournamentRow
                key={i}
                t={t}
                active={selected === i}
                onClick={() => setSelected(i)}
              />
            ))}
          </div>

          {/* Detail card */}
          <div className="lg:col-span-5">
            <TournamentDetail t={TOURNAMENTS[selected]}/>
          </div>
        </div>
      </div>
    </section>
  );
}

function TournamentRow({ t, active, onClick }) {
  const pct = Math.round(((t.total - t.spots) / t.total) * 100);
  return (
    <button
      onClick={onClick}
      className={`text-left border-t last:border-b border-black/10 dark:border-white/10 py-5 px-2 grid grid-cols-12 gap-3 items-center transition-colors ${
        active ? "bg-neutral-100 dark:bg-neutral-900" : "hover:bg-neutral-50 dark:hover:bg-neutral-900/50"
      }`}
    >
      <div className="col-span-12 sm:col-span-5">
        <div className="flex items-center gap-2 mb-1.5">
          {t.featured && <span className="px-1.5 py-0.5 bg-coral text-white text-[9px] font-bold uppercase tracking-[0.18em]">Featured</span>}
          <span className="text-[11px] uppercase tracking-[0.18em] text-neutral-500">{t.surface} · {t.levels}</span>
        </div>
        <div className="font-display font-bold text-[18px] tracking-[-0.01em] leading-tight">{t.name}</div>
        <div className="mt-1 text-xs text-neutral-500 flex items-center gap-3">
          <span className="flex items-center gap-1"><IconCalendar size={12}/> {t.date}</span>
          <span className="flex items-center gap-1"><IconMapPin size={12}/> {t.city}</span>
        </div>
      </div>
      <div className="col-span-6 sm:col-span-3">
        <div className="text-[10px] uppercase tracking-[0.18em] text-neutral-500 mb-1">Spots left</div>
        <div className="flex items-center gap-2">
          <div className="flex-1 h-1.5 bg-black/10 dark:bg-white/10 overflow-hidden">
            <div className="h-full bg-coral" style={{ width: `${pct}%` }}/>
          </div>
          <span className="font-display font-bold text-sm tabular-nums">{t.spots}</span>
        </div>
      </div>
      <div className="col-span-3 sm:col-span-2 font-display font-black text-lg tabular-nums">{t.fee}</div>
      <div className="col-span-3 sm:col-span-2 flex justify-end">
        <span className={`h-9 px-4 grid place-items-center text-[11px] font-bold uppercase tracking-[0.18em] transition-colors ${
          active ? "bg-coral text-white" : "border border-black/20 dark:border-white/20"
        }`}>
          {active ? "Selected" : "Details"}
        </span>
      </div>
    </button>
  );
}

function TournamentDetail({ t }) {
  return (
    <div className="bg-black text-white dark:bg-white dark:text-black sticky top-[140px]">
      <Placeholder label={`${t.surface.toLowerCase()} court · ${t.city}`} aspect="16/9" tone="dark" className="w-full">
        <div className="absolute top-4 left-4 px-2.5 py-1 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.2em] flex items-center gap-1.5">
          <span className="h-1.5 w-1.5 rounded-full bg-white animate-pulse"/> Registration open
        </div>
      </Placeholder>
      <div className="p-6 lg:p-8">
        <div className="text-[10px] uppercase tracking-[0.2em] opacity-60 mb-2">{t.date} · Starts {t.starts}</div>
        <h3 className="font-display font-black text-3xl tracking-[-0.02em] leading-[0.95]">{t.name}</h3>

        <div className="mt-6 grid grid-cols-3 gap-4 text-sm">
          <DetailStat label="Surface" value={t.surface}/>
          <DetailStat label="Skill" value={t.levels}/>
          <DetailStat label="Format" value="Single Elim"/>
          <DetailStat label="Draw Size" value={`${t.total}`}/>
          <DetailStat label="Spots Left" value={`${t.spots}`}/>
          <DetailStat label="Entry Fee" value={t.fee}/>
        </div>

        <ul className="mt-6 space-y-2 text-sm opacity-80">
          {[
            "USTA-sanctioned, official ranking points",
            "Pro stringing on-site (24h turnaround)",
            "Lunch + Gila merch in player gift bag",
          ].map(line => (
            <li key={line} className="flex items-start gap-2">
              <span className="mt-1.5 h-1 w-3 bg-coral shrink-0"/>
              {line}
            </li>
          ))}
        </ul>

        <button className="mt-8 w-full h-14 bg-coral text-white font-display font-bold text-base uppercase tracking-[0.18em] flex items-center justify-center gap-3 hover:bg-white hover:text-black dark:hover:bg-black dark:hover:text-white transition-colors">
          Register now <IconArrow size={18}/>
        </button>
        <button className="mt-2 w-full h-10 text-xs uppercase tracking-[0.2em] opacity-60 hover:opacity-100">
          Share with my doubles partner
        </button>
      </div>
    </div>
  );
}

function DetailStat({ label, value }) {
  return (
    <div>
      <div className="text-[10px] uppercase tracking-[0.18em] opacity-50">{label}</div>
      <div className="mt-1 font-display font-bold text-base">{value}</div>
    </div>
  );
}

function Newsletter() {
  const [email, setEmail] = React.useState("");
  const [sent, setSent] = React.useState(false);
  return (
    <section className="bg-coral text-white border-b border-black/10">
      <div className="max-w-[1400px] mx-auto px-6 py-16 lg:py-20 grid md:grid-cols-12 gap-8 items-center">
        <div className="md:col-span-7">
          <div className="text-[11px] font-bold uppercase tracking-[0.25em] opacity-80 mb-3">The Wednesday Sweep</div>
          <h2 className="font-display font-black text-4xl lg:text-5xl tracking-[-0.03em] leading-[0.95]">
            Weekly tips. <em className="not-italic opacity-70">Real</em> gear deals. Zero spam.
          </h2>
          <p className="mt-4 text-white/85 max-w-lg">
            One short email each Wednesday: the drill we're working on, a tournament near you, and the only product discounts we'd actually use ourselves.
          </p>
        </div>
        <form
          className="md:col-span-5"
          onSubmit={(e) => { e.preventDefault(); if (email) setSent(true); }}
        >
          {sent ? (
            <div className="bg-white text-black p-6">
              <div className="font-display font-black text-2xl">You're on the list.</div>
              <div className="mt-2 text-sm text-neutral-600">First issue lands Wednesday. Check your inbox to confirm.</div>
            </div>
          ) : (
            <div className="bg-white text-black p-2 flex flex-col sm:flex-row gap-2">
              <input
                value={email}
                onChange={e=>setEmail(e.target.value)}
                type="email"
                placeholder="your@email.com"
                className="flex-1 px-4 h-12 outline-none bg-transparent placeholder:text-neutral-400"
              />
              <button className="h-12 px-6 bg-black text-white text-xs font-bold uppercase tracking-[0.2em] hover:bg-coral transition-colors flex items-center justify-center gap-2">
                Subscribe <IconArrow size={14}/>
              </button>
            </div>
          )}
          <div className="mt-3 text-[11px] uppercase tracking-[0.2em] opacity-70">
            48,213 players already reading
          </div>
        </form>
      </div>
    </section>
  );
}

function Footer() {
  const cols = [
    { h: "Shop", links: ["Rackets", "Strings", "Shoes", "Apparel", "Bags", "Balls", "Sale"] },
    { h: "Read", links: ["Gear Reviews", "Tennis Tips", "Strategy", "Pro Watch", "Buyer's Guides"] },
    { h: "Play", links: ["Tournaments", "Find a Coach", "Stringing Service", "Demo Program"] },
    { h: "Company", links: ["About Gila", "Careers", "Press", "Contact", "Help Center"] },
  ];
  return (
    <footer className="bg-black text-white">
      <div className="max-w-[1400px] mx-auto px-6 pt-20 pb-8">
        <div className="grid lg:grid-cols-12 gap-10">
          <div className="lg:col-span-4">
            <div className="flex items-center gap-2.5">
              <span className="font-display font-black text-2xl" style={{ fontFamily: "\"Inter Tight\"" }}>Gilatennis</span>
            </div>
            <p className="mt-5 text-white/70 max-w-sm leading-relaxed">
              The internet's most useful tennis publication, with a pro shop attached. Independently owned, obsessively tested.
            </p>
            <div className="mt-6 flex gap-2">
              {["IG","X","YT","TT"].map(s => (
                <a key={s} href="#" className="h-10 w-10 grid place-items-center border border-white/20 text-xs font-bold hover:bg-coral hover:border-coral">{s}</a>
              ))}
            </div>
          </div>
          {cols.map(c => (
            <div key={c.h} className="lg:col-span-2">
              <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-4">{c.h}</div>
              <ul className="space-y-2.5">
                {c.links.map(l => <li key={l}><a href="#" className="text-sm text-white/80 hover:text-white">{l}</a></li>)}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-16 pt-6 border-t border-white/10 flex flex-col md:flex-row justify-between gap-4 text-xs text-white/50">
          <div>© 2026 Gila Tennis. Made by players, for players.</div>
          <div className="flex gap-5">
            <a href="#" className="hover:text-white">Privacy</a>
            <a href="#" className="hover:text-white">Terms</a>
            <a href="#" className="hover:text-white">Returns</a>
            <a href="#" className="hover:text-white">Accessibility</a>
          </div>
        </div>
      </div>
    </footer>
  );
}

window.Tournaments = Tournaments;
window.Newsletter = Newsletter;
window.Footer = Footer;
