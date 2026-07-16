/* ==========================================================================
   Checked Bags & Good Vibes — Scrollytelling engine
   Data-driven: edit the PHASES array to change images, focus points, or copy.
   ========================================================================== */
(function () {
  "use strict";

  gsap.registerPlugin(ScrollTrigger, ScrollToPlugin);

  var prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // ------------------------------------------------------------------------
  // 1. PHASE CONFIG — mirrors the Animation Sequence Matrix in the spec.
  //    origin values are "x% y%" transform-origin points on the source image.
  // ------------------------------------------------------------------------
  var PHASES = [
    {
      id: "sunset",
      gate: "GATE 01",
      label: "First Light",
      hasZoomOut: false,
      startScale: 1,
      startOrigin: "50% 50%",
      endScale: 1.22,
      endOrigin: "62% 38%" // into the sunset
    },
    {
      id: "cabin",
      gate: "GATE 02",
      label: "Cabin Views",
      hasZoomOut: true,
      startScale: 1.35,
      startOrigin: "50% 45%", // starts zoomed on the porthole
      fullOrigin: "50% 50%",
      endScale: 1.25,
      endOrigin: "48% 42%" // island inside the porthole
    },
    {
      id: "beach",
      gate: "GATE 03",
      label: "Shore Leave",
      hasZoomOut: true,
      startScale: 1.3,
      startOrigin: "28% 72%", // greenery
      fullOrigin: "50% 50%",
      endScale: 1.3,
      endOrigin: "68% 62%" // the drink in her hand
    },
    {
      id: "packing",
      gate: "GATE 04",
      label: "Pack List: Vibes Only",
      hasZoomOut: true,
      startScale: 1.3,
      startOrigin: "85% 50%", // side wall
      fullOrigin: "50% 50%",
      endScale: 1.25,
      endOrigin: "18% 28%" // the room wall
    },
    {
      id: "dancing",
      gate: "GATE 05",
      label: "Golden Hour Encore",
      hasZoomOut: true,
      startScale: 1.35,
      startOrigin: "55% 68%", // his shirt
      fullOrigin: "50% 50%",
      endScale: 1.3,
      endOrigin: "50% 12%" // the night sky
    },
    {
      id: "boarding",
      gate: "GATE 06",
      label: "Final Boarding",
      hasZoomOut: true,
      startScale: 1.35,
      startOrigin: "50% 10%", // starts on the night sky
      fullOrigin: "50% 50%",
      endScale: 1.3,
      endOrigin: "30% 55%" // the plane's blue exterior
    }
  ];

  var ZOOM_OUT_W   = 0.5;
  var ZOOM_IN_W    = 0.6;
  var HERO_ZOOM_W  = 0.7;
  var TRANSITION_W = 0.3;
  var CTA_HOLD_W   = 0.6;

  // ------------------------------------------------------------------------
  // 2. DOM refs
  // ------------------------------------------------------------------------
  var layerEls = Array.prototype.slice.call(document.querySelectorAll(".image-layer"));
  var ctaLayer = document.getElementById("cta-layer");
  var heroOverlay = document.getElementById("hero-overlay");
  var phaseTag = document.getElementById("phase-tag");
  var phaseTagGate = document.getElementById("phase-tag-gate");
  var phaseTagLabel = document.getElementById("phase-tag-label");
  var navList = document.getElementById("phase-nav-list");

  function mediaOf(layerEl) {
    return layerEl.querySelector(".image-layer-media");
  }

  // ------------------------------------------------------------------------
  // 3. Build nav from PHASES
  // ------------------------------------------------------------------------
  var navButtons = PHASES.map(function (phase, i) {
    var li = document.createElement("li");
    var btn = document.createElement("button");
    btn.type = "button";
    btn.textContent = phase.label;
    btn.setAttribute("data-phase-index", i);
    btn.setAttribute("aria-current", "false");
    li.appendChild(btn);
    navList.appendChild(li);
    return btn;
  });

  // ------------------------------------------------------------------------
  // 4. Reduced motion: static stacked sections, no pin/zoom, plain anchor nav
  // ------------------------------------------------------------------------
  if (prefersReducedMotion) {
    document.body.classList.add("reduced-motion");

    layerEls.forEach(function (layer, i) {
      layer.id = "phase-" + PHASES[i].id;
    });
    ctaLayer.id = "phase-cta";

    navButtons.forEach(function (btn, i) {
      btn.addEventListener("click", function () {
        document.getElementById("phase-" + PHASES[i].id).scrollIntoView({ behavior: "smooth" });
      });
    });

    setupNavToggle();
    return; // skip the GSAP pin/scrub engine entirely
  }

  // ------------------------------------------------------------------------
  // 5. Initial inline state (pre-scroll) for every layer
  // ------------------------------------------------------------------------
  layerEls.forEach(function (layer, i) {
    var phase = PHASES[i];
    var media = mediaOf(layer);
    gsap.set(layer, { opacity: i === 0 ? 1 : 0 });
    gsap.set(media, {
      scale: phase.startScale,
      transformOrigin: phase.hasZoomOut ? phase.startOrigin : phase.endOrigin
    });
  });
  gsap.set(ctaLayer, { opacity: 0 });

  // ------------------------------------------------------------------------
  // 6. Master timeline
  // ------------------------------------------------------------------------
  var tl = gsap.timeline();
  var phaseStartLabels = []; // timeline time (seconds) marking a clean nav jump point per phase

  PHASES.forEach(function (phase, i) {
    var layer = layerEls[i];
    var media = mediaOf(layer);
    var labelName = "phase-" + i + "-start";

    tl.addLabel(labelName);
    phaseStartLabels.push(labelName);

    if (i === 0) {
      // Hero: fade the welcome copy as the first zoom begins, then zoom into the sunset.
      tl.to(heroOverlay, { opacity: 0, duration: HERO_ZOOM_W * 0.6 }, labelName);
      tl.to(media, { scale: phase.endScale, duration: HERO_ZOOM_W }, labelName);
    } else {
      tl.to(media, { scale: 1, transformOrigin: phase.fullOrigin, duration: ZOOM_OUT_W }, labelName);
      tl.to(media, { scale: phase.endScale, transformOrigin: phase.endOrigin, duration: ZOOM_IN_W });
    }

    // Transition into the next layer (or the CTA, on the final phase)
    var nextTarget = (i === PHASES.length - 1) ? ctaLayer : layerEls[i + 1];
    tl.to(layer, { opacity: 0, duration: TRANSITION_W }, ">");
    tl.to(nextTarget, { opacity: 1, duration: TRANSITION_W }, "<");
  });

  // CTA settle
  tl.fromTo(
    ".cta-content",
    { y: 18, opacity: 0.85 },
    { y: 0, opacity: 1, duration: CTA_HOLD_W }
  );

  var TOTAL = tl.duration();

  // ------------------------------------------------------------------------
  // 7. Pin + scrub
  // ------------------------------------------------------------------------
  var mainST = ScrollTrigger.create({
    trigger: "#scrollytelling",
    start: "top top",
    end: function () { return "+=" + (TOTAL * 100) + "vh"; },
    pin: true,
    scrub: 1,
    animation: tl,
    onUpdate: syncChrome
  });

  // ------------------------------------------------------------------------
  // 8. Phase tag + nav highlighting, driven off timeline progress
  // ------------------------------------------------------------------------
  var boundaries = phaseStartLabels.map(function (label) { return tl.labels[label]; });

  function currentPhaseIndex(t) {
    var idx = 0;
    for (var i = 0; i < boundaries.length; i++) {
      if (t >= boundaries[i]) idx = i;
    }
    return idx;
  }

  function syncChrome() {
    var t = tl.time();
    var ctaStart = boundaries[boundaries.length - 1] + ZOOM_OUT_W + ZOOM_IN_W + TRANSITION_W;
    var inCTA = t >= ctaStart - 0.02;

    if (inCTA) {
      phaseTag.classList.remove("is-visible");
      navButtons.forEach(function (btn) { btn.setAttribute("aria-current", "false"); });
      return;
    }

    var idx = currentPhaseIndex(t);
    var phase = PHASES[idx];

    phaseTagGate.textContent = phase.gate;
    phaseTagLabel.textContent = phase.label;
    phaseTag.classList.add("is-visible");

    navButtons.forEach(function (btn, i) {
      btn.setAttribute("aria-current", i === idx ? "true" : "false");
    });
  }
  syncChrome();

  // ------------------------------------------------------------------------
  // 9. Nav click -> smooth jump via ScrollToPlugin
  // ------------------------------------------------------------------------
  navButtons.forEach(function (btn, i) {
    btn.addEventListener("click", function () {
      var label = phaseStartLabels[i];
      var ratio = tl.labels[label] / TOTAL;
      var targetY = mainST.start + ratio * (mainST.end - mainST.start);
      gsap.to(window, { duration: 1, ease: "power2.inOut", scrollTo: { y: targetY } });
      closeNav();
    });
  });

  setupNavToggle();

  // ------------------------------------------------------------------------
  // 10. Mobile nav toggle
  // ------------------------------------------------------------------------
  function setupNavToggle() {
    var toggle = document.getElementById("nav-toggle");
    var nav = document.getElementById("primary-nav");
    if (!toggle || !nav) return;
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  function closeNav() {
    var toggle = document.getElementById("nav-toggle");
    var nav = document.getElementById("primary-nav");
    if (nav && nav.classList.contains("is-open")) {
      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    }
  }
})();