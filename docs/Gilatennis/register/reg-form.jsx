// Registration Form — multi-step (Player → Event → Review)
const STEPS = ["01 Player", "02 Event", "03 Review"];

function ProgressRail({ step, setStep }) {
  return (
    <div className="flex items-center gap-0 mb-10">
      {STEPS.map((label, i) => {
        const done = i < step;
        const active = i === step;
        return (
          <React.Fragment key={i}>
            <button
              disabled={i > step}
              onClick={() => done && setStep(i)}
              className="flex flex-col items-center gap-2 group"
            >
              <div className={`h-9 w-9 grid place-items-center font-display font-black text-sm transition-colors
                ${done ? "bg-black dark:bg-white text-white dark:text-black cursor-pointer" :
                  active ? "bg-coral text-white" :
                  "border border-black/20 dark:border-white/20 text-neutral-400"}`}>
                {done ? <IconCheck size={14} stroke={2.4}/> : <span>{String(i + 1).padStart(2,"0")}</span>}
              </div>
              <span className={`text-[9px] uppercase tracking-[0.2em] hidden sm:block ${active ? "text-coral font-bold" : "text-neutral-400"}`}>
                {label.slice(3)}
              </span>
            </button>
            {i < STEPS.length - 1 && (
              <div className="flex-1 h-px mx-1 mb-4 bg-black/10 dark:bg-white/10 relative overflow-hidden">
                <div className="h-full bg-coral transition-all duration-300 ease-out" style={{ width: i < step ? "100%" : "0%" }}/>
              </div>
            )}
          </React.Fragment>
        );
      })}
    </div>
  );
}

function FieldError({ msg }) {
  return msg ? <div className="mt-1 text-xs text-coral font-medium">{msg}</div> : null;
}

function Input({ label, required, error, ...props }) {
  return (
    <div>
      <label className="block text-[11px] uppercase tracking-[0.2em] font-bold mb-2">
        {label}{required && <span className="text-coral ml-0.5">*</span>}
      </label>
      <input
        {...props}
        className={`w-full h-12 px-4 border bg-transparent outline-none font-sans text-sm transition-all
          ${error ? "border-coral ring-2 ring-coral/30" : "border-black/20 dark:border-white/20 focus:border-black dark:focus:border-white"}`}
        aria-invalid={!!error}
        aria-describedby={error ? `${props.id}-err` : undefined}
      />
      {error && <FieldError msg={error}/>}
    </div>
  );
}

function Step1({ data, setData, errors }) {
  return (
    <div className="space-y-5">
      <h2 className="font-display font-black text-2xl mb-6">Player information</h2>
      <Input id="name" label="Full Name" required type="text" placeholder="Jane Smith"
        value={data.name} onChange={e => setData(p => ({...p, name: e.target.value}))} error={errors.name}/>
      <Input id="email" label="Email" required type="email" placeholder="you@example.com"
        value={data.email} onChange={e => setData(p => ({...p, email: e.target.value}))} error={errors.email}/>
      <Input id="phone" label="Phone" required type="tel" placeholder="+1 (555) 000-0000"
        value={data.phone} onChange={e => setData(p => ({...p, phone: e.target.value}))} error={errors.phone}/>
      <Input id="dob" label="Date of birth" required type="date"
        value={data.dob} onChange={e => setData(p => ({...p, dob: e.target.value}))} error={errors.dob}/>

      {/* UTR rating */}
      <div>
        <label className="block text-[11px] uppercase tracking-[0.2em] font-bold mb-2">
          Player rating<span className="text-coral ml-0.5">*</span>
        </label>
        <div className="flex gap-0 mb-3">
          {["UTR","ITN","USTA"].map(t => (
            <button key={t} onClick={() => setData(p => ({...p, ratingType: t}))}
              className={`h-10 px-5 text-xs font-bold uppercase tracking-[0.18em] border transition-colors
                ${data.ratingType === t ? "bg-black dark:bg-white text-white dark:text-black border-black dark:border-white" : "border-black/20 dark:border-white/20 hover:border-black/40"}`}>
              {t}
            </button>
          ))}
        </div>
        <input
          type="number" step="0.1" min="1" max="16.5"
          placeholder={data.ratingType === "UTR" ? "4.0 – 6.0" : "Enter rating"}
          value={data.rating}
          onChange={e => setData(p => ({...p, rating: e.target.value}))}
          className={`w-full h-12 px-4 border bg-transparent outline-none text-sm transition-all
            ${errors.rating ? "border-coral ring-2 ring-coral/30" : "border-black/20 dark:border-white/20 focus:border-black dark:focus:border-white"}`}
          aria-invalid={!!errors.rating}
        />
        {errors.rating && <FieldError msg={errors.rating}/>}
        {!errors.rating && data.ratingType === "UTR" && data.rating && (parseFloat(data.rating) < 4.0 || parseFloat(data.rating) > 6.0) && (
          <div className="mt-2 text-xs text-coral">This event is rated 4.0–6.0. You may still submit.</div>
        )}
      </div>
    </div>
  );
}

