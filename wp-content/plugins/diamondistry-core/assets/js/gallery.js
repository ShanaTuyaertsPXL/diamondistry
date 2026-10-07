document.addEventListener("DOMContentLoaded", () => {
  const cards = document.querySelectorAll(".diamondistry-gallery-card");

  if (!cards.length) {
    return;
  }

  cards.forEach((card) => {
    const paintingId = card.dataset.paintingId;

    const total = Number(card.dataset.total);

    if (!paintingId || !total) {
      return;
    }

    const action = card.querySelector(".diamondistry-gallery-action");

    const progress = card.querySelector(".diamondistry-gallery-progress");

    const progressBar = card.querySelector(
      ".diamondistry-gallery-progress-bar",
    );

    const progressText = card.querySelector(
      ".diamondistry-gallery-progress-text",
    );

    const badge = card.querySelector(".diamondistry-gallery-badge");

    const storageKey = `diamondistry-progress-${paintingId}`;

    let completed = 0;

    const stored = localStorage.getItem(storageKey);

    if (stored) {
      try {
        const data = JSON.parse(stored);

        if (
          data.paintingId === paintingId &&
          Array.isArray(data.completedCells)
        ) {
          const validIndexes = new Set(
            data.completedCells
              .map((index) => Number(index))
              .filter(
                (index) =>
                  Number.isInteger(index) && index >= 0 && index < total,
              ),
          );

          completed = validIndexes.size;
        }
      } catch {
        // Invalid local data:
        // simply show painting as new.
      }
    }

    const percentage = Math.min(100, Math.round((completed / total) * 100));

    /*
    |--------------------------------------------------------------------------
    | Not started
    |--------------------------------------------------------------------------
    */

    if (completed === 0) {
      card.classList.add("is-new");

      if (action) {
        action.textContent = "Start painting →";
      }

      if (progress) {
        progress.hidden = true;
      }

      if (badge) {
        badge.hidden = true;
      }

      return;
    }

    /*
    |--------------------------------------------------------------------------
    | Completed
    |--------------------------------------------------------------------------
    */

    if (completed >= total) {
      card.classList.add("is-completed");

      if (action) {
        action.textContent = "Completed ✓";
      }

      if (progress) {
        progress.hidden = false;
      }

      if (progressBar) {
        progressBar.style.width = "100%";
      }

      if (progressText) {
        progressText.textContent = "100% completed";
      }

      if (badge) {
        badge.hidden = false;

        badge.textContent = "✓ Completed";
      }

      return;
    }

    /*
    |--------------------------------------------------------------------------
    | In progress
    |--------------------------------------------------------------------------
    */

    card.classList.add("is-in-progress");

    if (action) {
      action.textContent = "Continue painting →";
    }

    if (progress) {
      progress.hidden = false;
    }

    if (progressBar) {
      progressBar.style.width = `${percentage}%`;
    }

    if (progressText) {
      progressText.textContent = `${percentage}% completed`;
    }

    if (badge) {
      badge.hidden = false;

      badge.textContent = "In progress";
    }
  });
});
