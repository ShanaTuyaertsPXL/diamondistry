document.addEventListener("DOMContentLoaded", () => {
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  let lenis = null;

  // Lenis smooths wheel/trackpad only; touch keeps native scrolling.
  if (window.Lenis && !reduceMotion) {
    lenis = new window.Lenis({ lerp: 0.12 });

    const raf = (time) => {
      lenis.raf(time);
      window.requestAnimationFrame(raf);
    };
    window.requestAnimationFrame(raf);

    document.addEventListener("click", (event) => {
      const link = event.target.closest('a[href^="#"]');
      const target = link && link.hash.length > 1 && document.querySelector(link.hash);
      if (!target) {
        return;
      }
      event.preventDefault();
      const header = document.querySelector(".diamondistry-site-header");
      lenis.scrollTo(target, { offset: header ? -header.offsetHeight : 0 });
      history.pushState(null, "", link.hash);
    });
  }

  // Scroll reveal: one-shot per element, then unobserved.
  const animated = document.querySelectorAll("[data-animate]");
  if (document.documentElement.classList.contains("dd-anim")) {
    if (!animated.length || !("IntersectionObserver" in window)) {
      document.documentElement.classList.remove("dd-anim");
    } else {
      const reveal = (el, delay) => {
        el.style.setProperty("--dd-delay", `${delay}ms`);
        el.classList.add("is-revealing", "is-inview");
        // Drop the reveal transition afterwards so hover effects keep their own timing.
        window.setTimeout(() => {
          el.classList.remove("is-revealing");
          el.style.removeProperty("--dd-delay");
        }, delay + 900);
      };

      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) {
              return;
            }
            observer.unobserve(entry.target);
            if (entry.target.dataset.animate === "stagger") {
              [...entry.target.children].forEach((child, i) => reveal(child, i * 90));
            } else {
              reveal(entry.target, 0);
            }
          });
        },
        { rootMargin: "0px 0px -8% 0px", threshold: 0.1 },
      );

      animated.forEach((el) => observer.observe(el));
      window.ddAnimReady = true;
    }
  }

  // FAQ accordion: animate height. Without JS (or with reduced motion) the native
  // <details name="faq"> behaviour still gives one-open-at-a-time.
  const faqItems = [...document.querySelectorAll(".dd-faq__item")];
  if (faqItems.length && !reduceMotion) {
    const timing = { duration: 320, easing: "cubic-bezier(0.22, 1, 0.36, 1)" };

    const animateBody = (item, from, to, done) => {
      const body = item.querySelector(".dd-faq__a");
      if (item.faqAnim) {
        item.faqAnim.cancel();
      }
      item.faqAnim = body.animate({ height: [from, to], opacity: [from === "0px" ? 0 : 1, to === "0px" ? 0 : 1] }, timing);
      item.faqAnim.onfinish = () => {
        item.faqAnim = null;
        done();
      };
    };

    const closeItem = (item) => {
      const body = item.querySelector(".dd-faq__a");
      item.classList.add("is-closing");
      animateBody(item, `${body.offsetHeight}px`, "0px", () => {
        item.open = false;
        item.classList.remove("is-closing");
      });
    };

    const openItem = (item) => {
      const body = item.querySelector(".dd-faq__a");
      item.open = true;
      animateBody(item, "0px", `${body.offsetHeight}px`, () => {});
    };

    faqItems.forEach((item) => {
      // JS handles exclusivity; native name-grouping would snap the other item shut.
      item.removeAttribute("name");
      item.querySelector(".dd-faq__q").addEventListener("click", (event) => {
        event.preventDefault();
        if (item.open && !item.classList.contains("is-closing")) {
          closeItem(item);
          return;
        }
        item.classList.remove("is-closing");
        faqItems.forEach((other) => {
          if (other !== item && other.open && !other.classList.contains("is-closing")) {
            closeItem(other);
          }
        });
        openItem(item);
      });
    });
  }

  // Account: preview the chosen profile photo before saving.
  // Login page: fill in the shared demo account.
  const demoFill = document.querySelector("[data-demo-fill]");
  if (demoFill) {
    demoFill.addEventListener("click", () => {
      const form = demoFill.closest(".dd-auth__card").querySelector("form");
      form.elements.log.value = demoFill.dataset.login;
      form.elements.pwd.value = demoFill.dataset.password;
      form.querySelector('[type="submit"]').focus();
    });
  }

  const avatarInput = document.querySelector(".dd-avatar-field__input");
  if (avatarInput) {
    avatarInput.addEventListener("change", () => {
      const file = avatarInput.files && avatarInput.files[0];
      const img = document.querySelector(".dd-avatar-field__preview img");
      if (file && img) {
        img.removeAttribute("srcset");
        img.src = URL.createObjectURL(file);
      }
    });
  }

  // Class on the header, not <html>: Lenis rewrites <html> className while scrolling.
  const siteHeader = document.querySelector(".diamondistry-site-header");
  if (siteHeader) {
    const setScrolled = () => {
      siteHeader.classList.toggle("is-scrolled", window.scrollY > 4);
    };
    setScrolled();
    window.addEventListener("scroll", setScrolled, { passive: true });
  }

  const hero = document.querySelector(".diamondistry-home-hero");
  const photo = document.querySelector(".diamondistry-home-hero-photo");

  if (hero && photo && !reduceMotion) {
    let frame = 0;

    const updateHero = () => {
      frame = 0;
      const distance = -hero.getBoundingClientRect().top;
      photo.style.transform = `translate3d(0, ${distance * 0.18}px, 0)`;
    };

    const requestHero = () => {
      if (!frame) {
        frame = window.requestAnimationFrame(updateHero);
      }
    };

    updateHero();
    window.addEventListener("scroll", requestHero, { passive: true });
    window.addEventListener("resize", requestHero);
  }

  const toggle = document.querySelector(".diamondistry-menu-toggle");
  const menu = document.getElementById("diamondistry-mobile-menu");

  if (!toggle || !menu) {
    return;
  }

  const toggleLabel = toggle.querySelector(".screen-reader-text");
  let closeTimer = 0;

  // The menu stays `hidden` while closed; is-open drives the slide and fade.
  function setOpen(open) {
    clearTimeout(closeTimer);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (toggleLabel) {
      toggleLabel.textContent = open ? "Close menu" : "Menu";
    }
    document.documentElement.classList.toggle("is-menu-open", open);

    if (open) {
      menu.hidden = false;
      menu.getBoundingClientRect(); // commit the closed state so the slide-in runs
      menu.classList.add("is-open");
    } else {
      menu.classList.remove("is-open");
      // Matches the panel transition; reduced motion has none, so this is a timer, not transitionend.
      closeTimer = setTimeout(() => {
        menu.hidden = true;
      }, reduceMotion ? 0 : 420);
    }

    if (lenis) {
      open ? lenis.stop() : lenis.start();
    }
  }

  const isOpen = () => menu.classList.contains("is-open");

  toggle.addEventListener("click", () => {
    setOpen(!isOpen());
  });

  menu.addEventListener("click", (event) => {
    if (event.target === menu) {
      setOpen(false);
    }
  });

  // Tabbing past the last link closes the menu instead of moving behind it.
  menu.addEventListener("focusout", (event) => {
    if (isOpen() && event.relatedTarget && !menu.contains(event.relatedTarget) && event.relatedTarget !== toggle) {
      setOpen(false);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });
});