function Step2({ data, setData, errors }) {
  const eventTypes = [
    { id: "singles", label: "Singles", price: "$50" },
    { id: "doubles", label: "Doubles", price: "$25 add-on" },
    { id: "both", label: "Both", price: "$75 total" },
  ];
  const showPartner = data.eventType === "doubles" || data.eventType === "both";

  return (
    <div className="space-y-6">
      <h2 className="font-display font-black text-2xl mb-6">Event selection</h2>

      {/* Event type radio cards */}
      <div>
        <label className="block text-[11px] uppercase tracking-[0.2em] font-bold mb-3">
          Event type<span className="text-coral ml-0.5">*</span>
        </label>
        <div className="flex flex-col gap-2">
          {eventTypes.map(et => (
            <button key={et.id} onClick={() => setData(p => ({...p, eventType: et.id}))}
              className={`text-left border p-4 flex items-center justify-between transition-colors
                ${data.eventType === et.id ? "border-black dark:border-white bg-black/5 dark:bg-white/5" : "border-black/15 dark:border-white/15 hover:border-black/30 dark:hover:border-white/30"}`}>
              <div className="flex items-center gap-3">
                <div className={`h-4 w-4 rounded-full border-2 grid place-items-center ${data.eventType === et.id ? "border-black dark:border-white" : "border-black/30 dark:border-white/30"}`}>
                  {data.eventType === et.id && <div className="h-2 w-2 rounded-full bg-black dark:bg-white"/>}
                </div>
                <span className="font-display font-bold text-base">{et.label}</span>
              </div>
              <span className="font-mono text-sm opacity-60">{et.price}</span>
            </button>
          ))}
        </div>
        {errors.eventType && <FieldError msg={errors.eventType}/>}
      </div>

      {/* Partner fields (slide-down) */}
      {showPartner && (
        <div className="border border-black/10 dark:border-white/10 p-5 space-y-4">
          <div className="flex items-center justify-between">
            <span className="text-sm font-bold">Doubles partner</span>
            <label className="flex items-center gap-2 cursor-pointer">
              <span className="text-xs uppercase tracking-[0.18em] text-neutral-500">Find me a partner</span>
              <div
                onClick={() => setData(p => ({...p, findPartner: !p.findPartner}))}
                className={`relative h-5 w-9 rounded-full transition-colors cursor-pointer ${data.findPartner ? "bg-coral" : "bg-black/20 dark:bg-white/20"}`}>
                <div className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform ${data.findPartner ? "translate-x-4" : "translate-x-0.5"}`}/>
              </div>
            </label>
          </div>
          {!data.findPartner && (
            <div className="space-y-3">
              <Input id="partnerName" label="Partner name" type="text" placeholder="Partner full name"
                value={data.partnerName} onChange={e => setData(p => ({...p, partnerName: e.target.value}))}/>
              <Input id="partnerEmail" label="Partner email" type="email" placeholder="partner@email.com"
                value={data.partnerEmail} onChange={e => setData(p => ({...p, partnerEmail: e.target.value}))}/>
              <p className="text-xs text-neutral-500">We'll send them a confirmation link.</p>
            </div>
          )}
          {data.findPartner && (
            <p className="text-sm text-neutral-500">We'll match you with a player at your rating level by July 12.</p>
          )}
        </div>
      )}

      {/* T-shirt size */}
      <div>
        <label className="block text-[11px] uppercase tracking-[0.2em] font-bold mb-3">T-shirt size</label>
        <div className="flex gap-2 flex-wrap">
          {["XS","S","M","L","XL","XXL"].map(sz => (
            <button key={sz} onClick={() => setData(p => ({...p, tshirt: sz}))}
              className={`h-10 w-12 text-xs font-bold uppercase tracking-[0.1em] border transition-colors
                ${data.tshirt === sz ? "bg-black dark:bg-white text-white dark:text-black border-black dark:border-white" : "border-black/20 dark:border-white/20 hover:border-black/50"}`}>
              {sz}
            </button>
          ))}
        </div>
      </div>

      {/* Dietary */}
      <div>
        <label className="block text-[11px] uppercase tracking-[0.2em] font-bold mb-2">
          Dietary restrictions <span className="text-neutral-400">(optional)</span>
        </label>
        <textarea
          rows={2}
          placeholder="e.g. vegetarian, gluten-free"
          value={data.dietary}
          onChange={e => setData(p => ({...p, dietary: e.target.value}))}
          className="w-full px-4 py-3 border border-black/20 dark:border-white/20 bg-transparent outline-none text-sm resize-none focus:border-black dark:focus:border-white transition-all"
        />
      </div>
    </div>
  );
}

function Step3({ data, setStep, onSubmit, isSubmitting, errors }) {
  const [waiver, setWaiver] = React.useState(false);
  const [emailOpt, setEmailOpt] = React.useState(true);

  const singlesPrice = 50;
  const doublesAddon = (data.eventType === "doubles" || data.eventType === "both") ? 25 : 0;
  const processingFee = 2.50;
  const total = singlesPrice + doublesAddon + processingFee;

  const rows = [
    ["Full name", data.name],
    ["Email", data.email],
    ["Phone", data.phone],
    ["Date of birth", data.dob],
    ["Rating", `${data.ratingType} ${data.rating}`],
    ["Event", data.eventType ? data.eventType.charAt(0).toUpperCase() + data.eventType.slice(1) : "—"],
    ["T-shirt", data.tshirt || "—"],
  ];

  return (
    <div>
      <h2 className="font-display font-black text-2xl mb-6">Review &amp; pay</h2>

      {/* Summary table */}
      <div className="border border-black/10 dark:border-white/10 mb-6">
        {rows.map(([k, v], i) => (
          <div key={i} className="flex items-center justify-between px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0 text-sm">
            <span className="text-neutral-500 uppercase tracking-[0.15em] text-[10px]">{k}</span>
            <div className="flex items-center gap-2">
              <span className="font-medium">{v || "—"}</span>
              <button onClick={() => setStep(k === "Event" || k === "T-shirt" || k === "Dietary" ? 1 : 0)}
                className="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline">Edit</button>
            </div>
          </div>
        ))}
      </div>

      {/* Order summary */}
      <div className="bg-black text-white dark:bg-white dark:text-black p-6 mb-6">
        <div className="text-[11px] uppercase tracking-[0.25em] opacity-60 mb-4">Order summary</div>
        <div className="space-y-3 text-sm mb-5">
          <div className="flex justify-between"><span>Singles entry</span><span>${singlesPrice.toFixed(2)}</span></div>
          {doublesAddon > 0 && <div className="flex justify-between"><span>Doubles add-on</span><span>${doublesAddon.toFixed(2)}</span></div>}
          <div className="flex justify-between opacity-50"><span>Processing fee</span><span>${processingFee.toFixed(2)}</span></div>
        </div>
        <div className="flex justify-between items-baseline border-t border-white/15 dark:border-black/15 pt-4">
          <span className="text-[11px] uppercase tracking-[0.2em]">Total</span>
          <span className="font-display font-black text-3xl">${total.toFixed(2)}</span>
        </div>
      </div>

      {/* Waiver */}
      <label className="flex items-start gap-3 mb-3 cursor-pointer">
        <div
          onClick={() => setWaiver(v => !v)}
          className={`mt-0.5 h-5 w-5 border-2 grid place-items-center shrink-0 transition-colors ${waiver ? "bg-black dark:bg-white border-black dark:border-white" : "border-black/30 dark:border-white/30"}`}>
          {waiver && <IconCheck size={12} stroke={2.4} className="text-white dark:text-black"/>}
        </div>
        <span className="text-sm leading-relaxed">
          I agree to the <a href="#" className="text-coral underline">Tournament Rules</a>, <a href="#" className="text-coral underline">USTA Code of Conduct</a>, and <a href="#" className="text-coral underline">Refund Policy</a>.
          <span className="text-coral">*</span>
        </span>
      </label>
      {errors.waiver && <FieldError msg={errors.waiver}/>}

      <label className="flex items-start gap-3 mb-8 cursor-pointer">
        <div
          onClick={() => setEmailOpt(v => !v)}
          className={`mt-0.5 h-5 w-5 border-2 grid place-items-center shrink-0 transition-colors ${emailOpt ? "bg-black dark:bg-white border-black dark:border-white" : "border-black/30 dark:border-white/30"}`}>
          {emailOpt && <IconCheck size={12} stroke={2.4} className="text-white dark:text-black"/>}
        </div>
        <span className="text-sm text-neutral-500">Email me about future Gila tournaments.</span>
      </label>

      {/* Submit */}
      <button
        onClick={() => onSubmit(waiver)}
        disabled={!waiver || isSubmitting}
        className={`w-full h-14 font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center justify-center gap-3 transition-all active:scale-[0.98]
          ${waiver ? "bg-coral text-white hover:opacity-90" : "bg-black/10 dark:bg-white/10 text-neutral-400 cursor-not-allowed"}`}>
        <IconCreditCard size={18}/> Proceed to Payment · ${total.toFixed(2)}
      </button>

      {/* Trust signals */}
      <div className="mt-4 flex flex-wrap items-center justify-center gap-4 text-[10px] uppercase tracking-[0.2em] text-neutral-400">
        <span className="flex items-center gap-1"><IconShield size={12}/> 256-bit SSL</span>
        <span>·</span>
        <span>Powered by Stripe</span>
        <span>·</span>
        <span className="flex items-center gap-1"><IconArrow size={12}/> You'll be redirected to Stripe Checkout</span>
      </div>
    </div>
  );
}

function RegistrationForm({ tournament: t, formData, setFormData, onSubmit, isSubmitting }) {
  const [step, setStep] = React.useState(0);
  const [errors, setErrors] = React.useState({});
  const [dir, setDir] = React.useState(1);

  function validateStep(s) {
    const e = {};
    if (s === 0) {
      if (!formData.name?.trim()) e.name = "Name is required";
      if (!formData.email?.trim()) e.email = "Email is required";
      else if (!/^[^@]+@[^@]+\.[^@]+$/.test(formData.email)) e.email = "Enter a valid email";
      if (!formData.phone?.trim()) e.phone = "Phone is required";
      if (!formData.dob) e.dob = "Date of birth is required";
      if (!formData.rating) e.rating = "Rating is required";
    }
    if (s === 1) {
      if (!formData.eventType) e.eventType = "Select an event type";
    }
    return e;
  }

  function next() {
    const e = validateStep(step);
    if (Object.keys(e).length > 0) { setErrors(e); return; }
    setErrors({});
    setDir(1);
    setStep(s => s + 1);
  }

  function back() {
    setErrors({});
    setDir(-1);
    setStep(s => s - 1);
  }

  function handleSubmit(waiver) {
    if (!waiver) { setErrors({ waiver: "You must accept the terms to continue" }); return; }
    setErrors({});
    onSubmit();
  }

  const singlesPrice = 50;
  const doublesAddon = (formData.eventType === "doubles" || formData.eventType === "both") ? 25 : 0;
  const total = singlesPrice + doublesAddon + 2.50;

  return (
    <div>
      {/* Back bar */}
      <div className="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950">
        <div className="max-w-[1400px] mx-auto px-6 py-3">
          <button onClick={() => window._regGoDetail && window._regGoDetail()} className="flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] text-neutral-500 hover:text-coral transition-colors">
            <IconArrowLeft size={14}/> Back to tournament
          </button>
        </div>
      </div>

      <div className="max-w-[1400px] mx-auto px-6 pt-10 pb-24">
        <div className="grid lg:grid-cols-12 gap-8 items-start">
          {/* Summary rail */}
          <div className="lg:col-span-4 lg:sticky lg:top-[140px]">
            <div className="bg-neutral-100 dark:bg-neutral-900">
              <Placeholder label="indian wells · clay courts" aspect="16/9" tone="dark" className="w-full"/>
              <div className="p-6">
                <div className="text-[10px] uppercase tracking-[0.2em] text-coral font-bold mb-1">{t.surface} · {t.dates}</div>
                <div className="font-display font-black text-xl leading-tight mb-1">{t.name}</div>
                <div className="text-xs text-neutral-500 mb-5">{t.venue}</div>
                <div className="space-y-2 text-sm border-t border-black/10 dark:border-white/10 pt-4">
                  <div className="flex justify-between"><span className="opacity-50">Singles entry</span><span className="font-mono">${singlesPrice.toFixed(2)}</span></div>
                  {doublesAddon > 0 && <div className="flex justify-between"><span className="opacity-50">Doubles add-on</span><span className="font-mono">${doublesAddon.toFixed(2)}</span></div>}
                  <div className="flex justify-between"><span className="opacity-50">Processing fee</span><span className="font-mono">$2.50</span></div>
                  <div className="flex justify-between font-display font-black text-base pt-2 border-t border-black/10 dark:border-white/10">
                    <span>Total</span><span>${total.toFixed(2)}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Form */}
          <div className="lg:col-span-8">
            <ProgressRail step={step} setStep={(s) => { setDir(s < step ? -1 : 1); setStep(s); }}/>
            <div>
              {step === 0 && <Step1 data={formData} setData={setFormData} errors={errors}/>}
              {step === 1 && <Step2 data={formData} setData={setFormData} errors={errors}/>}
              {step === 2 && <Step3 data={formData} setStep={(s) => { setDir(-1); setStep(s); }} onSubmit={handleSubmit} isSubmitting={isSubmitting} errors={errors}/>}
            </div>
            {step < 2 && (
              <div className="mt-8 flex items-center gap-3">
                {step > 0 && (
                  <button onClick={back} className="h-14 px-6 border border-black/20 dark:border-white/20 font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 hover:border-black dark:hover:border-white transition-colors">
                    <IconArrowLeft size={16}/> Back
                  </button>
                )}
                <button onClick={next} className="h-14 px-8 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all hover:opacity-90">
                  {step === 1 ? "Review order" : "Next"} <IconArrow size={16}/>
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { RegistrationForm });
