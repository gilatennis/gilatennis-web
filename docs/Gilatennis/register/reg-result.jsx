// Success + Failed result views
function SuccessView({ formData, onBrowse }) {
  const email = formData?.email || "your email";
  const code = "GO-2026-" + Math.random().toString(36).slice(2, 6).toUpperCase();
  const [calOpen, setCalOpen] = React.useState(false);

  return (
    <div role="status" aria-live="polite">
      {/* Confetti */}
      <ConfettiDrop/>

      <div className="min-h-screen bg-black text-white flex flex-col">
        <div className="flex-1 flex items-center justify-center py-20 px-6">
          <div className="max-w-2xl w-full text-center">
            {/* Check circle */}
            <div className="mb-8 inline-flex h-20 w-20 rounded-full bg-coral items-center justify-center">
              <IconCheck size={36} stroke={2.4} className="text-white"/>
            </div>

            {/* Eyebrow */}
            <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-4">
              Registration Confirmed · #{code}
            </div>

            {/* Headline */}
            <h1 className="font-display font-black text-[44px] sm:text-[64px] leading-[0.93] tracking-[-0.03em] mb-6">
              You're in. See you on the clay.
            </h1>

            {/* Body */}
            <p className="text-white/70 text-base leading-relaxed max-w-lg mx-auto mb-10">
              We've emailed your draw confirmation to <strong className="text-white">{email}</strong>. Bring photo ID and a USTA membership card to check-in on Friday.
            </p>

            {/* Actions */}
            <div className="flex flex-col sm:flex-row items-center justify-center gap-3 mb-12">
              {/* Calendar dropdown */}
              <div className="relative">
                <button
                  onClick={() => setCalOpen(v => !v)}
                  className="h-14 px-6 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all"
                >
                  <IconCalendar size={16}/> Add to Calendar
                  <IconChevronDown size={14} className={calOpen ? "rotate-180 transition-transform" : "transition-transform"}/>
                </button>
                {calOpen && (
                  <div className="absolute top-full left-0 mt-1 w-56 bg-white text-black shadow-2xl z-20">
                    {[
                      ["Google Calendar", "https://calendar.google.com"],
                      ["Apple Calendar", "#"],
                      ["Outlook", "#"],
                      [".ics Download", "#"],
                    ].map(([label, href]) => (
                      <a key={label} href={href} className="flex items-center gap-3 px-4 py-3 text-sm hover:bg-neutral-100 border-b border-black/5 last:border-0">
                        <IconDownload size={13}/>
                        {label}
                      </a>
                    ))}
                  </div>
                )}
              </div>
              <button className="h-14 px-6 border border-white/30 text-white font-display font-bold text-sm uppercase tracking-[0.18em] hover:border-white transition-colors">
                Share registration
              </button>
              <button onClick={onBrowse} className="h-14 px-4 text-white/50 text-sm uppercase tracking-[0.18em] hover:text-white transition-colors">
                Browse more tournaments
              </button>
            </div>

            {/* What's next strip */}
            <div className="grid sm:grid-cols-3 gap-0 border border-white/10">
              {[
                { icon: <IconClock size={16}/>, label: "Check-in opens", value: "Fri 4:00 PM" },
                { icon: <IconTrophy size={16}/>, label: "Bracket releases", value: "Wed Jul 15" },
                { icon: <IconBolt size={16}/>, label: "Stringing service", value: "On-site" },
              ].map((item, i) => (
                <div key={i} className={`p-6 text-left ${i < 2 ? "sm:border-r border-b sm:border-b-0 border-white/10" : ""}`}>
                  <div className="flex items-center gap-2 text-coral mb-2">{item.icon}<span className="text-[10px] uppercase tracking-[0.2em]">{item.label}</span></div>
                  <div className="font-display font-bold text-lg">{item.value}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function ConfettiDrop() {
  const colors = ["oklch(64.7% .217 57.6)", "oklch(62% .18 145)", "oklch(82% .18 100)"];
  const pieces = Array.from({ length: 12 }, (_, i) => ({
    id: i,
    color: colors[i % colors.length],
    left: `${8 + (i / 12) * 84}%`,
    delay: `${(i * 0.09).toFixed(2)}s`,
    size: 6 + (i % 3) * 3,
  }));

  return (
    <div className="fixed inset-0 pointer-events-none overflow-hidden z-50" style={{ '--duration': '1.5s' }}>
      <style>{`
        @keyframes confettiFall {
          0%   { transform: translateY(-20px) rotate(0deg); opacity: 1; }
          80%  { opacity: 1; }
          100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        @media (prefers-reduced-motion: reduce) { .confetti-piece { display: none; } }
      `}</style>
      {pieces.map(p => (
        <div
          key={p.id}
          className="confetti-piece absolute top-0"
          style={{
            left: p.left,
            width: p.size,
            height: p.size,
            background: p.color,
            animation: `confettiFall 1.5s ${p.delay} ease-in forwards`,
          }}
        />
      ))}
    </div>
  );
}

function FailedView({ formData, onRetry, onCancel }) {
  return (
    <div role="status" aria-live="polite" className="min-h-screen bg-neutral-100 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 flex flex-col">
      <div className="flex-1 flex items-center justify-center py-20 px-6">
        <div className="max-w-2xl w-full text-center">
          {/* Alert icon */}
          <div className="mb-8 inline-flex h-20 w-20 border-2 border-coral items-center justify-center">
            <IconAlert size={32} className="text-coral"/>
          </div>

          {/* Eyebrow */}
          <div className="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-4">
            Payment Declined · Code 4302
          </div>

          {/* Headline */}
          <h1 className="font-display font-black text-[44px] sm:text-[64px] leading-[0.93] tracking-[-0.03em] mb-6">
            Your card was declined.
          </h1>

          {/* Body */}
          <p className="text-neutral-600 dark:text-neutral-400 text-base leading-relaxed max-w-lg mx-auto mb-8">
            Stripe couldn't authorize the payment. No charge has been made. Try a different card or contact your bank.
          </p>

          {/* Diagnostic */}
          <div className="inline-block text-left mb-10 bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
            <pre className="font-mono text-xs p-5 text-neutral-700 dark:text-neutral-300 leading-relaxed">{`txn_id   tx_2QkR9aP_xa
reason   insufficient_funds
retry    available`}</pre>
          </div>

          {/* Actions */}
          <div className="flex flex-col sm:flex-row items-center justify-center gap-3 mb-8">
            <button
              onClick={onRetry}
              className="h-14 px-8 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all"
            >
              <IconCreditCard size={16}/> Try Again
            </button>
            <button
              onClick={onRetry}
              className="h-14 px-8 border border-black/20 dark:border-white/20 font-display font-bold text-sm uppercase tracking-[0.18em] hover:border-black dark:hover:border-white transition-colors"
            >
              Use a different payment method
            </button>
            <button onClick={onCancel} className="h-14 px-4 text-neutral-400 text-sm uppercase tracking-[0.18em] hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">
              Cancel registration
            </button>
          </div>

          <p className="text-sm text-neutral-500">
            Need help? Email <a href="mailto:tournaments@gilatennis.com" className="text-coral underline">tournaments@gilatennis.com</a>
          </p>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { SuccessView, FailedView });
