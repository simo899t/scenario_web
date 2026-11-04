/* Scenario Lager – søgning, filtre, panelet i højre side og handlinger uden at siden genindlæses. */
(function () {
  "use strict";
  var $ = function (id) { return document.getElementById(id); };
  var all = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var csrfMeta = document.querySelector('meta[name="csrf"]');

  /* ---------- Mørk / lys tilstand ---------- */
  var root = document.documentElement;
  var isDark = function () {
    var t = root.getAttribute("data-theme");
    return t ? t === "dark" : window.matchMedia("(prefers-color-scheme: dark)").matches;
  };
  var themeLabel = function () { all("[data-theme-toggle]").forEach(function (b) { b.textContent = isDark() ? "Lys tilstand" : "Mørk tilstand"; }); };
  all("[data-theme-toggle]").forEach(function (b) {
    b.addEventListener("click", function () {
      var t = isDark() ? "light" : "dark";
      root.setAttribute("data-theme", t);
      try { localStorage.setItem("lager-tema", t); } catch (e) { /* ignorér */ }
      themeLabel();
    });
  });
  themeLabel();

  /* ---------- Almindelige formularer (indstillinger, brugere m.m.) ---------- */
  document.addEventListener("submit", function (e) {
    var f = e.target;
    if (f.hasAttribute("data-ajax")) return; // håndteres længere nede
    var msg = f.getAttribute("data-confirm");
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  all("form[data-autosubmit] select").forEach(function (s) { s.addEventListener("change", function () { s.form.submit(); }); });

  // Fuldt ID mens man skriver (ny enhed – både på siden og i panelet)
  document.addEventListener("input", function (e) { updateFullId(e.target); });
  document.addEventListener("change", function (e) { updateFullId(e.target); });
  function updateFullId(el) {
    var form = el.closest && el.closest("form");
    if (!form) return;
    var sek = form.querySelector("#n-sek, #f-sek"), nr = form.querySelector("#n-nr, #f-nr"), out = form.querySelector("#n-full, #f-full");
    if (!sek || !nr || !out) return;
    var v = nr.value.trim();
    out.textContent = v ? (sek.value ? sek.value + "-" : "") + v : "–";
  }

  // Ny bruger: foreslå brugernavn og lav en ny adgangskode (som i editoren)
  var uNavn = $("u-navn"), uBn = $("u-bn");
  if (uNavn && uBn) {
    var touched = uBn.value !== "";
    uBn.addEventListener("input", function () { touched = true; });
    uNavn.addEventListener("input", function () {
      if (touched) return;
      uBn.value = (uNavn.value.trim().split(/\s+/)[0] || "").toLowerCase()
        .replace(/æ/g, "ae").replace(/ø/g, "oe").replace(/å/g, "aa")
        .normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]/g, "");
    });
    $("u-pw-new").addEventListener("click", function () {
      var words = ["scene", "lys", "kulisse", "rolle", "sang", "dans", "musik", "billet", "applaus", "premiere", "replik", "kostume", "maske", "drama", "finale", "prolog", "regi", "rampe", "kor", "solo"];
      var r = new Uint32Array(5);
      crypto.getRandomValues(r);
      var w = [0, 1, 2, 3].map(function (i) { return words[r[i] % words.length]; });
      w[0] = w[0].charAt(0).toUpperCase() + w[0].slice(1);
      $("u-pw").value = w.join("-") + "-" + (r[4] % 90 + 10);
    });
  }
  var copy = $("sl-copy");
  if (copy && navigator.clipboard) {
    copy.addEventListener("click", function () {
      navigator.clipboard.writeText($("sl-creds-text").textContent).then(function () { copy.textContent = "Kopieret!"; });
    });
  }

  /* =========================================================
     Inventar
  ========================================================= */
  var table = $("sl-inventar");
  if (!table) return;
  var tbody = table.tBodies[0];
  var drawer = $("sl-drawer");
  var toastEl = $("sl-toast");
  var q = $("f-q"), statusSel = $("f-status"), sekBox = $("f-sek");
  var coll = new Intl.Collator("da", { numeric: true, sensitivity: "base" });
  var params = new URLSearchParams(location.search);
  var sortKey = ["sku", "navn", "placering", "status"].indexOf(params.get("sort")) >= 0 ? params.get("sort") : "sku";
  var sortDir = params.get("dir") === "desc" ? -1 : 1;
  var openId = null;
  var selected = {};

  // Start-tilstand fra adressen (også gamle adresser fra før)
  q.value = params.get("q") || params.get("search") || "";
  var st = params.get("status") || "";
  statusSel.value = { available: "ledig", reserved: "reserveret", overdue: "overskredet" }[st] || st;
  var startSek = params.getAll("sektion").concat(params.getAll("sektion[]"));
  all("input", sekBox).forEach(function (b) { b.checked = startSek.indexOf(b.value) >= 0; });

  var rows = function () { return all("tr[data-id]", tbody); };

  function toast(text, bad) {
    toastEl.textContent = text;
    toastEl.className = "sl-toast" + (bad ? " sl-toast-bad" : "");
    toastEl.hidden = false;
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { toastEl.hidden = true; }, bad ? 6000 : 3000);
  }

  function chosenSections() { return all("input:checked", sekBox).map(function (b) { return b.value; }); }

  function saveState() {
    var p = new URLSearchParams();
    if (q.value.trim()) p.set("q", q.value.trim());
    if (statusSel.value) p.set("status", statusSel.value);
    chosenSections().forEach(function (s) { p.append("sektion", s); });
    if (sortKey !== "sku") p.set("sort", sortKey);
    if (sortDir < 0) p.set("dir", "desc");
    if (openId) p.set("vis", openId);
    history.replaceState(null, "", p.toString() ? "?" + p.toString() : location.pathname);
  }

  function applyFilters() {
    var terms = q.value.toLowerCase().split(",").map(function (t) { return t.trim(); }).filter(Boolean);
    var status = statusSel.value;
    var secs = chosenSections();
    var shown = 0, total = 0;
    rows().forEach(function (r) {
      total++;
      var s = r.getAttribute("data-status");
      var ok = (!status || (status === "reserveret" ? s !== "ledig" : s === status)) &&
        (!secs.length || secs.indexOf(r.getAttribute("data-sektion")) >= 0);
      if (ok && terms.length) {
        var hay = (r.getAttribute("data-sku") + " " + r.getAttribute("data-navn") + " " + r.getAttribute("data-placering")).toLowerCase();
        ok = terms.some(function (t) { return hay.indexOf(t) >= 0; });
      }
      r.hidden = !ok;
      if (ok) shown++;
    });
    $("sl-count").textContent = shown === total ? total + " enheder" : shown + " af " + total;
    $("sl-none").hidden = shown > 0 || total === 0;
    sekBox.querySelector("summary").textContent = secs.length ? secs.map(function (s) { return s === "-" ? "Uden sektion" : s; }).join(", ") : "Alle sektioner";
    updateBulk();
    saveState();
  }

  function sortRows() {
    var order = { overskredet: 0, reserveret: 1, ledig: 2 };
    rows().sort(function (a, b) {
      var c = sortKey === "status"
        ? order[a.getAttribute("data-status")] - order[b.getAttribute("data-status")]
        : coll.compare(a.getAttribute("data-" + sortKey) || "", b.getAttribute("data-" + sortKey) || "");
      return sortDir * c || coll.compare(a.getAttribute("data-sku"), b.getAttribute("data-sku"));
    }).forEach(function (r) { tbody.appendChild(r); });
    all(".sl-sort", table).forEach(function (b) {
      var th = b.closest("th");
      if (b.getAttribute("data-sort") === sortKey) th.setAttribute("aria-sort", sortDir > 0 ? "ascending" : "descending");
      else th.removeAttribute("aria-sort");
    });
  }

  /* ---------- Valg af flere ---------- */
  function updateBulk() {
    var ids = Object.keys(selected);
    var free = 0, busy = 0;
    rows().forEach(function (r) {
      var on = !!selected[r.getAttribute("data-id")];
      r.querySelector(".sl-pick").checked = on;
      r.classList.toggle("sl-picked", on);
      if (on) { if (r.getAttribute("data-status") === "ledig") free++; else busy++; }
    });
    $("sl-bulkbar").hidden = ids.length === 0;
    $("sl-bulk-count").textContent = ids.length === 1 ? "1 valgt" : ids.length + " valgt";
    var res = $("sl-bulk-reserve"), ret = $("sl-bulk-return");
    res.hidden = !free; res.textContent = "Reserver" + (busy ? " " + free : "");
    ret.hidden = !busy; ret.textContent = "Returner" + (free ? " " + busy : "");
    var vis = rows().filter(function (r) { return !r.hidden; });
    var n = vis.filter(function (r) { return selected[r.getAttribute("data-id")]; }).length;
    $("sl-all").checked = vis.length > 0 && n === vis.length;
    $("sl-all").indeterminate = n > 0 && n < vis.length;
  }
  var idsBy = function (free) {
    return rows().filter(function (r) { return selected[r.getAttribute("data-id")] && (r.getAttribute("data-status") === "ledig") === free; })
      .map(function (r) { return r.getAttribute("data-id"); });
  };

  /* ---------- Panelet i højre side ---------- */
  function openPanel(mode, opts) {
    var p = new URLSearchParams({ side: "panel", panel: mode });
    if (opts.id) p.set("id", opts.id);
    if (opts.ids) p.set("ids", opts.ids);
    return fetch("?" + p.toString(), { credentials: "same-origin" })
      .then(function (r) {
        if (r.status === 401) { location.reload(); throw new Error(); }
        if (!r.ok) throw new Error();
        return r.text();
      })
      .then(function (html) {
        drawer.innerHTML = html;
        drawer.hidden = false;
        drawer.scrollTop = 0;
        document.body.classList.add("sl-drawer-open");
        var single = mode === "reserver" && String(opts.ids).indexOf(",") < 0;
        openId = mode === "info" || mode === "rediger" ? String(opts.id) : (single ? String(opts.ids) : null);
        rows().forEach(function (r) { r.classList.toggle("sl-current", r.getAttribute("data-id") === openId); });
        saveState();
        var first = drawer.querySelector("input[type=text], textarea");
        if (first && mode !== "info") first.focus();
      })
      .catch(function () { /* netværksfejl – prøv igen */ });
  }
  function closePanel() {
    drawer.hidden = true;
    drawer.innerHTML = "";
    document.body.classList.remove("sl-drawer-open");
    openId = null;
    rows().forEach(function (r) { r.classList.remove("sl-current"); });
    saveState();
  }

  /* ---------- Gem uden at siden genindlæses ---------- */
  function refreshList() {
    return fetch(location.pathname + location.search, { credentials: "same-origin" })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, "text/html");
        var nb = doc.querySelector("#sl-inventar tbody"), sum = doc.getElementById("sl-summary");
        if (nb) tbody.innerHTML = nb.innerHTML;
        if (sum) $("sl-summary").innerHTML = sum.innerHTML;
        var meta = doc.querySelector('meta[name="csrf"]');
        if (meta && csrfMeta) csrfMeta.content = meta.content;
        selected = {};
        sortRows();
        applyFilters();
        rows().forEach(function (r) { r.classList.toggle("sl-current", r.getAttribute("data-id") === openId); });
      });
  }
  function post(data) {
    return fetch("./", { method: "POST", body: data, credentials: "same-origin", headers: { "X-Requested-With": "fetch" } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, fejl: "Der skete en fejl. Prøv igen." }; }); })
      .then(function (j) {
        if (j.login) { location.reload(); throw new Error(); }
        if (!j.ok) { toast(j.fejl || "Der skete en fejl.", true); throw new Error(); }
        toast(j.besked || "Gemt.");
        return refreshList();
      });
  }
  function afterSave(after) {
    if (after && after.indexOf("info:") === 0) return openPanel("info", { id: after.slice(5) });
    if (after === "close") closePanel();
    else if (openId) openPanel("info", { id: openId });
  }
  function returnItems(ids, name) {
    var msg = ids.length === 1 && name ? "Returner “" + name + "”?" : "Returner " + ids.length + " enhed(er)?";
    if (!ids.length || !window.confirm(msg)) return;
    var fd = new FormData();
    fd.append("csrf", csrfMeta.content);
    fd.append("handling", "returner");
    ids.forEach(function (id) { fd.append("ids[]", id); });
    post(fd).then(function () { if (openId && ids.indexOf(openId) >= 0) openPanel("info", { id: openId }); }).catch(function () {});
  }

  document.addEventListener("submit", function (e) {
    var f = e.target;
    if (!f.hasAttribute("data-ajax")) return;
    e.preventDefault();
    var msg = f.getAttribute("data-confirm");
    if (msg && !window.confirm(msg)) return;
    var btn = f.querySelector("button[type=submit]");
    if (btn) btn.disabled = true;
    var fd = new FormData(f);
    fd.set("csrf", csrfMeta.content);
    post(fd).then(function () { afterSave(f.getAttribute("data-after")); })
      .catch(function () { if (btn) btn.disabled = false; });
  });

  /* ---------- Klik ---------- */
  document.addEventListener("click", function (e) {
    var t = e.target;
    if (t.closest("[data-close]")) { e.preventDefault(); closePanel(); return; }
    var pb = t.closest("[data-panel]");
    if (pb) {
      if (e.ctrlKey || e.metaKey) return;
      e.preventDefault();
      openPanel(pb.getAttribute("data-panel"), { id: pb.getAttribute("data-id"), ids: pb.getAttribute("data-ids") });
      return;
    }
    var rb = t.closest("[data-return]");
    if (rb) { returnItems([rb.getAttribute("data-return")], rb.getAttribute("data-name")); return; }
    var sb = t.closest(".sl-sort");
    if (sb) {
      var k = sb.getAttribute("data-sort");
      sortDir = k === sortKey ? -sortDir : 1;
      sortKey = k;
      sortRows();
      saveState();
      return;
    }
    if (t.closest("[data-multi-clear]")) { all("input", sekBox).forEach(function (b) { b.checked = false; }); applyFilters(); return; }
    if (!t.closest("details.sl-multi")) all("details.sl-multi[open]").forEach(function (d) { d.open = false; });
    var cell = t.closest("#sl-inventar td.sl-col-check");
    if (cell && t.tagName !== "INPUT") {
      var cb = cell.querySelector("input");
      cb.checked = !cb.checked;
      cb.dispatchEvent(new Event("change", { bubbles: true }));
      return;
    }
    var row = t.closest("#sl-inventar tr[data-id]");
    if (!row || t.closest("button, input, label") || (t.closest("a") && !t.closest("a[data-open]"))) return;
    if (getSelection().toString()) return;
    e.preventDefault();
    var id = row.getAttribute("data-id");
    if (openId === id && !drawer.hidden) closePanel(); else openPanel("info", { id: id });
  });

  table.addEventListener("change", function (e) {
    if (e.target.id === "sl-all") {
      rows().filter(function (r) { return !r.hidden; }).forEach(function (r) {
        if (e.target.checked) selected[r.getAttribute("data-id")] = true; else delete selected[r.getAttribute("data-id")];
      });
    } else if (e.target.classList.contains("sl-pick")) {
      if (e.target.checked) selected[e.target.value] = true; else delete selected[e.target.value];
    }
    updateBulk();
  });
  $("sl-bulk-clear").addEventListener("click", function () { selected = {}; updateBulk(); });
  $("sl-bulk-reserve").addEventListener("click", function () { openPanel("reserver", { ids: idsBy(true).join(",") }); });
  $("sl-bulk-return").addEventListener("click", function () { returnItems(idsBy(false), ""); });

  q.addEventListener("input", applyFilters);
  statusSel.addEventListener("change", applyFilters);
  sekBox.addEventListener("change", applyFilters);
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    if (document.querySelector("details.sl-multi[open]")) { all("details.sl-multi[open]").forEach(function (d) { d.open = false; }); return; }
    if (!drawer.hidden) closePanel();
  });

  sortRows();
  applyFilters();
  if (params.get("vis")) openPanel("info", { id: params.get("vis") });
})();
