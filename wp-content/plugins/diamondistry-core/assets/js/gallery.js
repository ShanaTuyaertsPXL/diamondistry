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
    const canvas = preview.querySelector(".diamondistry-preview-canvas");
    if (!canvas || !window.DiamondistryGrid) {
      return;
    }
    const data = window.DiamondistryGrid.previewData(canvas);
    data.status = status;
    data.filled = status === "progress" ? completedIndexes : [];
    window.DiamondistryGrid.paintPreview(canvas, data);
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
    let percentage = Math.min(100, Math.round((completed / total) * 100));
    let status = "new";
    if (completed >= total) {
      status = "completed";
    } else if (completed > 0) {
      status = "progress";
      // Same as PHP: in progress never reads 0% or 100%.
      percentage = Math.max(1, Math.min(99, percentage));
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
    const actionLabel = action && action.querySelector(".diamondistry-painting-card-action-label");
    if (actionLabel && !card.classList.contains("is-locked")) {
      actionLabel.textContent =
        status === "completed"
          ? "View painting"
          : status === "progress"
            ? "Continue painting"
            : "Start painting";
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
  const priceButtons = document.querySelectorAll(".diamondistry-price-toggle [data-price]");
  const empty = document.querySelector(".diamondistry-gallery-empty");
  const galleryCards = document.querySelectorAll(".diamondistry-painting-card--gallery");

  if (!search && !filters.length) {
    return;
  }

  let activeFilter = "all";
  let activePrice = "all";
  const gallery = document.querySelector(".diamondistry-gallery");
  const motionOk = !window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Cards fade up as they scroll into view, like the site's other reveals; a row staggers in.
  // The theme hides unshown cards before first paint (html.dd-anim), so the first row animates too.
  if (gallery && motionOk && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries
          .filter((entry) => entry.isIntersecting)
          .forEach((entry, i) => {
            const card = entry.target;
            observer.unobserve(card);
            card.style.transitionDelay = `${Math.min(i, 6) * 90}ms`;
            card.classList.add("is-revealing", "is-shown");
            window.setTimeout(() => {
              card.classList.remove("is-revealing");
              card.style.transitionDelay = "";
            }, 1500);
          });
      },
      { rootMargin: "0px 0px -8% 0px", threshold: 0.1 },
    );
    galleryCards.forEach((card) => observer.observe(card));
  } else {
    galleryCards.forEach((card) => card.classList.add("is-shown"));
  }

  /*
   * Filtering animates with FLIP: leaving cards are taken out of the grid at once
   * and fade out where they stood, staying cards glide to their new cell and new
   * cards fade in, so nothing snaps into place. Running animations are measured
   * where they are, so quick successive clicks continue smoothly.
   */
  const EASE = "cubic-bezier(0.22, 1, 0.36, 1)";
  const leaving = new Set();

  function clearLeaving(card) {
    leaving.delete(card);
    card.getAnimations().forEach((animation) => animation.cancel());
    ["position", "top", "left", "width", "height", "zIndex", "pointerEvents"].forEach((prop) => {
      card.style[prop] = "";
    });
  }

  function applyFilters() {
    const query = (search?.value || "").trim().toLowerCase();
    let visible = 0;

    const matches = new Map();
    galleryCards.forEach((card) => {
      card.classList.add("is-shown"); // the user is filtering: no pending scroll reveal anymore
      const title = (card.dataset.title || "").toLowerCase();
      const categories = (card.dataset.categories || "").split(/\s+/).filter(Boolean);
      const show =
        (!query || title.includes(query)) &&
        (activeFilter === "all" || categories.includes(activeFilter)) &&
        (activePrice === "all" || card.dataset.price === activePrice);
      matches.set(card, show);
      if (show) {
        visible += 1;
      }
    });

    if (empty) {
      empty.classList.toggle("is-visible", visible === 0);
    }

    if (!motionOk || !gallery) {
      galleryCards.forEach((card) => {
        clearLeaving(card);
        card.hidden = !matches.get(card);
      });
      return;
    }

    // First: where every card is on screen right now, mid-animation included.
    const box = gallery.getBoundingClientRect();
    const first = new Map();
    galleryCards.forEach((card) => {
      if (!card.hidden) {
        const r = card.getBoundingClientRect();
        first.set(card, { x: r.left - box.left, y: r.top - box.top, w: r.width, h: r.height, o: parseFloat(getComputedStyle(card).opacity) });
      }
    });

    // Apply the new layout.
    const entering = [];
    galleryCards.forEach((card) => {
      const wasVisible = first.has(card) && !leaving.has(card);
      clearLeaving(card);
      if (matches.get(card)) {
        if (!wasVisible) {
          entering.push(card);
        }
        card.hidden = false;
      } else if (wasVisible || first.has(card)) {
        // Out of the grid at once, fading where it stood.
        const f = first.get(card);
        leaving.add(card);
        Object.assign(card.style, {
          position: "absolute",
          top: `${f.y}px`,
          left: `${f.x}px`,
          width: `${f.w}px`,
          height: `${f.h}px`,
          zIndex: "0",
          pointerEvents: "none",
        });
      } else {
        card.hidden = true;
      }
    });

    // Last + invert + play.
    galleryCards.forEach((card) => {
      if (leaving.has(card)) {
        const fade = card.animate(
          [{ opacity: first.get(card).o, transform: "scale(1)" }, { opacity: 0, transform: "scale(0.94)" }],
          { duration: 220, easing: "ease", fill: "forwards" },
        );
        fade.onfinish = () => {
          clearLeaving(card);
          card.hidden = true;
        };
        return;
      }
      if (card.hidden) {
        return;
      }
      if (entering.includes(card)) {
        card.animate(
          [{ opacity: 0, transform: "scale(0.94)" }, { opacity: 1, transform: "scale(1)" }],
          { duration: 420, delay: 120, easing: EASE, fill: "backwards" },
        );
        return;
      }
      const f = first.get(card);
      const r = card.getBoundingClientRect();
      const dx = f.x - (r.left - box.left);
      const dy = f.y - (r.top - box.top);
      if (dx || dy) {
        card.animate(
          [{ transform: `translate(${dx}px, ${dy}px)` }, { transform: "translate(0, 0)" }],
          { duration: 480, easing: EASE },
        );
      }
    });
  }

  if (search) {
    search.addEventListener("input", applyFilters);
  }

  priceButtons.forEach((button) => {
    button.addEventListener("click", () => {
      activePrice = button.dataset.price || "all";
      priceButtons.forEach((item) => {
        item.classList.toggle("is-active", item === button);
        item.setAttribute("aria-pressed", item === button ? "true" : "false");
      });
      applyFilters();
    });
  });

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
