document.addEventListener("DOMContentLoaded", () => {
  const grid = document.getElementById("diamondistry-grid");
  const palette = document.getElementById("diamondistry-palette");
  const progressBar = document.getElementById("diamondistry-progress-bar");
  const progressLabel = document.getElementById("diamondistry-progress-label");
  const titleElement = document.getElementById("diamondistry-title");
  const message = document.getElementById("diamondistry-message");
  const resetButton = document.getElementById("diamondistry-reset");
  const zoomInButton = document.getElementById("diamondistry-zoom-in");
  const zoomOutButton = document.getElementById("diamondistry-zoom-out");
  const fullscreenButton = document.getElementById("diamondistry-fullscreen");
  const workspace = document.querySelector(".diamondistry-workspace");

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
    message.textContent = "This painting could not be loaded.";
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
      "This painting is not finished yet and cannot be played.";
    return;
  }

  const paletteIds = new Set(painting.palette.map((color) => color.id));

  if (painting.pattern.some((value) => !paletteIds.has(value))) {
    message.textContent =
      "This painting contains an invalid colour and cannot be loaded.";
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
    message.textContent = "This painting has no usable colours.";
    return;
  }

  const colorById = new Map(colors.map((color) => [color.id, color]));
  const totalDiamonds = expectedCells;
  const placed = new Uint8Array(totalDiamonds);
  let hoverIndex = -1;
  let wrongIndex = -1;
  let wrongTimer = 0;
  let board = null;

  const storageKey = `diamondistry-progress-${painting.id}`;

  let selectedColor = colors[0]?.id ?? null;
  let completed = 0;
  let isPainting = false;

  let saveTimer = null;
  let saveEpoch = 0;
  let progressDirty = false;
  let loadedRevision = Number(userState.progress?.revision || 0);
  let completionNoted = false;
  let completionReward = null;

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

  const cellSteps = [12, 16, 20, 24, 28, 32, 36, 40, 48, 56];
  let zoomLevel = 0;

  function fittedCellSize() {
    const fullscreen = workspace.classList.contains("is-expanded");
    const targetWidth = fullscreen
      ? Math.max(280, window.innerWidth - 680)
      : Math.min(560, Math.max(340, window.innerWidth - 520));
    const targetHeight = fullscreen
      ? Math.max(280, window.innerHeight - 200)
      : Math.min(window.innerHeight * 0.68, 640);
    const byWidth = Math.floor((targetWidth - 22) / painting.width);
    const byHeight = Math.floor((targetHeight - 22) / painting.height);
    return Math.max(18, Math.min(40, byWidth, byHeight));
  }

  function nearestZoom(size) {
    let best = 0;
    cellSteps.forEach((step, index) => {
      if (Math.abs(step - size) < Math.abs(cellSteps[best] - size)) {
        best = index;
      }
    });
    return best;
  }

  function applyZoom() {
    if (board) {
      board.setCellSize(cellSteps[zoomLevel]);
    }
    if (zoomInButton) {
      zoomInButton.disabled = zoomLevel >= cellSteps.length - 1;
    }
    if (zoomOutButton) {
      zoomOutButton.disabled = zoomLevel <= 0;
    }

    syncLoupeMetrics();
  }

  zoomLevel = nearestZoom(fittedCellSize());

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

    for (let index = 0; index < placed.length; index += 1) {
      if (placed[index]) {
        indexes.push(index);
      }
    }

    return indexes;
  }

  function syncReference() {
    const canvas = document.querySelector(".diamondistry-reference .diamondistry-preview-canvas");
    if (!canvas || !window.DiamondistryGrid) {
      return;
    }
    const data = window.DiamondistryGrid.previewData(canvas);
    data.ghostUnfilled = true;
    data.filled = getCompletedIndexes();
    window.DiamondistryGrid.paintPreview(canvas, data);
  }

  function cellStyle(index) {
    const color = colorById.get(painting.pattern[index]);
    const isPlaced = placed[index] === 1;
    return {
      hex: color ? color.hex : "#eeeeee",
      symbol: color ? color.symbol : "",
      placed: isPlaced,
      hover: index === hoverIndex,
      wrong: index === wrongIndex,
      shape: painting.shape === "round" ? "round" : "square",
      scale: index === hoverIndex ? (isPlaced ? 0.92 : 1.06) : isPlaced ? 0.88 : 1,
    };
  }

  /*
  |--------------------------------------------------------------------------
  | Grid
  |--------------------------------------------------------------------------
  */

  function createGrid() {
    painting.pattern.forEach((colorId) => {
      const color = colorById.get(colorId);
      if (color) {
        totalsByColor[color.id] += 1;
      }
    });

    const stage = grid.closest(".diamondistry-stage");
    board = window.DiamondistryGrid.createViewport(grid, {
      columns: painting.width,
      rows: painting.height,
      cellSize: cellSteps[zoomLevel],
      gap: 1,
      padding: 10,
      scrollParent: stage || grid.parentElement,
      variant: "play",
      styleAt: cellStyle,
    });
    applyZoom();
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
            0 left
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

  function placeDiamond(index) {
    if (!Number.isInteger(index) || index < 0 || index >= totalDiamonds || placed[index]) {
      return;
    }

    const color = colorById.get(painting.pattern[index]);

    if (!color) {
      return;
    }

    if (selectedColor !== color.id) {
      wrongIndex = index;
      message.textContent = `Verkeerde diamond — kies ${color.symbol} ${color.name}.`;
      board.redraw();
      syncLoupeCell(index);
      window.clearTimeout(wrongTimer);
      wrongTimer = window.setTimeout(() => {
        if (wrongIndex === index) {
          wrongIndex = -1;
          board.redraw();
        }
      }, 320);
      return;
    }

    if (wrongIndex === index) {
      wrongIndex = -1;
    }

    placed[index] = 1;

    completed++;
    completedByColor[color.id]++;

    updateProgress();
    updatePaletteCounts();
    board.redraw();
    syncReference();
    syncLoupeCell(index);

    /*
     * Bij volledige painting meteen opslaan,
     * zodat reward direct kan worden toegekend.
     */
    if (completed === totalDiamonds) {
      openCompletion();

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
    const rounded = Math.round(percentage);

    if (progressLabel) {
      progressLabel.textContent = `${rounded}%`;
    }

    if (progressBar) {
      progressBar.style.width = `${percentage}%`;
    }

    if (completed === totalDiamonds) {
      message.textContent = `✨ ${painting.title} completed!`;
      return;
    }

    message.textContent = `${Math.round(percentage)}% complete`;
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
          remaining === 0 ? "✓ Done" : `${remaining} left`;
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
    progressDirty = false;
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

    const epoch = saveEpoch;
    const revision = loadedRevision;
    const cells = getCompletedIndexes();
    const color = selectedColor || 0;

    progressDirty = false;

    const body = new URLSearchParams();

    body.set("action", "diamondistry_save_progress");
    body.set("nonce", userState.nonce);
    body.set("paintingId", String(painting.postId));
    body.set("selectedColor", String(color));
    body.set("completedCells", JSON.stringify(cells));
    body.set("revision", String(revision));

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

      if (epoch !== saveEpoch) {
        return null;
      }

      if (!result.success) {
        console.error("Diamondistry progress save failed:", result);
        progressDirty = true;
        return null;
      }

      if (result.data?.stale) {
        loadedRevision = Number(result.data.revision ?? loadedRevision);

        if (progressDirty) {
          return saveServerProgress();
        }

        return null;
      }

      if (Number.isFinite(Number(result.data?.revision))) {
        loadedRevision = Number(result.data.revision);
      }

      /*
       * Server is leidend.
       * Oude lokale progress mag weg.
       */
      localStorage.removeItem(storageKey);

      if (result.data?.rewardGranted) {
        userState.balance = Number(result.data.balance || 0);

        message.textContent =
          `✨ ${painting.title} completed! ` +
          `+${result.data.reward} 💎 · ` +
          `Balance: ${userState.balance} 💎`;

        setCompletionReward(
          Number(result.data.reward || 0),
          userState.balance,
        );
      } else if (completed === totalDiamonds) {
        message.textContent = `✨ ${painting.title} completed!`;
      }

      return result.data;
    } catch (error) {
      console.error("Diamondistry progress request failed:", error);

      if (epoch === saveEpoch) {
        progressDirty = true;
      }

      return null;
    }
  }

  function persistProgress({ immediate = false } = {}) {
    progressDirty = true;

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

      const color = colorById.get(painting.pattern[numericIndex]);

      if (!color) {
        return;
      }

      restored.add(numericIndex);
      placed[numericIndex] = 1;
      completed++;
      completedByColor[color.id]++;
    });

    board.redraw();
    syncReference();

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
      const serverRevision = Number(serverProgress?.revision || 0);

      loadedRevision = serverRevision;

      /*
       * revision > 0 means the account already has a record, including a
       * reset painting. That record wins over leftover localStorage.
       */
      const serverHasHistory =
        serverRevision > 0 ||
        (serverProgress &&
          ((Array.isArray(serverProgress.completedCells) &&
            serverProgress.completedCells.length > 0) ||
            serverProgress.completed ||
            serverProgress.rewardClaimed ||
            serverProgress.selectedColor));

      if (serverHasHistory) {
        localStorage.removeItem(storageKey);
        progressDirty = false;

        applyProgress(serverProgress || { completedCells: [] });

        if (completed > 0 && completed < totalDiamonds) {
          const percentage = Math.round((completed / totalDiamonds) * 100);

          message.textContent = `Welcome back, picking up at ${percentage}%`;
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
          "Moving your progress to your account…";

        saveServerProgress().then(() => {
          if (completed < totalDiamonds) {
            const percentage = Math.round((completed / totalDiamonds) * 100);

            message.textContent = `Progress saved to your account: ${percentage}% complete`;
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

      message.textContent = `Welcome back, picking up at ${percentage}%`;
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

    placed.fill(0);
    hoverIndex = -1;
    wrongIndex = -1;
    board.redraw();
    syncReference();

    if (colors.length) {
      selectColor(colors[0].id, false);
    }

    updateProgress();
    updatePaletteCounts();
    syncLoupeGrid();
    closeCompletion();
    completionNoted = false;
    completionReward = null;
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
      `Start "${painting.title}" over from scratch?`,
    );

    if (!confirmed) {
      return;
    }

    saveEpoch += 1;
    progressDirty = false;

    if (saveTimer) {
      clearTimeout(saveTimer);
      saveTimer = null;
    }

    if (userState.loggedIn) {
      try {
        const result = await resetServerProgress();

        if (!result.success) {
          window.alert("The reset could not be saved. Please try again.");

          return;
        }

        if (Number.isFinite(Number(result.data?.revision))) {
          loadedRevision = Number(result.data.revision);
        }

        localStorage.removeItem(storageKey);
        resetInterface();
        progressDirty = false;

        message.textContent = "Painting opnieuw gestart.";

        return;
      } catch (error) {
        console.error(error);

        window.alert("The reset could not be saved. Please try again.");

        return;
      }
    }

    localStorage.removeItem(storageKey);
    resetInterface();
    progressDirty = false;

    message.textContent = "Painting opnieuw gestart.";
  }

  /*
  |--------------------------------------------------------------------------
  | Completion message
  |--------------------------------------------------------------------------
  */

  const completion = document.createElement("div");
  const completionCard = document.createElement("div");
  const completionTitle = document.createElement("h2");
  const completionRewardText = document.createElement("p");
  const completionClose = document.createElement("button");

  completion.className = "diamondistry-complete";
  completion.setAttribute("role", "dialog");
  completion.setAttribute("aria-modal", "true");
  completion.setAttribute("aria-hidden", "true");
  completion.inert = true;

  completionCard.className = "diamondistry-complete-card";

  const completionMark = document.createElement("div");
  completionMark.className = "diamondistry-complete-mark";
  completionMark.setAttribute("aria-hidden", "true");
  completionMark.textContent = "💎";

  const completionKicker = document.createElement("p");
  completionKicker.className = "diamondistry-complete-kicker";
  completionKicker.textContent = "Completed";

  completionTitle.id = "diamondistry-complete-title";
  completionTitle.textContent = painting.title;
  completion.setAttribute("aria-labelledby", completionTitle.id);

  const completionBody = document.createElement("p");
  completionBody.className = "diamondistry-complete-text";
  completionBody.textContent = "Every diamond is in its place.";

  completionRewardText.className = "diamondistry-complete-reward";
  completionRewardText.hidden = true;

  const completionActions = document.createElement("div");
  completionActions.className = "diamondistry-complete-actions";

  completionClose.type = "button";
  completionClose.className = "diamondistry-complete-close";
  completionClose.textContent = "Close";

  const completionBack = document.createElement("a");
  const paintingsLink = document.querySelector(".diamondistry-breadcrumb a");
  completionBack.className = "diamondistry-complete-back";
  completionBack.href = paintingsLink ? paintingsLink.href : "/paintings/";
  completionBack.textContent = "Back to paintings";

  completionActions.append(completionClose, completionBack);

  completionCard.append(
    completionMark,
    completionKicker,
    completionTitle,
    completionBody,
    completionRewardText,
    completionActions,
  );
  completion.append(completionCard);
  document.body.append(completion);

  function renderCompletionReward() {
    if (!completionReward) {
      completionRewardText.hidden = true;
      completionRewardText.textContent = "";
      return;
    }

    completionRewardText.hidden = false;
    completionRewardText.textContent =
      `+${completionReward.amount} 💎 added · Balance ${completionReward.balance} 💎`;
  }

  function openCompletion() {
    if (completionNoted) {
      return;
    }

    completionNoted = true;
    completionTitle.textContent = painting.title;
    renderCompletionReward();
    completion.classList.add("is-open", "suppress-focus");
    completion.setAttribute("aria-hidden", "false");
    completion.inert = false;
    completionClose.focus({ preventScroll: true, focusVisible: false });
  }

  function closeCompletion() {
    completion.classList.remove("is-open");
    completion.setAttribute("aria-hidden", "true");
    completion.inert = true;
  }

  function setCompletionReward(amount, balance) {
    completionReward = {
      amount,
      balance,
    };
    renderCompletionReward();
  }

  completion.addEventListener("keydown", () => {
    completion.classList.remove("suppress-focus");
  });

  completionClose.addEventListener("click", closeCompletion);

  completion.addEventListener("click", (event) => {
    if (event.target === completion) {
      closeCompletion();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && completion.classList.contains("is-open")) {
      event.preventDefault();
      event.stopPropagation();
      closeCompletion();
    }
  });

  /*
  |--------------------------------------------------------------------------
  | Pointer controls
  |--------------------------------------------------------------------------
  */

  function hoverCell(event) {
    const index = board ? board.cellFromEvent(event) : -1;
    if (index === hoverIndex) {
      return index;
    }
    hoverIndex = index;
    if (board) {
      board.redraw();
    }
    return index;
  }

  grid.addEventListener("pointerdown", (event) => {
    const index = hoverCell(event);

    if (index < 0) {
      return;
    }

    event.preventDefault();
    isPainting = true;
    placeDiamond(index);
  });

  grid.addEventListener("pointermove", (event) => {
    const index = hoverCell(event);

    if (!isPainting || index < 0) {
      return;
    }

    placeDiamond(index);
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
    if (!progressDirty) {
      return;
    }

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

  const loupeButton = document.getElementById("diamondistry-loupe");
  const stage = grid.closest(".diamondistry-stage");
  const loupe = document.createElement("div");
  const loupeView = document.createElement("div");
  const loupeCanvas = document.createElement("canvas");
  const loupeLens = 188;
  const loupeScale = 2.2;
  let loupeOn = false;
  let loupePoint = null;

  loupe.className = "diamondistry-loupe";
  loupe.hidden = true;
  loupe.setAttribute("aria-hidden", "true");
  loupeView.className = "diamondistry-loupe-view";
  loupeCanvas.className = "diamondistry-loupe-canvas";
  loupeView.appendChild(loupeCanvas);
  loupe.appendChild(loupeView);
  document.body.appendChild(loupe);

  function syncLoupeMetrics() {
    syncLoupeGrid();
  }

  function syncLoupeGrid() {
    if (!loupeOn || !loupePoint || !board) {
      return;
    }
    board.paintMagnifier(loupeCanvas, loupePoint.x, loupePoint.y, loupeLens, loupeScale);
  }

  function syncLoupeCell() {
    syncLoupeGrid();
  }

  function moveLoupe(event) {
    if (!loupeOn || !board) {
      return;
    }

    const point = board.localPoint(event);
    const rect = grid.getBoundingClientRect();
    const inside =
      point.x >= 0 &&
      point.y >= 0 &&
      point.x <= rect.width &&
      point.y <= rect.height;

    loupe.hidden = !inside;

    if (!inside) {
      loupePoint = null;
      return;
    }

    loupePoint = point;
    const lift = event.pointerType === "touch" ? loupeLens / 2 + 28 : 0;
    const frame = 3;
    loupe.style.transform = `translate(${event.clientX - loupeLens / 2 - frame}px, ${event.clientY - loupeLens / 2 - frame - lift}px)`;
    board.paintMagnifier(loupeCanvas, point.x, point.y, loupeLens, loupeScale);
  }

  function setLoupe(enabled) {
    loupeOn = enabled;
    if (stage) {
      stage.classList.toggle("is-loupe", enabled);
    }
    if (loupeButton) {
      loupeButton.setAttribute("aria-pressed", enabled ? "true" : "false");
    }
    if (!enabled) {
      loupe.hidden = true;
      return;
    }
    syncLoupeGrid();
  }

  if (loupeButton) {
    loupeButton.addEventListener("click", () => {
      setLoupe(!loupeOn);
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.repeat || event.metaKey || event.ctrlKey || event.altKey) {
      return;
    }
    if (event.key !== "m" && event.key !== "M") {
      return;
    }
    const tag = event.target?.tagName;
    if (
      tag === "INPUT" ||
      tag === "TEXTAREA" ||
      event.target?.isContentEditable
    ) {
      return;
    }
    event.preventDefault();
    setLoupe(!loupeOn);
  });

  grid.addEventListener("pointermove", moveLoupe);
  grid.addEventListener("pointerdown", moveLoupe);
  grid.addEventListener("pointerleave", () => {
    loupe.hidden = true;
    loupePoint = null;
    if (hoverIndex !== -1) {
      hoverIndex = -1;
      board.redraw();
    }
  });

  if (zoomInButton) {
    zoomInButton.addEventListener("click", () => {
      zoomLevel = Math.min(cellSteps.length - 1, zoomLevel + 1);
      applyZoom();
    });
  }

  if (zoomOutButton) {
    zoomOutButton.addEventListener("click", () => {
      zoomLevel = Math.max(0, zoomLevel - 1);
      applyZoom();
    });
  }

  let workspaceExpanded = false;
  let workspaceAnimating = false;
  let workspacePlaceholder = null;
  const fullscreenFade = document.createElement("div");

  fullscreenFade.className = "diamondistry-fullscreen-fade";
  fullscreenFade.setAttribute("aria-hidden", "true");
  document.body.appendChild(fullscreenFade);

  function wait(ms) {
    return new Promise((resolve) => {
      window.setTimeout(resolve, ms);
    });
  }

  function fadeThrough(applyChange) {
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (reduced) {
      applyChange();
      return Promise.resolve();
    }

    fullscreenFade.classList.add("is-visible");

    return wait(340).then(() => {
      applyChange();

      return new Promise((resolve) => {
        requestAnimationFrame(() => {
          fullscreenFade.classList.remove("is-visible");
          wait(360).then(resolve);
        });
      });
    });
  }

  function setWorkspaceExpanded(next) {
    if (!workspace || workspaceAnimating || next === workspaceExpanded) {
      return;
    }

    workspaceAnimating = true;
    loupe.hidden = true;

    fadeThrough(() => {
      if (next) {
        const first = workspace.getBoundingClientRect();
        workspacePlaceholder = document.createElement("div");
        workspacePlaceholder.className = "diamondistry-workspace-placeholder";
        workspacePlaceholder.style.height = `${first.height}px`;
        workspacePlaceholder.setAttribute("aria-hidden", "true");
        workspace.before(workspacePlaceholder);
        workspace.classList.add("is-expanded");
        document.documentElement.classList.add("diamondistry-workspace-open");
      } else {
        workspace.classList.remove("is-expanded");
        document.documentElement.classList.remove("diamondistry-workspace-open");
        if (workspacePlaceholder) {
          workspacePlaceholder.remove();
          workspacePlaceholder = null;
        }
      }

      workspaceExpanded = next;
      fullscreenButton.setAttribute("aria-pressed", next ? "true" : "false");
      fullscreenButton.setAttribute(
        "aria-label",
        next ? "Exit fullscreen" : "Fullscreen",
      );
      zoomLevel = nearestZoom(fittedCellSize());
      applyZoom();
    }).then(() => {
      workspaceAnimating = false;
    });
  }

  if (fullscreenButton && workspace) {
    fullscreenButton.addEventListener("click", () => {
      setWorkspaceExpanded(!workspaceExpanded);
    });

    document.addEventListener("keydown", (event) => {
      if (
        event.key !== "Escape" ||
        !workspaceExpanded ||
        workspaceAnimating ||
        completion.classList.contains("is-open")
      ) {
        return;
      }
      event.preventDefault();
      setWorkspaceExpanded(false);
    });
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
  syncReference();
});
