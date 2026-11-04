/* Scenario Lager – lidt hjælp i browseren. Alt virker også uden. */
(function () {
  "use strict";
  var $ = function (id) { return document.getElementById(id); };

  /* ---------- Mørk / lys tilstand ---------- */
  var root = document.documentElement;
  var isDark = function () {
    var t = root.getAttribute("data-theme");
    return t ? t === "dark" : window.matchMedia("(prefers-color-scheme: dark)").matches;
  };
  var label = function () {
    document.querySelectorAll("[data-theme-toggle]").forEach(function (b) { b.textContent = isDark() ? "Lys tilstand" : "Mørk tilstand"; });
  };
  document.querySelectorAll("[data-theme-toggle]").forEach(function (b) {
    b.addEventListener("click", function () {
      var t = isDark() ? "light" : "dark";
      root.setAttribute("data-theme", t);
      try { localStorage.setItem("lager-tema", t); } catch (e) { /* ignorér */ }
      label();
    });
  });
  label();

  // Spørg før man sletter eller returnerer
  document.addEventListener("submit", function (e) {
    var msg = e.target.getAttribute("data-confirm");
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Status og sektion opdaterer listen med det samme
  document.querySelectorAll("form[data-autosubmit] select").forEach(function (s) {
    s.addEventListener("change", function () { s.form.submit(); });
  });

  /* ---------- Sektion: dropdown med flueben ---------- */
  document.querySelectorAll("details.sl-multi").forEach(function (d) {
    var boxes = d.querySelectorAll("input[type=checkbox]");
    var state = function () { return Array.prototype.map.call(boxes, function (b) { return b.checked ? 1 : 0; }).join(""); };
    var start = state();
    var label = function () {
      var sel = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).map(function (b) { return b.value; });
      d.querySelector("summary").textContent = sel.length ? sel.join(", ") : "Alle";
    };
    d.addEventListener("change", label);
    d.querySelector("[data-multi-clear]").addEventListener("click", function () {
      boxes.forEach(function (b) { b.checked = false; });
      label();
    });
    // Når listen lukkes, opdateres inventaret, hvis man har ændret noget
    d.addEventListener("toggle", function () {
      if (!d.open && state() !== start) d.closest("form").submit();
    });
  });
  document.addEventListener("click", function (e) {
    document.querySelectorAll("details.sl-multi[open]").forEach(function (d) { if (!d.contains(e.target)) d.open = false; });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") document.querySelectorAll("details.sl-multi[open]").forEach(function (d) { d.open = false; });
  });

  /* ---------- Info om en enhed i panelet til højre ---------- */
  var panel = $("sl-panel"), split = $("sl-split");
  var setVis = function (id) {
    var p = new URLSearchParams(location.search);
    if (id) p.set("vis", id); else p.delete("vis");
    history.replaceState(null, "", p.toString() ? "?" + p.toString() : location.pathname);
    // Formularer i panelet skal lande samme sted bagefter
    if (panel) panel.querySelectorAll('input[name="retur"]').forEach(function (i) { i.value = p.toString(); });
    document.querySelectorAll("form.sl-toolbar input[name=vis]").forEach(function (i) { i.value = id || ""; });
  };
  var markCurrent = function (id) {
    document.querySelectorAll("#sl-inventar tbody tr[data-id]").forEach(function (r) {
      r.classList.toggle("sl-current", r.getAttribute("data-id") === String(id));
    });
  };
  function showInfo(id, href) {
    if (!panel) { location.href = href; return; }
    fetch("?side=info&id=" + encodeURIComponent(id) + "&popup=1", { credentials: "same-origin" })
      .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
      .then(function (html) {
        panel.innerHTML = html;
        panel.hidden = false;
        split.classList.add("has-panel");
        markCurrent(id);
        setVis(id);
        panel.scrollTop = 0;
      })
      .catch(function () { location.href = href; });
  }
  function closeInfo() {
    if (!panel) return;
    panel.hidden = true;
    panel.innerHTML = "";
    split.classList.remove("has-panel");
    markCurrent(null);
    setVis(null);
  }
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && panel && !panel.hidden) closeInfo(); });
  document.addEventListener("click", function (e) {
    if (e.target.closest("[data-close]")) { closeInfo(); return; }
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    var cell = e.target.closest("#sl-inventar td.sl-col-check");
    if (cell && e.target.tagName !== "INPUT") {
      var cb = cell.querySelector("input");
      cb.checked = !cb.checked;
      cb.dispatchEvent(new Event("change"));
      return;
    }
    var row = e.target.closest("#sl-inventar tbody tr[data-id]");
    if (!row) return;
    var link = e.target.closest("a");
    if (link && !link.hasAttribute("data-popup")) return; // fx "Rediger"
    if (!link && (e.target.closest("button, input, label") || getSelection().toString())) return;
    e.preventDefault();
    showInfo(row.getAttribute("data-id"), row.getAttribute("data-href"));
  });

  /* ---------- Vælg flere og reserver/returner ---------- */
  var picks = document.querySelectorAll(".sl-pick");
  var all = $("sl-all");
  if (all) {
    var chosen = function () { return Array.prototype.filter.call(picks, function (b) { return b.checked; }); };
    var update = function () {
      var sel = chosen();
      var free = sel.filter(function (b) { return b.dataset.free === "1"; }).length;
      $("sl-count").textContent = sel.length;
      $("sl-reserve").disabled = free === 0;
      $("sl-reserve").textContent = free ? "Reserver (" + free + ")" : "Reserver";
      $("sl-return").disabled = sel.length - free === 0;
      $("sl-return").textContent = sel.length - free ? "Returner (" + (sel.length - free) + ")" : "Returner";
      all.checked = picks.length > 0 && sel.length === picks.length;
      picks.forEach(function (b) { b.closest("tr").classList.toggle("sl-picked", b.checked); });
    };
    picks.forEach(function (b) { b.addEventListener("change", update); });
    all.addEventListener("change", function () {
      picks.forEach(function (b) { b.checked = all.checked; });
      update();
    });
    $("sl-reserve").addEventListener("click", function () {
      var ids = chosen().filter(function (b) { return b.dataset.free === "1"; }).map(function (b) { return b.value; });
      if (ids.length) location.href = "?side=reserver&ids=" + ids.join(",");
    });
    $("sl-return").addEventListener("click", function () {
      var ids = chosen().filter(function (b) { return b.dataset.free === "0"; }).map(function (b) { return b.value; });
      if (!ids.length || !window.confirm("Marker " + ids.length + " enhed(er) som returneret?")) return;
      var f = $("sl-return-form");
      ids.forEach(function (id) {
        var i = document.createElement("input");
        i.type = "hidden"; i.name = "ids[]"; i.value = id;
        f.appendChild(i);
      });
      f.submit();
    });
    update();
  }

  /* ---------- Tilføj enhed: vis det fulde ID ---------- */
  var sek = $("f-sek"), nr = $("f-nr"), full = $("f-full");
  if (sek && nr && full) {
    var showFull = function () {
      var v = nr.value.trim();
      full.textContent = v ? (sek.value ? sek.value + "-" : "") + v : "-";
    };
    sek.addEventListener("change", showFull);
    nr.addEventListener("input", showFull);
    showFull();
  }

  /* ---------- Ny bruger (som i editoren) ---------- */
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

  // Kopiér login-beskeden
  var copy = $("sl-copy");
  if (copy && navigator.clipboard) {
    copy.addEventListener("click", function () {
      navigator.clipboard.writeText($("sl-creds-text").textContent).then(function () { copy.textContent = "Kopieret!"; });
    });
  }
})();
