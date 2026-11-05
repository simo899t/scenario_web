/*
 * Redigeringsværktøj til Teater Scenario.
 * Ren JavaScript uden build-trin – snakker med api.php.
 */
(() => {
  "use strict";

  const API = "api.php";
  const DRAFT_KEY = "scenario-kladde";
  const app = document.getElementById("app");

  const S = {
    csrf: "",
    bruger: null,
    skrivbar: true,
    maksUpload: 8 * 1024 * 1024,
    indhold: null,
    snapshot: "",
    dirty: false,
    saving: false,
    nye: new Set(), // forestillinger, der ikke er gemt endnu (deres id følger titlen)
    billeder: null,
  };

  /* =========================================================
     Små hjælpere
  ========================================================= */

  const ICONS = {
    grid: '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
    ticket: '<path d="M3 7a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4z"/><path d="M15 6v2M15 11v2M15 16v2"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.01"/>',
    megaphone: '<path d="M3 11v2a1 1 0 0 0 1 1h3l6 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
    heart: '<path d="M12 20s-7.5-4.6-9-9.5C2 7 4.5 4 7.5 4c2 0 3.5 1.2 4.5 2.8C13 5.2 14.5 4 16.5 4 19.5 4 22 7 21 10.5 19.5 15.4 12 20 12 20z"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.3a6.5 6.5 0 0 1 3.5 5.7"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    logout: '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
    up: '<path d="m6 15 6-6 6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    left: '<path d="m15 6-6 6 6 6"/>',
    right: '<path d="m9 6 6 6-6 6"/>',
    external: '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
    upload: '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
    check: '<path d="m5 12 5 5 9-10"/>',
    x: '<path d="M6 6l12 12M18 6 6 18"/>',
    alert: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17v.01"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="M3 3l18 18"/><path d="M10.6 6.1A10 10 0 0 1 12 6c5 0 9 6 9 6a17 17 0 0 1-2.6 3.2M6.5 7.6C4.2 9.2 3 12 3 12s4 6 9 6a9 9 0 0 0 4.4-1.2"/>',
    star: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
    key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l2 2"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/>',
    crop: '<path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/>',
    download: '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 20h16"/>',
    spark: '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
  };

  function icon(name, cls = "ico") {
    const s = document.createElement("span");
    s.className = cls;
    s.setAttribute("aria-hidden", "true");
    s.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[name] || "") + "</svg>";
    return s;
  }

  const PROPS = new Set(["value", "checked", "disabled", "hidden", "multiple", "required", "readOnly"]);

  function h(tag, props, ...kids) {
    const el = document.createElement(tag);
    if (props) {
      for (const [k, v] of Object.entries(props)) {
        if (v == null || v === false) continue;
        if (k === "class") el.className = v;
        else if (k === "text") el.textContent = v;
        else if (k.startsWith("on") && typeof v === "function") el.addEventListener(k.slice(2), v);
        else if (PROPS.has(k)) el[k] = v;
        else el.setAttribute(k, v === true ? "" : v);
      }
    }
    for (const k of kids.flat(Infinity)) {
      if (k == null || k === false || k === "") continue;
      el.append(k instanceof Node ? k : String(k));
    }
    return el;
  }

  let uidN = 0;
  const uid = () => "f" + ++uidN;
  const imgSrc = (p) => (/^https?:\/\//i.test(p) ? p : "../" + p);

  function slugify(v) {
    return String(v || "").toLowerCase()
      .replace(/æ/g, "ae").replace(/ø/g, "oe").replace(/å/g, "aa")
      .normalize("NFD").replace(/[̀-ͯ]/g, "")
      .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 60);
  }

  function uniqueId(base, taken) {
    base = base || "element";
    let id = base, n = 2;
    while (taken.includes(id)) id = base + "-" + n++;
    return id;
  }

  const MAANEDER = ["januar", "februar", "marts", "april", "maj", "juni", "juli", "august", "september", "oktober", "november", "december"];

  function startText(start) {
    if (!start) return "";
    const d = new Date(start);
    if (isNaN(d)) return "";
    return `${d.getDate()}. ${MAANEDER[d.getMonth()]} ${d.getFullYear()}`;
  }
  const whenText = (ev) => ev.datoer || startText(ev.start);
  const isUpcoming = (ev) => !!ev.start && new Date(ev.start) > new Date();

  function fmtTime(iso) {
    if (!iso) return "";
    const d = new Date(iso);
    if (isNaN(d)) return "";
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return "lige nu";
    if (diff < 3600) return `for ${Math.round(diff / 60)} min. siden`;
    const time = d.toLocaleTimeString("da-DK", { hour: "2-digit", minute: "2-digit" });
    if (d.toDateString() === now.toDateString()) return "i dag kl. " + time;
    const y = new Date(now);
    y.setDate(now.getDate() - 1);
    if (d.toDateString() === y.toDateString()) return "i går kl. " + time;
    const opts = { day: "numeric", month: "long" };
    if (d.getFullYear() !== now.getFullYear()) opts.year = "numeric";
    return d.toLocaleDateString("da-DK", opts) + " kl. " + time;
  }

  function move(arr, i, dir) {
    const j = i + dir;
    if (j < 0 || j >= arr.length) return false;
    [arr[i], arr[j]] = [arr[j], arr[i]];
    return true;
  }

  function makePassword() {
    const words = ["scene", "lys", "kulisse", "rolle", "sang", "dans", "musik", "billet", "applaus", "premiere", "replik", "kostume", "maske", "drama", "finale", "encore", "prolog", "regi", "rampe", "kor", "solo", "duet", "akt", "tango"];
    const r = new Uint32Array(5);
    crypto.getRandomValues(r);
    const w = [0, 1, 2, 3].map((i) => words[r[i] % words.length]);
    w[0] = w[0][0].toUpperCase() + w[0].slice(1);
    return w.join("-") + "-" + String(r[4] % 90 + 10);
  }

  /* =========================================================
     Server
  ========================================================= */

  function apiError(message, status, data) {
    const e = new Error(message);
    e.status = status;
    e.data = data || {};
    return e;
  }

  async function api(action, { body } = {}) {
    const opts = { method: body ? "POST" : "GET", headers: {}, credentials: "same-origin", cache: "no-store" };
    if (body) {
      opts.headers["Content-Type"] = "application/json";
      opts.headers["X-CSRF-Token"] = S.csrf;
      opts.body = JSON.stringify(body);
    }
    let res;
    try {
      res = await fetch(API + "?action=" + encodeURIComponent(action), opts);
    } catch {
      throw apiError("Ingen forbindelse til serveren. Tjek dit internet og prøv igen.", 0);
    }
    let data = null;
    try { data = await res.json(); } catch { /* ikke JSON */ }
    if (!res.ok) throw apiError((data && data.fejl) || `Serveren svarede med en fejl (${res.status}).`, res.status, data);
    return data || {};
  }

  function uploadRaw(file, type, onProgress) {
    return new Promise((resolve, reject) => {
      const fd = new FormData();
      fd.append("fil", file, file.name);
      fd.append("type", type);
      const x = new XMLHttpRequest();
      x.open("POST", API + "?action=upload");
      x.setRequestHeader("X-CSRF-Token", S.csrf);
      x.upload.onprogress = (e) => { if (e.lengthComputable && onProgress) onProgress(e.loaded / e.total); };
      x.onload = () => {
        let d = null;
        try { d = JSON.parse(x.responseText); } catch { /* ikke JSON */ }
        if (x.status >= 200 && x.status < 300 && d && d.sti) resolve(d);
        else reject(apiError((d && d.fejl) || (x.status === 413 ? "Billedet er for stort til serveren." : "Billedet kunne ikke uploades."), x.status, d));
      };
      x.onerror = () => reject(apiError("Ingen forbindelse til serveren.", 0));
      x.send(fd);
    });
  }

  /** Gør store billeder mindre i browseren før upload (hurtigere og undgår serverens størrelsesgrænse). */
  async function prepareImage(file, max) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || typeof createImageBitmap !== "function") return file;
    let bmp;
    try { bmp = await createImageBitmap(file, { imageOrientation: "from-image" }); } catch { return file; }
    const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
    if (scale === 1 && file.size < 2.5e6) { if (bmp.close) bmp.close(); return file; }
    const w = Math.round(bmp.width * scale), hh = Math.round(bmp.height * scale);
    const c = document.createElement("canvas");
    c.width = w;
    c.height = hh;
    c.getContext("2d").drawImage(bmp, 0, 0, w, hh);
    if (bmp.close) bmp.close();
    const toBlob = (type) => new Promise((r) => c.toBlob(r, type, 0.9));
    let blob = await toBlob("image/webp");
    if (!blob || blob.type !== "image/webp") blob = await toBlob(file.type === "image/jpeg" ? "image/jpeg" : "image/png");
    if (!blob || (scale === 1 && blob.size >= file.size)) return file;
    const ext = blob.type.split("/")[1].replace("jpeg", "jpg");
    return new File([blob], file.name.replace(/\.[^.]+$/, "") + "." + ext, { type: blob.type });
  }

  async function uploadImage(file, kind, onProgress) {
    if (!/^image\//.test(file.type) && !/\.(jpe?g|png|webp|gif)$/i.test(file.name)) {
      throw apiError(`“${file.name}” er ikke et billede. Brug JPG, PNG eller WebP.`, 400);
    }
    const ready = await prepareImage(file, kind === "logo" ? 800 : 2400);
    if (ready.size > S.maksUpload) {
      throw apiError(`Billedet er for stort (${(ready.size / 1048576).toFixed(1)} MB). Serveren tager højst ${Math.round(S.maksUpload / 1048576)} MB.`, 413);
    }
    const r = await uploadRaw(ready, kind, onProgress);
    S.billeder = null; // hent billedlisten igen næste gang
    return r;
  }

  /* =========================================================
     Beskæring
  ========================================================= */

  const FORMATER = [["fri", "Fri", 0], ["16:9", "Bred", 16 / 9], ["4:3", "4:3", 4 / 3], ["1:1", "Kvadrat", 1], ["4:5", "Stående", 4 / 5]];
  const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
  const isLocal = (p) => !!p && !/^https?:\/\//i.test(p);
  const cropOpts = (kind) => (kind === "logo" ? { aspect: "1:1", max: 800 } : { aspect: "fri", max: 2400 });

  function loadImg(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(apiError("Billedet kunne ikke åbnes.", 0));
      img.src = src;
    });
  }

  /**
   * Viser et vindue, hvor man kan beskære et billede.
   * source er enten en fil (nyt billede) eller en sti (et billede, der allerede er på siden).
   * Svarer med en fil, der skal uploades – eller null, hvis man fortryder.
   */
  async function cropImage(source, { aspect = "fri", max = 2400 } = {}) {
    const isFile = source instanceof Blob;
    if (isFile && !/^image\/(jpeg|png|webp)$/.test(source.type)) return source; // fx GIF bruges, som den er
    const url = isFile ? URL.createObjectURL(source) : imgSrc(source);
    let img;
    try {
      img = await loadImg(url);
    } catch (err) {
      if (isFile) { URL.revokeObjectURL(url); return source; }
      throw err;
    }
    const W = img.naturalWidth, H = img.naturalHeight;
    const MIN = Math.max(16, Math.min(W, H) * 0.04);
    let ratio = (FORMATER.find((f) => f[0] === aspect) || FORMATER[0])[2];
    let r = { x: 0, y: 0, w: W, h: H };
    const fit = () => {
      if (!ratio) { r = { x: 0, y: 0, w: W, h: H }; return; }
      let w = W, hh = W / ratio;
      if (hh > H) { hh = H; w = H * ratio; }
      r = { x: (W - w) / 2, y: (H - hh) / 2, w, h: hh };
    };
    fit();

    const shown = h("img", { src: url, alt: "", draggable: "false" });
    const shade = h("div", { class: "crop-shade" }, h("span"));
    const box = h("div", { class: "crop-box", tabindex: "0", "aria-label": "Beskæringsramme – flyt den med piletasterne" },
      h("span", { class: "crop-grid" }),
      ["nw", "n", "ne", "e", "se", "s", "sw", "w"].map((d) => h("span", { class: "crop-h crop-" + d, "data-d": d })));
    const stage = h("div", { class: "crop-stage" }, shown, shade, box);
    const sizeInfo = h("span", { class: "muted small" });
    const scale = () => (shown.clientWidth || W) / W;
    const draw = () => {
      const k = scale();
      const css = { left: r.x * k + "px", top: r.y * k + "px", width: r.w * k + "px", height: r.h * k + "px" };
      Object.assign(box.style, css);
      Object.assign(shade.firstChild.style, css);
      box.classList.toggle("locked", !!ratio);
      sizeInfo.textContent = `${Math.round(r.w)} × ${Math.round(r.h)} px`;
    };

    let drag = null;
    box.addEventListener("pointerdown", (e) => {
      e.preventDefault();
      box.setPointerCapture(e.pointerId);
      box.classList.add("dragging");
      drag = { d: e.target.dataset.d || "move", px: e.clientX, py: e.clientY, o: { ...r } };
    });
    box.addEventListener("pointermove", (e) => {
      if (!drag) return;
      const k = scale();
      const dx = (e.clientX - drag.px) / k, dy = (e.clientY - drag.py) / k;
      const o = drag.o, d = drag.d;
      if (d === "move") {
        r = { ...o, x: clamp(o.x + dx, 0, W - o.w), y: clamp(o.y + dy, 0, H - o.h) };
      } else if (ratio) {
        // Låst format: det modsatte hjørne står stille
        const wX = d.includes("e") ? o.w + dx : o.w - dx;
        const wY = (d.includes("s") ? o.h + dy : o.h - dy) * ratio;
        const maxW = d.includes("e") ? W - o.x : o.x + o.w;
        const maxH = d.includes("s") ? H - o.y : o.y + o.h;
        const w = clamp(Math.max(wX, wY), Math.max(MIN, MIN * ratio), Math.min(maxW, maxH * ratio));
        const hh = w / ratio;
        r = { x: d.includes("w") ? o.x + o.w - w : o.x, y: d.includes("n") ? o.y + o.h - hh : o.y, w, h: hh };
      } else {
        let L = o.x, T = o.y, R = o.x + o.w, B = o.y + o.h;
        if (d.includes("w")) L = clamp(o.x + dx, 0, R - MIN);
        if (d.includes("e")) R = clamp(R + dx, L + MIN, W);
        if (d.includes("n")) T = clamp(o.y + dy, 0, B - MIN);
        if (d.includes("s")) B = clamp(B + dy, T + MIN, H);
        r = { x: L, y: T, w: R - L, h: B - T };
      }
      draw();
    });
    const end = () => { drag = null; box.classList.remove("dragging"); };
    box.addEventListener("pointerup", end);
    box.addEventListener("pointercancel", end);
    box.addEventListener("keydown", (e) => {
      const k = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
      if (!k) return;
      e.preventDefault();
      const step = e.shiftKey ? 0.1 : 0.01;
      r = { ...r, x: clamp(r.x + k[0] * W * step, 0, W - r.w), y: clamp(r.y + k[1] * H * step, 0, H - r.h) };
      draw();
    });

    const chips = h("div", { class: "chips", role: "group", "aria-label": "Format" });
    const drawChips = () => chips.replaceChildren(...FORMATER.map(([, label, rt]) =>
      h("button", {
        type: "button", class: "chip" + (rt === ratio ? " on" : ""), "aria-pressed": String(rt === ratio),
        onclick: () => { ratio = rt; if (rt) fit(); draw(); drawChips(); },
      }, label)));
    drawChips();

    let ro = null;
    const choice = await modal({
      title: "Beskær billede",
      wide: true,
      body: (close) => h("div", { class: "stack" },
        chips,
        h("div", { class: "crop-wrap" }, stage),
        h("div", { class: "crop-foot" },
          h("p", { class: "help", text: "Træk i rammen for at flytte den, og i hjørnerne for at gøre den større eller mindre." }), sizeInfo),
        h("div", { class: "modal-actions flush" },
          h("button", { type: "button", class: "btn btn-secondary", onclick: () => close(null) }, "Annuller"),
          isFile && h("button", { type: "button", class: "btn btn-ghost", onclick: () => close("hele") }, "Brug hele billedet"),
          h("button", { type: "button", class: "btn btn-primary", onclick: () => close("beskaer") }, icon("crop"), "Beskær og brug"))),
      onOpen: () => {
        requestAnimationFrame(draw);
        if (typeof ResizeObserver === "function") { ro = new ResizeObserver(draw); ro.observe(shown); }
      },
    });
    if (ro) ro.disconnect();
    try {
      if (!choice) return null;
      if (choice === "hele") return source;
      const k = Math.min(1, max / Math.max(r.w, r.h));
      const c = document.createElement("canvas");
      c.width = Math.max(1, Math.round(r.w * k));
      c.height = Math.max(1, Math.round(r.h * k));
      const ctx = c.getContext("2d");
      ctx.imageSmoothingQuality = "high";
      ctx.drawImage(img, r.x, r.y, r.w, r.h, 0, 0, c.width, c.height);
      const jpeg = isFile ? source.type === "image/jpeg" : /\.jpe?g$/i.test(source);
      const toBlob = (type) => new Promise((res) => c.toBlob(res, type, 0.9));
      let blob = await toBlob("image/webp");
      if (!blob || blob.type !== "image/webp") blob = await toBlob(jpeg ? "image/jpeg" : "image/png");
      if (!blob) throw apiError("Billedet kunne ikke beskæres i denne browser.", 0);
      const base = (isFile ? source.name : source.split("/").pop()).replace(/\.[^.]+$/, "").replace(/-[0-9a-f]{6}$/, "");
      return new File([blob], base + "." + blob.type.split("/")[1].replace("jpeg", "jpg"), { type: blob.type });
    } finally {
      if (isFile) URL.revokeObjectURL(url);
    }
  }

  /* =========================================================
     Gem, kladde og ændringer
  ========================================================= */

  function setContent(c) {
    S.indhold = c;
    S.snapshot = JSON.stringify(c);
    S.dirty = false;
    renderSaveState();
  }

  let changeRaf = 0;
  function changed() {
    if (changeRaf) return;
    changeRaf = requestAnimationFrame(() => {
      changeRaf = 0;
      S.dirty = JSON.stringify(S.indhold) !== S.snapshot;
      renderSaveState();
      saveDraft();
    });
  }

  let draftTimer = 0;
  function saveDraft() {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(() => {
      try {
        if (S.dirty) {
          localStorage.setItem(DRAFT_KEY, JSON.stringify({ bruger: S.bruger.brugernavn, version: S.indhold.meta.version, tid: Date.now(), indhold: S.indhold }));
        } else localStorage.removeItem(DRAFT_KEY);
      } catch { /* privat browsing e.l. */ }
    }, 500);
  }

  function clearDraft() {
    clearTimeout(draftTimer);
    try { localStorage.removeItem(DRAFT_KEY); } catch { /* ignorér */ }
  }

  async function offerDraft() {
    let d = null;
    try { d = JSON.parse(localStorage.getItem(DRAFT_KEY) || "null"); } catch { /* ignorér */ }
    if (!d || !d.indhold || d.bruger !== S.bruger.brugernavn) return;
    if (JSON.stringify(d.indhold) === S.snapshot) { clearDraft(); return; }
    const newer = d.version !== S.indhold.meta.version;
    const ok = await confirmBox(
      "Du har ugemte ændringer",
      `Sidst du var her (${fmtTime(new Date(d.tid).toISOString())}), lavede du ændringer, som ikke blev gemt. Vil du hente dem tilbage?` +
        (newer ? " Bemærk: En anden har gemt siden da – hvis du gemmer dine, kan du overskrive deres." : ""),
      "Hent mine ændringer", false, "Nej, kassér dem"
    );
    if (ok) {
      S.indhold = d.indhold;
      changed();
      renderView(true);
      toast("Dine ændringer er hentet tilbage. Husk at gemme.");
    } else clearDraft();
  }

  async function save(force = false) {
    if (S.saving || (!S.dirty && !force)) return;
    S.saving = true;
    renderSaveState();
    try {
      const r = await api("gem", { body: { indhold: S.indhold, version: S.indhold.meta.version, tving: force } });
      setContent(r.indhold);
      clearDraft();
      S.nye.clear();
      renderView(true);
      toast("Gemt! Ændringerne er nu på hjemmesiden.");
    } catch (err) {
      S.saving = false;
      renderSaveState();
      if (err.status === 409) return await conflict(err.data);
      if (err.status === 401) {
        if (await relogin()) return await save(force);
        return;
      }
      toast(err.message, "error");
    } finally {
      S.saving = false;
      renderSaveState();
    }
  }

  async function discard() {
    if (!S.dirty) return;
    const ok = await confirmBox("Fortryd ændringerne?", "Alt, hvad du har ændret siden sidst du gemte, bliver sat tilbage.", "Fortryd ændringer", true);
    if (!ok) return;
    S.indhold = JSON.parse(S.snapshot);
    S.dirty = false;
    S.nye.clear();
    clearDraft();
    renderSaveState();
    renderView(true);
    toast("Ændringerne er fortrudt.");
  }

  async function conflict(d) {
    const v = await modal({
      title: "Nogen har gemt imens",
      body: h("p", { text: `${d.af || "En anden"} gemte ændringer ${fmtTime(d.gemt) || "for nylig"}, efter du åbnede editoren. Hvis du gemmer nu, overskriver du deres ændringer.` }),
      actions: [
        { label: "Annuller", value: null },
        { label: "Hent deres version", value: "hent" },
        { label: "Overskriv med mine", value: "tving", cls: "btn-danger" },
      ],
    });
    if (v === "tving") return save(true);
    if (v === "hent") {
      try {
        const r = await api("indhold");
        setContent(r.indhold);
        clearDraft();
        S.nye.clear();
        renderView(true);
        toast("Den nyeste version er hentet.");
      } catch (err) { handleError(err); }
    }
  }

  function handleError(err) {
    if (err.status === 401) { relogin(); return; }
    toast(err.message || "Noget gik galt.", "error");
  }

  /* =========================================================
     Dialoger og beskeder
  ========================================================= */

  let toastBox = null;
  function toast(msg, type = "ok") {
    if (!toastBox || !toastBox.isConnected) {
      toastBox = h("div", { class: "toasts", role: "status", "aria-live": "polite" });
      document.body.append(toastBox);
    }
    const t = h("div", { class: "toast toast-" + type }, icon(type === "ok" ? "check" : "alert"), h("span", { text: msg }));
    toastBox.append(t);
    setTimeout(() => {
      t.classList.add("out");
      setTimeout(() => t.remove(), 300);
    }, type === "error" ? 7000 : 3500);
  }

  function modal({ title, body, actions, wide, onOpen }) {
    return new Promise((resolve) => {
      const dlg = h("dialog", { class: "modal" + (wide ? " modal-wide" : ""), "aria-label": title });
      let done = false;
      const close = (v) => {
        if (done) return;
        done = true;
        dlg.close();
        dlg.remove();
        resolve(v);
      };
      const content = typeof body === "function" ? body(close) : body;
      dlg.append(...[
        h("div", { class: "modal-head" },
          h("h2", { text: title }),
          h("button", { type: "button", class: "icon-btn", "aria-label": "Luk", onclick: () => close(null) }, icon("x"))),
        h("div", { class: "modal-body" }, content),
        actions && h("div", { class: "modal-actions" }, actions.map((a) =>
          h("button", { type: "button", class: "btn " + (a.cls || "btn-secondary"), onclick: () => close(a.value) }, a.label))),
      ].filter(Boolean));
      dlg.addEventListener("cancel", (e) => { e.preventDefault(); close(null); });
      document.body.append(dlg);
      dlg.showModal();
      if (onOpen) onOpen(dlg);
    });
  }

  function confirmBox(title, text, okLabel, danger, cancelLabel = "Annuller") {
    return modal({
      title,
      body: h("p", { text }),
      actions: [
        { label: cancelLabel, value: false },
        { label: okLabel, value: true, cls: danger ? "btn-danger" : "btn-primary" },
      ],
    }).then((v) => v === true);
  }

  function promptBox(title, label, placeholder, okLabel) {
    return modal({
      title,
      body: (close) => {
        const id = uid();
        const inp = h("input", { id, class: "input", placeholder, autocomplete: "off" });
        return h("form", { class: "stack", onsubmit: (e) => { e.preventDefault(); if (inp.value.trim()) close(inp.value.trim()); } },
          h("label", { class: "label", for: id, text: label }), inp,
          h("div", { class: "modal-actions flush" },
            h("button", { type: "button", class: "btn btn-secondary", onclick: () => close(null) }, "Annuller"),
            h("button", { type: "submit", class: "btn btn-primary" }, okLabel)));
      },
      onOpen: (dlg) => dlg.querySelector("input").focus(),
    });
  }

  /**
   * Vælg billeder, der allerede ligger på serveren.
   * opt.event: forestillingen, der redigeres (giver fanen "Denne forestilling")
   * opt.multiple: vælg flere på én gang (svarer med en liste)
   */
  function pickImage(kind, opt = {}) {
    const multiple = !!opt.multiple;
    const ev = opt.event || null;
    return modal({
      title: multiple ? "Vælg billeder" : "Vælg et billede",
      wide: true,
      body: (close) => {
        let filter = null;
        const chosen = new Set();
        const tabs = h("div", { class: "chips" });
        const grid = h("div", { class: "picker-grid" }, h("div", { class: "loading" }, h("div", { class: "spinner" }), "Henter billeder…"));
        const fileInp = h("input", { type: "file", accept: "image/jpeg,image/png,image/webp,image/gif", hidden: true });
        const upBtn = h("button", { type: "button", class: "btn btn-secondary btn-sm", onclick: () => fileInp.click() }, icon("upload"), h("span", { text: "Upload nyt" }));
        const addBtn = multiple ? h("button", { type: "button", class: "btn btn-primary", disabled: true, onclick: () => close([...chosen]) }, icon("check"), h("span", { text: "Tilføj valgte" })) : null;
        const updateAdd = () => {
          if (!addBtn) return;
          addBtn.disabled = !chosen.size;
          addBtn.lastChild.textContent = chosen.size ? `Tilføj ${chosen.size} ${chosen.size === 1 ? "billede" : "billeder"}` : "Tilføj valgte";
        };
        fileInp.addEventListener("change", async () => {
          const f = fileInp.files[0];
          fileInp.value = "";
          if (!f) return;
          let ready = null;
          try { ready = await cropImage(f, cropOpts(kind)); } catch (err) { handleError(err); return; }
          if (!ready) return;
          upBtn.disabled = true;
          upBtn.lastChild.textContent = "Uploader…";
          try {
            const sti = (await uploadImage(ready, kind)).sti;
            close(multiple ? [sti] : sti);
          } catch (err) {
            handleError(err);
            upBtn.disabled = false;
            upBtn.lastChild.textContent = "Upload nyt";
          }
        });
        const lists = () => {
          const all = S.billeder;
          const own = ev ? [...new Set([ev.billede, ...ev.galleri].filter(Boolean))] : [];
          return {
            ubrugte: all.filter((b) => b.mappe === "uploads" && !usages(b.sti).length),
            forestilling: own.map((p) => all.find((b) => b.sti === p) || { sti: p }),
            alle: all,
          };
        };
        const EMPTY = {
          ubrugte: "Der er ingen ubrugte billeder. Upload et nyt, eller se under “Alle”.",
          forestilling: "Forestillingen har ingen billeder endnu.",
          alle: "Der er ingen billeder endnu. Upload et nyt.",
        };
        const draw = () => {
          const L = lists();
          if (!filter) filter = L.ubrugte.length ? "ubrugte" : L.forestilling.length ? "forestilling" : "alle";
          const defs = [["ubrugte", "Ikke i brug"], ev && ["forestilling", "Denne forestilling"], ["alle", "Alle"]].filter(Boolean);
          tabs.replaceChildren(...defs.map(([id, label]) =>
            h("button", { type: "button", class: "chip" + (filter === id ? " on" : ""), "aria-pressed": String(filter === id), onclick: () => { filter = id; draw(); } }, `${label} (${L[id].length})`)));
          const list = L[filter];
          grid.replaceChildren();
          if (!list.length) grid.append(h("p", { class: "muted", text: EMPTY[filter] }));
          for (const b of list) {
            const item = h("button", {
              type: "button", class: "picker-item" + (chosen.has(b.sti) ? " on" : ""), title: fileName(b.sti),
              "aria-pressed": multiple ? String(chosen.has(b.sti)) : null,
              onclick: () => {
                if (!multiple) return close(b.sti);
                if (chosen.has(b.sti)) chosen.delete(b.sti); else chosen.add(b.sti);
                item.classList.toggle("on", chosen.has(b.sti));
                item.setAttribute("aria-pressed", String(chosen.has(b.sti)));
                updateAdd();
              },
            }, h("img", { src: imgSrc(b.sti), alt: "", loading: "lazy" }), multiple && h("span", { class: "picker-check" }, icon("check")));
            grid.append(item);
          }
          updateAdd();
        };
        (async () => {
          try {
            S.billeder = (await api("billeder")).billeder;
            draw();
          } catch (err) {
            grid.replaceChildren(h("p", { class: "muted", text: err.message }));
          }
        })();
        return h("div", { class: "stack" },
          h("div", { class: "picker-bar" }, tabs, upBtn, fileInp),
          h("p", { class: "help", text: multiple ? "Klik på billederne for at vælge dem, og tryk så “Tilføj”." : "Klik på et billede for at bruge det." }),
          grid,
          multiple && h("div", { class: "modal-actions flush picker-actions" },
            h("button", { type: "button", class: "btn btn-secondary", onclick: () => close(null) }, "Annuller"), addBtn));
      },
    });
  }

  async function relogin() {
    if (relogin.open) return relogin.open;
    relogin.open = modal({
      title: "Log ind igen",
      body: (close) => {
        const err = h("p", { class: "form-error", role: "alert", hidden: true });
        const user = h("input", { class: "input", value: S.bruger ? S.bruger.brugernavn : "", autocomplete: "username", "aria-label": "Brugernavn" });
        const pw = h("input", { class: "input", type: "password", autocomplete: "current-password", "aria-label": "Kodeord", placeholder: "Kodeord" });
        const btn = h("button", { type: "submit", class: "btn btn-primary" }, "Log ind");
        return h("form", {
          class: "stack",
          onsubmit: async (e) => {
            e.preventDefault();
            btn.disabled = true;
            err.hidden = true;
            try {
              const st = await api("status");
              S.csrf = st.csrf;
              const r = await api("login", { body: { brugernavn: user.value, kodeord: pw.value } });
              S.csrf = r.csrf;
              S.bruger = r.bruger;
              close(true);
              toast("Du er logget ind igen. Dine ændringer er ikke gået tabt.");
            } catch (ex) {
              err.textContent = ex.message;
              err.hidden = false;
              btn.disabled = false;
            }
          },
        },
        h("p", { text: "Du har været væk et stykke tid og er blevet logget ud. Log ind igen – dine ændringer er gemt i mellemtiden." }),
        user, pw, err, h("div", { class: "modal-actions flush" }, btn));
      },
      onOpen: (dlg) => dlg.querySelector('input[type="password"]').focus(),
    }).then((v) => { relogin.open = null; return v === true; });
    return relogin.open;
  }

  /* =========================================================
     Formularfelter
  ========================================================= */

  function field({ label, help, id, cls }, ...control) {
    return h("div", { class: "field" + (cls ? " " + cls : "") },
      label && h("label", { class: "label", for: id, text: label }),
      control,
      help && h("p", { class: "help", text: help }));
  }

  function textInput(obj, key, o = {}) {
    const id = uid();
    const inp = h("input", {
      id, class: "input", type: o.type || "text", value: obj[key] || "", placeholder: o.placeholder,
      autocomplete: o.autocomplete || "off", inputmode: o.inputmode, spellcheck: o.type === "url" || o.type === "email" ? "false" : null,
      oninput: (e) => { obj[key] = e.target.value; if (o.onInput) o.onInput(e.target.value); changed(); },
    });
    return field({ label: o.label, help: o.help, id, cls: o.cls }, inp);
  }

  function autosize(ta) {
    ta.style.height = "auto";
    ta.style.height = ta.scrollHeight + 2 + "px";
  }

  function textArea(obj, key, o = {}) {
    const id = uid();
    const ta = h("textarea", {
      id, class: "input textarea", rows: o.rows || 4, placeholder: o.placeholder,
      oninput: (e) => { obj[key] = e.target.value; autosize(e.target); changed(); },
    });
    ta.value = obj[key] || "";
    requestAnimationFrame(() => { if (ta.isConnected) autosize(ta); });
    const help = o.help === undefined ? "Lav en tom linje for at starte et nyt afsnit." : o.help;
    return field({ label: o.label, help, id, cls: o.cls }, ta);
  }

  function selectField(obj, key, options, o = {}) {
    const id = uid();
    const sel = h("select", {
      id, class: "input select",
      onchange: (e) => { obj[key] = e.target.value; if (o.onChange) o.onChange(e.target.value); changed(); },
    }, options.map(([v, l]) => h("option", { value: v, selected: obj[key] === v }, l)));
    return field({ label: o.label, help: o.help, id, cls: o.cls }, sel);
  }

  function toggle(obj, key, o = {}) {
    const inp = h("input", {
      type: "checkbox", role: "switch", class: "switch-input", checked: !!obj[key],
      onchange: (e) => { obj[key] = e.target.checked; if (o.onChange) o.onChange(e.target.checked); changed(); },
    });
    return h("label", { class: "toggle" }, inp, h("span", { class: "switch", "aria-hidden": "true" }),
      h("span", { class: "toggle-text" }, h("strong", { text: o.label }), o.help && h("span", { class: "help", text: o.help })));
  }

  function dropzone(el, onFiles) {
    let depth = 0;
    el.addEventListener("dragenter", (e) => { if ([...e.dataTransfer.types].includes("Files")) { e.preventDefault(); depth++; el.classList.add("drag"); } });
    el.addEventListener("dragover", (e) => { if ([...e.dataTransfer.types].includes("Files")) e.preventDefault(); });
    el.addEventListener("dragleave", () => { if (--depth <= 0) { depth = 0; el.classList.remove("drag"); } });
    el.addEventListener("drop", (e) => {
      e.preventDefault();
      depth = 0;
      el.classList.remove("drag");
      const files = [...e.dataTransfer.files].filter((f) => /^image\//.test(f.type));
      if (files.length) onFiles(files);
      else toast("Træk et billede (JPG, PNG eller WebP) herind.", "error");
    });
  }

  function imageField(obj, key, o = {}) {
    const wrap = h("div", { class: "field" });
    const kind = o.kind || "billede";
    const render = () => {
      const val = obj[key];
      const bar = h("span");
      const progress = h("div", { class: "progress", hidden: true }, bar);
      const box = h("div", { class: "image-field" + (val ? "" : " empty") + (o.compact ? " compact" : "") });
      const fileInp = h("input", { type: "file", accept: "image/jpeg,image/png,image/webp,image/gif", hidden: true });
      const send = async (f) => {
        progress.hidden = false;
        box.classList.add("busy");
        try {
          const r = await uploadImage(f, kind, (p) => { bar.style.width = Math.round(p * 100) + "%"; });
          obj[key] = r.sti;
          changed();
          if (o.onChange) o.onChange(r.sti);
          render();
          toast("Billedet er uploadet.");
        } catch (err) {
          handleError(err);
          progress.hidden = true;
          box.classList.remove("busy");
        }
      };
      const doUpload = async (f, existing) => {
        let ready = null;
        try { ready = await cropImage(existing || f, cropOpts(kind)); } catch (err) { handleError(err); return; }
        if (ready) send(ready);
      };
      fileInp.addEventListener("change", () => { const f = fileInp.files[0]; fileInp.value = ""; if (f) doUpload(f); });
      const preview = val
        ? h("img", { src: imgSrc(val), alt: "" })
        : h("button", { type: "button", class: "image-empty", onclick: () => fileInp.click() }, icon("image"),
            h("span", { text: o.compact ? "Upload logo" : "Klik for at uploade – eller træk et billede herind" }));
      const choose = async () => {
        const p = await pickImage(kind, { event: o.event });
        if (p) { obj[key] = p; changed(); if (o.onChange) o.onChange(p); render(); }
      };
      box.append(
        h("div", { class: "image-preview" }, preview, progress),
        h("div", { class: "image-actions" },
          h("button", { type: "button", class: "btn btn-secondary btn-sm", onclick: () => fileInp.click(), title: "Upload et billede fra din computer eller telefon" }, icon("upload"), o.compact ? "Upload" : val ? "Upload nyt" : "Upload billede"),
          h("button", { type: "button", class: "btn btn-ghost btn-sm", onclick: choose, title: "Vælg et billede, der allerede er uploadet" }, icon("image"), o.compact ? "Vælg" : "Vælg fra billeder"),
          isLocal(val) && h("button", { type: "button", class: "btn btn-ghost btn-sm", onclick: () => doUpload(null, val), "aria-label": "Beskær billedet", title: "Beskær billedet" }, icon("crop"), o.compact ? "" : "Beskær"),
          val && h("button", { type: "button", class: "btn btn-ghost btn-sm danger", onclick: () => { obj[key] = ""; changed(); if (o.onChange) o.onChange(""); render(); }, "aria-label": "Fjern billedet", title: "Fjern billedet" }, icon("x"), o.compact ? "" : "Fjern"),
          fileInp)
      );
      dropzone(box, (files) => doUpload(files[0]));
      wrap.replaceChildren(o.label ? h("span", { class: "label", text: o.label }) : "", box, o.help ? h("p", { class: "help", text: o.help }) : "");
    };
    render();
    return wrap;
  }

  function galleryField(arr, o = {}) {
    const wrap = h("div", { class: "field" });
    const status = h("p", { class: "help", "aria-live": "polite" });
    const render = () => {
      const grid = h("ul", { class: "gallery-grid" });
      arr.forEach((p, i) => {
        grid.append(h("li", {},
          h("img", { src: imgSrc(p), alt: `Billede ${i + 1}` }),
          h("div", { class: "gallery-tools" },
            h("button", { type: "button", class: "icon-btn", "aria-label": "Flyt til venstre", title: "Flyt til venstre", disabled: i === 0, onclick: () => { move(arr, i, -1); changed(); render(); } }, icon("left")),
            h("button", { type: "button", class: "icon-btn", "aria-label": "Flyt til højre", title: "Flyt til højre", disabled: i === arr.length - 1, onclick: () => { move(arr, i, 1); changed(); render(); } }, icon("right")),
            isLocal(p) && h("button", { type: "button", class: "icon-btn", "aria-label": "Beskær", title: "Beskær", onclick: () => recrop(i) }, icon("crop")),
            h("button", { type: "button", class: "icon-btn danger", "aria-label": "Fjern billedet", title: "Fjern billedet", onclick: () => { arr.splice(i, 1); changed(); render(); } }, icon("trash")))));
      });
      const fileInp = h("input", { type: "file", multiple: true, accept: "image/jpeg,image/png,image/webp,image/gif", hidden: true });
      fileInp.addEventListener("change", () => { const files = [...fileInp.files]; fileInp.value = ""; addFiles(files); });
      grid.append(h("li", { class: "gallery-add" },
        h("button", { type: "button", onclick: () => fileInp.click() }, icon("plus"), h("span", { text: "Tilføj billeder" })), fileInp));
      dropzone(grid, addFiles);
      wrap.replaceChildren(
        h("div", { class: "label-row" }, h("span", { class: "label", text: o.label }),
          h("button", {
            type: "button", class: "btn btn-ghost btn-sm",
            onclick: async () => {
              const ps = await pickImage("billede", { event: o.event, multiple: true });
              const nye = (ps || []).filter((p) => !arr.includes(p));
              if (!nye.length) return;
              arr.push(...nye);
              changed();
              render();
              toast(nye.length === 1 ? "Billedet er tilføjet." : `${nye.length} billeder er tilføjet.`);
            },
          }, icon("image"), "Vælg fra billeder")),
        grid, status, o.help && h("p", { class: "help", text: o.help }));
    };
    async function recrop(i) {
      try {
        const f = await cropImage(arr[i], cropOpts("billede"));
        if (!f) return;
        status.textContent = "Gemmer det beskårne billede…";
        arr[i] = (await uploadImage(f, "billede")).sti;
        changed();
        render();
        toast("Billedet er beskåret.");
      } catch (err) { handleError(err); }
      status.textContent = "";
    }
    async function addFiles(files) {
      if (files.length === 1) { // ét billede ad gangen: tilbyd at beskære det
        let ready = null;
        try { ready = await cropImage(files[0], cropOpts("billede")); } catch (err) { handleError(err); return; }
        if (!ready) return;
        files = [ready];
      }
      let ok = 0;
      for (const [n, f] of files.entries()) {
        status.textContent = `Uploader billede ${n + 1} af ${files.length}…`;
        try { arr.push((await uploadImage(f, "billede")).sti); ok++; changed(); } catch (err) { handleError(err); }
      }
      status.textContent = "";
      render();
      if (ok) toast(ok === 1 ? "Billedet er tilføjet." : `${ok} billeder er tilføjet.`);
    }
    render();
    return wrap;
  }

  function tagsField(arr, o = {}) {
    const id = uid();
    const box = h("div", { class: "tags" });
    const inp = h("input", { id, class: "tags-input", placeholder: o.placeholder, autocomplete: "off" });
    const add = () => {
      const v = inp.value.replace(/,/g, "").trim();
      if (!v) return false;
      arr.push(v);
      inp.value = "";
      changed();
      render();
      return true;
    };
    inp.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === ",") { e.preventDefault(); add(); inp.focus(); }
      else if (e.key === "Backspace" && !inp.value && arr.length) { arr.pop(); changed(); render(); inp.focus(); }
    });
    inp.addEventListener("blur", add);
    box.addEventListener("click", (e) => { if (e.target === box) inp.focus(); });
    const render = () => {
      box.replaceChildren(...arr.map((t, i) => h("span", { class: "tag" }, t,
        h("button", { type: "button", "aria-label": `Fjern “${t}”`, onclick: () => { arr.splice(i, 1); changed(); render(); inp.focus(); } }, icon("x")))), inp);
    };
    render();
    return field({ label: o.label, help: o.help, id }, box);
  }

  function rowsField(arr, o) {
    const wrap = h("div", { class: "field" });
    const render = (focusLast) => {
      const list = h("div", { class: "rows" });
      if (arr.length) {
        list.append(h("div", { class: "row row-head", "aria-hidden": "true" }, o.cols.map((c) => h("span", { text: c.label })), h("span")));
      }
      arr.forEach((row, i) => {
        list.append(h("div", { class: "row" },
          o.cols.map((c) => h("input", {
            class: "input", value: row[c.key] || "", placeholder: c.placeholder, "aria-label": `${c.label} (række ${i + 1})`,
            oninput: (e) => { row[c.key] = e.target.value; changed(); },
          })),
          h("div", { class: "row-tools" },
            h("button", { type: "button", class: "icon-btn", "aria-label": "Flyt op", title: "Flyt op", disabled: i === 0, onclick: () => { move(arr, i, -1); changed(); render(); } }, icon("up")),
            h("button", { type: "button", class: "icon-btn", "aria-label": "Flyt ned", title: "Flyt ned", disabled: i === arr.length - 1, onclick: () => { move(arr, i, 1); changed(); render(); } }, icon("down")),
            h("button", { type: "button", class: "icon-btn danger", "aria-label": "Fjern", title: "Fjern", onclick: () => { arr.splice(i, 1); changed(); render(); } }, icon("trash")))));
      });
      wrap.replaceChildren(list, h("button", { type: "button", class: "btn btn-secondary btn-sm", onclick: () => { arr.push(o.make()); changed(); render(true); } }, icon("plus"), o.addLabel));
      if (focusLast) {
        const inputs = list.querySelectorAll(".row:last-child input");
        if (inputs[0]) inputs[0].focus();
      }
    };
    render();
    return wrap;
  }

  function moveTools(arr, i, after, labels = ["Flyt op", "Flyt ned"]) {
    return [
      h("button", { type: "button", class: "icon-btn", "aria-label": labels[0], title: labels[0], disabled: i === 0, onclick: () => { move(arr, i, -1); changed(); after(); } }, icon("up")),
      h("button", { type: "button", class: "icon-btn", "aria-label": labels[1], title: labels[1], disabled: i === arr.length - 1, onclick: () => { move(arr, i, 1); changed(); after(); } }, icon("down")),
    ];
  }

  /* =========================================================
     Byggeklodser til siderne
  ========================================================= */

  function pageHead(title, intro, ...actions) {
    return h("header", { class: "page-head" },
      h("div", {}, h("h1", { text: title }), intro && h("p", { class: "intro", text: intro })),
      actions.length ? h("div", { class: "page-actions" }, actions) : null);
  }

  function card(title, desc, ...content) {
    return h("section", { class: "card" },
      title && h("h2", { class: "card-title", text: title }),
      desc && h("p", { class: "card-desc", text: desc }),
      content);
  }

  const grid2 = (...kids) => h("div", { class: "grid-2" }, kids);

  function thumb(src, cls = "thumb") {
    return src ? h("img", { class: cls, src: imgSrc(src), alt: "", loading: "lazy" }) : h("span", { class: cls + " thumb-empty" }, icon("image"));
  }

  function badge(text, kind = "") {
    return h("span", { class: "badge" + (kind ? " badge-" + kind : ""), text });
  }

  function emptyState(ic, title, text, action) {
    return h("div", { class: "empty" }, icon(ic, "empty-ico"), h("h2", { text: title }), h("p", { text }), action);
  }

  /* =========================================================
     Sider i editoren
  ========================================================= */

  function viewOversigt() {
    const c = S.indhold;
    const fornavn = (S.bruger.navn || "").split(" ")[0];
    const idx = c.events.findIndex((e) => e.fremhaevet && !e.skjult);
    const aktuel = c.events[idx];
    const action = (ic, title, text, opts) =>
      h(opts.href ? "a" : "button", { class: "quick", type: opts.href ? null : "button", href: opts.href, target: opts.target, rel: opts.target ? "noopener" : null, onclick: opts.onclick },
        icon(ic, "quick-ico"), h("span", { class: "quick-text" }, h("strong", { text: title }), h("span", { text })));

    return [
      pageHead(`Hej ${fornavn}!`, "Her retter du alt indhold på hjemmesiden. Vælg et område i menuen, ret teksten, og tryk på “Gem og udgiv”, når du er færdig."),
      h("div", { class: "dash" },
        aktuel
          ? h("a", { class: "card feature-card", href: `#/forestillinger/${idx}` },
              aktuel.billede
                ? h("div", { class: "feature-img", style: `--img:url("${imgSrc(aktuel.billede).replace(/["\\]/g, "")}")` }, h("img", { src: imgSrc(aktuel.billede), alt: "" }))
                : thumb("", "feature-img"),
              h("div", { class: "feature-body" },
                h("span", { class: "eyebrow" }, icon("star"), "Aktuel på forsiden"),
                h("strong", { class: "feature-title", text: aktuel.titel }),
                h("span", { class: "muted", text: [whenText(aktuel), aktuel.sted].filter(Boolean).join(" · ") }),
                h("span", { class: "link-like", text: "Redigér forestillingen →" })))
          : h("a", { class: "card feature-card feature-none", href: "#/forside" },
              h("div", { class: "feature-body" },
                h("span", { class: "eyebrow" }, icon("star"), "Aktuel på forsiden"),
                h("strong", { class: "feature-title", text: "Ingen forestilling er valgt" }),
                h("span", { class: "muted", text: "Vælg hvilken forestilling, der skal vises stort på forsiden." }),
                h("span", { class: "link-like", text: "Vælg forestilling →" }))),
        h("div", { class: "quick-grid" },
          action("plus", "Tilføj forestilling", "Med billeder, tekst og billetlink", { onclick: newEvent }),
          action("heart", "Tilføj sponsor", "Logo, navn og link", { onclick: addSponsor }),
          action("info", "Ret teksten om os", "Forsiden og siden “Om os”", { href: "#/om" }),
          action("external", "Se hjemmesiden", "Åbner i en ny fane", { href: "../", target: "_blank" }))),
      h("div", { class: "stats" },
        stat(c.events.filter((e) => !e.skjult).length, "forestillinger på siden", "#/forestillinger"),
        stat(c.sponsorer.length, "sponsorer og partnere", "#/sponsorer"),
        stat(c.om.afsnit.length, "afsnit på “Om os”", "#/om")),
      card("Sådan gør du", null,
        h("ol", { class: "steps" },
          h("li", {}, h("strong", { text: "Ret" }), " – vælg et område i menuen og skriv direkte i felterne."),
          h("li", {}, h("strong", { text: "Gem" }), " – tryk på “Gem og udgiv” nederst. Så er ændringerne på hjemmesiden med det samme."),
          h("li", {}, h("strong", { text: "Fortryd" }), " – kom du til at slette noget? Under “Historik” kan du hente enhver tidligere version tilbage.")),
        h("p", { class: "muted small", text: c.meta.gemt ? `Sidst gemt ${fmtTime(c.meta.gemt)} af ${c.meta.af}.` : "Indholdet er ikke blevet ændret i editoren endnu." })),
    ];
  }

  function stat(n, label, href) {
    return h("a", { class: "stat", href }, h("strong", { text: String(n) }), h("span", { text: label }));
  }

  function viewForside() {
    const c = S.indhold;
    const synlige = c.events.filter((e) => !e.skjult);
    const valgt = { id: (synlige.find((e) => e.fremhaevet) || {}).id || "" };
    return [
      pageHead("Forside", "Toppen af forsiden og den forestilling, der vises stort lige under.",
        h("a", { class: "btn btn-secondary", href: "../", target: "_blank", rel: "noopener" }, icon("external"), "Se forsiden")),
      card("Toppen af siden", "Det første, man ser: logoet på et stort baggrundsbillede.",
        textInput(c.forside, "undertitel", { label: "Undertitel under logoet", placeholder: "fx Teater for unge" }),
        imageField(c.forside, "baggrund", { label: "Baggrundsbillede", help: "Brug et stort, liggende billede. Det bliver mørkere i bunden, så logoet kan ses." })),
      card("Aktuel forestilling", "Vises som en stor “billet” med nedtælling, tekst og knapper.",
        selectField(valgt, "id", [["", "Ingen – skjul feltet på forsiden"], ...synlige.map((e) => [e.id, e.titel || "Uden titel"])], {
          label: "Hvilken forestilling skal vises?",
          onChange: (id) => c.events.forEach((e) => { e.fremhaevet = !!id && e.id === id; }),
        }),
        textInput(c.forside, "aktuelOverskrift", { label: "Lille overskrift over forestillingen", placeholder: "fx Næste forestilling" })),
      card("Google og deling", "Bruges, når siden vises i Google, eller når den deles på fx Facebook.",
        textInput(c.generelt, "sidenavn", { label: "Sidens navn", placeholder: "Teater Scenario" }),
        textArea(c.generelt, "beskrivelse", { label: "Kort beskrivelse", rows: 2, help: "Én til to sætninger om, hvad Teater Scenario er." })),
    ];
  }

  function newEvent() {
    promptBox("Ny forestilling", "Hvad hedder forestillingen?", "fx Final Call", "Opret").then((titel) => {
      if (!titel) return;
      const evs = S.indhold.events;
      const ev = {
        id: uniqueId(slugify(titel) || "forestilling", evs.map((e) => e.id)),
        titel, type: "", datoer: "", start: "", sted: "", adresse: "", spoergsmaal: "", tekst: "", beskrivelse: "",
        billede: "", galleri: [], hold: [], video: "", billetLink: "", proeveplan: "", fremhaevet: false, skjult: false,
      };
      evs.unshift(ev);
      S.nye.add(ev.id);
      changed();
      if (location.hash === "#/forestillinger/0") renderView();
      else location.hash = "#/forestillinger/0";
    });
  }

  function viewForestillinger() {
    const c = S.indhold;
    const evs = c.events;
    const list = h("ul", { class: "item-list" });
    evs.forEach((ev, i) => {
      list.append(h("li", { class: "item" + (ev.skjult ? " is-hidden" : "") },
        h("a", { class: "item-main", href: `#/forestillinger/${i}` },
          thumb(ev.billede),
          h("span", { class: "item-text" },
            h("strong", { text: ev.titel || "Uden titel" }),
            h("span", { class: "muted", text: [whenText(ev), ev.type].filter(Boolean).join(" · ") || "Ingen dato endnu" }),
            h("span", { class: "badges" },
              ev.fremhaevet && !ev.skjult && badge("På forsiden", "accent"),
              isUpcoming(ev) && badge("Kommende", "ok"),
              ev.skjult && badge("Skjult", "muted"),
              S.nye.has(ev.id) && badge("Ny – ikke gemt", "warn")))),
        h("div", { class: "item-tools" },
          moveTools(evs, i, () => renderView(true)),
          h("a", { class: "btn btn-secondary btn-sm", href: `#/forestillinger/${i}` }, "Redigér"))));
    });
    return [
      pageHead("Forestillinger", "Rækkefølgen her er også rækkefølgen på hjemmesiden – den øverste vises først. Brug pilene til at flytte rundt.",
        h("button", { type: "button", class: "btn btn-primary", onclick: newEvent }, icon("plus"), "Ny forestilling")),
      evs.length ? list : emptyState("ticket", "Ingen forestillinger endnu", "Opret den første forestilling – så kommer den på hjemmesiden, når du gemmer.",
        h("button", { type: "button", class: "btn btn-primary", onclick: newEvent }, icon("plus"), "Ny forestilling")),
      card("Tekster", "Overskriften bruges på forsiden og på siden med alle forestillinger.",
        textInput(c.forestillingerTekst, "overskrift", { label: "Overskrift", placeholder: "Forestillinger" }),
        textArea(c.forestillingerTekst, "intro", { label: "Intro på siden “Forestillinger” (valgfri)", rows: 2 })),
    ];
  }

  function viewEvent(i) {
    const evs = S.indhold.events;
    const ev = evs[i];
    if (!ev) { location.hash = "#/forestillinger"; return []; }
    const titleEl = h("h1", { text: ev.titel || "Uden titel" });
    const isNew = () => S.nye.has(ev.id);
    const del = async () => {
      const ok = await confirmBox(`Slet “${ev.titel || "forestillingen"}”?`, "Forestillingen forsvinder fra hjemmesiden, når du gemmer. Du kan altid hente den tilbage under Historik.", "Slet forestilling", true);
      if (!ok) return;
      evs.splice(evs.indexOf(ev), 1);
      S.nye.delete(ev.id);
      changed();
      location.hash = "#/forestillinger";
      toast("Forestillingen er slettet. Husk at gemme.");
    };
    return [
      h("a", { class: "back", href: "#/forestillinger" }, icon("left"), "Alle forestillinger"),
      h("header", { class: "page-head" },
        h("div", {}, titleEl, h("p", { class: "intro", text: "Udfyld det, du har. Tomme felter bliver bare ikke vist på hjemmesiden." })),
        h("div", { class: "page-actions" },
          !isNew() && !ev.skjult && h("a", { class: "btn btn-secondary", href: "../forestilling.php?id=" + encodeURIComponent(ev.id), target: "_blank", rel: "noopener" }, icon("external"), "Se siden"),
          h("button", { type: "button", class: "btn btn-ghost danger", onclick: del }, icon("trash"), "Slet"))),
      card("Det vigtigste", null,
        grid2(
          textInput(ev, "titel", {
            label: "Titel", placeholder: "fx Final Call",
            onInput: (v) => {
              titleEl.textContent = v || "Uden titel";
              if (isNew()) {
                S.nye.delete(ev.id);
                ev.id = uniqueId(slugify(v) || "forestilling", evs.filter((e) => e !== ev).map((e) => e.id));
                S.nye.add(ev.id);
              }
            },
          }),
          textInput(ev, "type", { label: "Type", placeholder: "fx Musical, Teaterkoncert, Workshop" }),
          textInput(ev, "datoer", { label: "Datoer, som de skal stå på siden", placeholder: "fx 17. – 19. december 2026" }),
          textInput(ev, "start", { label: "Første forestilling starter", type: "datetime-local", help: "Bruges til nedtællingen og til mærket “Kommende”. Kan stå tomt." }),
          textInput(ev, "sted", { label: "Sted", placeholder: "fx Den Fynske Opera" }),
          textInput(ev, "adresse", { label: "Adresse", placeholder: "fx Jernbanegade 22, 5000 Odense C", help: "Bliver til et link til Google Maps." }))),
      card("Tekst", null,
        textInput(ev, "spoergsmaal", { label: "Overskrift til tekst (kan være tomt)", placeholder: "fx Hvis verden sluttede om fire timer – hvem ville du så være?", help: "Vises med stor skrift." }),
        textArea(ev, "tekst", { label: "Kort tekst", rows: 3, help: "Vises på forsiden og øverst på forestillingens side." }),
        textArea(ev, "beskrivelse", { label: "Lang beskrivelse", rows: 8, help: "Vises kun på forestillingens egen side. Lav en tom linje for at starte et nyt afsnit." })),
      card("Billeder", null,
        imageField(ev, "billede", { event: ev, label: "Hovedbillede", help: "Vises på forsiden, i oversigten og øverst på forestillingens side." }),
        galleryField(ev.galleri, { event: ev, label: "Galleri", help: "Fx billeder fra prøver og forestillinger. Man kan klikke på dem for at se dem i stor størrelse." })),
      card("Medvirkende og hold", null,
        rowsField(ev.hold, {
          cols: [{ key: "rolle", label: "Rolle eller funktion", placeholder: "fx Instruktør" }, { key: "navn", label: "Navn", placeholder: "fx Anna Hansen" }],
          addLabel: "Tilføj person",
          make: () => ({ rolle: "", navn: "" }),
        })),
      card("Billetter, prøveplan og video", null,
        textInput(ev, "billetLink", { label: "Link til billetsalg", type: "url", placeholder: "https://…", help: "Når der står et link her, vises knappen “Køb billet”." }),
        textInput(ev, "proeveplan", { label: "Link til prøveplan", type: "url", placeholder: "https://…", help: "Fx et link til et Google Docs-dokument eller en PDF. Når der står et link her, vises knappen “Se prøveplan”." }),
        textInput(ev, "video", { label: "Video fra YouTube eller Vimeo", type: "url", placeholder: "https://www.youtube.com/watch?v=…", help: "Fx en trailer. Kopiér linket fra YouTube og sæt det ind her." })),
      card("Visning", null,
        toggle(ev, "fremhaevet", {
          label: "Vis som aktuel forestilling på forsiden",
          help: "Den store “billet” på forsiden. Kun én forestilling kan vises ad gangen.",
          onChange: (v) => { if (v) evs.forEach((e) => { if (e !== ev) e.fremhaevet = false; }); },
        }),
        toggle(ev, "skjult", { label: "Skjul fra hjemmesiden", help: "Brug det, mens du skriver den færdig – eller hvis den skal væk uden at blive slettet." })),
    ];
  }

  function viewOm() {
    const om = S.indhold.om;
    const list = h("div", { class: "stack-lg" });
    om.afsnit.forEach((a, i) => {
      list.append(h("section", { class: "card" },
        h("div", { class: "card-head" },
          h("h2", { class: "card-title", text: `Afsnit ${i + 1}${a.overskrift ? ": " + a.overskrift : ""}` }),
          h("div", { class: "item-tools" },
            moveTools(om.afsnit, i, () => renderView(true)),
            h("button", {
              type: "button", class: "icon-btn danger", "aria-label": "Slet afsnit", title: "Slet afsnit",
              onclick: async () => {
                if (!(await confirmBox("Slet afsnittet?", `“${a.overskrift || "Afsnit " + (i + 1)}” bliver fjernet fra siden “Om os”, når du gemmer.`, "Slet afsnit", true))) return;
                om.afsnit.splice(i, 1);
                changed();
                renderView(true);
              },
            }, icon("trash")))),
        grid2(
          textInput(a, "overskrift", { label: "Overskrift", placeholder: "fx Visioner og mål" }),
          textInput(a, "undertitel", { label: "Undertitel (valgfri)", placeholder: "fx For dig, der vil mere med scenekunst" })),
        textArea(a, "tekst", { label: "Tekst", rows: 8 })));
    });
    return [
      pageHead("Om os", "Den korte tekst på forsiden og hele siden “Om os”.",
        h("a", { class: "btn btn-secondary", href: "../om.php", target: "_blank", rel: "noopener" }, icon("external"), "Se siden")),
      card("På forsiden", "Vises på forsiden med en knap videre til siden “Om os”.",
        textInput(om, "overskrift", { label: "Overskrift", placeholder: "Om scenario" }),
        textArea(om, "kort", { label: "Kort tekst", rows: 3 }),
        textInput(om, "knap", { label: "Tekst på knappen", placeholder: "Læs mere om Scenario" })),
      h("div", { class: "section-head" },
        h("h2", { text: "Afsnit på siden “Om os”" }),
        h("button", { type: "button", class: "btn btn-secondary btn-sm", onclick: () => { om.afsnit.push({ overskrift: "", undertitel: "", tekst: "" }); changed(); renderView(true); focusLast(".stack-lg > .card", "input"); } }, icon("plus"), "Tilføj afsnit")),
      om.afsnit.length ? list : emptyState("info", "Ingen afsnit", "Tilføj et afsnit for at skrive om foreningen.", null),
    ];
  }

  function viewMedvirk() {
    const m = S.indhold.medvirk;
    const mail = S.indhold.kontakt.email;
    return [
      pageHead("Vær med", "Den røde sektion på forsiden, hvor nye folk inviteres med."),
      card(null, null,
        textInput(m, "overskrift", { label: "Overskrift", placeholder: "Vil du være med?" }),
        textArea(m, "tekst", { label: "Tekst", rows: 3 }),
        tagsField(m.roller, { label: "Ting man kan være med til", placeholder: "Skriv og tryk Enter…", help: "Vises som små mærker. Tryk på × for at fjerne et." }),
        grid2(
          textInput(m, "knap", { label: "Tekst på knappen", placeholder: "Skriv til os" }),
          textInput(m, "link", { label: "Knappen går til", type: "url", placeholder: mail ? `mail til ${mail}` : "https://…", help: mail ? `Lad stå tomt for at åbne en mail til ${mail}. Du kan også indsætte et link, fx til en tilmeldingsformular.` : "Fx et link til en tilmeldingsformular." }))),
      card("Billeder", "Vises under teksten i den røde sektion. Man kan klikke på dem for at se dem i stor størrelse.",
        galleryField(m.billeder, { label: "Billeder", help: "Fx billeder fra prøver, bag scenen eller holdet. Brug pilene til at ændre rækkefølgen." })),
    ];
  }

  function addSponsor() {
    const list = S.indhold.sponsorer;
    list.push({ id: uniqueId("sponsor", list.map((s) => s.id)), navn: "", logo: "", link: "", niveau: "sponsor" });
    changed();
    afterRender = () => focusLast(".sponsor-card", "input.input");
    if (parseHash().name === "sponsorer") renderView(true);
    else location.hash = "#/sponsorer";
  }

  function focusLast(cardSel, inputSel) {
    requestAnimationFrame(() => {
      const cards = document.querySelectorAll(cardSel);
      const last = cards[cards.length - 1];
      if (!last) return;
      last.scrollIntoView({ behavior: "smooth", block: "center" });
      const inp = last.querySelector(inputSel);
      if (inp) inp.focus({ preventScroll: true });
    });
  }

  function viewSponsorer() {
    const c = S.indhold;
    const list = c.sponsorer;
    const grid = h("ul", { class: "sponsor-grid" });
    list.forEach((s, i) => {
      grid.append(h("li", { class: "card sponsor-card" + (s.niveau === "hoved" ? " is-main" : "") },
        imageField(s, "logo", { compact: true, kind: "logo" }),
        textInput(s, "navn", { label: "Navn", placeholder: "fx Odense Kommune" }),
        textInput(s, "link", { label: "Hjemmeside (valgfri)", type: "url", placeholder: "fx odense.dk" }),
        selectField(s, "niveau", [["hoved", "Hovedsponsor"], ["sponsor", "Sponsor"], ["netvaerk", "Netværk / partner"]], { label: "Type", onChange: () => renderView(true) }),
        h("div", { class: "sponsor-tools" },
          moveTools(list, i, () => renderView(true), ["Flyt frem", "Flyt tilbage"]),
          h("button", {
            type: "button", class: "btn btn-ghost btn-sm danger",
            onclick: async () => {
              if (!(await confirmBox(`Fjern ${s.navn || "sponsoren"}?`, "Sponsoren forsvinder fra hjemmesiden, når du gemmer.", "Fjern", true))) return;
              list.splice(i, 1);
              changed();
              renderView(true);
            },
          }, icon("trash"), "Fjern"))));
    });
    grid.append(h("li", { class: "sponsor-add" }, h("button", { type: "button", onclick: addSponsor }, icon("plus"), h("span", { text: "Tilføj sponsor" }))));
    return [
      pageHead("Sponsorer", "Hovedsponsorer vises stort øverst. Sponsorer og netværk kører i det rullende bånd i den rækkefølge, de står her.",
        h("button", { type: "button", class: "btn btn-primary", onclick: addSponsor }, icon("plus"), "Tilføj sponsor")),
      grid,
      card("Overskrift og tekst", null,
        textInput(c.sponsorTekst, "overskrift", { label: "Overskrift", placeholder: "Sponsorer & netværk" }),
        textArea(c.sponsorTekst, "tekst", { label: "Tekst under overskriften (valgfri)", rows: 2, help: "Fx en tak til alle, der støtter foreningen." })),
    ];
  }

  function viewKontakt() {
    const k = S.indhold.kontakt;
    return [
      pageHead("Kontakt", "Bunden af alle sider. Felter, der står tomme, bliver ikke vist."),
      card("Kontaktoplysninger", null,
        grid2(
          textInput(k, "overskrift", { label: "Overskrift", placeholder: "Kontakt" }),
          textInput(k, "email", { label: "E-mail", type: "email", placeholder: "kontakt@teaterscenario.dk" }),
          textInput(k, "telefon", { label: "Telefon", type: "tel", placeholder: "fx 12 34 56 78" }),
          textInput(k, "cvr", { label: "CVR-nummer", inputmode: "numeric" })),
        textInput(k, "adresse", { label: "Adresse", placeholder: "fx Vejnavn 1, 5000 Odense C" }),
        textArea(k, "tekst", { label: "Kort tekst (valgfri)", rows: 2 })),
      card("Sociale medier", "Indsæt linket til jeres profil. Der vises en knap for hvert felt, der er udfyldt.",
        grid2(
          textInput(k, "instagram", { label: "Instagram", type: "url", placeholder: "https://instagram.com/…" }),
          textInput(k, "facebook", { label: "Facebook", type: "url", placeholder: "https://facebook.com/…" }),
          textInput(k, "tiktok", { label: "TikTok", type: "url", placeholder: "https://tiktok.com/@…" }),
          textInput(k, "youtube", { label: "YouTube", type: "url", placeholder: "https://youtube.com/@…" }))),
    ];
  }

  /** Steder på hjemmesiden, hvor et billede bruges (ud fra det, der står i editoren lige nu). */
  function usages(path) {
    const c = S.indhold, out = [];
    if (c.forside.baggrund === path) out.push(["Forsiden – baggrundsbillede", "#/forside"]);
    c.events.forEach((ev, i) => {
      const t = ev.titel || "Forestilling uden titel";
      if (ev.billede === path) out.push([`${t} – hovedbillede`, `#/forestillinger/${i}`]);
      if (ev.galleri.includes(path)) out.push([`${t} – galleri`, `#/forestillinger/${i}`]);
    });
    if (c.medvirk.billeder.includes(path)) out.push(["Vær med – billeder", "#/medvirk"]);
    c.sponsorer.forEach((sp) => { if (sp.logo === path) out.push([`Sponsor: ${sp.navn || "uden navn"}`, "#/sponsorer"]); });
    return out;
  }

  const fmtBytes = (n) => (n >= 1048576 ? (n / 1048576).toFixed(1).replace(".", ",") + " MB" : Math.max(1, Math.round(n / 1024)) + " KB");
  const fileName = (p) => p.split("/").pop();

  function viewBilleder() {
    let filter = "alle";
    const wrap = h("div", { class: "stack-lg" }, h("div", { class: "card" }, h("div", { class: "loading" }, h("div", { class: "spinner" }), "Henter billeder…")));
    const status = h("p", { class: "help", "aria-live": "polite" });
    const fileInp = h("input", { type: "file", multiple: true, accept: "image/jpeg,image/png,image/webp,image/gif", hidden: true });

    const load = async () => {
      try {
        S.billeder = (await api("billeder")).billeder;
        draw();
      } catch (err) {
        wrap.replaceChildren(h("p", { class: "muted", text: err.message }));
        if (err.status === 401) relogin().then((ok) => ok && load());
      }
    };
    const tiles = (list) => h("ul", { class: "media-grid" }, list.map((b, i) =>
      h("li", {},
        h("button", { type: "button", class: "media-tile", "aria-label": `Åbn ${fileName(b.sti)}`, onclick: () => viewer(list, i, load) },
          h("img", { src: imgSrc(b.sti), alt: "", loading: "lazy" }),
          usages(b.sti).length > 0 && badge("I brug", "ok")),
        h("span", { class: "media-name", title: fileName(b.sti), text: fileName(b.sti) }))));
    const draw = () => {
      const up = S.billeder.filter((b) => b.mappe === "uploads");
      const fast = S.billeder.filter((b) => b.mappe !== "uploads");
      const unused = up.filter((b) => !usages(b.sti).length);
      const list = filter === "ubrugte" ? unused : up;
      const grid = tiles(list);
      dropzone(grid, addFiles);
      wrap.replaceChildren(
        h("section", { class: "card" },
          h("div", { class: "card-head" },
            h("h2", { class: "card-title", text: "Uploadede billeder" }),
            h("div", { class: "chips" }, [["alle", `Alle (${up.length})`], ["ubrugte", `Ikke i brug (${unused.length})`]].map(([id, label]) =>
              h("button", { type: "button", class: "chip" + (filter === id ? " on" : ""), "aria-pressed": String(filter === id), onclick: () => { filter = id; draw(); } }, label)))),
          list.length ? grid : h("p", { class: "muted", text: filter === "ubrugte" ? "Alle uploadede billeder bruges på hjemmesiden." : "Der er ikke uploadet nogen billeder endnu. Træk billeder herind eller tryk på “Upload billeder”." }),
          status),
        fast.length > 0 && h("section", { class: "card" },
          h("h2", { class: "card-title", text: "Faste billeder" }),
          h("p", { class: "card-desc", text: "En del af hjemmesidens design. De kan bruges i felterne, men ikke slettes her." }),
          tiles(fast)));
    };
    async function addFiles(files) {
      let ok = 0;
      for (const [n, f] of files.entries()) {
        status.textContent = `Uploader billede ${n + 1} af ${files.length}…`;
        try { await uploadImage(f, "billede"); ok++; } catch (err) { handleError(err); }
      }
      status.textContent = "";
      if (ok) toast(ok === 1 ? "Billedet er uploadet." : `${ok} billeder er uploadet.`);
      load();
    }
    fileInp.addEventListener("change", () => { const files = [...fileInp.files]; fileInp.value = ""; addFiles(files); });
    load();
    return [
      pageHead("Billeder", "Alle billeder på hjemmesiden. Klik på et billede for at se det stort, hente det eller slette det.",
        h("button", { type: "button", class: "btn btn-primary", onclick: () => fileInp.click() }, icon("upload"), "Upload billeder"), fileInp),
      wrap,
    ];
  }

  /** Stor visning af et billede med info, hent og slet. */
  function viewer(list, start, onDeleted) {
    let i = start;
    modal({
      title: "Billede",
      wide: true,
      body: (close) => {
        const box = h("div", { class: "viewer" });
        const go = (step) => { i = (i + step + list.length) % list.length; render(); };
        const del = async () => {
          const b = list[i];
          if (!(await confirmBox("Slet billedet?", `“${fileName(b.sti)}” bliver slettet permanent fra serveren. Det kan ikke fortrydes.`, "Slet billede", true))) return;
          try {
            S.billeder = (await api("slet-billede", { body: { sti: b.sti } })).billeder;
            close(null);
            toast("Billedet er slettet.");
            if (onDeleted) onDeleted();
          } catch (err) { handleError(err); }
        };
        const render = () => {
          const b = list[i];
          const uses = usages(b.sti);
          const deletable = b.mappe === "uploads";
          box.replaceChildren(
            h("div", { class: "viewer-img" },
              h("img", { src: imgSrc(b.sti), alt: fileName(b.sti) }),
              list.length > 1 && h("button", { type: "button", class: "viewer-nav prev", "aria-label": "Forrige billede", onclick: () => go(-1) }, icon("left")),
              list.length > 1 && h("button", { type: "button", class: "viewer-nav next", "aria-label": "Næste billede", onclick: () => go(1) }, icon("right"))),
            h("div", { class: "viewer-info" },
              h("strong", { class: "viewer-name", text: fileName(b.sti) }),
              h("span", { class: "muted small", text: [
                b.bredde ? `${b.bredde} × ${b.hoejde} px` : "",
                b.str ? fmtBytes(b.str) : "",
                deletable && b.tid ? "uploadet " + fmtTime(new Date(b.tid * 1000).toISOString()) : "",
                list.length > 1 ? `${i + 1} af ${list.length}` : "",
              ].filter(Boolean).join(" · ") }),
              uses.length
                ? h("div", { class: "viewer-uses" }, h("span", { class: "label", text: "Bruges her:" }),
                    h("ul", {}, uses.map(([label, href]) => h("li", {}, h("a", { href, onclick: () => close(null), text: label })))))
                : h("p", { class: "muted small", text: "Bruges ikke på hjemmesiden lige nu." })),
            h("div", { class: "viewer-actions" },
              h("a", { class: "btn btn-secondary btn-sm", href: imgSrc(b.sti), target: "_blank", rel: "noopener" }, icon("external"), "Åbn i fuld størrelse"),
              h("a", { class: "btn btn-secondary btn-sm", href: imgSrc(b.sti), download: fileName(b.sti) }, icon("download"), "Hent"),
              deletable && h("button", { type: "button", class: "btn btn-ghost btn-sm danger", disabled: uses.length > 0, onclick: del }, icon("trash"), "Slet")),
            deletable && uses.length > 0 && h("p", { class: "help", text: "Billedet kan ikke slettes, fordi det bruges på hjemmesiden. Fjern det fra stederne ovenfor først." }));
        };
        render();
        box.go = go;
        return box;
      },
      onOpen: (dlg) => dlg.addEventListener("keydown", (e) => {
        const v = dlg.querySelector(".viewer");
        if (!v || !v.go || e.target.closest("a, button") && e.key === "Enter") return;
        if (e.key === "ArrowLeft") v.go(-1);
        if (e.key === "ArrowRight") v.go(1);
      }),
    });
  }

  function viewHistorik() {
    const box = h("div", { class: "card history" }, h("div", { class: "loading" }, h("div", { class: "spinner" }), "Henter historik…"));
    const load = async () => {
      try {
        const r = await api("historik");
        const list = h("ol", { class: "history-list" });
        list.append(h("li", { class: "history-item current" },
          h("span", { class: "history-dot" }),
          h("div", { class: "history-text" },
            h("strong", { text: r.nuvaerende.gemt ? `Gemt ${fmtTime(r.nuvaerende.gemt)}` : "Oprindeligt indhold" }),
            h("span", { class: "muted", text: r.nuvaerende.af ? `af ${r.nuvaerende.af}` : "" })),
          badge("Nu på hjemmesiden", "ok")));
        for (const v of r.versioner) {
          list.append(h("li", { class: "history-item" },
            h("span", { class: "history-dot" }),
            h("div", { class: "history-text" },
              h("strong", { text: v.note || (v.gemt ? `Gemt ${fmtTime(v.gemt)}` : "Tidligere version") }),
              h("span", { class: "muted", text: [v.af && `af ${v.af}`, `${v.forestillinger} forestillinger`, `${v.sponsorer} sponsorer`].filter(Boolean).join(" · ") })),
            h("button", { type: "button", class: "btn btn-secondary btn-sm", onclick: () => restore(v) }, icon("history"), "Gendan")));
        }
        box.replaceChildren(list);
        if (!r.versioner.length) box.append(h("p", { class: "muted pad", text: "Der er ingen tidligere versioner endnu. De dukker op her, når I begynder at gemme." }));
      } catch (err) {
        box.replaceChildren(h("p", { class: "muted pad", text: err.message }));
        if (err.status === 401) relogin().then((ok) => ok && load());
      }
    };
    const restore = async (v) => {
      const when = v.note || (v.gemt ? fmtTime(v.gemt) : "den tidligere version");
      const ok = await confirmBox("Gendan denne version?",
        `Hjemmesiden bliver sat tilbage til, som den så ud (${when}). Den nuværende version bliver gemt i historikken, så du kan fortryde igen.` + (S.dirty ? " Dine ugemte ændringer går tabt." : ""),
        "Gendan version", false);
      if (!ok) return;
      try {
        const r = await api("gendan", { body: { id: v.id } });
        setContent(r.indhold);
        clearDraft();
        S.nye.clear();
        toast("Versionen er gendannet og er nu på hjemmesiden.");
        load();
      } catch (err) { handleError(err); }
    };
    load();
    return [
      pageHead("Historik", "Hver gang nogen gemmer, bliver den forrige version lagt her. Kom du til at slette noget, kan du hente det tilbage."),
      box,
    ];
  }

  function viewBrugere() {
    if (S.bruger.rolle !== "admin") { location.hash = "#/"; return []; }
    const box = h("div", { class: "card" }, h("div", { class: "loading" }, h("div", { class: "spinner" }), "Henter brugere…"));
    const draw = (brugere) => {
      const list = h("ul", { class: "user-list" });
      for (const u of brugere) {
        const me = u.brugernavn === S.bruger.brugernavn;
        list.append(h("li", { class: "user" },
          h("span", { class: "avatar", text: (u.navn || u.brugernavn).split(/\s+/).map((p) => p[0]).slice(0, 2).join("").toUpperCase() }),
          h("div", { class: "user-text" },
            h("strong", {}, u.navn, me ? h("span", { class: "muted", text: " (dig)" }) : null),
            h("span", { class: "muted", text: `Brugernavn: ${u.brugernavn} · ${u.sidstLoggetInd ? "Sidst logget ind " + fmtTime(u.sidstLoggetInd) : "Har ikke logget ind endnu"}` })),
          h("div", { class: "user-tools" },
            me ? badge(u.rolle === "admin" ? "Administrator" : "Redaktør", u.rolle === "admin" ? "accent" : "")
              : h("select", {
                  class: "input select select-sm", "aria-label": `Rolle for ${u.navn}`,
                  onchange: async (e) => {
                    try { draw((await api("gem-bruger", { body: { brugernavn: u.brugernavn, rolle: e.target.value } })).brugere); toast(`${u.navn} er nu ${e.target.value === "admin" ? "administrator" : "redaktør"}.`); }
                    catch (err) { handleError(err); e.target.value = u.rolle; }
                  },
                }, h("option", { value: "redaktoer", selected: u.rolle !== "admin" }, "Redaktør"), h("option", { value: "admin", selected: u.rolle === "admin" }, "Administrator")),
            !me && h("button", { type: "button", class: "btn btn-ghost btn-sm", onclick: () => resetPassword(u) }, icon("key"), "Nyt kodeord"),
            !me && h("button", {
              type: "button", class: "icon-btn danger", "aria-label": `Slet ${u.navn}`, title: "Slet bruger",
              onclick: async () => {
                if (!(await confirmBox(`Slet ${u.navn}?`, `${u.navn} kan ikke længere logge ind. Det, de har lavet på hjemmesiden, bliver der.`, "Slet bruger", true))) return;
                try { draw((await api("slet-bruger", { body: { brugernavn: u.brugernavn } })).brugere); toast(`${u.navn} er slettet.`); } catch (err) { handleError(err); }
              },
            }, icon("trash")))));
      }
      box.replaceChildren(list);
    };
    const resetPassword = async (u) => {
      const pw = makePassword();
      const ok = await confirmBox(`Nyt kodeord til ${u.navn}?`, `${u.navn} får et nyt kodeord, og det gamle holder op med at virke.`, "Lav nyt kodeord", false);
      if (!ok) return;
      try {
        await api("gem-bruger", { body: { brugernavn: u.brugernavn, kodeord: pw } });
        showCredentials(u.navn, u.brugernavn, pw);
      } catch (err) { handleError(err); }
    };
    api("brugere").then((r) => draw(r.brugere)).catch((err) => box.replaceChildren(h("p", { class: "muted pad", text: err.message })));

    const ny = { navn: "", brugernavn: "", kodeord: makePassword(), rolle: "redaktoer" };
    let userTouched = false;
    const userInput = textInput(ny, "brugernavn", { label: "Brugernavn", placeholder: "fx anna", help: "Små bogstaver, uden mellemrum.", onInput: () => { userTouched = true; } });
    const pwInput = textInput(ny, "kodeord", { label: "Midlertidigt kodeord", help: "Personen kan selv skifte det under “Min konto”." });
    const form = h("form", {
      class: "stack",
      onsubmit: async (e) => {
        e.preventDefault();
        const btn = form.querySelector("button[type=submit]");
        btn.disabled = true;
        try {
          const r = await api("gem-bruger", { body: { ny: true, ...ny } });
          draw(r.brugere);
          showCredentials(ny.navn, ny.brugernavn.toLowerCase(), ny.kodeord);
          renderView(true);
        } catch (err) { handleError(err); btn.disabled = false; }
      },
    },
    grid2(
      textInput(ny, "navn", {
        label: "Navn", placeholder: "fx Anna Hansen",
        onInput: (v) => { if (!userTouched) { ny.brugernavn = slugify(v.split(" ")[0]).replace(/-/g, ""); userInput.querySelector("input").value = ny.brugernavn; } },
      }),
      userInput,
      h("div", { class: "field-with-btn" }, pwInput,
        h("button", { type: "button", class: "btn btn-ghost btn-sm", onclick: () => { ny.kodeord = makePassword(); pwInput.querySelector("input").value = ny.kodeord; } }, icon("spark"), "Lav et nyt")),
      selectField(ny, "rolle", [["redaktoer", "Redaktør – kan rette indhold"], ["admin", "Administrator – kan også styre brugere"]], { label: "Rolle" })),
    h("div", {}, h("button", { type: "submit", class: "btn btn-primary" }, icon("plus"), "Opret bruger")));

    return [
      pageHead("Brugere", "Alle, der kan logge ind og rette hjemmesiden. Ændringer her gemmes med det samme."),
      box,
      card("Tilføj en person", "Opret et login til en ny redaktør. Bagefter får du en besked, du kan sende til personen.", form),
    ];
  }

  function showCredentials(navn, brugernavn, kodeord) {
    const url = location.href.split("#")[0];
    const text = `Hej ${navn.split(" ")[0]}!\n\nDu kan nu rette Teater Scenarios hjemmeside her:\n${url}\n\nBrugernavn: ${brugernavn}\nKodeord: ${kodeord}\n\nSkift gerne kodeordet under “Min konto”, når du er logget ind.`;
    modal({
      title: "Login er klar",
      body: (close) => {
        const pre = h("pre", { class: "creds", text });
        const copyBtn = h("button", {
          type: "button", class: "btn btn-primary",
          onclick: async () => {
            try { await navigator.clipboard.writeText(text); copyBtn.lastChild.textContent = "Kopieret!"; }
            catch { const r = document.createRange(); r.selectNodeContents(pre); getSelection().removeAllRanges(); getSelection().addRange(r); }
          },
        }, icon("copy"), "Kopiér beskeden");
        return h("div", { class: "stack" },
          h("p", { text: `Send denne besked til ${navn}, fx på sms eller mail. Kodeordet bliver ikke vist igen.` }),
          pre,
          h("div", { class: "modal-actions flush" }, h("button", { type: "button", class: "btn btn-secondary", onclick: () => close() }, "Luk"), copyBtn));
      },
    });
  }

  function viewKonto() {
    const me = { navn: S.bruger.navn };
    const pw = { kodeord: "", nytKodeord: "", gentag: "" };
    const nameForm = h("form", {
      class: "stack",
      onsubmit: async (e) => {
        e.preventDefault();
        try { S.bruger = (await api("min-konto", { body: { navn: me.navn } })).bruger; renderShellUser(); toast("Dit navn er gemt."); } catch (err) { handleError(err); }
      },
    }, textInput(me, "navn", { label: "Dit navn", autocomplete: "name", help: "Vises i historikken, når du gemmer." }),
      h("div", {}, h("button", { type: "submit", class: "btn btn-secondary" }, "Gem navn")));
    const pwForm = h("form", {
      class: "stack",
      onsubmit: async (e) => {
        e.preventDefault();
        if (pw.nytKodeord !== pw.gentag) { toast("De to nye kodeord er ikke ens.", "error"); return; }
        try {
          await api("min-konto", { body: { kodeord: pw.kodeord, nytKodeord: pw.nytKodeord } });
          pwForm.reset();
          Object.assign(pw, { kodeord: "", nytKodeord: "", gentag: "" });
          toast("Dit kodeord er skiftet.");
        } catch (err) { handleError(err); }
      },
    },
    textInput(pw, "kodeord", { label: "Nuværende kodeord", type: "password", autocomplete: "current-password" }),
    grid2(
      textInput(pw, "nytKodeord", { label: "Nyt kodeord", type: "password", autocomplete: "new-password", help: "Mindst 8 tegn." }),
      textInput(pw, "gentag", { label: "Gentag nyt kodeord", type: "password", autocomplete: "new-password" })),
    h("div", {}, h("button", { type: "submit", class: "btn btn-secondary" }, icon("key"), "Skift kodeord")));
    return [
      pageHead("Min konto", `Du er logget ind som ${S.bruger.brugernavn} (${S.bruger.rolle === "admin" ? "administrator" : "redaktør"}).`),
      card("Navn", null, nameForm),
      card("Skift kodeord", null, pwForm),
    ];
  }

  /* =========================================================
     Skal, menu og navigation
  ========================================================= */

  const NAV = [
    { id: "", label: "Oversigt", icon: "grid" },
    { group: "Indhold" },
    { id: "forside", label: "Forside", icon: "home" },
    { id: "forestillinger", label: "Forestillinger", icon: "ticket" },
    { id: "om", label: "Om os", icon: "info" },
    { id: "medvirk", label: "Vær med", icon: "megaphone" },
    { id: "sponsorer", label: "Sponsorer", icon: "heart" },
    { id: "kontakt", label: "Kontakt", icon: "mail" },
    { id: "billeder", label: "Billeder", icon: "image" },
    { group: "Andet" },
    { id: "historik", label: "Historik", icon: "history" },
    { id: "brugere", label: "Brugere", icon: "users", admin: true },
    { id: "konto", label: "Min konto", icon: "user" },
  ];

  const VIEWS = {
    "": viewOversigt, forside: viewForside, forestillinger: viewForestillinger, om: viewOm, medvirk: viewMedvirk,
    sponsorer: viewSponsorer, kontakt: viewKontakt, billeder: viewBilleder, historik: viewHistorik, brugere: viewBrugere, konto: viewKonto,
  };

  const UI = {};
  let afterRender = null;

  function renderShell() {
    const nav = h("nav", { class: "side-nav", "aria-label": "Editor-menu" });
    for (const item of NAV) {
      if (item.group) { nav.append(h("p", { class: "nav-group", text: item.group })); continue; }
      if (item.admin && S.bruger.rolle !== "admin") continue;
      nav.append(h("a", { href: "#/" + item.id, class: "nav-item", "data-id": item.id }, icon(item.icon), h("span", { text: item.label })));
    }
    UI.userBox = h("div", { class: "side-user" });
    UI.saveState = h("p", { class: "save-state" });
    UI.sidebar = h("aside", { class: "sidebar", id: "sidebar" },
      h("a", { class: "brand", href: "#/" }, h("img", { src: "../billeder/logo.png", alt: "Teater Scenario" }), h("span", { text: "Editor" })),
      nav,
      h("div", { class: "side-foot" },
        UI.saveState,
        h("a", { class: "nav-item", href: "../", target: "_blank", rel: "noopener" }, icon("external"), h("span", { text: "Se hjemmesiden" })),
        UI.userBox));
    UI.main = h("main", { class: "main", id: "main", tabindex: "-1" });
    UI.saveBtn = h("button", { type: "button", class: "btn btn-primary", onclick: () => save() }, icon("check"), h("span", { text: "Gem og udgiv" }));
    UI.savebar = h("div", { class: "savebar", role: "region", "aria-label": "Gem ændringer" },
      h("span", { class: "savebar-text" }, h("span", { class: "pulse", "aria-hidden": "true" }), "Du har ændringer, der ikke er gemt"),
      h("div", { class: "savebar-actions" },
        h("button", { type: "button", class: "btn btn-ghost-dark", onclick: discard }, "Fortryd"),
        UI.saveBtn));
    UI.menuBtn = h("button", { type: "button", class: "icon-btn menu-btn", "aria-label": "Åbn menu", "aria-expanded": "false", "aria-controls": "sidebar", onclick: () => toggleMenu() }, icon("menu"));
    const topbar = h("header", { class: "topbar" }, UI.menuBtn, h("img", { src: "../billeder/logo.png", alt: "Teater Scenario", class: "topbar-logo" }));
    const warn = !S.skrivbar && h("div", { class: "banner banner-warn", role: "alert" }, icon("alert"),
      h("span", { text: "Serveren kan ikke gemme filer i mapperne data/ og uploads/. Bed den, der står for hjemmesiden, om at give skriveadgang – ellers kan ændringer ikke gemmes." }));
    app.replaceChildren(h("div", { class: "shell" },
      UI.sidebar,
      h("div", { class: "scrim", onclick: () => toggleMenu(false) }),
      h("div", { class: "content" }, topbar, warn, UI.main),
      UI.savebar));
    renderShellUser();
    renderSaveState();
  }

  function renderShellUser() {
    if (!UI.userBox) return;
    UI.userBox.replaceChildren(
      h("a", { class: "side-me", href: "#/konto" },
        h("span", { class: "avatar avatar-sm", text: (S.bruger.navn || "?").split(/\s+/).map((p) => p[0]).slice(0, 2).join("").toUpperCase() }),
        h("span", {}, h("strong", { text: S.bruger.navn }), h("span", { text: S.bruger.rolle === "admin" ? "Administrator" : "Redaktør" }))),
      h("button", { type: "button", class: "icon-btn", "aria-label": "Log ud", title: "Log ud", onclick: logout }, icon("logout")));
  }

  function renderSaveState() {
    if (!UI.savebar) return;
    UI.savebar.classList.toggle("show", S.dirty || S.saving);
    UI.saveBtn.disabled = S.saving;
    UI.saveBtn.lastChild.textContent = S.saving ? "Gemmer…" : "Gem og udgiv";
    UI.saveState.className = "save-state" + (S.dirty ? " is-dirty" : "");
    UI.saveState.textContent = S.dirty ? "● Ugemte ændringer" : "✓ Alt er gemt";
    document.title = (S.dirty ? "• " : "") + "Redigér hjemmesiden – Teater Scenario";
  }

  function toggleMenu(open) {
    const isOpen = document.body.classList.toggle("nav-open", open);
    UI.menuBtn.setAttribute("aria-expanded", String(isOpen));
  }

  function parseHash() {
    const parts = location.hash.replace(/^#\/?/, "").split("/");
    return { name: parts[0] || "", arg: parts[1] };
  }

  function renderView(keepScroll) {
    if (!UI.main) return;
    const { name, arg } = parseHash();
    let view = VIEWS[name];
    if (!view) { location.hash = "#/"; return; }
    if (name === "forestillinger" && arg !== undefined && arg !== "") view = () => viewEvent(Number(arg));
    const y = window.scrollY;
    UI.main.replaceChildren(...[].concat(view()).filter(Boolean));
    UI.main.classList.remove("enter");
    if (keepScroll) window.scrollTo(0, y);
    else {
      void UI.main.offsetWidth;
      UI.main.classList.add("enter");
      window.scrollTo(0, 0);
      UI.main.focus({ preventScroll: true });
    }
    UI.sidebar.querySelectorAll(".nav-item[data-id]").forEach((a) => {
      if (a.dataset.id === name) a.setAttribute("aria-current", "page");
      else a.removeAttribute("aria-current");
    });
    toggleMenu(false);
    if (afterRender) { const fn = afterRender; afterRender = null; fn(); }
  }

  async function logout() {
    if (S.dirty && !(await confirmBox("Log ud uden at gemme?", "Du har ændringer, der ikke er gemt. De går tabt, hvis du logger ud nu.", "Log ud alligevel", true))) return;
    try { await api("logout", { body: {} }); } catch { /* ligegyldigt */ }
    clearDraft();
    location.hash = "";
    location.reload();
  }

  /* =========================================================
     Login og første opsætning
  ========================================================= */

  function viewLogin(setup) {
    const err = h("p", { class: "form-error", role: "alert", hidden: true });
    const v = { navn: "", brugernavn: "", kodeord: "", gentag: "" };
    let showPw = false;
    const pwType = () => (showPw ? "text" : "password");
    const input = (key, label, attrs = {}, help = "") => {
      const id = uid();
      const inp = h("input", { id, class: "input input-lg", oninput: (e) => { v[key] = e.target.value; }, ...attrs });
      return h("div", { class: "field" }, h("label", { class: "label", for: id, text: label }), inp, help && h("p", { class: "help", text: help }));
    };
    const pwField = input("kodeord", "Kodeord", { type: "password", autocomplete: setup ? "new-password" : "current-password", required: true }, setup ? "Mindst 8 tegn." : "");
    const pwToggle = h("button", {
      type: "button", class: "pw-toggle", "aria-label": "Vis kodeord",
      onclick: () => {
        showPw = !showPw;
        app.querySelectorAll("input[data-pw]").forEach((i) => (i.type = pwType()));
        pwToggle.replaceChildren(icon(showPw ? "eyeOff" : "eye"));
        pwToggle.setAttribute("aria-label", showPw ? "Skjul kodeord" : "Vis kodeord");
      },
    }, icon("eye"));
    pwField.querySelector("input").dataset.pw = "1";
    pwField.querySelector("input").after(pwToggle);
    pwField.classList.add("pw-field");
    const btn = h("button", { type: "submit", class: "btn btn-primary btn-lg" }, setup ? "Opret og log ind" : "Log ind");

    const form = h("form", {
      class: "login-form",
      onsubmit: async (e) => {
        e.preventDefault();
        err.hidden = true;
        if (setup && v.kodeord !== v.gentag) { err.textContent = "De to kodeord er ikke ens."; err.hidden = false; return; }
        btn.disabled = true;
        btn.textContent = setup ? "Opretter…" : "Logger ind…";
        try {
          const r = await api(setup ? "opsaetning" : "login", { body: v });
          S.csrf = r.csrf;
          S.bruger = r.bruger;
          await startEditor();
        } catch (ex) {
          err.textContent = ex.message;
          err.hidden = false;
          btn.disabled = false;
          btn.textContent = setup ? "Opret og log ind" : "Log ind";
        }
      },
    },
    h("h1", { text: setup ? "Velkommen!" : "Log ind" }),
    h("p", { class: "muted", text: setup ? "Opret den første administrator. Bagefter kan du tilføje flere personer under “Brugere”." : "Log ind for at rette indholdet på hjemmesiden." }),
    setup && input("navn", "Dit navn", { autocomplete: "name", required: true, placeholder: "fx Simon Hansen" }),
    input("brugernavn", "Brugernavn", { autocomplete: "username", required: true, autocapitalize: "none", spellcheck: "false" }, setup ? "Små bogstaver, uden mellemrum – fx simon." : ""),
    pwField,
    setup && (() => { const f = input("gentag", "Gentag kodeord", { type: "password", autocomplete: "new-password", required: true }); f.querySelector("input").dataset.pw = "1"; return f; })(),
    err,
    btn,
    !S.skrivbar && h("p", { class: "banner banner-warn" }, icon("alert"), h("span", { text: "Serveren kan ikke skrive i mappen data/. Giv mappen skriveadgang, før du fortsætter." })),
    h("a", { class: "back-site", href: "../" }, icon("left"), "Tilbage til hjemmesiden"));

    app.replaceChildren(h("div", { class: "login" },
      h("div", { class: "login-art" },
        h("img", { src: "../billeder/logo.png", alt: "Teater Scenario" }),
        h("p", { text: "Redigér hjemmesiden" })),
      h("div", { class: "login-panel" }, form)));
    const first = form.querySelector("input");
    if (first) first.focus();
  }

  /* =========================================================
     Opstart
  ========================================================= */

  async function startEditor() {
    const r = await api("indhold");
    setContent(r.indhold);
    renderShell();
    renderView();
    offerDraft();
    // Hold forbindelsen i live, mens editoren er åben
    clearInterval(startEditor.ping);
    startEditor.ping = setInterval(() => {
      if (document.hidden) return;
      api("ping").catch((err) => { if (err.status === 401) relogin(); });
    }, 4 * 60 * 1000);
  }

  async function boot() {
    try {
      const st = await api("status");
      S.csrf = st.csrf;
      S.skrivbar = st.skrivbar;
      S.maksUpload = st.maksUpload || S.maksUpload;
      if (st.opsaetning) return viewLogin(true);
      if (!st.bruger) return viewLogin(false);
      S.bruger = st.bruger;
      await startEditor();
    } catch (err) {
      app.replaceChildren(h("div", { class: "boot" },
        icon("alert", "empty-ico"),
        h("h1", { text: "Editoren kunne ikke starte" }),
        h("p", { text: err.message }),
        h("button", { type: "button", class: "btn btn-primary", onclick: () => location.reload() }, "Prøv igen")));
    }
  }

  addEventListener("hashchange", () => renderView());
  addEventListener("beforeunload", (e) => {
    if (S.dirty) { e.preventDefault(); e.returnValue = ""; }
  });
  addEventListener("keydown", (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s" && S.indhold) {
      e.preventDefault();
      if (document.activeElement && document.activeElement.blur && document.activeElement.classList.contains("tags-input")) document.activeElement.blur();
      save();
    }
    if (e.key === "Escape" && document.body.classList.contains("nav-open")) toggleMenu(false);
  });

  boot();
})();
