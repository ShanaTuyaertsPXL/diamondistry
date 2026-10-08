document.addEventListener("DOMContentLoaded", () => {
  const hero = document.querySelector(".diamondistry-home-hero");
  const photo = document.querySelector(".diamondistry-home-hero-photo");

  if (hero && photo && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
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

  const closeButton = menu.querySelector(".diamondistry-menu-close");

  function setOpen(open) {
    menu.hidden = !open;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    document.documentElement.classList.toggle("is-menu-open", open);
  }

  toggle.addEventListener("click", () => {
    setOpen(menu.hidden);
  });

  if (closeButton) {
    closeButton.addEventListener("click", () => setOpen(false));
  }

  menu.addEventListener("click", (event) => {
    if (event.target === menu) {
      setOpen(false);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !menu.hidden) {
      setOpen(false);
      toggle.focus();
    }
  });
});
