// Tournament Detail Page — Stage 1
function DetailStat({ label, value }) {
  return (
    <div>
      <div className="text-[10px] uppercase tracking-[0.18em] opacity-50">{label}</div>
      <div className="mt-1 font-display font-bold text-base">{value}</div>
    </div>
  );
}

function useCountdown(closesStr) {
  const [label, setLabel] = React.useState("");
  React.useEffect(() => {
    function calc() {
      const diff = new Date(closesStr) - Date.now();
      if (diff <= 0) { setLabel("Closed"); return; }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      setLabel(`${d}d ${h}h ${m}m`);
    }
    calc();
    const id = setInterval(calc, 30000);
    return () => clearInterval(id);
  }, [closesStr]);
  return label;
}

function TournamentDetailPage({ tournament: t, onRegister }) {
  const countdown = useCountdown(t.registrationCloses);
  const [faqOpen, setFaqOpen] = React.useState({});
  const sold = t.spotsLeft === 0;

  const faqs = [
    { q: "How does check-in work?", a: "Check-in is Friday July 17, 4–7 PM at the main desk. Bring photo ID and your USTA membership card. Late check-in must be arranged by email." },
    { q: "What is the refund policy?", a: "Full refund if cancelled 7+ days before the event. 50% refund within 7 days. No refund after July 11 unless the event is cancelled by organizers." },
    { q: "What if I need a doubles partner?", a: "Select 'Find me a partner' during registration. We'll match you with a similarly-rated player by July 12 and send mutual confirmation emails." },
    { q: "Is stringing available on-site?", a: "Yes — professional stringing service runs Fri–Sun. Drop off by 8 PM for same-morning turnaround. Bring your own strings or purchase from our pro shop." },
  ];

  return (
    <div>
      {/* Eyebrow row */}
      <div className="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950">
        <div className="max-w-[1400px] mx-auto px-6 py-3 flex flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.2em]">
          <span className="flex items-center gap-1.5 px-2.5 py-1 bg-coral text-white font-bold">
            <span className="h-1.5 w-1.5 rounded-full bg-white animate-pulse"/>
            Registration Open
          </span>
          <span className="text-neutral-500">Closes {t.registrationCloses.split(",")[0]}</span>
          <span className="text-neutral-500">·</span>
          <span className={sold ? "text-neutral-400 line-through" : "font-bold text-neutral-800 dark:text-neutral-200"}>
            {sold ? "Sold out" : `${t.spotsLeft} spots left`}
          </span>
        </div>
      </div>

      <div className="max-w-[1400px] mx-auto px-6 pt-8 pb-20">
        {/* Hero band */}
        <div className="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
          {/* Cols 1–8: hero image */}
          <div className="lg:col-span-8">
            <Placeholder label="indian wells · clay courts · 16:9" aspect="16/9" tone="dark" className="w-full">
              {/* Chips */}
              <div className="absolute top-5 left-5 flex flex-wrap gap-2">
                {["Clay", "USTA-Sanctioned", "Singles + Doubles"].map(chip => (
                  <span key={chip} className="px-2.5 py-1 bg-black/50 text-white text-[10px] font-bold uppercase tracking-[0.18em] backdrop-blur-sm">{chip}</span>
                ))}
              </div>
              {/* Title */}
              <div className="absolute inset-x-0 bottom-0 p-6 lg:p-10 bg-gradient-to-t from-black/90 via-black/50 to-transparent">
                <h1 className="font-display font-black text-white text-[32px] sm:text-[52px] lg:text-[72px] leading-[0.92] tracking-[-0.03em] max-w-3xl">
                  Gila Open: <em className="not-italic text-coral">Summer Smash</em>
                </h1>
                <p className="mt-3 text-white/75 text-sm sm:text-base max-w-xl">
                  {t.dates} · {t.venue}
                </p>
              </div>
            </Placeholder>
          </div>

          {/* Cols 9–12: registration card */}
          <div className="lg:col-span-4 lg:sticky lg:top-[140px]">
            <div className="bg-black text-white dark:bg-white dark:text-black">
              <div className="p-6 lg:p-8">
                {/* Countdown */}
                <div className="flex items-center justify-between mb-6 pb-6 border-b border-white/10 dark:border-black/10">
                  <div>
                    <div className="text-[10px] uppercase tracking-[0.2em] opacity-50 mb-1">Registration closes in</div>
                    <div className="font-display font-black text-2xl tabular-nums">{countdown}</div>
                  </div>
                  <div className="text-right">
                    <div className="text-[10px] uppercase tracking-[0.2em] opacity-50 mb-1">Entry fee</div>
                    <div className="font-display font-black text-2xl">${t.fee}</div>
                  </div>
                </div>

                {/* Stats grid */}
                <div className="grid grid-cols-2 gap-x-4 gap-y-5 mb-6">
                  <DetailStat label="Surface" value={t.surface}/>
                  <DetailStat label="Draw Size" value={`${t.drawSize} players`}/>
                  <DetailStat label="UTR Range" value={`${t.utrMin} – ${t.utrMax}`}/>
                  <DetailStat label="Format" value="Single Elim · B3"/>
                  <DetailStat label="Ball" value={t.ballType}/>
                  <DetailStat label="Spots Left" value={sold ? "Sold out" : `${t.spotsLeft}`}/>
                </div>

                {/* CTA */}
                <button
                  onClick={onRegister}
                  className="w-full h-14 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center justify-center gap-3 hover:opacity-90 active:scale-[0.98] transition-all"
                >
                  {sold ? "Join Waitlist" : "Register Now"} <IconArrow size={18}/>
                </button>
                <button className="mt-2 w-full h-10 text-xs uppercase tracking-[0.2em] opacity-50 hover:opacity-100 transition-opacity">
                  Share with my doubles partner
                </button>
                <button className="w-full h-8 text-xs uppercase tracking-[0.18em] opacity-40 hover:opacity-80 transition-opacity flex items-center justify-center gap-1.5">
                  <IconHeart size={13}/> Add to watchlist
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Three-col info strip */}
        <div className="mt-10 grid lg:grid-cols-3 gap-0 border border-black/10 dark:border-white/10">
          {/* Schedule */}
          <div className="p-6 lg:p-8 border-b lg:border-b-0 lg:border-r border-black/10 dark:border-white/10">
            <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-2">Schedule</div>
            <h3 className="font-display font-bold text-xl mb-5">Full draw timeline</h3>
            <div className="space-y-4">
              {t.schedule.map((s, i) => (
                <div key={i}>
                  <div className="font-display font-bold text-sm">{s.day}</div>
                  <ul className="mt-1.5 space-y-1">
                    {s.items.map((item, j) => (
                      <li key={j} className="flex items-start gap-2 text-sm opacity-70">
                        <span className="mt-2 h-1 w-3 bg-coral shrink-0"/>
                        <span className="font-mono text-xs tracking-[0.08em]">{item}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </div>

          {/* Rules */}
          <div className="p-6 lg:p-8 border-b lg:border-b-0 lg:border-r border-black/10 dark:border-white/10">
            <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-2">Rules &amp; Format</div>
            <h3 className="font-display font-bold text-xl mb-5">{t.format}</h3>
            <ul className="space-y-3">
              {t.rules.map((rule, i) => (
                <li key={i} className="flex items-start gap-2 text-sm">
                  <span className="mt-2 h-1 w-3 bg-coral shrink-0"/>
                  <span className="opacity-75 leading-relaxed">{rule}</span>
                </li>
              ))}
            </ul>
          </div>

          {/* Prizes */}
          <div className="p-6 lg:p-8">
            <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-2">Prizes</div>
            <h3 className="font-display font-bold text-xl mb-5">Winner takes home</h3>
            <div className="space-y-5">
              {t.prizes.map((p, i) => (
                <div key={i} className={`${i < t.prizes.length - 1 ? "pb-5 border-b border-black/10 dark:border-white/10" : ""}`}>
                  <div className="text-[10px] uppercase tracking-[0.2em] opacity-50 mb-0.5">{p.place}</div>
                  <div className="font-display font-black text-2xl">{p.value}</div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Players strip */}
        <div className="mt-8 py-6 border-b border-black/10 dark:border-white/10">
          <div className="flex items-center gap-3 mb-4">
            <IconUsers size={14} className="text-coral"/>
            <span className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral">Players registered</span>
          </div>
          <div className="flex items-center gap-2 overflow-x-auto pb-1">
            {Array.from({ length: 20 }).map((_, i) => (
              <Placeholder key={i} label="" aspect="1/1" tone={i % 2 === 0 ? "dark" : "light"} className="h-10 w-10 rounded-full shrink-0 overflow-hidden"/>
            ))}
            <div className="h-10 w-10 rounded-full border-2 border-dashed border-black/20 dark:border-white/20 shrink-0 grid place-items-center text-[10px] font-bold text-neutral-500">
              +{t.drawSize - 20 - t.spotsLeft}
            </div>
          </div>
        </div>

        {/* FAQ */}
        <div className="mt-8">
          <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-6">FAQ</div>
          <div className="space-y-0">
            {faqs.map((f, i) => (
              <div key={i} className="border-b border-black/10 dark:border-white/10">
                <button
                  className="w-full text-left py-5 flex items-center justify-between gap-4"
                  onClick={() => setFaqOpen(p => ({ ...p, [i]: !p[i] }))}
                >
                  <span className="font-display font-bold text-base">{f.q}</span>
                  <IconChevronDown size={16} className={`shrink-0 transition-transform ${faqOpen[i] ? "rotate-180" : ""}`}/>
                </button>
                {faqOpen[i] && (
                  <div className="pb-5 text-sm opacity-70 leading-relaxed max-w-2xl">{f.a}</div>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Sticky mobile CTA */}
      <div className="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-neutral-950 border-t border-black/10 dark:border-white/10 p-4 flex items-center gap-4">
        <div className="flex-1 min-w-0">
          <div className="font-display font-bold text-sm truncate">{t.name}</div>
          <div className="text-xs text-neutral-500">${t.fee} entry</div>
        </div>
        <button
          onClick={onRegister}
          className="h-12 px-6 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all shrink-0"
        >
          Register <IconArrow size={16}/>
        </button>
      </div>
    </div>
  );
}

Object.assign(window, { TournamentDetailPage, DetailStat });
