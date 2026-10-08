document.addEventListener("DOMContentLoaded", () => {
  const grid = document.getElementById("diamondistry-grid");
  const palette = document.getElementById("diamondistry-palette");
  const progressBar = document.getElementById("diamondistry-progress-bar");
  const completedElement = document.getElementById("diamondistry-completed");
  const totalElement = document.getElementById("diamondistry-total");
  const titleElement = document.getElementById("diamondistry-title");
  const message = document.getElementById("diamondistry-message");
  const resetButton = document.getElementById("diamondistry-reset");

  if (!grid || !palette || !message) {
    return;
  }

  const painting = window.DiamondistryPainting || {};

  const userState = window.DiamondistryUser || {
    loggedIn: false,
    progress: null,
    balance: 0,
    ajaxUrl: "",
    nonce: "",
  };

  /*
  |--------------------------------------------------------------------------
  | Validate painting data
  |--------------------------------------------------------------------------
  */

  if (
    !painting.id ||
    !painting.postId ||
    !painting.title ||
    !Number(painting.width) ||
    !Number(painting.height) ||
    !Array.isArray(painting.palette) ||
    !Array.isArray(painting.pattern)
  ) {
    message.textContent = "Painting kon niet correct worden geladen.";
    return;
  }

  painting.postId = Number(painting.postId);
  painting.width = Number(painting.width);
  painting.height = Number(painting.height);
  painting.reward = Number(painting.reward || 0);

  painting.palette = painting.palette.map((color) => ({
    ...color,
    id: Number(color.id),
  }));

  painting.pattern = painting.pattern.map((value) => Number(value));

  const expectedCells = painting.width * painting.height;

  if (painting.pattern.length !== expectedCells) {
    message.textContent = `Ongeldig patroon: verwacht ${expectedCells} vakjes, maar vond ${painting.pattern.length}.`;
    return;
  }

  if (painting.pattern.some((value) => value === 0)) {
    message.textContent =
      "Deze painting is nog niet compleet en kan nog niet gespeeld worden.";
    return;
  }

  const paletteIds = new Set(painting.palette.map((color) => color.id));

  if (painting.pattern.some((value) => !paletteIds.has(value))) {
    message.textContent =
      "Deze painting bevat een ongeldige kleur en kan niet geladen worden.";
    return;
  }

  /*
  |--------------------------------------------------------------------------
  | Only colors actually used by this painting
  |--------------------------------------------------------------------------
  */

  const usedColorIds = new Set(painting.pattern);

  const colors = painting.palette.filter((color) => usedColorIds.has(color.id));

  if (!colors.length) {
    message.textContent = "Deze painting bevat geen bruikbare kleuren.";
    return;
  }

  const totalDiamonds = expectedCells;

  const storageKey = `diamondistry-progress-${painting.id}`;

  let selectedColor = colors[0]?.id ?? null;
  let completed = 0;
  let isPainting = false;

  let saveTimer = null;

  const totalsByColor = {};
  const completedByColor = {};

  colors.forEach((color) => {
    totalsByColor[color.id] = 0;
    completedByColor[color.id] = 0;
  });

  if (titleElement) {
    titleElement.textContent = painting.title;
  }

  document.title = `${painting.title} – Diamondistry`;

  if (totalElement) {
    totalElement.textContent = totalDiamonds;
  }

  /*
  |--------------------------------------------------------------------------
  | Helpers
  |--------------------------------------------------------------------------
  */

  function getColor(colorId) {
    return colors.find((color) => color.id === Number(colorId));
  }

  function getRemaining(colorId) {
    return totalsByColor[colorId] - completedByColor[colorId];
  }

  function getCompletedIndexes() {
    const indexes = [];

    document.querySelectorAll(".diamondistry-cell").forEach((cell) => {
      if (cell.dataset.completed === "true") {
        indexes.push(Number(cell.dataset.index));
      }
    });

    return indexes;
  }

  /*
  |--------------------------------------------------------------------------
  | Grid
  |--------------------------------------------------------------------------
  */

  function createGrid() {
    grid.innerHTML = "";

    grid.style.gridTemplateColumns = `repeat(${painting.width}, 1fr)`;

    painting.pattern.forEach((colorId, index) => {
      const color = getColor(colorId);

      if (!color) {
        return;
      }

      totalsByColor[color.id]++;

      const cell = document.createElement("button");

      cell.type = "button";
      cell.className = "diamondistry-cell";

      cell.dataset.color = color.id;
      cell.dataset.completed = "false";
      cell.dataset.index = index;

      cell.textContent = color.symbol;

      cell.setAttribute("aria-label", `${color.name} diamond`);

      grid.appendChild(cell);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Palette
  |--------------------------------------------------------------------------
  */

  function createPalette() {
    palette.innerHTML = "";

    colors.forEach((color, index) => {
      const button = document.createElement("button");

      button.type = "button";
      button.className = "diamondistry-color";
      button.dataset.color = color.id;

      if (index === 0) {
        button.classList.add("is-selected");
      }

      button.innerHTML = `
        <span
          class="diamondistry-color-preview"
          style="--diamond-color: ${color.hex}"
        >
          ${color.symbol}
        </span>

        <span class="diamondistry-color-info">
          <strong>
            ${color.symbol} ${color.name}
          </strong>

          <small>
            Diamond ${color.id}
          </small>
        </span>

        <span class="diamondistry-color-count">
          <strong data-remaining="${color.id}">
            0 over
          </strong>

          <small data-total="${color.id}">
            0 / 0
          </small>
        </span>
      `;

      button.addEventListener("click", () => {
        selectColor(color.id);

        persistProgress();
      });

      palette.appendChild(button);
    });
  }

  function selectColor(colorId, showMessage = true) {
    const color = getColor(colorId);

    if (!color) {
      return;
    }

    selectedColor = color.id;

    document.querySelectorAll(".diamondistry-color").forEach((button) => {
      button.classList.remove("is-selected");
    });

    const active = document.querySelector(
      `.diamondistry-color[data-color="${color.id}"]`,
    );

    if (active) {
      active.classList.add("is-selected");
    }

    if (showMessage) {
      message.textContent = `${color.symbol} ${color.name} geselecteerd`;
    }
  }

  function selectNextAvailableColor() {
    const currentIndex = colors.findIndex(
      (color) => color.id === selectedColor,
    );

    for (let offset = 1; offset <= colors.length; offset++) {
      const nextIndex = (currentIndex + offset) % colors.length;
      const nextColor = colors[nextIndex];

      if (getRemaining(nextColor.id) > 0) {
        selectColor(nextColor.id, false);

        persistProgress();

        message.textContent = `${nextColor.symbol} ${nextColor.name} geselecteerd`;

        return;
      }
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Cell completion
  |--------------------------------------------------------------------------
  */

  function completeCell(cell, color) {
    cell.dataset.completed = "true";

    cell.classList.remove("is-wrong");
    cell.classList.add("is-completed");

    cell.style.setProperty("--diamond-color", color.hex);

    cell.textContent = "";

    cell.setAttribute("aria-label", `${color.name} diamond geplaatst`);
  }

  function placeDiamond(cell) {
    if (!cell || !cell.classList.contains("diamondistry-cell")) {
      return;
    }

    if (cell.dataset.completed === "true") {
      return;
    }

    const color = getColor(cell.dataset.color);

    if (!color) {
      return;
    }

    if (selectedColor !== color.id) {
      cell.classList.remove("is-wrong");

      void cell.offsetWidth;

      cell.classList.add("is-wrong");

      message.textContent = `Verkeerde diamond — kies ${color.symbol} ${color.name}.`;

      return;
    }

    completeCell(cell, color);

    completed++;
    completedByColor[color.id]++;

    updateProgress();
    updatePaletteCounts();

    /*
     * Bij volledige painting meteen opslaan,
     * zodat reward direct kan worden toegekend.
     */
    if (completed === totalDiamonds) {
      persistProgress({
        immediate: true,
      });

      return;
    }

    persistProgress();

    if (getRemaining(color.id) === 0) {
      message.textContent = `✓ ${color.name} klaar`;

      selectNextAvailableColor();
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Progress UI
  |--------------------------------------------------------------------------
  */

  function updateProgress() {
    const percentage =
      totalDiamonds > 0 ? (completed / totalDiamonds) * 100 : 0;

    if (completedElement) {
      completedElement.textContent = completed;
    }

    if (progressBar) {
      progressBar.style.width = `${percentage}%`;
    }

    if (completed === totalDiamonds) {
      message.textContent = `✨ ${painting.title} voltooid!`;
      return;
    }

    message.textContent = `${Math.round(percentage)}% voltooid`;
  }

  function updatePaletteCounts() {
    colors.forEach((color) => {
      const total = totalsByColor[color.id];
      const done = completedByColor[color.id];
      const remaining = total - done;

      const remainingElement = document.querySelector(
        `[data-remaining="${color.id}"]`,
      );

      const totalElementForColor = document.querySelector(
        `[data-total="${color.id}"]`,
      );

      const button = document.querySelector(
        `.diamondistry-color[data-color="${color.id}"]`,
      );

      if (remainingElement) {
        remainingElement.textContent =
          remaining === 0 ? "✓ Klaar" : `${remaining} over`;
      }

      if (totalElementForColor) {
        totalElementForColor.textContent = `${done} / ${total}`;
      }

      if (button) {
        button.classList.toggle("is-complete", remaining === 0);
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Guest localStorage
  |--------------------------------------------------------------------------
  */

  function saveLocalProgress() {
    const progress = {
      version: 2,
      paintingId: painting.id,
      selectedColor,
      completedCells: getCompletedIndexes(),
      updatedAt: new Date().toISOString(),
    };

    localStorage.setItem(storageKey, JSON.stringify(progress));
  }

  function readLocalProgress() {
    const stored = localStorage.getItem(storageKey);

    if (!stored) {
      return null;
    }

    try {
      const progress = JSON.parse(stored);

      if (
        progress.paintingId !== painting.id ||
        !Array.isArray(progress.completedCells)
      ) {
        return null;
      }

      return progress;
    } catch {
      return null;
    }
  }

  /*
  |--------------------------------------------------------------------------
  | WordPress account progress
  |--------------------------------------------------------------------------
  */

  async function saveServerProgress() {
    if (!userState.loggedIn || !userState.ajaxUrl || !userState.nonce) {
      return null;
    }

    const body = new URLSearchParams();

    body.set("action", "diamondistry_save_progress");
    body.set("nonce", userState.nonce);
    body.set("paintingId", String(painting.postId));
    body.set("selectedColor", String(selectedColor || 0));
    body.set("completedCells", JSON.stringify(getCompletedIndexes()));

    try {
      const response = await fetch(userState.ajaxUrl, {
        method: "POST",

        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },

        body: body.toString(),

        credentials: "same-origin",

        /*
         * Zorgt ervoor dat de save nog kan afronden
         * als iemand snel de pagina verlaat.
         */
        keepalive: true,
      });

      const result = await response.json();

      if (!result.success) {
        console.error("Diamondistry progress save failed:", result);
        return null;
      }

      /*
       * Server is leidend.
       * Oude lokale progress mag weg.
       */
      localStorage.removeItem(storageKey);

      if (result.data?.rewardGranted) {
        userState.balance = Number(result.data.balance || 0);

        message.textContent =
          `✨ ${painting.title} voltooid! ` +
          `+${result.data.reward} 💎 · ` +
          `Balance: ${userState.balance} 💎`;
      } else if (completed === totalDiamonds) {
        message.textContent = `✨ ${painting.title} voltooid!`;
      }

      return result.data;
    } catch (error) {
      console.error("Diamondistry progress request failed:", error);

      return null;
    }
  }

  function persistProgress({ immediate = false } = {}) {
    if (!userState.loggedIn) {
      saveLocalProgress();
      return;
    }

    if (saveTimer) {
      clearTimeout(saveTimer);
      saveTimer = null;
    }

    if (immediate) {
      saveServerProgress();
      return;
    }

    saveTimer = window.setTimeout(() => {
      saveTimer = null;

      saveServerProgress();
    }, 350);
  }

  /*
  |--------------------------------------------------------------------------
  | Restore progress
  |--------------------------------------------------------------------------
  */

  function applyProgress(progress) {
    if (!progress || !Array.isArray(progress.completedCells)) {
      return 0;
    }

    completed = 0;

    colors.forEach((color) => {
      completedByColor[color.id] = 0;
    });

    const restored = new Set();

    progress.completedCells.forEach((index) => {
      const numericIndex = Number(index);

      if (
        !Number.isInteger(numericIndex) ||
        numericIndex < 0 ||
        numericIndex >= totalDiamonds ||
        restored.has(numericIndex)
      ) {
        return;
      }

      const cell = document.querySelector(
        `.diamondistry-cell[data-index="${numericIndex}"]`,
      );

      if (!cell) {
        return;
      }

      const color = getColor(cell.dataset.color);

      if (!color) {
        return;
      }

      restored.add(numericIndex);

      completeCell(cell, color);

      completed++;
      completedByColor[color.id]++;
    });

    const storedColor = Number(progress.selectedColor);

    if (colors.some((color) => color.id === storedColor)) {
      selectColor(storedColor, false);
    }

    updateProgress();
    updatePaletteCounts();

    return completed;
  }

  function loadProgress() {
    /*
     * Ingelogde gebruiker:
     * WordPress-progress is leidend.
     */
    if (userState.loggedIn) {
      const serverProgress = userState.progress;

      const serverHasHistory =
        serverProgress &&
        ((Array.isArray(serverProgress.completedCells) &&
          serverProgress.completedCells.length > 0) ||
          serverProgress.completed ||
          serverProgress.rewardClaimed ||
          serverProgress.selectedColor);

      if (serverHasHistory) {
        applyProgress(serverProgress);

        if (completed > 0 && completed < totalDiamonds) {
          const percentage = Math.round((completed / totalDiamonds) * 100);

          message.textContent = `Verder waar je gebleven was — ${percentage}% voltooid`;
        }

        return;
      }

      /*
       * Eenmalige migratie van oude localStorage
       * naar WordPress-accountprogress.
       */
      const localProgress = readLocalProgress();

      if (localProgress && localProgress.completedCells.length > 0) {
        applyProgress(localProgress);

        message.textContent =
          "Bestaande voortgang wordt naar je account overgezet…";

        saveServerProgress().then(() => {
          if (completed < totalDiamonds) {
            const percentage = Math.round((completed / totalDiamonds) * 100);

            message.textContent = `Voortgang opgeslagen in je account — ${percentage}% voltooid`;
          }
        });

        return;
      }

      updateProgress();
      updatePaletteCounts();

      return;
    }

    /*
     * Gastgebruiker.
     */
    const localProgress = readLocalProgress();

    if (!localProgress) {
      updateProgress();
      updatePaletteCounts();

      return;
    }

    applyProgress(localProgress);

    if (completed > 0 && completed < totalDiamonds) {
      const percentage = Math.round((completed / totalDiamonds) * 100);

      message.textContent = `Verder waar je gebleven was — ${percentage}% voltooid`;
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Reset UI
  |--------------------------------------------------------------------------
  */

  function resetInterface() {
    completed = 0;

    selectedColor = colors[0]?.id ?? null;

    colors.forEach((color) => {
      completedByColor[color.id] = 0;
    });

    document.querySelectorAll(".diamondistry-cell").forEach((cell) => {
      const color = getColor(cell.dataset.color);

      cell.dataset.completed = "false";

      cell.classList.remove("is-completed", "is-wrong");

      cell.style.removeProperty("--diamond-color");

      if (color) {
        cell.textContent = color.symbol;

        cell.setAttribute("aria-label", `${color.name} diamond`);
      }
    });

    if (colors.length) {
      selectColor(colors[0].id, false);
    }

    updateProgress();
    updatePaletteCounts();
  }

  async function resetServerProgress() {
    const body = new URLSearchParams();

    body.set("action", "diamondistry_reset_progress");
    body.set("nonce", userState.nonce);
    body.set("paintingId", String(painting.postId));

    const response = await fetch(userState.ajaxUrl, {
      method: "POST",

      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },

      body: body.toString(),

      credentials: "same-origin",
    });

    return response.json();
  }

  async function resetPainting() {
    const confirmed = window.confirm(
      `Weet je zeker dat je "${painting.title}" opnieuw wilt beginnen?`,
    );

    if (!confirmed) {
      return;
    }

    if (userState.loggedIn) {
      try {
        const result = await resetServerProgress();

        if (!result.success) {
          window.alert("Reset kon niet worden opgeslagen. Probeer opnieuw.");

          return;
        }

        localStorage.removeItem(storageKey);

        resetInterface();

        message.textContent = "Painting opnieuw gestart.";

        return;
      } catch (error) {
        console.error(error);

        window.alert("Reset kon niet worden opgeslagen. Probeer opnieuw.");

        return;
      }
    }

    localStorage.removeItem(storageKey);

    resetInterface();

    message.textContent = "Painting opnieuw gestart.";
  }

  /*
  |--------------------------------------------------------------------------
  | Pointer controls
  |--------------------------------------------------------------------------
  */

  grid.addEventListener("pointerdown", (event) => {
    const cell = event.target.closest(".diamondistry-cell");

    if (!cell) {
      return;
    }

    event.preventDefault();

    isPainting = true;

    placeDiamond(cell);
  });

  grid.addEventListener("pointermove", (event) => {
    if (!isPainting) {
      return;
    }

    const element = document.elementFromPoint(event.clientX, event.clientY);

    const cell = element?.closest(".diamondistry-cell");

    if (cell && grid.contains(cell)) {
      placeDiamond(cell);
    }
  });

  /*
   * BELANGRIJKE FIX:
   * na loslaten meteen opslaan.
   */
  document.addEventListener("pointerup", () => {
    if (!isPainting) {
      return;
    }

    isPainting = false;

    persistProgress({
      immediate: true,
    });
  });

  document.addEventListener("pointercancel", () => {
    if (!isPainting) {
      return;
    }

    isPainting = false;

    persistProgress({
      immediate: true,
    });
  });

  /*
   * Extra veiligheid wanneer tab/pagina
   * wordt afgesloten of verlaten.
   */
  window.addEventListener("pagehide", () => {
    if (saveTimer) {
      clearTimeout(saveTimer);

      saveTimer = null;
    }

    if (userState.loggedIn) {
      saveServerProgress();
    } else {
      saveLocalProgress();
    }
  });

  if (resetButton) {
    resetButton.addEventListener("click", resetPainting);
  }

  /*
  |--------------------------------------------------------------------------
  | Start
  |--------------------------------------------------------------------------
  */

  createGrid();
  createPalette();
  updatePaletteCounts();
  loadProgress();
});
