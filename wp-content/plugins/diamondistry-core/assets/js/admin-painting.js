document.addEventListener("DOMContentLoaded", () => {
  const editor = document.getElementById("diamondistry-admin-editor");

  if (!editor) {
    return;
  }

  const widthInput = document.getElementById("diamondistry_width");

  const heightInput = document.getElementById("diamondistry_height");

  const paletteContainer = document.getElementById(
    "diamondistry-admin-palette",
  );

  const grid = document.getElementById("diamondistry-admin-grid");

  const paletteField = document.getElementById("diamondistry_palette");

  const patternField = document.getElementById("diamondistry_pattern");

  const addColorButton = document.getElementById("diamondistry-add-color");

  const resizeButton = document.getElementById("diamondistry-resize-grid");

  let palette = [];
  let pattern = [];
  let selectedColorId = null;

  try {
    palette = JSON.parse(paletteField.value);
  } catch {
    palette = [];
  }

  pattern = patternField.value
    .split(",")
    .map((value) => Number(value.trim()))
    .filter((value) => Number.isInteger(value));

  if (palette.length > 0) {
    selectedColorId = Number(palette[0].id);
  }

  function getColor(colorId) {
    return palette.find((color) => Number(color.id) === Number(colorId));
  }

  function syncFields() {
    paletteField.value = JSON.stringify(palette);

    patternField.value = pattern.join(",");
  }

  function renderPalette() {
    paletteContainer.innerHTML = "";

    palette.forEach((color) => {
      const row = document.createElement("div");

      row.className = "diamondistry-admin-color";

      if (Number(color.id) === Number(selectedColorId)) {
        row.classList.add("is-selected");
      }

      row.innerHTML = `
					<button
						type="button"
						class="diamondistry-admin-color-select"
						aria-label="Select ${color.name}"
					>
						<span
							class="diamondistry-admin-swatch"
							style="--color:${color.hex}"
						>
							${color.symbol}
						</span>
					</button>

					<input
						type="text"
						class="diamondistry-admin-color-name"
						value="${color.name}"
						placeholder="Color name"
					>

					<input
						type="color"
						class="diamondistry-admin-color-hex"
						value="${color.hex}"
					>

					<input
						type="text"
						class="diamondistry-admin-color-symbol"
						value="${color.symbol}"
						maxlength="2"
						placeholder="●"
					>

					<button
						type="button"
						class="button-link-delete diamondistry-admin-color-remove"
					>
						Remove
					</button>
				`;

      const selectButton = row.querySelector(
        ".diamondistry-admin-color-select",
      );

      const nameInput = row.querySelector(".diamondistry-admin-color-name");

      const hexInput = row.querySelector(".diamondistry-admin-color-hex");

      const symbolInput = row.querySelector(".diamondistry-admin-color-symbol");

      const removeButton = row.querySelector(
        ".diamondistry-admin-color-remove",
      );

      selectButton.addEventListener("click", () => {
        selectedColorId = Number(color.id);

        renderPalette();
      });

      nameInput.addEventListener("input", () => {
        color.name = nameInput.value;

        syncFields();
        renderGrid();
      });

      hexInput.addEventListener("input", () => {
        color.hex = hexInput.value;

        syncFields();

        row
          .querySelector(".diamondistry-admin-swatch")
          .style.setProperty("--color", color.hex);
      });

      symbolInput.addEventListener("input", () => {
        color.symbol = symbolInput.value || "?";

        syncFields();
        renderGrid();
      });

      removeButton.addEventListener("click", () => {
        removeColor(color.id);
      });

      paletteContainer.appendChild(row);
    });

    syncFields();
  }

  function renderGrid() {
    const width = Math.max(1, Number(widthInput.value) || 1);

    const height = Math.max(1, Number(heightInput.value) || 1);

    const total = width * height;

    if (pattern.length < total) {
      const fallbackId = selectedColorId || palette[0]?.id || 1;

      while (pattern.length < total) {
        pattern.push(Number(fallbackId));
      }
    }

    if (pattern.length > total) {
      pattern = pattern.slice(0, total);
    }

    grid.innerHTML = "";

    grid.style.gridTemplateColumns = `repeat(${width}, minmax(30px, 1fr))`;

    pattern.forEach((colorId, index) => {
      const color = getColor(colorId) || palette[0];

      const cell = document.createElement("button");

      cell.type = "button";

      cell.className = "diamondistry-admin-cell";

      cell.dataset.index = index;

      if (color) {
        cell.style.setProperty("--color", color.hex);

        cell.textContent = color.symbol;
      }

      cell.addEventListener("click", () => {
        if (!selectedColorId) {
          return;
        }

        pattern[index] = Number(selectedColorId);

        renderGrid();
        syncFields();
      });

      grid.appendChild(cell);
    });

    syncFields();
  }

  function addColor() {
    const ids = palette.map((color) => Number(color.id));

    const nextId = ids.length ? Math.max(...ids) + 1 : 1;

    const symbols = ["●", "◆", "▲", "■", "○", "★", "+", "×"];

    palette.push({
      id: nextId,
      name: `Color ${nextId}`,
      hex: "#888888",
      symbol: symbols[(nextId - 1) % symbols.length],
    });

    selectedColorId = nextId;

    renderPalette();
    renderGrid();
  }

  function removeColor(colorId) {
    if (palette.length <= 1) {
      window.alert("Een painting moet minstens één kleur hebben.");

      return;
    }

    const color = getColor(colorId);

    const confirmed = window.confirm(
      `Kleur "${color?.name || colorId}" verwijderen?`,
    );

    if (!confirmed) {
      return;
    }

    palette = palette.filter((item) => Number(item.id) !== Number(colorId));

    const replacementId = Number(palette[0].id);

    pattern = pattern.map((value) =>
      Number(value) === Number(colorId) ? replacementId : value,
    );

    if (Number(selectedColorId) === Number(colorId)) {
      selectedColorId = replacementId;
    }

    renderPalette();
    renderGrid();
  }

  addColorButton.addEventListener("click", addColor);

  resizeButton.addEventListener("click", () => {
    renderGrid();
  });

  renderPalette();
  renderGrid();
});
