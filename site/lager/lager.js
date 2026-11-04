/* Scenario Lager – lidt hjælp i browseren. Alt virker også uden. */
(function () {
  "use strict";
  var $ = function (id) { return document.getElementById(id); };

  // Spørg før man sletter eller returnerer
  document.addEventListener("submit", function (e) {
    var msg = e.target.getAttribute("data-confirm");
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Status og sektion opdaterer listen med det samme
  document.querySelectorAll("form[data-autosubmit] select").forEach(function (s) {
    s.addEventListener("change", function () { s.form.submit(); });
  });

  /* ---------- Info om en enhed i et vindue ---------- */
  var popup = $("sl-popup");
  function openInfo(href) {
    if (!popup || typeof popup.showModal !== "function") { location.href = href; return; }
    fetch(href + "&popup=1", { credentials: "same-origin" })
      .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
      .then(function (html) {
        popup.innerHTML = html;
        if (!popup.open) popup.showModal();
      })
      .catch(function () { location.href = href; });
  }
  document.addEventListener("click", function (e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    var a = e.target.closest("a[data-popup]");
    if (a) { e.preventDefault(); openInfo(a.href); return; }
    var cell = e.target.closest("#sl-inventar td.sl-col-check");
    if (cell && e.target.tagName !== "INPUT") {
      var cb = cell.querySelector("input");
      cb.checked = !cb.checked;
      cb.dispatchEvent(new Event("change"));
      return;
    }
    var row = e.target.closest("#sl-inventar tbody tr[data-href]");
    if (row && !e.target.closest("a, button, input, label") && !getSelection().toString()) openInfo(row.getAttribute("data-href"));
    if (e.target.closest("[data-close]") || e.target === popup) popup.close();
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
