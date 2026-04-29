// Root state machine for the registration flow
function RegistrationApp() {
  const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
    "darkMode": false,
    "accent": "oklch(64.7% .217 57.6)",
    "simulateOutcome": "success",
    "startStage": "detail",
    "redirectDelay": 1800,
    "entryFee": 50,
    "spotsLeft": 7,
    "drawSize": 32,
    "doublesDefault": false
  }/*EDITMODE-END*/;

  const [tweaks, setTweak] = window.useTweaks
    ? window.useTweaks(TWEAK_DEFAULTS)
    : [TWEAK_DEFAULTS, () => {}];

  // Stage persistence
  const initStage = () => {
    const saved = sessionStorage.getItem("reg_stage");
    if (saved && ["detail","form","success","failed"].includes(saved)) return saved;
    return tweaks.startStage || "detail";
  };

  const [stage, _setStage] = React.useState(initStage);
  const [formData, setFormData] = React.useState({
    name: "", email: "", phone: "", dob: "",
    ratingType: "UTR", rating: "",
    eventType: tweaks.doublesDefault ? "doubles" : "singles",
    findPartner: tweaks.doublesDefault,
    partnerName: "", partnerEmail: "",
    tshirt: "M", dietary: "",
  });
  const [dark, setDark] = React.useState(tweaks.darkMode);
  const [isSubmitting, setIsSubmitting] = React.useState(false);
  const [visible, setVisible] = React.useState(true);

  function setStage(s) {
    setVisible(false);
    setTimeout(() => {
      _setStage(s);
      sessionStorage.setItem("reg_stage", s);
      setVisible(true);
      window.scrollTo({ top: 0, behavior: "smooth" });
    }, 180);
  }

  // Expose goDetail for BackBar in reg-form
  window._regGoDetail = () => setStage("detail");

  React.useEffect(() => {
    document.documentElement.classList.toggle("dark", dark);
  }, [dark]);
  React.useEffect(() => { setDark(tweaks.darkMode); }, [tweaks.darkMode]);

  React.useEffect(() => {
    if (tweaks.accent) {
      const css = `
        .bg-coral{background-color:${tweaks.accent}!important}
        .text-coral{color:${tweaks.accent}!important}
        .border-coral{border-color:${tweaks.accent}!important}
        .hover\\:bg-coral:hover{background-color:${tweaks.accent}!important}
        .hover\\:text-coral:hover{color:${tweaks.accent}!important}
        .bg-coral\\/10{background-color:color-mix(in oklch,${tweaks.accent} 10%,transparent)!important}
      `;
      let tag = document.getElementById("accent-override");
      if (!tag) { tag = document.createElement("style"); tag.id = "accent-override"; document.head.appendChild(tag); }
      tag.textContent = css;
    }
  }, [tweaks.accent]);

  // Tournament object patched with tweak overrides
  const tournament = React.useMemo(() => ({
    ...window.TOURNAMENT,
    fee: tweaks.entryFee ?? window.TOURNAMENT.fee,
    spotsLeft: tweaks.spotsLeft ?? window.TOURNAMENT.spotsLeft,
    drawSize: tweaks.drawSize ?? window.TOURNAMENT.drawSize,
  }), [tweaks.entryFee, tweaks.spotsLeft, tweaks.drawSize]);

  function handleSubmit() {
    setIsSubmitting(true);
    setStage("redirecting");
    const delay = tweaks.redirectDelay ?? 1800;
    setTimeout(() => {
      const outcome = tweaks.simulateOutcome;
      if (outcome === "success") setStage("success");
      else if (outcome === "failed") setStage("failed");
      else {
        setStage(Math.random() < 0.7 ? "success" : "failed");
      }
      setIsSubmitting(false);
    }, delay);
  }

  const transitionStyle = {
    opacity: visible ? 1 : 0,
    transform: visible ? "translateY(0)" : "translateY(8px)",
    transition: "opacity 0.2s ease, transform 0.2s ease",
  };

  return (
    <div className="min-h-screen bg-white dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100">
      <Header dark={dark} setDark={(v) => { setDark(v); setTweak("darkMode", v); }}/>

      <main style={transitionStyle}>
        {stage === "detail" && (
          <TournamentDetailPage
            tournament={tournament}
            onRegister={() => setStage("form")}
          />
        )}
        {stage === "form" && (
          <RegistrationForm
            tournament={tournament}
            formData={formData}
            setFormData={setFormData}
            onSubmit={handleSubmit}
            isSubmitting={isSubmitting}
          />
        )}
        {stage === "success" && (
          <SuccessView
            formData={formData}
            onBrowse={() => { setFormData(fd => ({ ...fd })); setStage("detail"); }}
          />
        )}
        {stage === "failed" && (
          <FailedView
            formData={formData}
            onRetry={() => setStage("form")}
            onCancel={() => { setFormData({ name:"",email:"",phone:"",dob:"",ratingType:"UTR",rating:"",eventType:"singles",findPartner:false,partnerName:"",partnerEmail:"",tshirt:"M",dietary:"" }); setStage("detail"); }}
          />
        )}
      </main>

      {/* Stripe redirect overlay */}
      {stage === "redirecting" && (
        <div
          role="dialog" aria-modal="true" aria-label="Redirecting to Stripe"
          className="fixed inset-0 bg-black/70 backdrop-blur-md grid place-items-center z-50"
        >
          <div className="bg-white text-black max-w-md w-full mx-4 p-10 text-center">
            {/* Spinner */}
            <div className="relative inline-flex items-center justify-center h-16 w-16 mb-6">
              <svg className="absolute" width="64" height="64" viewBox="0 0 64 64">
                <circle cx="32" cy="32" r="28" fill="none" stroke="#e5e5e5" strokeWidth="4"/>
                <circle cx="32" cy="32" r="28" fill="none" stroke="oklch(64.7% .217 57.6)" strokeWidth="4"
                  strokeLinecap="round" strokeDasharray="44 132"
                  style={{ animation: "spin 1s linear infinite", transformOrigin: "center" }}/>
              </svg>
              <IconCreditCard size={22}/>
            </div>
            <h2 className="font-display font-black text-2xl mb-3">Redirecting to Stripe&hellip;</h2>
            <p className="text-sm text-neutral-600 mb-4">Please don't close this tab. We're handing off to our payment partner.</p>
            <div className="font-mono text-[11px] text-neutral-400 bg-neutral-100 px-3 py-2 inline-block">
              STRIPE &bull; SESSION_a8f3e9c7&hellip;
            </div>
          </div>
        </div>
      )}

      {/* Tweaks panel */}
      {window.TweaksPanel && (
        <window.TweaksPanel title="Registration Tweaks">
          <window.TweakSection title="Theme">
            <window.TweakToggle label="Dark mode" value={tweaks.darkMode} onChange={v => setTweak("darkMode", v)}/>
            <window.TweakColor label="Accent color" value={tweaks.accent} onChange={v => setTweak("accent", v)}/>
          </window.TweakSection>
          <window.TweakSection title="Quick presets">
            <window.TweakButton label="Coral (default)" onClick={() => setTweak("accent", "oklch(64.7% .217 57.6)")}/>
            <window.TweakButton label="Court green" onClick={() => setTweak("accent", "oklch(62% .18 145)")}/>
            <window.TweakButton label="Electric yellow" onClick={() => setTweak("accent", "oklch(82% .18 100)")}/>
            <window.TweakButton label="Hardcourt blue" onClick={() => setTweak("accent", "oklch(58% .18 240)")}/>
          </window.TweakSection>
          <window.TweakSection title="Demo controls">
            <window.TweakRadio label="Outcome on submit" value={tweaks.simulateOutcome}
              options={[{label:"Success",value:"success"},{label:"Failed",value:"failed"},{label:"Random",value:"random"}]}
              onChange={v => setTweak("simulateOutcome", v)}/>
            <window.TweakRadio label="Start on stage" value={tweaks.startStage}
              options={[{label:"Detail",value:"detail"},{label:"Form",value:"form"},{label:"Success",value:"success"},{label:"Failed",value:"failed"}]}
              onChange={v => { setTweak("startStage", v); setStage(v); }}/>
            <window.TweakSlider label="Stripe redirect delay (ms)" value={tweaks.redirectDelay} min={0} max={4000} step={100} onChange={v => setTweak("redirectDelay", v)}/>
            <window.TweakToggle label="Doubles default" value={tweaks.doublesDefault} onChange={v => setTweak("doublesDefault", v)}/>
          </window.TweakSection>
          <window.TweakSection title="Tournament data">
            <window.TweakNumber label="Entry fee ($)" value={tweaks.entryFee} onChange={v => setTweak("entryFee", v)}/>
            <window.TweakNumber label="Spots left" value={tweaks.spotsLeft} onChange={v => setTweak("spotsLeft", v)}/>
            <window.TweakNumber label="Draw size" value={tweaks.drawSize} onChange={v => setTweak("drawSize", v)}/>
          </window.TweakSection>
        </window.TweaksPanel>
      )}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<RegistrationApp/>);
