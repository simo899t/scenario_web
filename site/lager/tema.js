/* Vælger lys/mørk før siden tegnes, så den ikke blinker. Standard: som computeren/telefonen. */
try {
  var t = localStorage.getItem("lager-tema");
  if (t === "dark" || t === "light") document.documentElement.setAttribute("data-theme", t);
} catch (e) { /* privat browsing e.l. */ }
