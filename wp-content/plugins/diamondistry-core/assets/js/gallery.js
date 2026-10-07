document.addEventListener("DOMContentLoaded", () => {
  const cards = document.querySelectorAll(".diamondistry-gallery-card");

  if (!cards.length) {
    return;
  }

  const userState = window.DiamondistryGalleryUser || {
    loggedIn: false,
    progress: {},
    balance: 0,
  };

  function getServerProgress(postId) {
    if (!userState.progress || typeof userState.progress !== "object") {
      return null;
    }

    /*
     * PHP/JSON object keys always arrive as strings.
     */
    const key = String(postId);

    if (userState.progress[key]) {
      return userState.progress[key];
    }

    /*
     * Extra defensive fallback.
     */
    const matchingKey = Object.keys(userState.progress).find(
      (progressKey) => Number(progressKey) === Number(postId),
    );

    return matchingKey ? userState.progress[matchingKey] : null;
  }

  function getValidCompletedCount(completedCells, total) {
    if (!Array.isArray(completedCells)) {
      return 0;
    }

    const validIndexes = new Set(
      completedCells
        .map((index) => Number(index))
        .filter(
          (index) => Number.isInteger(index) && index >= 0 && index < total,
        ),
    );

    return validIndexes.size;
  }

  cards.forEach((card) => {
    const paintingSlug = card.dataset.paintingId;

    const paintingPostId = card.dataset.paintingPostId;

    const total = Number(card.dataset.total);

    if (!paintingSlug || !paintingPostId || !total) {
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

    let completed = 0;
    let completedFlag = false;

    /*
    |--------------------------------------------------------------------------
    | Logged-in user
    |--------------------------------------------------------------------------
    */

    if (userState.loggedIn) {
      const serverProgress = getServerProgress(paintingPostId);

      if (serverProgress) {
        completed = getValidCompletedCount(
          serverProgress.completedCells,
          total,
        );

        completedFlag =
          serverProgress.completed === true ||
          serverProgress.completed === 1 ||
          serverProgress.completed === "1";
      }
    }

    /*
    |--------------------------------------------------------------------------
    | Guest
    |--------------------------------------------------------------------------
    */

    if (!userState.loggedIn) {
      const storageKey = `diamondistry-progress-${paintingSlug}`;

      const stored = localStorage.getItem(storageKey);

      if (stored) {
        try {
          const data = JSON.parse(stored);

          if (data.paintingId === paintingSlug) {
            completed = getValidCompletedCount(data.completedCells, total);
          }
        } catch {
          // Ignore damaged local progress.
        }
      }
    }

    /*
     * Server's completed flag is authoritative.
     *
     * This also makes the gallery resilient if an older
     * saved progress record has an imperfect cell array.
     */
    if (userState.loggedIn && completedFlag) {
      completed = total;
    }

    const percentage = Math.min(100, Math.round((completed / total) * 100));

    /*
    |--------------------------------------------------------------------------
    | Reset classes first
    |--------------------------------------------------------------------------
    */

    card.classList.remove("is-new", "is-in-progress", "is-completed");

    /*
    |--------------------------------------------------------------------------
    | New
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
