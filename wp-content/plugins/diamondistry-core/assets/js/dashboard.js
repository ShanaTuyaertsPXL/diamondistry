document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector(".diamondistry-dashboard-tabs");
  if (!root) {
    return;
  }

  const tabs = root.querySelectorAll("[data-dashboard-tab]");
  const panels = document.querySelectorAll("[data-dashboard-panel]");
  const indicator = root.querySelector(".diamondistry-dashboard-tabs-indicator");

  const moveIndicator = () => {
    const active = root.querySelector("[data-dashboard-tab].is-active");
    if (!indicator || !active) {
      return;
    }
    indicator.style.width = `${active.offsetWidth}px`;
    indicator.style.transform = `translateX(${active.offsetLeft}px)`;
  };

  moveIndicator();
  window.addEventListener("resize", moveIndicator);
  // Web fonts change tab widths after first paint.
  if (document.fonts) {
    document.fonts.ready.then(moveIndicator);
  }

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const name = tab.dataset.dashboardTab;
      tabs.forEach((item) => {
        const on = item === tab;
        item.classList.toggle("is-active", on);
        item.setAttribute("aria-selected", on ? "true" : "false");
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.dashboardPanel !== name;
      });
      moveIndicator();
    });
  });
});
