document.addEventListener("DOMContentLoaded", () => {
  const grid = document.getElementById("diamondistry-grid");
  const palette = document.getElementById("diamondistry-palette");
  const progressBar = document.getElementById("diamondistry-progress-bar");
  const completedElement = document.getElementById("diamondistry-completed");
  const totalElement = document.getElementById("diamondistry-total");
  const titleElement = document.getElementById("diamondistry-title");
  const message = document.getElementById("diamondistry-message");
  const resetButton = document.getElementById("diamondistry-reset");

  if (!grid || !palette) {
    return;
  }

  const painting = window.DiamondistryPainting || {};

  if (
    !painting.id ||
    !painting.title ||
    !Number(painting.width) ||
    !Number(painting.height) ||
    !Array.isArray(painting.palette) ||
    !Array.isArray(painting.pattern)
  ) {
    message.textContent = "Painting kon niet correct worden geladen.";

    return;
  }

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

  const colors = painting.palette;

  const totalDiamonds = expectedCells;

  const storageKey = `diamondistry-progress-${painting.id}`;

  let selectedColor = colors[0]?.id ?? null;

  let completed = 0;
  let isPainting = false;

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

  totalElement.textContent = totalDiamonds;

  function getColor(colorId) {
    return colors.find((color) => color.id === Number(colorId));
  }

  function getRemaining(colorId) {
    return totalsByColor[colorId] - completedByColor[colorId];
  }

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

						<strong
							data-remaining="${color.id}"
						>
							0 over
						</strong>

						<small
							data-total="${color.id}"
						>
							0 / 0
						</small>

					</span>
				`;

      button.addEventListener("click", () => {
        selectColor(color.id);

        saveProgress();
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

    const activeButton = document.querySelector(
      `.diamondistry-color[data-color="${color.id}"]`,
    );

    if (activeButton) {
      activeButton.classList.add("is-selected");
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

        saveProgress();

        message.textContent = `${nextColor.symbol} ${nextColor.name} geselecteerd`;

        return;
      }
    }
  }

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
    saveProgress();

    if (getRemaining(color.id) === 0 && completed < totalDiamonds) {
      message.textContent = `✓ ${color.name} klaar`;

      selectNextAvailableColor();
    }
  }

  function updateProgress() {
    const percentage =
      totalDiamonds > 0 ? (completed / totalDiamonds) * 100 : 0;

    completedElement.textContent = completed;

    progressBar.style.width = `${percentage}%`;

    if (completed === totalDiamonds) {
      message.textContent = `✨ ${painting.title} voltooid! +${painting.reward} Diamonds`;

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

      const totalElement = document.querySelector(`[data-total="${color.id}"]`);

      const button = document.querySelector(
        `.diamondistry-color[data-color="${color.id}"]`,
      );

      if (remainingElement) {
        remainingElement.textContent =
          remaining === 0 ? "✓ Klaar" : `${remaining} over`;
      }

      if (totalElement) {
        totalElement.textContent = `${done} / ${total}`;
      }

      if (button) {
        button.classList.toggle("is-complete", remaining === 0);
      }
    });
  }

  function saveProgress() {
    const completedCells = [];

    document.querySelectorAll(".diamondistry-cell").forEach((cell) => {
      if (cell.dataset.completed === "true") {
        completedCells.push(Number(cell.dataset.index));
      }
    });

    const progress = {
      version: 1,

      paintingId: painting.id,

      selectedColor,

      completedCells,

      updatedAt: new Date().toISOString(),
    };

    localStorage.setItem(storageKey, JSON.stringify(progress));
  }

  function loadProgress() {
    const stored = localStorage.getItem(storageKey);

    if (!stored) {
      updateProgress();
      updatePaletteCounts();

      return;
    }

    let progress;

    try {
      progress = JSON.parse(stored);
    } catch {
      localStorage.removeItem(storageKey);

      return;
    }

    if (
      progress.paintingId !== painting.id ||
      !Array.isArray(progress.completedCells)
    ) {
      return;
    }

    completed = 0;

    colors.forEach((color) => {
      completedByColor[color.id] = 0;
    });

    progress.completedCells.forEach((index) => {
      const cell = document.querySelector(
        `.diamondistry-cell[data-index="${index}"]`,
      );

      if (!cell) {
        return;
      }

      const color = getColor(cell.dataset.color);

      if (!color) {
        return;
      }

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

    if (completed > 0 && completed < totalDiamonds) {
      const percentage = Math.round((completed / totalDiamonds) * 100);

      message.textContent = `Verder waar je gebleven was — ${percentage}% voltooid`;
    }
  }

  function resetPainting() {
    const confirmed = window.confirm(
      `Weet je zeker dat je "${painting.title}" opnieuw wilt beginnen?`,
    );

    if (!confirmed) {
      return;
    }

    localStorage.removeItem(storageKey);

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

    if (colors.length > 0) {
      selectColor(colors[0].id, false);
    }

    updateProgress();
    updatePaletteCounts();

    message.textContent = "Painting opnieuw gestart.";
  }

  grid.addEventListener("pointerdown", (event) => {
    const cell = event.target.closest(".diamondistry-cell");

    if (!cell) {
      return;
    }

    isPainting = true;

    grid.setPointerCapture(event.pointerId);

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

  grid.addEventListener("pointerup", (event) => {
    isPainting = false;

    if (grid.hasPointerCapture(event.pointerId)) {
      grid.releasePointerCapture(event.pointerId);
    }
  });

  grid.addEventListener("pointercancel", (event) => {
    isPainting = false;

    if (grid.hasPointerCapture(event.pointerId)) {
      grid.releasePointerCapture(event.pointerId);
    }
  });

  grid.addEventListener("pointerleave", (event) => {
    if (event.buttons === 0) {
      isPainting = false;
    }
  });

  if (resetButton) {
    resetButton.addEventListener("click", resetPainting);
  }

  createGrid();
  createPalette();
  updatePaletteCounts();
  loadProgress();
});
