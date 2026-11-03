// Fylder siden med indhold fra indhold.js. Normalt behøver du ikke røre denne fil.
const $ = (id) => document.getElementById(id);
const el = (tag, cls, text) => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text) e.textContent = text;
  return e;
};
const D = INDHOLD;

// Forside
if ($("f-undertitel")) $("f-undertitel").textContent = D.forside.undertitel;

// Fremhævet event
const aktuel = D.events.find((e) => e.fremhaevet);
if (aktuel && $("aktuelt")) {
  $("aktuelt").hidden = false;
  $("a-billede").src = aktuel.billede;
  $("a-billede").alt = aktuel.titel;
  $("a-titel").textContent = aktuel.titel;
  $("a-type").textContent = aktuel.type;
  $("a-datoer").textContent = aktuel.datoer;
  $("a-sted").textContent = aktuel.sted;
  $("a-spoergsmaal").textContent = aktuel.spoergsmaal || "";
  $("a-tekst").textContent = aktuel.tekst;
  if (aktuel.laesMereLink) {
    $("a-laesmere").href = aktuel.laesMereLink;
    $("a-laesmere").hidden = false;
  }
  if (aktuel.billetLink) {
    $("a-billet").href = aktuel.billetLink;
    $("a-billet").hidden = false;
  }
  if (aktuel.start) startCountdown(new Date(aktuel.start));
}

function startCountdown(target) {
  const box = $("countdown");
  const tick = () => {
    const diff = target - new Date();
    if (diff <= 0) { box.hidden = true; return; }
    box.hidden = false;
    const parts = {
      d: Math.floor(diff / 864e5),
      h: Math.floor(diff / 36e5) % 24,
      m: Math.floor(diff / 6e4) % 60,
      s: Math.floor(diff / 1e3) % 60,
    };
    box.querySelectorAll("[data-u]").forEach((b) => (b.textContent = String(parts[b.dataset.u]).padStart(2, "0")));
  };
  tick();
  setInterval(tick, 1000);
}

// Om – kort på forsiden
if ($("om-kort")) {
  $("om-overskrift").textContent = D.om.overskrift;
  $("om-kort").textContent = D.om.kort;
  $("om-knap").textContent = D.om.knap + " →";
}

// Om – hele siden (om.html)
if ($("om-afsnit")) {
  D.om.afsnit.forEach((a) => {
    const sec = el("section", "om-section");
    const w = el("div", "wrap om-grid");
    const head = el("div");
    head.append(el("h2", "big", a.overskrift));
    if (a.undertitel) head.append(el("p", "om-sub", a.undertitel));
    const body = el("div", "om-body");
    a.tekst.forEach((t) => body.append(el("p", "", t)));
    w.append(head, body);
    sec.append(w);
    $("om-afsnit").append(sec);
  });
}

// Event-liste. På forsiden står data-max="3", så kun de nyeste vises.
const list = $("event-list");
const max = list ? Number(list.dataset.max) || D.events.length : 0;
if (list) D.events.slice(0, max).forEach((e) => {
  const link = e.laesMereLink || e.billetLink;
  const row = el(link ? "a" : "div", "event-row");
  if (link) row.href = link;
  row.append(el("span", "event-year", e.datoer));
  const mid = el("div");
  mid.append(el("div", "event-title", e.titel), el("div", "event-type", e.type));
  row.append(mid);
  if (link) row.append(el("span", "event-more", "Læs mere →"));
  list.append(row);
});
if ($("alle-link") && D.events.length > max) $("alle-link").hidden = false;

// Vær med
if ($("m-overskrift")) {
  $("m-overskrift").textContent = D.medvirk.overskrift;
  $("m-tekst").textContent = D.medvirk.tekst;
  D.medvirk.roller.forEach((r) => $("m-roller").append(el("li", "", r)));
  $("m-knap").textContent = D.medvirk.knap;
  $("m-knap").href = "mailto:" + D.kontakt.email;
}

// Sponsorer (vises to gange så rullebåndet kører uendeligt)
const track = $("sponsor-track");
if (track) [...D.sponsorer, ...D.sponsorer].forEach((s) => {
  const item = el("figure", "sponsor");
  if (s.logo) {
    const img = el("img");
    img.src = s.logo;
    img.alt = "";
    img.onerror = () => img.remove();
    item.append(img);
  }
  item.append(el("figcaption", "", s.navn));
  track.append(item);
});

// Kontakt
$("k-email").textContent = D.kontakt.email;
$("k-email").href = "mailto:" + D.kontakt.email;
$("k-cvr").textContent = "CVR " + D.kontakt.cvr;
[["Instagram", D.kontakt.instagram], ["Facebook", D.kontakt.facebook]].forEach(([navn, url]) => {
  if (!url) return;
  const a = el("a", "", navn);
  a.href = url;
  a.target = "_blank";
  a.rel = "noopener";
  $("k-some").append(a);
});
$("year").textContent = new Date().getFullYear();

// Menu bliver mørk ved scroll + parallax på forsiden
const nav = document.querySelector(".nav");
const heroBg = document.querySelector(".hero-bg");
addEventListener("scroll", () => {
  nav.classList.toggle("scrolled", scrollY > 40);
  if (heroBg && scrollY < innerHeight) heroBg.style.transform = `translateY(${scrollY * 0.3}px)`;
}, { passive: true });

// Fade ind ved scroll
const io = new IntersectionObserver((entries) => {
  entries.forEach((en) => {
    if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
  });
}, { threshold: 0.15 });
document.querySelectorAll(".reveal").forEach((r) => io.observe(r));
