document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector(".diamondistry-dashboard-tabs");
  if (!root) {
    return;
  }

  const tabs = root.querySelectorAll("[data-dashboard-tab]");
  const panels = document.querySelectorAll("[data-dashboard-panel]");

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
    });
  });
});
