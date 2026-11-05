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

// Levende, sløret rød/sort baggrund bag hele siden – tegnes af browseren (WebGL), så den er skarp på alle skærme.
// Det er én sammenhængende baggrund: rød silke ved "Vær med", der glider blødt over i næsten sort resten af vejen.
// Den bevæger sig roligt og følger med, når man scroller. Kan browseren ikke tegne den, bruges billederne fra style.css.
(function () {
  "use strict";
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const VERT = "attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}";
  const FRAG = [
    "#ifdef GL_FRAGMENT_PRECISION_HIGH", "precision highp float;", "#else", "precision mediump float;", "#endif",
    "uniform vec2 r;uniform float t;uniform float sy;uniform vec4 band;",
    "float h(vec2 p){p=fract(p*vec2(.1031,.1030));p+=dot(p,p.yx+33.33);return fract((p.x+p.y)*p.x);}",
    "float n(vec2 p){vec2 i=floor(p),f=fract(p);vec2 u=f*f*(3.-2.*f);",
    " return mix(mix(h(i),h(i+vec2(1.,0.)),u.x),mix(h(i+vec2(0.,1.)),h(i+vec2(1.,1.)),u.x),u.y);}",
    "float fbm(vec2 p){float v=0.,a=.55;mat2 m=mat2(1.6,1.2,-1.2,1.6);for(int i=0;i<3;i++){v+=a*n(p);p=m*p;a*=.42;}return v/.9;}",
    "vec3 pal(float f){",
    " vec3 c=mix(vec3(.02,.012,.012),vec3(.16,.02,.03),smoothstep(0.,.3,f));",
    " c=mix(c,vec3(.44,.04,.07),smoothstep(.25,.55,f));",
    " c=mix(c,vec3(.71,.06,.10),smoothstep(.5,.78,f));",
    " return mix(c,vec3(.89,.13,.15),smoothstep(.75,1.,f));}",
    "void main(){",
    " vec2 uv=(vec2(gl_FragCoord.x,gl_FragCoord.y-sy)-.5*r)/max(r.y,r.x*.56);",
    " float a=-.55;uv=mat2(cos(a),-sin(a),sin(a),cos(a))*uv;",
    " vec2 p=vec2(uv.x*.75,uv.y*1.4);",
    " float tt=t*.06;",
    " vec2 q=vec2(fbm(p+tt*.6),fbm(p+vec2(5.2,1.3)-tt*.5));",
    " vec2 s=vec2(fbm(p+1.7*q+vec2(1.7,9.2)+tt*.35),fbm(p+1.7*q+vec2(8.3,2.8)-tt*.4));",
    " float f=smoothstep(.18,.82,fbm(p+1.6*s));",
    // rød omkring "Vær med" (band = nederste og øverste kant på skærmen), mørk længere væk
    " float y=gl_FragCoord.y;float d=max(max(band.x-y,y-band.y),0.);",
    " float tone=band.w*(1.-smoothstep(0.,band.z,d));",
    " f=mix(f*.3-.05,f*.88+.14,tone);",
    " vec3 col=pal(clamp(f,0.,1.));",
    " col+=(h(gl_FragCoord.xy+fract(t)*61.)-.5)/160.;",
    " gl_FragColor=vec4(col,1.);}",
  ].join("\n");

  const SCALE = 0.5;   // tegn i halv opløsning – baggrunden er sløret, så det ses ikke, og det skåner batteriet
  const FOLLOW = 0.7;  // mønsteret følger med, når man scroller (lidt langsommere end indholdet)
  const FADE = 200;    // så mange px glider det røde over i det mørke over og under "Vær med"
  const join = document.querySelector(".join");

  const canvas = document.createElement("canvas");
  canvas.className = "bg-gl";
  canvas.setAttribute("aria-hidden", "true");
  let gl = null, U = null;
  try {
    gl = canvas.getContext("webgl", { alpha: false, antialias: false, depth: false, stencil: false, powerPreference: "low-power" });
    if (!gl) return;
    const shader = (type, src) => {
      const sh = gl.createShader(type);
      gl.shaderSource(sh, src);
      gl.compileShader(sh);
      if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(sh) || "shader");
      return sh;
    };
    const prog = gl.createProgram();
    gl.attachShader(prog, shader(gl.VERTEX_SHADER, VERT));
    gl.attachShader(prog, shader(gl.FRAGMENT_SHADER, FRAG));
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) throw new Error("link");
    gl.useProgram(prog);
    gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    const loc = gl.getAttribLocation(prog, "p");
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
    U = { r: gl.getUniformLocation(prog, "r"), t: gl.getUniformLocation(prog, "t"), sy: gl.getUniformLocation(prog, "sy"), band: gl.getUniformLocation(prog, "band") };
  } catch (e) {
    return;
  }
  document.body.prepend(canvas);
  let dead = false;
  canvas.addEventListener("webglcontextlost", (e) => { e.preventDefault(); dead = true; canvas.remove(); document.documentElement.classList.remove("bg-gl-on"); });

  const draw = (t) => {
    if (dead) return;
    const w = Math.max(1, Math.round(canvas.clientWidth * SCALE)), hgt = Math.max(1, Math.round(canvas.clientHeight * SCALE));
    if (canvas.width !== w || canvas.height !== hgt) { canvas.width = w; canvas.height = hgt; gl.viewport(0, 0, w, hgt); }
    gl.uniform2f(U.r, w, hgt);
    gl.uniform1f(U.t, t % 2000);
    gl.uniform1f(U.sy, scrollY * FOLLOW * SCALE);
    if (join) {
      const b = join.getBoundingClientRect();
      gl.uniform4f(U.band, hgt - b.bottom * SCALE, hgt - b.top * SCALE, FADE * SCALE, 1);
    } else {
      gl.uniform4f(U.band, 0, 0, 1, 0);
    }
    gl.drawArrays(gl.TRIANGLES, 0, 3);
  };

  const t0 = 40; // start et stykke inde i bevægelsen
  draw(t0);
  document.documentElement.classList.add("bg-gl-on");

  if (reduce) {
    // Ingen bevægelse – men baggrunden følger stadig med, når man scroller
    let queued = false;
    const redraw = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; draw(t0); }); };
    addEventListener("scroll", redraw, { passive: true });
    addEventListener("resize", redraw);
    return;
  }
  const start = performance.now();
  let last = 0, lastY = -1;
  const frame = (now) => {
    requestAnimationFrame(frame);
    const scrolling = scrollY !== lastY;
    if (!scrolling && now - last < 33) return; // ca. 30 billeder i sekundet, når man ikke scroller
    last = now;
    lastY = scrollY;
    draw(t0 + (now - start) / 1000);
  };
  requestAnimationFrame(frame);
})();
