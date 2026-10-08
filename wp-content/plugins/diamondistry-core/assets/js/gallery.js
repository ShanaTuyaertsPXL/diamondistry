document.addEventListener("DOMContentLoaded", () => {
  const cards = document.querySelectorAll(".diamondistry-painting-card");
  const userState = window.DiamondistryGalleryUser || {
    loggedIn: false,
    progress: {},
    balance: 0,
    favorites: [],
    ajaxUrl: "",
    nonce: "",
  };

  const favoriteKey = "diamondistry-favorites";

  function readGuestFavorites() {
    try {
      const stored = JSON.parse(localStorage.getItem(favoriteKey) || "[]");
      return Array.isArray(stored) ? stored.map(String) : [];
    } catch {
      return [];
    }
  }

  function writeGuestFavorites(ids) {
    localStorage.setItem(favoriteKey, JSON.stringify(ids));
  }

  function setFavorite(button, on) {
    button.classList.toggle("is-favorite", on);
    button.setAttribute("aria-pressed", on ? "true" : "false");
    button.setAttribute(
      "aria-label",
      on ? "Remove from favourites" : "Save to favourites",
    );
  }

  function pulseFavorite(button, added) {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      return;
    }
    button.classList.remove("is-pop", "is-pop-off");
    void button.offsetWidth;
    button.classList.add(added ? "is-pop" : "is-pop-off");
  }

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
    preview.querySelectorAll(".diamondistry-card-preview-cell").forEach((cell) => {
      cell.classList.toggle(
        "is-filled",
        completedSet.has(Number(cell.dataset.index)),
      );
    });
  }

  if (!userState.loggedIn) {
    const guestFavorites = new Set(readGuestFavorites());
    cards.forEach((card) => {
      const button = card.querySelector(".diamondistry-favorite");
      if (button) {
        setFavorite(button, guestFavorites.has(String(card.dataset.paintingPostId)));
      }
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
    const progressBar = card.querySelector(".diamondistry-painting-card-progress-bar");
    const progressText = card.querySelector(".diamondistry-painting-card-progress-text");
    const badge = card.querySelector(".diamondistry-painting-card-badge");
    const completedMeta = card.querySelector(".diamondistry-painting-card-completed");

    let completedIndexes = [];
    let completedFlag = false;
    let rewardClaimed = false;

    if (userState.loggedIn) {
      const serverProgress = getServerProgress(paintingPostId);
      if (serverProgress) {
        completedIndexes = getValidIndexes(serverProgress.completedCells, total);
        completedFlag =
          serverProgress.completed === true ||
          serverProgress.completed === 1 ||
          serverProgress.completed === "1";
        rewardClaimed =
          serverProgress.rewardClaimed === true ||
          serverProgress.rewardClaimed === 1 ||
          serverProgress.rewardClaimed === "1";
      }
    } else {
      const stored = localStorage.getItem(`diamondistry-progress-${paintingSlug}`);
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

    if (progress) {
      progress.hidden = false;
    }
    if (progressBar) {
      progressBar.style.width = `${percentage}%`;
    }
    if (progressText) {
      progressText.textContent = `${percentage}%`;
    }
    if (badge) {
      badge.hidden = status !== "completed";
      badge.textContent = status === "completed" ? "Completed" : "";
    }
    if (completedMeta) {
      completedMeta.hidden = status !== "completed";
      if (status === "completed") {
        completedMeta.textContent = rewardClaimed
          ? "Reward claimed"
          : "Reward available";
      }
    }
    if (action) {
      action.textContent =
        status === "completed"
          ? "View painting →"
          : status === "progress"
            ? "Continue Painting →"
            : "Start painting →";
    }
  });

  document.addEventListener("click", async (event) => {
    const button = event.target.closest(".diamondistry-favorite");
    if (!button) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();

    const card = button.closest(".diamondistry-painting-card");
    const postId = card?.dataset.paintingPostId;
    if (!postId) {
      return;
    }

    const next = button.getAttribute("aria-pressed") !== "true";
    setFavorite(button, next);
    pulseFavorite(button, next);

    if (!userState.loggedIn) {
      const favorites = new Set(readGuestFavorites());
      if (next) {
        favorites.add(String(postId));
      } else {
        favorites.delete(String(postId));
      }
      writeGuestFavorites(Array.from(favorites));
      return;
    }

    if (!userState.ajaxUrl || !userState.nonce) {
      setFavorite(button, !next);
      return;
    }

    const body = new URLSearchParams();
    body.set("action", "diamondistry_toggle_favorite");
    body.set("nonce", userState.nonce);
    body.set("paintingId", postId);

    try {
      const response = await fetch(userState.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
        credentials: "same-origin",
      });
      const result = await response.json();
      if (!result.success) {
        setFavorite(button, !next);
      }
    } catch {
      setFavorite(button, !next);
    }
  });

  const search = document.getElementById("diamondistry-gallery-search");
  const filters = document.querySelectorAll(".diamondistry-filters [data-filter]");
  const empty = document.querySelector(".diamondistry-gallery-empty");
  const galleryCards = document.querySelectorAll(".diamondistry-painting-card--gallery");

  if (!search && !filters.length) {
    return;
  }

  let activeFilter = "all";
  const gallery = document.querySelector(".diamondistry-gallery");
  const motionOk = !window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (gallery && motionOk) {
    gallery.classList.add("is-booting");
    galleryCards.forEach((card, index) => {
      card.style.transitionDelay = `${Math.min(index, 10) * 45}ms`;
    });
    requestAnimationFrame(() => {
      requestAnimationFrame(() => gallery.classList.remove("is-booting"));
    });
  }

  function revealCard(card) {
    if (card._hideFinish) {
      card.removeEventListener("transitionend", card._hideFinish);
      card._hideFinish = null;
    }
    card.hidden = false;
    card.classList.remove("is-leaving");
    if (!motionOk) {
      card.classList.remove("is-entering");
      return;
    }
    card.classList.add("is-entering");
    requestAnimationFrame(() => {
      requestAnimationFrame(() => card.classList.remove("is-entering"));
    });
  }

  function hideCard(card) {
    if (card.hidden || card.classList.contains("is-leaving")) {
      return;
    }
    if (!motionOk) {
      card.hidden = true;
      return;
    }
    card.classList.add("is-leaving");
    const finish = (event) => {
      if (event.propertyName !== "opacity") {
        return;
      }
      card.hidden = true;
      card.classList.remove("is-leaving");
      card.removeEventListener("transitionend", finish);
      card._hideFinish = null;
    };
    card._hideFinish = finish;
    card.addEventListener("transitionend", finish);
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    let visible = 0;

    galleryCards.forEach((card) => {
      card.style.transitionDelay = "0ms";
      const title = (card.dataset.title || "").toLowerCase();
      const categories = (card.dataset.categories || "").split(/\s+/).filter(Boolean);
      const matchesQuery = !query || title.includes(query);
      const matchesCategory = activeFilter === "all" || categories.includes(activeFilter);
      const show = matchesQuery && matchesCategory;

      if (show) {
        visible += 1;
        if (card.hidden || card.classList.contains("is-leaving")) {
          card.classList.remove("is-leaving");
          revealCard(card);
        }
      } else {
        hideCard(card);
      }
    });

    if (empty) {
      empty.classList.toggle("is-visible", visible === 0);
    }
  }

  if (search) {
    search.addEventListener("input", applyFilters);
  }

  filters.forEach((button) => {
    button.addEventListener("click", () => {
      activeFilter = button.dataset.filter || "all";
      filters.forEach((item) => {
        const on = item === button;
        item.classList.toggle("is-active", on);
        item.setAttribute("aria-selected", on ? "true" : "false");
      });
      applyFilters();
    });
  });
});
