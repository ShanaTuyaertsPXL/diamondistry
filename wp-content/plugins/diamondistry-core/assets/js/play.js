document.addEventListener("DOMContentLoaded", () => {
  const grid = document.getElementById("diamondistry-grid");
  const palette = document.getElementById("diamondistry-palette");
  const progressBar = document.getElementById("diamondistry-progress-bar");
  const completedElement = document.getElementById("diamondistry-completed");
  const totalElement = document.getElementById("diamondistry-total");
  const message = document.getElementById("diamondistry-message");

  if (!grid || !palette) {
    return;
  }

  const colors = [
    {
      id: 1,
      name: "Rose",
      hex: "#ef7c8e",
      symbol: "●",
    },
    {
      id: 2,
      name: "Sunshine",
      hex: "#f5c451",
      symbol: "◆",
    },
    {
      id: 3,
      name: "Meadow",
      hex: "#75b798",
      symbol: "▲",
    },
  ];

  const gridSize = 10;
  const totalDiamonds = gridSize * gridSize;

  let selectedColor = colors[0].id;
  let completed = 0;
  let isPainting = false;

  const totalsByColor = {};
  const completedByColor = {};

  colors.forEach((color) => {
    totalsByColor[color.id] = 0;
    completedByColor[color.id] = 0;
  });

  totalElement.textContent = totalDiamonds;

  function getPatternColor(row, column) {
    if (row < 3) {
      return 1;
    }

    if (row >= 3 && row <= 6 && column >= 3 && column <= 6) {
      return 2;
    }

    return 3;
  }

  function createGrid() {
    for (let row = 0; row < gridSize; row++) {
      for (let column = 0; column < gridSize; column++) {
        const colorId = getPatternColor(row, column);

        const color = colors.find((item) => item.id === colorId);

        if (!color) {
          continue;
        }

        totalsByColor[color.id]++;

        const cell = document.createElement("button");

        cell.type = "button";
        cell.className = "diamondistry-cell";

        cell.dataset.color = color.id;
        cell.dataset.completed = "false";

        cell.setAttribute("aria-label", `${color.name} diamond`);

        cell.textContent = color.symbol;

        grid.appendChild(cell);
      }
    }
  }

  function createPalette() {
    colors.forEach((color, index) => {
      const button = document.createElement("button");

      button.type = "button";
      button.className = "diamondistry-color";

      if (index === 0) {
        button.classList.add("is-selected");
      }

      button.dataset.color = color.id;

      button.innerHTML = `
				<span
					class="diamondistry-color-preview"
					style="--diamond-color: ${color.hex}"
				>
					${color.symbol}
				</span>

				<span class="diamondistry-color-info">
					<strong>${color.symbol} ${color.name}</strong>
					<small>Diamond ${color.id}</small>
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
      });

      palette.appendChild(button);
    });
  }

  function selectColor(colorId) {
    const color = colors.find((item) => item.id === colorId);

    if (!color) {
      return;
    }

    selectedColor = colorId;

    document.querySelectorAll(".diamondistry-color").forEach((item) => {
      item.classList.remove("is-selected");
    });

    const activeButton = document.querySelector(
      `.diamondistry-color[data-color="${colorId}"]`,
    );

    if (activeButton) {
      activeButton.classList.add("is-selected");
    }

    message.textContent = `${color.symbol} ${color.name} geselecteerd`;
  }

  function selectNextAvailableColor() {
    const currentIndex = colors.findIndex(
      (color) => color.id === selectedColor,
    );

    for (let offset = 1; offset <= colors.length; offset++) {
      const nextIndex = (currentIndex + offset) % colors.length;

      const nextColor = colors[nextIndex];

      const remaining =
        totalsByColor[nextColor.id] - completedByColor[nextColor.id];

      if (remaining > 0) {
        selectColor(nextColor.id);
        return;
      }
    }
  }

  function placeDiamond(cell) {
    if (!cell || !cell.classList.contains("diamondistry-cell")) {
      return;
    }

    if (cell.dataset.completed === "true") {
      return;
    }

    const cellColorId = Number(cell.dataset.color);

    const color = colors.find((item) => item.id === cellColorId);

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

    cell.dataset.completed = "true";

    cell.classList.remove("is-wrong");
    cell.classList.add("is-completed");

    cell.style.setProperty("--diamond-color", color.hex);

    cell.textContent = "";

    completed++;
    completedByColor[color.id]++;

    updateProgress();
    updatePaletteCounts();

    const remainingForCurrentColor =
      totalsByColor[color.id] - completedByColor[color.id];

    if (remainingForCurrentColor === 0 && completed < totalDiamonds) {
      message.textContent = `✓ ${color.name} klaar — volgende kleur geselecteerd`;

      selectNextAvailableColor();
    }
  }

  function updateProgress() {
    const percentage = (completed / totalDiamonds) * 100;

    completedElement.textContent = completed;

    progressBar.style.width = `${percentage}%`;

    if (completed === totalDiamonds) {
      message.textContent = "✨ Painting voltooid! +50 Diamonds";
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

  createGrid();
  createPalette();
  updatePaletteCounts();
});
