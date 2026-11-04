/* Lager – søgning, filtre, valg af flere enheder og små hjælpere. */
(() => {
  "use strict";
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const pad = (n) => String(n).padStart(2, "0");

  /* ---------- Beskeder ---------- */
  $$(".toast").forEach((t, i) => {
    setTimeout(() => {
      t.classList.add("out");
      setTimeout(() => t.remove(), 300);
    }, (t.classList.contains("toast-error") ? 7000 : 4000) + i * 400);
  });

  /* ---------- "Er du sikker?" ---------- */
  const dlg = $("#lg-confirm");
  function ask(text, danger) {
    return new Promise((resolve) => {
      if (!dlg || typeof dlg.showModal !== "function") { resolve(window.confirm(text)); return; }
      $("#lg-confirm-text").textContent = text;
      const ok = $("#lg-confirm-ok");
      ok.className = "btn " + (danger ? "btn-danger" : "btn-primary");
      ok.textContent = danger ? "Ja, gør det" : "OK";
      const done = (v) => { dlg.close(); resolve(v); };
      $$("button", dlg).forEach((b) => { b.onclick = () => done(b.value === "ja"); });
      dlg.oncancel = (e) => { e.preventDefault(); done(false); };
      dlg.showModal();
      ok.focus();
    });
  }
  document.addEventListener("submit", async (e) => {
    const f = e.target;
    if (!f.matches("form[data-confirm]") || f.dataset.ok) return;
    e.preventDefault();
    if (await ask(f.dataset.confirm, f.hasAttribute("data-danger"))) {
      f.dataset.ok = "1";
      f.submit();
      delete f.dataset.ok;
    }
  });
  // Undgå dobbelt-klik på "Gem" og lignende
  document.addEventListener("submit", (e) => {
    if (e.defaultPrevented) return;
    const f = e.target;
    if (f.querySelector('input[name="handling"][value="eksport"]')) return;
    setTimeout(() => $$('button[type="submit"]', f).forEach((b) => { b.disabled = true; }), 0);
  });
  addEventListener("pageshow", () => $$('button[type="submit"]').forEach((b) => { b.disabled = false; }));

  $$("form[data-autosubmit] select").forEach((s) => s.addEventListener("change", () => s.form.submit()));

  /* ---------- Vis kodeord ---------- */
  $$("[data-pw-toggle]").forEach((b) => b.addEventListener("click", () => {
    const show = b.getAttribute("aria-label") === "Vis kodeord";
    $$("input[data-pw]").forEach((i) => { i.type = show ? "text" : "password"; });
    b.setAttribute("aria-label", show ? "Skjul kodeord" : "Vis kodeord");
  }));

  /* ---------- Login-besked til ny bruger (som i editoren) ---------- */
  const creds = $("#lg-creds");
  if (creds && typeof creds.showModal === "function") {
    creds.showModal();
    $('button[value="luk"]', creds).onclick = () => creds.close();
    const copy = $("#lg-copy");
    copy.onclick = async () => {
      const pre = $("#lg-creds-text");
      try {
        await navigator.clipboard.writeText(pre.textContent);
        copy.lastChild.textContent = "Kopieret!";
      } catch {
        const r = document.createRange();
        r.selectNodeContents(pre);
        getSelection().removeAllRanges();
        getSelection().addRange(r);
      }
    };
  }

  /* ---------- Ny bruger: forslag til brugernavn og kodeord ---------- */
  function slugify(v) {
    return String(v || "").toLowerCase().replace(/æ/g, "ae").replace(/ø/g, "oe").replace(/å/g, "aa")
      .normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "");
  }
  function makePassword() {
    const words = ["scene", "lys", "kulisse", "rolle", "sang", "dans", "musik", "billet", "applaus", "premiere", "replik", "kostume", "maske", "drama", "finale", "encore", "prolog", "regi", "rampe", "kor", "solo", "duet", "akt", "tango"];
    const r = new Uint32Array(5);
    crypto.getRandomValues(r);
    const w = [0, 1, 2, 3].map((i) => words[r[i] % words.length]);
    w[0] = w[0][0].toUpperCase() + w[0].slice(1);
    return w.join("-") + "-" + String(r[4] % 90 + 10);
  }
  const newUser = $("#lg-new-user");
  if (newUser) {
    const navn = $("#u-navn"), bn = $("#u-bn");
    let touched = bn.value !== "";
    bn.addEventListener("input", () => { touched = true; });
    navn.addEventListener("input", () => { if (!touched) bn.value = slugify(navn.value.trim().split(/\s+/)[0]); });
    $("#u-pw-new").addEventListener("click", () => { $("#u-pw").value = makePassword(); });
  }

  /* ---------- Inventar ---------- */
  const table = $("#lg-inventar");
  if (table) {
    const tbody = table.tBodies[0];
    const rows = $$("tr", tbody);
    const q = $("#lg-q");
    const none = $("#lg-none");
    const counter = $("#lg-count");
    const all = $("#lg-all");
    const bar = $("#lg-bulk");
    const coll = new Intl.Collator("da", { numeric: true, sensitivity: "base" });
    const selected = new Set();
    const params = new URLSearchParams(location.search);
    const filters = { status: params.get("status") || "", sektion: params.get("sektion") || "" };
    let sortKey = ["sku", "navn", "placering"].includes(params.get("sort")) ? params.get("sort") : "sku";
    let sortDir = params.get("dir") === "desc" ? -1 : 1;
    q.value = params.get("q") || "";

    const saveState = () => {
      const p = new URLSearchParams();
      if (q.value.trim()) p.set("q", q.value.trim());
      if (filters.status) p.set("status", filters.status);
      if (filters.sektion) p.set("sektion", filters.sektion);
      if (sortKey !== "sku") p.set("sort", sortKey);
      if (sortDir < 0) p.set("dir", "desc");
      const s = p.toString();
      history.replaceState(null, "", s ? "?" + s : location.pathname);
    };

    const visible = () => rows.filter((r) => !r.hidden);
    const updateAll = () => {
      const vis = visible();
      const n = vis.filter((r) => selected.has(r.dataset.id)).length;
      all.checked = vis.length > 0 && n === vis.length;
      all.indeterminate = n > 0 && n < vis.length;
    };

    const apply = () => {
      const terms = q.value.toLowerCase().split(",").map((s) => s.trim()).filter(Boolean);
      let shown = 0;
      for (const r of rows) {
        const st = r.dataset.status;
        const okStatus = !filters.status || (filters.status === "reserveret" ? st !== "ledig" : st === filters.status);
        const okSection = !filters.sektion || r.dataset.sektion === filters.sektion;
        const hay = (r.dataset.sku + " " + r.dataset.navn + " " + r.dataset.placering).toLowerCase();
        const okText = !terms.length || terms.some((t) => hay.includes(t));
        r.hidden = !(okStatus && okSection && okText);
        if (!r.hidden) shown++;
      }
      none.hidden = shown > 0;
      counter.textContent = shown === rows.length ? "" : `Viser ${shown} af ${rows.length} enheder`;
      $$("[data-filter]").forEach((g) => $$(".chip", g).forEach((c) => {
        const on = c.dataset.value === filters[g.dataset.filter];
        c.classList.toggle("on", on);
        c.setAttribute("aria-pressed", String(on));
      }));
      updateAll();
      saveState();
    };

    const sort = () => {
      rows.sort((a, b) => sortDir * coll.compare(a.dataset[sortKey] || "", b.dataset[sortKey] || "") || coll.compare(a.dataset.sku, b.dataset.sku));
      rows.forEach((r) => tbody.appendChild(r));
      $$(".lg-sort", table).forEach((b) => {
        const th = b.closest("th");
        if (b.dataset.sort === sortKey) th.setAttribute("aria-sort", sortDir > 0 ? "ascending" : "descending");
        else th.removeAttribute("aria-sort");
        b.removeAttribute("aria-sort");
      });
    };

    const updateBar = () => {
      const sel = rows.filter((r) => selected.has(r.dataset.id));
      const free = sel.filter((r) => r.dataset.status === "ledig").length;
      const busy = sel.length - free;
      bar.classList.toggle("show", sel.length > 0);
      $("#lg-bulk-count").textContent = sel.length === 1 ? "1 valgt" : `${sel.length} valgt`;
      const res = $("#lg-bulk-reserve"), ret = $("#lg-bulk-return");
      res.disabled = !free;
      res.lastChild.textContent = free ? `Reserver ${free}` : "Reserver";
      ret.disabled = !busy;
      ret.lastChild.textContent = busy ? `Returner ${busy}` : "Returner";
      rows.forEach((r) => r.classList.toggle("is-selected", selected.has(r.dataset.id)));
      updateAll();
    };
    const setChecked = (r, on) => {
      $(".lg-select", r).checked = on;
      if (on) selected.add(r.dataset.id); else selected.delete(r.dataset.id);
    };

    table.addEventListener("change", (e) => {
      if (e.target === all) {
        visible().forEach((r) => setChecked(r, all.checked));
        updateBar();
      } else if (e.target.classList.contains("lg-select")) {
        setChecked(e.target.closest("tr"), e.target.checked);
        updateBar();
      }
    });
    table.addEventListener("click", (e) => {
      const s = e.target.closest(".lg-sort");
      if (s) {
        sortDir = s.dataset.sort === sortKey ? -sortDir : 1;
        sortKey = s.dataset.sort;
        sort();
        saveState();
        return;
      }
      const tr = e.target.closest("tbody tr");
      if (!tr || e.target.closest("a, button, input, label")) return;
      if (e.target.closest(".lg-check")) {
        setChecked(tr, !$(".lg-select", tr).checked);
        updateBar();
        return;
      }
      if (getSelection().toString()) return; // man markerede tekst
      location.href = tr.dataset.href;
    });

    $$("[data-filter]").forEach((g) => g.addEventListener("click", (e) => {
      const c = e.target.closest(".chip");
      if (!c) return;
      filters[g.dataset.filter] = c.dataset.value;
      apply();
    }));
    q.addEventListener("input", apply);
    addEventListener("keydown", (e) => {
      if (e.key === "/" && document.activeElement === document.body) { e.preventDefault(); q.focus(); }
      if (e.key === "Escape" && selected.size && !document.querySelector("dialog[open]")) $("#lg-bulk-clear").click();
    });

    $("#lg-bulk-clear").addEventListener("click", () => {
      rows.forEach((r) => setChecked(r, false));
      updateBar();
    });
    $("#lg-bulk-reserve").addEventListener("click", () => {
      const ids = rows.filter((r) => selected.has(r.dataset.id) && r.dataset.status === "ledig").map((r) => r.dataset.id);
      if (ids.length) location.href = "?side=reserver&ids=" + ids.join(",");
    });
    $("#lg-bulk-return").addEventListener("click", () => {
      const ids = rows.filter((r) => selected.has(r.dataset.id) && r.dataset.status !== "ledig").map((r) => r.dataset.id);
      if (!ids.length) return;
      const f = $("#lg-return-form");
      $$('input[name="ids[]"]', f).forEach((i) => i.remove());
      ids.forEach((id) => f.append(Object.assign(document.createElement("input"), { type: "hidden", name: "ids[]", value: id })));
      f.dataset.confirm = ids.length === 1 ? "Markér den valgte enhed som returneret?" : `Markér ${ids.length} enheder som returneret?`;
      if (f.requestSubmit) f.requestSubmit(); else f.submit();
    });

    // Browseren kan huske flueben, når man går tilbage – tag dem med
    rows.forEach((r) => { if ($(".lg-select", r).checked) selected.add(r.dataset.id); });
    sort();
    apply();
    updateBar();
  }

  /* ---------- Historik ---------- */
  const hist = $("#lg-historik");
  if (hist) {
    const rows = $$("tbody tr", hist);
    const q = $("#lg-hq");
    let status = "";
    const run = () => {
      const t = q.value.toLowerCase().trim();
      let n = 0;
      rows.forEach((r) => {
        const okS = !status || (status === "aktiv" ? r.dataset.status !== "returneret" : r.dataset.status === status);
        const ok = okS && (!t || r.dataset.search.includes(t));
        r.hidden = !ok;
        if (ok) n++;
      });
      $("#lg-none").hidden = n > 0;
    };
    q.addEventListener("input", run);
    const chips = $("[data-hfilter]");
    chips.addEventListener("click", (e) => {
      const c = e.target.closest(".chip");
      if (!c) return;
      status = c.dataset.value;
      $$(".chip", chips).forEach((x) => { x.classList.toggle("on", x === c); x.setAttribute("aria-pressed", String(x === c)); });
      run();
    });
  }

  /* ---------- Ny enhed: vis det fulde ID og foreslå næste nummer ---------- */
  const skusEl = $("#lg-skus");
  if (skusEl) {
    const skus = JSON.parse(skusEl.textContent || "[]").map((s) => String(s).toLowerCase());
    const sek = $("#f-sek"), nr = $("#f-nr"), out = $("#lg-sku-preview"), next = $("#lg-next");
    const prefix = () => (sek.value ? sek.value + "-" : "");
    const suggest = () => {
      const p = prefix().toLowerCase();
      let max = 0, width = 3;
      for (const s of skus) {
        if (p ? !s.startsWith(p) : s.includes("-")) continue;
        const m = s.slice(p.length).match(/^(\d+)$/);
        if (m) { max = Math.max(max, parseInt(m[1], 10)); width = Math.max(width, m[1].length); }
      }
      return String(max + 1).padStart(width, "0");
    };
    const update = () => {
      const v = nr.value.trim();
      const full = prefix() + v;
      const taken = v !== "" && skus.includes(full.toLowerCase());
      out.textContent = v ? full + (taken ? " – findes allerede" : "") : "–";
      out.classList.toggle("lg-taken", taken);
      nr.setCustomValidity(taken ? "Det ID findes allerede. Vælg et andet nummer." : "");
      const s = suggest();
      next.hidden = v === s;
      next.textContent = `Brug næste ledige (${prefix() + s})`;
      next.dataset.value = s;
    };
    next.addEventListener("click", () => { nr.value = next.dataset.value; update(); nr.focus(); });
    sek.addEventListener("change", update);
    nr.addEventListener("input", update);
    update();
    if (sek.value && !nr.value) nr.focus();
  }

  /* ---------- Reserver: hurtigvalg og tjek af datoer ---------- */
  const rf = $("#lg-reserve-form");
  if (rf) {
    const fra = $("#f-fra"), til = $("#f-til"), err = $("#lg-date-error");
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    const check = () => {
      const bad = fra.value && til.value && new Date(til.value) <= new Date(fra.value);
      err.hidden = !bad;
      til.setCustomValidity(bad ? "“Til” skal ligge efter “Fra”." : "");
    };
    $$(".lg-quick .chip", rf).forEach((c) => c.addEventListener("click", () => {
      const d = new Date(fra.value || Date.now());
      d.setDate(d.getDate() + Number(c.dataset.add));
      til.value = fmt(d);
      check();
    }));
    fra.addEventListener("change", check);
    til.addEventListener("change", check);
    check();
  }
})();
