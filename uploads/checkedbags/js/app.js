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

  // Nav dropdowns (My Profile / Navigation / Account) -- click-toggled, not
  // hover, so touch and desktop behave the same and there's no hover/focus
  // edge cases to fight. Same is-open + aria-expanded pattern as the
  // hamburger toggle above.
  var dropdowns = nav.querySelectorAll(".nav-dropdown");

  function closeAllDropdowns() {
    dropdowns.forEach(function (d) {
      d.classList.remove("is-open");
      var b = d.querySelector(".nav-dropdown-toggle");
      if (b) {
        b.setAttribute("aria-expanded", "false");
      }
    });
  }

  dropdowns.forEach(function (dropdown) {
    var btn = dropdown.querySelector(".nav-dropdown-toggle");
    if (!btn) {
      return;
    }

    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var wasOpen = dropdown.classList.contains("is-open");
      closeAllDropdowns();
      if (!wasOpen) {
        dropdown.classList.add("is-open");
        btn.setAttribute("aria-expanded", "true");
      }
    });
  });

  document.addEventListener("click", closeAllDropdowns);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeAllDropdowns();
    }
  });
})();
