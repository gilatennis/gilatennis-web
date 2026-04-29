// Minimal stroke icons (Lucide-style, hand-built so we don't need a CDN)
const I = ({ d, size = 18, stroke = 1.6, fill = "none", children, ...rest }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke="currentColor"
       strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" {...rest}>
    {d ? <path d={d} /> : children}
  </svg>
);

const IconSearch = (p) => <I {...p}><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></I>;
const IconUser = (p) => <I {...p}><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></I>;
const IconBag = (p) => <I {...p}><path d="M5 7h14l-1.2 13.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8L5 7Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></I>;
const IconHeart = (p) => <I {...p}><path d="M20.4 5.6a5.5 5.5 0 0 0-7.8 0L12 6.2l-.6-.6a5.5 5.5 0 1 0-7.8 7.8l.6.6L12 21.6l7.8-7.6.6-.6a5.5 5.5 0 0 0 0-7.8Z"/></I>;
const IconArrow = (p) => <I {...p}><path d="M5 12h14"/><path d="m13 5 7 7-7 7"/></I>;
const IconArrowUpRight = (p) => <I {...p}><path d="M7 17 17 7"/><path d="M8 7h9v9"/></I>;
const IconChevronRight = (p) => <I {...p}><path d="m9 6 6 6-6 6"/></I>;
const IconChevronLeft = (p) => <I {...p}><path d="m15 6-6 6 6 6"/></I>;
const IconChevronDown = (p) => <I {...p}><path d="m6 9 6 6 6-6"/></I>;
const IconClock = (p) => <I {...p}><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></I>;
const IconCalendar = (p) => <I {...p}><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></I>;
const IconMapPin = (p) => <I {...p}><path d="M12 21s7-6.3 7-12a7 7 0 1 0-14 0c0 5.7 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></I>;
const IconStar = (p) => <I {...p}><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.5 2.9 1-6.1L3 9.5l6.1-.9L12 3Z"/></I>;
const IconPlay = (p) => <I {...p} fill="currentColor"><path d="M7 5v14l12-7L7 5Z"/></I>;
const IconMenu = (p) => <I {...p}><path d="M4 7h16M4 12h16M4 17h16"/></I>;
const IconX = (p) => <I {...p}><path d="M6 6l12 12M18 6 6 18"/></I>;
const IconSun = (p) => <I {...p}><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.4 1.4M17.6 17.6 19 19M5 19l1.4-1.4M17.6 6.4 19 5"/></I>;
const IconMoon = (p) => <I {...p}><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></I>;
const IconBolt = (p) => <I {...p}><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></I>;
const IconTrophy = (p) => <I {...p}><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></I>;
const IconTarget = (p) => <I {...p}><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></I>;
const IconFire = (p) => <I {...p}><path d="M12 3s4 4 4 8a4 4 0 0 1-8 0c0-1 .5-2 1-2.5C9 10 8 12 8 14a4 4 0 0 0 8 0"/></I>;

const IconCheck = (p) => <I {...p}><path d="M20 6 9 17l-5-5"/></I>;
const IconAlert = (p) => <I {...p}><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></I>;
const IconArrowLeft = (p) => <I {...p}><path d="M19 12H5"/><path d="m11 5-7 7 7 7"/></I>;
const IconCreditCard = (p) => <I {...p}><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></I>;
const IconShield = (p) => <I {...p}><path d="M12 3 4 7v5c0 4.4 3.4 8.5 8 9.5 4.6-1 8-5.1 8-9.5V7l-8-4Z"/></I>;
const IconDownload = (p) => <I {...p}><path d="M12 3v13M5 16l7 7 7-7"/><path d="M3 21h18"/></I>;
const IconUsers = (p) => <I {...p}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></I>;

Object.assign(window, {
  IconSearch, IconUser, IconBag, IconHeart, IconArrow, IconArrowUpRight,
  IconChevronRight, IconChevronLeft, IconChevronDown, IconClock, IconCalendar,
  IconMapPin, IconStar, IconPlay, IconMenu, IconX, IconSun, IconMoon,
  IconBolt, IconTrophy, IconTarget, IconFire,
  IconCheck, IconAlert, IconArrowLeft, IconCreditCard, IconShield, IconDownload, IconUsers,
});
