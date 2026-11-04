/* Scenario Lager – lidt hjælp i browseren (samme funktioner som det gamle plugin). */
(function () {
  "use strict";

  // Spørg før man sletter eller returnerer
  document.addEventListener("submit", function (e) {
    var msg = e.target.getAttribute("data-confirm");
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Filtrene i venstre side opdaterer listen med det samme
  document.querySelectorAll("form[data-autosubmit] input[type=checkbox]").forEach(function (cb) {
    cb.addEventListener("change", function () { cb.form.submit(); });
  });

  // Vælg flere enheder og reserver/returner dem på én gang
  var boxes = document.querySelectorAll(".sl-item-checkbox");
  var allBtn = document.getElementById("sl-select-all-btn");
  var resBtn = document.getElementById("sl-reserve-btn");
  var retBtn = document.getElementById("sl-return-btn");
  if (boxes.length && allBtn) {
    allBtn.hidden = false;
    var checked = function () { return Array.prototype.filter.call(boxes, function (b) { return b.checked; }); };
    var update = function () {
      var sel = checked();
      var free = sel.filter(function (b) { return b.dataset.available === "1"; }).length;
      var busy = sel.length - free;
      allBtn.textContent = sel.length === boxes.length ? "Fravælg alle" : "Vælg alle";
      resBtn.hidden = !(free > 0 && busy === 0);
      resBtn.textContent = "Reserver valgte (" + free + ")";
      retBtn.hidden = !(busy > 0 && free === 0);
      retBtn.textContent = "Returner valgte (" + busy + ")";
      boxes.forEach(function (b) { b.closest("tr").classList.toggle("sl-selected", b.checked); });
    };
    boxes.forEach(function (b) { b.addEventListener("change", update); });
    allBtn.addEventListener("click", function () {
      var on = checked().length !== boxes.length;
      boxes.forEach(function (b) { b.checked = on; });
      update();
    });
    resBtn.addEventListener("click", function () {
      var ids = checked().map(function (b) { return b.value; });
      if (ids.length) location.href = "?side=reserver&ids=" + ids.join(",");
    });
    retBtn.addEventListener("click", function () {
      var ids = checked().map(function (b) { return b.value; });
      if (!ids.length || !window.confirm("Marker " + ids.length + " Enhed(er) som returneret?")) return;
      var f = document.getElementById("sl-return-form");
      ids.forEach(function (id) {
        var i = document.createElement("input");
        i.type = "hidden"; i.name = "ids[]"; i.value = id;
        f.appendChild(i);
      });
      f.submit();
    });
    update();
  }

  // Tilføj Enhed: live preview
  var type = document.getElementById("item-type-select");
  if (type) {
    var sku = document.getElementById("sku-input"), name = document.getElementById("item-name"), loc = document.getElementById("item-location");
    var show = function (el, v) { el.textContent = v || "-"; el.classList.toggle("sl-muted", !v); };
    var preview = function () {
      var s = sku.value.trim();
      show(document.getElementById("preview-sku"), s ? (type.value ? type.value + "-" : "") + s : "");
      show(document.getElementById("preview-name"), name.value.trim());
      show(document.getElementById("preview-location"), loc.value.trim());
    };
    [sku, name, loc].forEach(function (el) { el.addEventListener("input", preview); });
    type.addEventListener("change", preview);
    preview();
  }

  // Ny bruger: foreslå brugernavn og lav en ny adgangskode (som i editoren)
  var uNavn = document.getElementById("u-navn"), uBn = document.getElementById("u-bn");
  if (uNavn && uBn) {
    var touched = uBn.value !== "";
    uBn.addEventListener("input", function () { touched = true; });
    uNavn.addEventListener("input", function () {
      if (touched) return;
      uBn.value = (uNavn.value.trim().split(/\s+/)[0] || "").toLowerCase()
        .replace(/æ/g, "ae").replace(/ø/g, "oe").replace(/å/g, "aa")
        .normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]/g, "");
    });
    document.getElementById("u-pw-new").addEventListener("click", function () {
      var words = ["scene", "lys", "kulisse", "rolle", "sang", "dans", "musik", "billet", "applaus", "premiere", "replik", "kostume", "maske", "drama", "finale", "prolog", "regi", "rampe", "kor", "solo"];
      var r = new Uint32Array(5);
      crypto.getRandomValues(r);
      var w = [0, 1, 2, 3].map(function (i) { return words[r[i] % words.length]; });
      w[0] = w[0].charAt(0).toUpperCase() + w[0].slice(1);
      document.getElementById("u-pw").value = w.join("-") + "-" + (r[4] % 90 + 10);
    });
  }

  // Kopiér login-beskeden
  var copy = document.getElementById("sl-copy");
  if (copy) {
    copy.addEventListener("click", function () {
      var text = document.getElementById("sl-creds-text").textContent;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () { copy.textContent = "Kopieret!"; });
      }
    });
  }
})();
