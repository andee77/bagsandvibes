/* ==========================================================================
   Checked Bags & Good Vibes — mobile nav toggle
   No GSAP, no ScrollTrigger. Nav links are plain anchor jumps handled by
   the browser via `scroll-behavior: smooth` in styles.css.
   ========================================================================== */
(function () {
  "use strict";

  var toggle = document.getElementById("nav-toggle");
  var nav = document.getElementById("primary-nav");
  if (!toggle || !nav) return;

  toggle.addEventListener("click", function () {
    var open = nav.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  });

  // Close the mobile menu after tapping a nav link
  nav.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () {
      if (nav.classList.contains("is-open")) {
        nav.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      }
    });
  });
})();
