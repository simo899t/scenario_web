// Effekter på hjemmesiden. Indholdet kommer fra editoren (/admin) – denne fil behøver du ikke røre.
(function () {
  "use strict";
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const reduceMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Menu bliver mørk ved scroll + parallax på forsiden
  const nav = document.querySelector(".nav");
  const heroBg = document.querySelector(".hero-bg");
  let ticking = false;
  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      nav.classList.toggle("scrolled", scrollY > 40);
      if (heroBg && !reduceMotion && scrollY < innerHeight) heroBg.style.transform = `translateY(${scrollY * 0.3}px)`;
      ticking = false;
    });
  };
  addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  // Mobilmenu
  const toggle = document.querySelector(".nav-toggle");
  const setMenu = (open) => {
    document.body.classList.toggle("menu-open", open);
    toggle.setAttribute("aria-expanded", String(open));
  };
  toggle.addEventListener("click", () => setMenu(!document.body.classList.contains("menu-open")));
  $$(".nav-links a").forEach((a) => a.addEventListener("click", () => setMenu(false)));
  addEventListener("keydown", (e) => { if (e.key === "Escape") setMenu(false); });

  // Fade ind ved scroll
  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    $$(".reveal").forEach((r) => io.observe(r));
  } else {
    $$(".reveal").forEach((r) => r.classList.add("in"));
  }

  // Nedtælling til premieren
  $$(".countdown[data-start]").forEach((box) => {
    const target = new Date(box.dataset.start);
    const tick = () => {
      const diff = target - new Date();
      if (diff <= 0) { box.hidden = true; clearInterval(timer); return; }
      const parts = {
        d: Math.floor(diff / 864e5),
        h: Math.floor(diff / 36e5) % 24,
        m: Math.floor(diff / 6e4) % 60,
        s: Math.floor(diff / 1e3) % 60,
      };
      box.querySelectorAll("[data-u]").forEach((b) => (b.textContent = String(parts[b.dataset.u]).padStart(2, "0")));
    };
    const timer = setInterval(tick, 1000);
    tick();
  });

  // Billede der følger musen over forestillingslisten (kun med mus)
  const rows = $$(".event-row[data-img]");
  if (rows.length && matchMedia("(hover: hover) and (pointer: fine)").matches && !reduceMotion) {
    const peek = document.createElement("div");
    peek.className = "peek";
    peek.setAttribute("aria-hidden", "true");
    const img = document.createElement("img");
    img.alt = "";
    peek.append(img);
    document.body.append(peek);
    let x = 0, y = 0, px = 0, py = 0, raf = 0;
    const follow = () => {
      px += (x - px) * 0.18;
      py += (y - py) * 0.18;
      peek.style.left = px + "px";
      peek.style.top = py + "px";
      raf = Math.abs(x - px) + Math.abs(y - py) > 0.5 ? requestAnimationFrame(follow) : 0;
    };
    rows.forEach((row) => {
      row.addEventListener("mouseenter", (e) => {
        img.src = row.dataset.img;
        if (!peek.classList.contains("on")) { px = x = e.clientX + 200; py = y = e.clientY; }
        peek.classList.add("on");
      });
      row.addEventListener("mousemove", (e) => {
        x = e.clientX + 200; y = e.clientY;
        if (!raf) raf = requestAnimationFrame(follow);
      });
      row.addEventListener("mouseleave", () => peek.classList.remove("on"));
    });
    addEventListener("scroll", () => peek.classList.remove("on"), { passive: true });
  }

  // Lysboks til billedgalleriet
  const gallery = document.querySelector("[data-gallery]");
  if (gallery && typeof HTMLDialogElement === "function") {
    const links = $$("a", gallery);
    const dlg = document.createElement("dialog");
    dlg.className = "lightbox";
    dlg.setAttribute("aria-label", "Billedgalleri");
    dlg.innerHTML = '<img alt=""><button class="lb-close" type="button" aria-label="Luk">×</button>' +
      '<button class="lb-prev" type="button" aria-label="Forrige billede">‹</button>' +
      '<button class="lb-next" type="button" aria-label="Næste billede">›</button><p class="lb-count"></p>';
    document.body.append(dlg);
    const big = dlg.querySelector("img");
    let current = 0;
    const show = (i) => {
      current = (i + links.length) % links.length;
      big.src = links[current].getAttribute("href");
      big.alt = links[current].querySelector("img").alt;
      dlg.querySelector(".lb-count").textContent = (current + 1) + " / " + links.length;
    };
    links.forEach((a, i) => a.addEventListener("click", (e) => {
      e.preventDefault();
      show(i);
      dlg.showModal();
    }));
    if (links.length < 2) dlg.querySelectorAll(".lb-prev, .lb-next").forEach((b) => (b.hidden = true));
    dlg.querySelector(".lb-close").addEventListener("click", () => dlg.close());
    dlg.querySelector(".lb-prev").addEventListener("click", () => show(current - 1));
    dlg.querySelector(".lb-next").addEventListener("click", () => show(current + 1));
    dlg.addEventListener("click", (e) => { if (e.target === dlg) dlg.close(); });
    dlg.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") show(current - 1);
      if (e.key === "ArrowRight") show(current + 1);
    });
    let sx = 0;
    dlg.addEventListener("touchstart", (e) => { sx = e.touches[0].clientX; }, { passive: true });
    dlg.addEventListener("touchend", (e) => {
      const dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
    });
  }
})();
