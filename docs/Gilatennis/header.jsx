// Sticky header with search, categories, account/cart, and dark mode toggle.
function Header({ dark, setDark }) {
    const [searchOpen, setSearchOpen] = React.useState(false);
    const [mobile, setMobile] = React.useState(false);
    const cats = ["Rackets", "Strings", "Shoes", "Apparel", "Bags", "Balls"];

    return (
        <header className="sticky top-0 z-40 border-b border-black/10 dark:border-white/10 bg-white/85 dark:bg-neutral-950/85 backdrop-blur-sm">
            {/* Top utility strip */}
            <div className="border-b border-black/5 dark:border-white/5 text-[11px] tracking-[0.2em] uppercase">
                <div className="max-w-[1400px] mx-auto px-6 py-2 flex items-center justify-between text-neutral-600 dark:text-neutral-400">
                    <div className="hidden md:flex items-center gap-5">
                        <span className="flex items-center gap-1.5">
                            <span className="relative inline-flex">
                                <span className="absolute inline-flex h-2 w-2 rounded-full bg-coral animate-ping opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-coral"></span>
                            </span>
                            Live · Madrid Open R16
                        </span>
                        <span>Free shipping over $99</span>
                    </div>
                    <div className="flex items-center gap-5 ml-auto">
                        <a href="#" className="hover:text-coral">Pro Shop</a>
                        <a href="#" className="hover:text-coral">Stringing Service</a>
                        <a href="#" className="hover:text-coral">Help</a>
                    </div>
                </div>
            </div>

            {/* Main bar */}
            <div className="max-w-[1400px] mx-auto px-6 h-[72px] flex items-center gap-8">
                <button onClick={() => setMobile(true)} className="lg:hidden -ml-2 p-2">
                    <IconMenu size={22} />
                </button>

                <a href="#" className="flex items-center gap-2.5 shrink-0">
                    <span className="font-display font-black text-[22px] tracking-[-0.02em] text-black dark:text-white" style={{ fontFamily: "\"Inter Tight\"" }}>Gilatennis</span>
                </a>

                <nav className="hidden lg:flex items-center gap-7 text-[13.5px] font-medium">
                    {cats.map((c) =>
                        <a key={c} href="#" className="relative py-2 hover:text-coral transition-colors">
                            {c}
                        </a>
                    )}
                    <span className="w-px h-4 bg-black/15 dark:bg-white/15" />
                    <a href="#courts" className="py-2 hover:text-coral">Courts</a>
                    <a href="#tournaments" className="py-2 hover:text-coral">Tournaments</a>
                    <a href="#articles" className="py-2 hover:text-coral">Tips & Reviews</a>
                </nav>

                <div className="ml-auto flex items-center gap-1">
                    <button
                        onClick={() => setSearchOpen((v) => !v)}
                        className="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5"
                        aria-label="Search">

                        <IconSearch size={19} />
                    </button>
                    <button
                        onClick={() => setDark(!dark)}
                        className="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5"
                        aria-label="Toggle dark mode">

                        {dark ? <IconSun size={19} /> : <IconMoon size={19} />}
                    </button>
                    <button className="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5 hidden sm:grid">
                        <IconHeart size={19} />
                    </button>
                    <button className="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5 hidden sm:grid">
                        <IconUser size={19} />
                    </button>
                    <button className="relative h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5">
                        <IconBag size={19} />
                        <span className="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 rounded-full bg-coral text-white text-[10px] font-bold grid place-items-center">2</span>
                    </button>
                </div>
            </div>

            {/* Search drawer */}
            {searchOpen &&
                <div className="border-t border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950">
                    <div className="max-w-[1400px] mx-auto px-6 py-5">
                        <div className="flex items-center gap-3 border-b-2 border-black dark:border-white pb-3">
                            <IconSearch size={22} />
                            <input
                                autoFocus
                                placeholder="Search rackets, articles, tournaments…"
                                className="flex-1 bg-transparent outline-none text-lg font-display font-medium placeholder:text-neutral-400" />

                            <button onClick={() => setSearchOpen(false)} className="text-xs uppercase tracking-[0.2em] text-neutral-500 hover:text-coral">Close</button>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <span className="text-[11px] uppercase tracking-[0.2em] text-neutral-500 mr-2 self-center">Trending</span>
                            {["Babolat Pure Aero 2024", "Topspin forehand drills", "3.5 Tournaments NorCal", "Best clay court shoes", "Poly string tension"].map((t) =>
                                <button key={t} className="px-3 py-1.5 rounded-full border border-black/15 dark:border-white/15 text-xs hover:border-coral hover:text-coral">
                                    {t}
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            }

            {/* Mobile drawer */}
            {mobile &&
                <div className="fixed inset-0 h-screen z-[100] lg:hidden" onClick={() => setMobile(false)}>
                    <div className="absolute inset-0 bg-black/60"></div>
                    <div className="absolute left-0 top-0 bottom-0 w-[280px] bg-white dark:bg-neutral-950 shadow-2xl p-6 overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-6">
                            <span className="font-display font-black text-xl text-black dark:text-white" style={{ fontFamily: "\"Inter Tight\"" }}>Gilatennis</span>
                            <button onClick={() => setMobile(false)} className="text-black dark:text-white"><IconX size={20} /></button>
                        </div>
                        <nav className="flex flex-col gap-4 text-lg font-display font-semibold text-black dark:text-white">
                            {cats.map((c) => <a key={c} href="#" className="hover:text-coral">{c}</a>)}
                            <hr className="border-black/10 dark:border-white/10" />
                            <a href="#courts" className="hover:text-coral">Courts</a>
                            <a href="#tournaments" className="hover:text-coral">Tournaments</a>
                            <a href="#articles" className="hover:text-coral">Tips & Reviews</a>
                        </nav>
                    </div>
                </div>
            }
        </header>);

}

function Logo() {
    return (
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
            <circle cx="14" cy="14" r="13" fill="oklch(64.7% .217 57.6)" />
            <path d="M3.2 9.5c4 1.4 7.8 1.4 11.8 0s7.8-1.4 9.8 0M3.2 18.5c2-1.4 5.8-1.4 9.8 0s7.8 1.4 11.8 0"
                stroke="white" strokeWidth="1.4" strokeLinecap="round" fill="none" />
        </svg>);

}

window.Header = Header;
