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

  const preview = document.getElementById("diamondistry-admin-preview");

  const previewInfo = document.getElementById(
    "diamondistry-admin-preview-info",
  );

  const paletteField = document.getElementById("diamondistry_palette");

  const patternField = document.getElementById("diamondistry_pattern");

  const addColorButton = document.getElementById("diamondistry-add-color");

  const resizeButton = document.getElementById("diamondistry-resize-grid");

  const undoButton = document.getElementById("diamondistry-undo");

  const redoButton = document.getElementById("diamondistry-redo");

  const paintButton = document.getElementById("diamondistry-paint-tool");

  const eraserButton = document.getElementById("diamondistry-eraser");

  const fillRowButton = document.getElementById("diamondistry-fill-row");

  const fillColumnButton = document.getElementById("diamondistry-fill-column");

  const fillAllButton = document.getElementById("diamondistry-fill-all");

  const clearButton = document.getElementById("diamondistry-clear-grid");

  const selectedColorDisplay = document.getElementById(
    "diamondistry-selected-color",
  );

  const status = document.getElementById("diamondistry-admin-status");

  let palette = [];
  const shapeInput = document.getElementById("diamondistry_shape");

  function diamondShape() {
    return shapeInput && shapeInput.value === "round" ? "round" : "square";
  }

  let pattern = [];

  let selectedColorId = null;
  let editingColorId = null;

  let activeTool = "paint";

  let isPainting = false;
  let dragSnapshotTaken = false;
  let board = null;
  let previewCanvas = null;
  let adminHover = -1;
  let boardEventsBound = false;

  let currentWidth = Number(widthInput.value) || 10;

  let currentHeight = Number(heightInput.value) || 10;

  const undoStack = [];
  const redoStack = [];

  const historyLimit = 100;

  /*
  |--------------------------------------------------------------------------
  | Load initial state
  |--------------------------------------------------------------------------
  */

  try {
    palette = JSON.parse(paletteField.value);
  } catch {
    palette = [];
  }

  pattern = patternField.value
    .split(",")
    .map((value) => Number(value.trim()))
    .filter((value) => Number.isInteger(value));

  const initialCellCount = currentWidth * currentHeight;

  if (pattern.length !== initialCellCount) {
    pattern = new Array(initialCellCount).fill(0);
  }

  if (palette.length > 0) {
    selectedColorId = Number(palette[0].id);
  }

  /*
  |--------------------------------------------------------------------------
  | Helpers
  |--------------------------------------------------------------------------
  */

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function getColor(colorId) {
    return palette.find((color) => Number(color.id) === Number(colorId));
  }

  function getContrastColor(hex) {
    const clean = String(hex).replace("#", "");

    if (clean.length !== 6) {
      return "#111";
    }

    const red = parseInt(clean.slice(0, 2), 16);

    const green = parseInt(clean.slice(2, 4), 16);

    const blue = parseInt(clean.slice(4, 6), 16);

    const luminance = (red * 299 + green * 587 + blue * 114) / 1000;

    return luminance > 150 ? "#111" : "#fff";
  }

  function syncFields() {
    paletteField.value = JSON.stringify(palette);

    patternField.value = pattern.join(",");
  }

  /*
  |--------------------------------------------------------------------------
  | History
  |--------------------------------------------------------------------------
  */

  function makeSnapshot() {
    return {
      palette: clone(palette),
      pattern: [...pattern],
      width: currentWidth,
      height: currentHeight,
      selectedColorId,
    };
  }

  function pushHistory() {
    undoStack.push(makeSnapshot());

    if (undoStack.length > historyLimit) {
      undoStack.shift();
    }

    redoStack.length = 0;

    updateHistoryButtons();
  }

  function restoreSnapshot(snapshot) {
    palette = clone(snapshot.palette);

    pattern = [...snapshot.pattern];

    currentWidth = snapshot.width;

    currentHeight = snapshot.height;

    selectedColorId = snapshot.selectedColorId;

    if (!getColor(selectedColorId) && palette.length > 0) {
      selectedColorId = Number(palette[0].id);
    }

    widthInput.value = currentWidth;

    heightInput.value = currentHeight;

    editingColorId = null;

    renderEverything();
  }

  function undo() {
    if (undoStack.length === 0) {
      return;
    }

    redoStack.push(makeSnapshot());

    restoreSnapshot(undoStack.pop());

    updateHistoryButtons();
  }

  function redo() {
    if (redoStack.length === 0) {
      return;
    }

    undoStack.push(makeSnapshot());

    restoreSnapshot(redoStack.pop());

    updateHistoryButtons();
  }

  function updateHistoryButtons() {
    undoButton.disabled = undoStack.length === 0;

    redoButton.disabled = redoStack.length === 0;
  }

  /*
  |--------------------------------------------------------------------------
  | Status
  |--------------------------------------------------------------------------
  */

  function updateStatus() {
    const validIds = new Set(palette.map((color) => Number(color.id)));

    let empty = 0;
    let invalid = 0;

    pattern.forEach((value) => {
      const id = Number(value);

      if (id === 0) {
        empty++;
        return;
      }

      if (!validIds.has(id)) {
        invalid++;
      }
    });

    const filled = pattern.length - empty - invalid;

    if (empty === 0 && invalid === 0) {
      status.className = "diamondistry-admin-status is-complete";

      status.innerHTML = `✓ <strong>${filled} / ${pattern.length}</strong> vakjes ingevuld — klaar om te publiceren.`;

      return;
    }

    status.className = "diamondistry-admin-status is-incomplete";

    if (invalid > 0) {
      status.innerHTML = `⚠ ${empty} leeg · ${invalid} ongeldig`;

      return;
    }

    status.innerHTML = `⚠ <strong>${empty}</strong> van ${pattern.length} vakjes zijn nog leeg.`;
  }

  /*
  |--------------------------------------------------------------------------
  | Tools
  |--------------------------------------------------------------------------
  */

  function setTool(tool) {
    activeTool = tool;

    editingColorId = null;

    updateToolbar();
    updateSelectedColor();
    renderPalette();
  }

  function updateToolbar() {
    const tools = {
      paint: paintButton,
      eraser: eraserButton,
      row: fillRowButton,
      column: fillColumnButton,
    };

    Object.entries(tools).forEach(([tool, button]) => {
      button.classList.toggle("button-primary", activeTool === tool);
    });
  }

  function updateSelectedColor() {
    if (activeTool === "eraser") {
      selectedColorDisplay.innerHTML = `
        <span class="diamondistry-selected-label">
          Tool
        </span>

        <strong>
          Eraser
        </strong>
      `;

      return;
    }

    const color = getColor(selectedColorId);

    if (!color) {
      selectedColorDisplay.textContent = "Selecteer een kleur.";

      return;
    }

    const labels = {
      paint: "Paint",
      row: "Fill row",
      column: "Fill column",
    };

    selectedColorDisplay.innerHTML = `
      <span class="diamondistry-selected-label">
        ${labels[activeTool] || "Paint"}
      </span>

      <span
        class="diamondistry-selected-swatch"
        style="--color:${escapeHtml(color.hex)}"
      ></span>

      <strong>
        ${escapeHtml(color.symbol)}
        ${escapeHtml(color.name)}
      </strong>
    `;
  }

  function selectColor(colorId) {
    selectedColorId = Number(colorId);

    activeTool = "paint";
    editingColorId = null;

    updateToolbar();
    updateSelectedColor();
    renderPalette();
  }

  /*
  |--------------------------------------------------------------------------
  | Palette
  |--------------------------------------------------------------------------
  */

  function renderPalette() {
    paletteContainer.innerHTML = "";

    palette.forEach((color) => {
      const colorId = Number(color.id);

      const row = document.createElement("div");

      row.className = "diamondistry-admin-color";

      if (colorId === Number(selectedColorId) && activeTool !== "eraser") {
        row.classList.add("is-selected");
      }

      if (colorId === Number(editingColorId)) {
        row.classList.add("is-editing");

        renderColorEditor(row, color);

        paletteContainer.appendChild(row);

        return;
      }

      row.innerHTML = `
        <div
          class="diamondistry-admin-swatch"
          style="
            --color:${escapeHtml(color.hex)};
            --symbol-color:${getContrastColor(color.hex)};
          "
        >
          ${escapeHtml(color.symbol)}
        </div>

        <div class="diamondistry-admin-color-summary">

          <strong>
            ${escapeHtml(color.name)}
          </strong>

          <span>
            ${escapeHtml(color.symbol)}
            ·
            ${escapeHtml(color.hex)}
          </span>

        </div>

        <div class="diamondistry-admin-color-actions">

          <button
            type="button"
            class="button diamondistry-admin-color-edit"
            title="Edit color"
          >
            ✎
          </button>

          <button
            type="button"
            class="button-link-delete diamondistry-admin-color-remove"
            title="Remove color"
          >
            ×
          </button>

        </div>
      `;

      row.addEventListener("click", (event) => {
        if (event.target.closest("button")) {
          return;
        }

        selectColor(color.id);
      });

      row
        .querySelector(".diamondistry-admin-color-edit")
        .addEventListener("click", (event) => {
          event.stopPropagation();

          editingColorId = colorId;

          renderPalette();
        });

      row
        .querySelector(".diamondistry-admin-color-remove")
        .addEventListener("click", (event) => {
          event.stopPropagation();

          removeColor(color.id);
        });

      paletteContainer.appendChild(row);
    });

    syncFields();
  }

  function renderColorEditor(row, color) {
    const original = clone(color);

    row.innerHTML = `
      <div
        class="diamondistry-admin-swatch"
        style="
          --color:${escapeHtml(color.hex)};
          --symbol-color:${getContrastColor(color.hex)};
        "
      >
        ${escapeHtml(color.symbol)}
      </div>

      <div class="diamondistry-admin-color-edit-fields">

        <label>
          <span>Name</span>

          <input
            type="text"
            class="diamondistry-admin-color-name"
            value="${escapeHtml(color.name)}"
          >
        </label>

        <label>
          <span>Color</span>

          <input
            type="color"
            class="diamondistry-admin-color-hex"
            value="${escapeHtml(color.hex)}"
          >
        </label>

        <label>
          <span>Symbol</span>

          <input
            type="text"
            class="diamondistry-admin-color-symbol"
            value="${escapeHtml(color.symbol)}"
            maxlength="2"
            placeholder="A"
          >
        </label>

      </div>

      <div class="diamondistry-admin-color-edit-actions">

        <button
          type="button"
          class="button button-primary diamondistry-admin-color-save"
        >
          Save
        </button>

        <button
          type="button"
          class="button diamondistry-admin-color-cancel"
        >
          Cancel
        </button>

      </div>
    `;

    const nameInput = row.querySelector(".diamondistry-admin-color-name");

    const hexInput = row.querySelector(".diamondistry-admin-color-hex");

    const symbolInput = row.querySelector(".diamondistry-admin-color-symbol");

    row
      .querySelector(".diamondistry-admin-color-save")
      .addEventListener("click", () => {
        const name = nameInput.value.trim();

        const symbol = symbolInput.value.trim();

        if (!name) {
          window.alert("Geef deze kleur een naam.");

          return;
        }

        if (!symbol) {
          window.alert("Kies een symbool, letter of cijfer.");

          return;
        }

        const duplicate = palette.find(
          (item) =>
            Number(item.id) !== Number(color.id) &&
            String(item.symbol).toLowerCase() === symbol.toLowerCase(),
        );

        if (duplicate) {
          window.alert(
            `"${symbol}" wordt al gebruikt door "${duplicate.name}".`,
          );

          return;
        }

        pushHistory();

        color.name = name;
        color.hex = hexInput.value;
        color.symbol = symbol;

        editingColorId = null;

        renderEverything();
      });

    row
      .querySelector(".diamondistry-admin-color-cancel")
      .addEventListener("click", () => {
        color.name = original.name;

        color.hex = original.hex;

        color.symbol = original.symbol;

        editingColorId = null;

        renderPalette();
      });
  }

  /*
  |--------------------------------------------------------------------------
  | Grid
  |--------------------------------------------------------------------------
  */

  function adminCellStyle(index) {
    const colorId = Number(pattern[index] || 0);
    const color = getColor(colorId);
    return {
      empty: !color,
      hex: color ? color.hex : "#ffffff",
      symbol: color ? color.symbol : "",
      hover: index === adminHover,
      shape: diamondShape(),
      scale: 1,
    };
  }

  function bindBoardEvents() {
    if (boardEventsBound) {
      return;
    }
    boardEventsBound = true;

    grid.addEventListener("pointerdown", (event) => {
      const index = board.cellFromEvent(event);
      if (index < 0) {
        return;
      }
      event.preventDefault();
      if (activeTool === "row" || activeTool === "column") {
        paintCell(index);
        return;
      }
      isPainting = true;
      if (!dragSnapshotTaken) {
        pushHistory();
        dragSnapshotTaken = true;
      }
      paintCell(index);
    });

    grid.addEventListener("pointermove", (event) => {
      const index = board.cellFromEvent(event);
      if (index !== adminHover) {
        adminHover = index;
        board.redraw();
      }
      if (!isPainting || index < 0) {
        return;
      }
      if (activeTool === "paint" || activeTool === "eraser") {
        paintCell(index);
      }
    });

    grid.addEventListener("pointerleave", () => {
      if (adminHover === -1) {
        return;
      }
      adminHover = -1;
      board.redraw();
    });
  }

  function paintCell(index) {
    index = Number(index);

    if (!Number.isInteger(index)) {
      return;
    }

    if (activeTool === "row") {
      fillRow(index);
      return;
    }

    if (activeTool === "column") {
      fillColumn(index);
      return;
    }

    const target = activeTool === "eraser" ? 0 : Number(selectedColorId);

    if (activeTool !== "eraser" && !getColor(target)) {
      return;
    }

    if (Number(pattern[index]) === target) {
      return;
    }

    pattern[index] = target;

    board.redraw();
    syncFields();
    updateStatus();
    if (!isPainting) {
      renderPreview();
    }
  }

  function renderGrid() {
    if (!board) {
      const wrapper = grid.closest(".diamondistry-admin-grid-wrapper");
      board = window.DiamondistryGrid.createViewport(grid, {
        columns: () => currentWidth,
        rows: () => currentHeight,
        cellSize: 36,
        gap: 3,
        padding: 0,
        scrollParent: wrapper || grid.parentElement,
        variant: "admin",
        styleAt: adminCellStyle,
      });
      bindBoardEvents();
    }

    board.redraw();
    syncFields();
    updateStatus();
    renderPreview();
  }

  /*
  |--------------------------------------------------------------------------
  | Fill tools
  |--------------------------------------------------------------------------
  */

  function fillRow(index) {
    const color = getColor(selectedColorId);

    if (!color) {
      return;
    }

    pushHistory();

    const row = Math.floor(index / currentWidth);

    const start = row * currentWidth;

    for (let column = 0; column < currentWidth; column++) {
      pattern[start + column] = Number(selectedColorId);
    }

    renderGrid();
  }

  function fillColumn(index) {
    const color = getColor(selectedColorId);

    if (!color) {
      return;
    }

    pushHistory();

    const column = index % currentWidth;

    for (let row = 0; row < currentHeight; row++) {
      pattern[row * currentWidth + column] = Number(selectedColorId);
    }

    renderGrid();
  }

  function fillAll() {
    const color = getColor(selectedColorId);

    if (!color) {
      return;
    }

    if (!window.confirm(`Volledige painting vullen met "${color.name}"?`)) {
      return;
    }

    pushHistory();

    pattern = new Array(currentWidth * currentHeight).fill(
      Number(selectedColorId),
    );

    renderGrid();
  }

  function clearGrid() {
    if (!window.confirm("Volledige painting leegmaken?")) {
      return;
    }

    pushHistory();

    pattern = new Array(currentWidth * currentHeight).fill(0);

    renderGrid();
  }

  /*
  |--------------------------------------------------------------------------
  | Preview
  |--------------------------------------------------------------------------
  */

  function renderPreview() {
    if (!previewCanvas) {
      preview.innerHTML = "";
      previewCanvas = document.createElement("canvas");
      previewCanvas.className = "diamondistry-preview-canvas";
      preview.appendChild(previewCanvas);
    }

    preview.style.aspectRatio = `${currentWidth} / ${currentHeight}`;

    const paletteMap = {};
    palette.forEach((color) => {
      paletteMap[color.id] = color.hex;
    });

    window.DiamondistryGrid.paintPreview(previewCanvas, {
      width: currentWidth,
      height: currentHeight,
      status: "new",
      palette: paletteMap,
      pattern,
      filled: [],
      shape: diamondShape(),
      empty: "#f3f1f8",
    });

    const filled = pattern.filter((value) => Number(value) !== 0).length;

    previewInfo.textContent = `${currentWidth} × ${currentHeight} · ${filled} / ${pattern.length} filled`;
  }

  /*
  |--------------------------------------------------------------------------
  | Resize
  |--------------------------------------------------------------------------
  */

  function resizeGrid() {
    const newWidth = Math.max(1, Math.min(1000, Number(widthInput.value) || 1));

    const newHeight = Math.max(
      1,
      Math.min(1000, Number(heightInput.value) || 1),
    );

    if (newWidth === currentWidth && newHeight === currentHeight) {
      return;
    }

    const shrinking = newWidth < currentWidth || newHeight < currentHeight;

    if (
      shrinking &&
      !window.confirm(
        "Je maakt het raster kleiner. Cellen buiten het nieuwe formaat gaan verloren. Doorgaan?",
      )
    ) {
      widthInput.value = currentWidth;

      heightInput.value = currentHeight;

      return;
    }

    pushHistory();

    const nextPattern = new Array(newWidth * newHeight).fill(0);

    const copyRows = Math.min(currentHeight, newHeight);

    const copyColumns = Math.min(currentWidth, newWidth);

    for (let row = 0; row < copyRows; row++) {
      for (let column = 0; column < copyColumns; column++) {
        const oldIndex = row * currentWidth + column;

        const newIndex = row * newWidth + column;

        nextPattern[newIndex] = Number(pattern[oldIndex]) || 0;
      }
    }

    pattern = nextPattern;

    currentWidth = newWidth;

    currentHeight = newHeight;

    widthInput.value = newWidth;

    heightInput.value = newHeight;

    renderEverything();
  }

  /*
  |--------------------------------------------------------------------------
  | Colors
  |--------------------------------------------------------------------------
  */

  function addColor() {
    pushHistory();

    const ids = palette.map((color) => Number(color.id));

    const nextId = ids.length ? Math.max(...ids) + 1 : 1;

    const choices = [
      "●",
      "◆",
      "▲",
      "■",
      "○",
      "★",
      "A",
      "B",
      "C",
      "D",
      "1",
      "2",
      "3",
      "4",
      "+",
      "×",
    ];

    const used = new Set(
      palette.map((color) => String(color.symbol).toLowerCase()),
    );

    let symbol = choices.find(
      (candidate) => !used.has(candidate.toLowerCase()),
    );

    if (!symbol) {
      symbol = String(nextId);
    }

    palette.push({
      id: nextId,
      name: `Color ${nextId}`,
      hex: "#888888",
      symbol,
    });

    selectedColorId = nextId;

    editingColorId = nextId;

    activeTool = "paint";

    renderEverything();
  }

  function removeColor(colorId) {
    if (palette.length <= 1) {
      window.alert("Een painting moet minstens één kleur hebben.");

      return;
    }

    const color = getColor(colorId);

    const count = pattern.filter(
      (value) => Number(value) === Number(colorId),
    ).length;

    let warning = `Kleur "${color?.name || colorId}" verwijderen?`;

    if (count > 0) {
      warning += ` ${count} vakjes worden leeg.`;
    }

    if (!window.confirm(warning)) {
      return;
    }

    pushHistory();

    palette = palette.filter((item) => Number(item.id) !== Number(colorId));

    pattern = pattern.map((value) =>
      Number(value) === Number(colorId) ? 0 : value,
    );

    if (Number(selectedColorId) === Number(colorId)) {
      selectedColorId = Number(palette[0].id);
    }

    editingColorId = null;

    renderEverything();
  }

  /*
  |--------------------------------------------------------------------------
  | Render everything
  |--------------------------------------------------------------------------
  */

  function renderEverything() {
    syncFields();
    renderPalette();
    renderGrid();
    updateToolbar();
    updateSelectedColor();
    updateHistoryButtons();
  }

  /*
  |--------------------------------------------------------------------------
  | Events
  |--------------------------------------------------------------------------
  */

  undoButton.addEventListener("click", undo);

  redoButton.addEventListener("click", redo);

  paintButton.addEventListener("click", () => setTool("paint"));

  eraserButton.addEventListener("click", () => setTool("eraser"));

  fillRowButton.addEventListener("click", () => setTool("row"));

  fillColumnButton.addEventListener("click", () => setTool("column"));

  fillAllButton.addEventListener("click", fillAll);

  clearButton.addEventListener("click", clearGrid);

  addColorButton.addEventListener("click", addColor);

  resizeButton.addEventListener("click", resizeGrid);

  if (shapeInput) {
    shapeInput.addEventListener("change", () => {
      if (board) {
        board.redraw();
      }
      renderPreview();
    });
  }

  document.addEventListener("pointerup", () => {
    const wasPainting = isPainting;
    isPainting = false;
    dragSnapshotTaken = false;
    if (wasPainting) {
      renderPreview();
    }
  });

  document.addEventListener("pointercancel", () => {
    isPainting = false;
    dragSnapshotTaken = false;
  });

  document.addEventListener("keydown", (event) => {
    const command = event.metaKey || event.ctrlKey;

    if (!command) {
      return;
    }

    const key = event.key.toLowerCase();

    if (key === "z" && !event.shiftKey) {
      event.preventDefault();
      undo();
      return;
    }

    if (key === "y" || (key === "z" && event.shiftKey)) {
      event.preventDefault();
      redo();
    }
  });

  renderEverything();
});
