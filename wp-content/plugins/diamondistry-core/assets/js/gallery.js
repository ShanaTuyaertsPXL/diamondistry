document.addEventListener("DOMContentLoaded", () => {
  const cards = document.querySelectorAll(
    ".diamondistry-painting-card--gallery",
  );

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

    const key = String(postId);

    if (userState.progress[key]) {
      return userState.progress[key];
    }

    const matchingKey = Object.keys(userState.progress).find(
      (progressKey) => Number(progressKey) === Number(postId),
    );

    return matchingKey ? userState.progress[matchingKey] : null;
  }

  function getValidIndexes(completedCells, total) {
    if (!Array.isArray(completedCells)) {
      return [];
    }

    return Array.from(
      new Set(
        completedCells
          .map((index) => Number(index))
          .filter(
            (index) => Number.isInteger(index) && index >= 0 && index < total,
          ),
      ),
    );
  }

  function updatePreview(card, status, completedIndexes) {
    const preview = card.querySelector(".diamondistry-card-preview-grid");

    if (!preview) {
      return;
    }

    preview.classList.remove("is-new", "is-progress", "is-completed");

    preview.classList.add(`is-${status}`);

    const completedSet = new Set(completedIndexes);

    preview
      .querySelectorAll(".diamondistry-card-preview-cell")
      .forEach((cell) => {
        const index = Number(cell.dataset.index);

        cell.classList.toggle("is-filled", completedSet.has(index));
      });
  }

  cards.forEach((card) => {
    const paintingSlug = card.dataset.paintingId;

    const paintingPostId = card.dataset.paintingPostId;

    const total = Number(card.dataset.total);

    if (!paintingSlug || !paintingPostId || !total) {
      return;
    }

    const action = card.querySelector(".diamondistry-painting-card-action");

    const progress = card.querySelector(".diamondistry-painting-card-progress");

    const progressBar = card.querySelector(
      ".diamondistry-painting-card-progress-bar",
    );

    const progressText = card.querySelector(
      ".diamondistry-painting-card-progress-text",
    );

    const badge = card.querySelector(".diamondistry-painting-card-badge");

    const completedMeta = card.querySelector(
      ".diamondistry-painting-card-completed",
    );

    let completedIndexes = [];

    let completedFlag = false;

    let rewardClaimed = false;

    /*
    |--------------------------------------------------------------------------
    | Logged in
    |--------------------------------------------------------------------------
    */

    if (userState.loggedIn) {
      const serverProgress = getServerProgress(paintingPostId);

      if (serverProgress) {
        completedIndexes = getValidIndexes(
          serverProgress.completedCells,
          total,
        );

        completedFlag =
          serverProgress.completed === true ||
          serverProgress.completed === 1 ||
          serverProgress.completed === "1";

        rewardClaimed =
          serverProgress.rewardClaimed === true ||
          serverProgress.rewardClaimed === 1 ||
          serverProgress.rewardClaimed === "1";
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
            completedIndexes = getValidIndexes(data.completedCells, total);
          }
        } catch {
          // Ignore damaged local state.
        }
      }
    }

    let completed = completedIndexes.length;

    if (completedFlag) {
      completed = total;
    }

    const percentage = Math.min(100, Math.round((completed / total) * 100));

    let status = "new";

    if (completed >= total) {
      status = "completed";
    } else if (completed > 0) {
      status = "progress";
    }

    card.classList.remove("is-new", "is-progress", "is-completed");

    card.classList.add(`is-${status}`);

    updatePreview(card, status, completedIndexes);

    /*
    |--------------------------------------------------------------------------
    | New
    |--------------------------------------------------------------------------
    */

    if (status === "new") {
      if (action) {
        action.textContent = "Start painting →";
      }

      if (progress) {
        progress.hidden = true;
      }

      if (badge) {
        badge.hidden = true;
      }

      if (completedMeta) {
        completedMeta.hidden = true;
      }

      return;
    }

    /*
    |--------------------------------------------------------------------------
    | Completed
    |--------------------------------------------------------------------------
    */

    if (status === "completed") {
      if (action) {
        action.textContent = "View painting →";
      }

      if (progress) {
        progress.hidden = true;
      }

      if (badge) {
        badge.hidden = false;

        badge.textContent = "✓ Completed";
      }

      if (completedMeta) {
        completedMeta.hidden = false;

        completedMeta.textContent = rewardClaimed
          ? "✓ Reward claimed"
          : "Reward available";
      }

      return;
    }

    /*
    |--------------------------------------------------------------------------
    | Progress
    |--------------------------------------------------------------------------
    */

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

      badge.textContent = `${percentage}%`;
    }

    if (completedMeta) {
      completedMeta.hidden = true;
    }
  });
});
