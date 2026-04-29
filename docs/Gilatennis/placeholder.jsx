// Striped image placeholder with monospace caption
function Placeholder({ label = "image", className = "", aspect = null, tone = "dark", children }) {
  const isDark = tone === "dark";
  const bg = isDark ? "#0a0a0a" : "#e8e8e6";
  const stripe = isDark ? "rgba(255,255,255,0.04)" : "rgba(0,0,0,0.04)";
  const text = isDark ? "rgba(255,255,255,0.55)" : "rgba(0,0,0,0.45)";
  const style = {
    background: `repeating-linear-gradient(135deg, ${bg} 0 14px, ${stripe} 14px 28px)`,
  };
  if (aspect) style.aspectRatio = aspect;
  return (
    <div className={`relative overflow-hidden ${className}`} style={style}>
      <div className="absolute inset-0 flex items-end p-3">
        <span
          className="font-mono text-[10px] uppercase tracking-[0.18em] px-1.5 py-0.5"
          style={{ color: text, background: isDark ? "rgba(0,0,0,0.35)" : "rgba(255,255,255,0.6)", backdropFilter: "blur(2px)" }}
        >
          {label}
        </span>
      </div>
      {children}
    </div>
  );
}

window.Placeholder = Placeholder;
