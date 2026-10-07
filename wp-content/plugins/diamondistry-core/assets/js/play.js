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

				<span>
					<strong>${color.symbol} ${color.name}</strong>
					<small>Diamond ${color.id}</small>
				</span>
			`;

      button.addEventListener("click", () => {
        selectedColor = color.id;

        document.querySelectorAll(".diamondistry-color").forEach((item) => {
          item.classList.remove("is-selected");
        });

        button.classList.add("is-selected");
      });

      palette.appendChild(button);
    });
  }

  function createGrid() {
    for (let row = 0; row < gridSize; row++) {
      for (let column = 0; column < gridSize; column++) {
        const colorId = getPatternColor(row, column);

        const color = colors.find((item) => item.id === colorId);

        const cell = document.createElement("button");

        cell.type = "button";
        cell.className = "diamondistry-cell";

        cell.dataset.color = color.id;
        cell.dataset.completed = "false";

        cell.textContent = color.symbol;

        cell.addEventListener("click", () => {
          placeDiamond(cell, color);
        });

        grid.appendChild(cell);
      }
    }
  }

  function placeDiamond(cell, color) {
    if (cell.dataset.completed === "true") {
      return;
    }

    if (Number(selectedColor) !== color.id) {
      cell.classList.remove("is-wrong");

      void cell.offsetWidth;

      cell.classList.add("is-wrong");

      message.textContent = "Oeps — probeer een diamond met hetzelfde symbool.";

      return;
    }

    cell.dataset.completed = "true";

    cell.classList.add("is-completed");

    cell.style.setProperty("--diamond-color", color.hex);

    cell.textContent = "";

    completed++;

    updateProgress();
  }

  function updateProgress() {
    const percentage = (completed / totalDiamonds) * 100;

    completedElement.textContent = completed;

    progressBar.style.width = `${percentage}%`;

    message.textContent = `${Math.round(percentage)}% voltooid`;

    if (completed === totalDiamonds) {
      message.textContent = "✨ Painting voltooid! +50 Diamonds";
    }
  }

  createPalette();
  createGrid();
});
