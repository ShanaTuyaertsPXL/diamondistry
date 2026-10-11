document.addEventListener("DOMContentLoaded", () => {
  const button = document.querySelector("[data-unlock]");
  const error = document.querySelector(".diamondistry-locked__error");
  if (!button || !window.DiamondistryUnlock) {
    return;
  }

  button.addEventListener("click", async () => {
    button.disabled = true;
    button.textContent = "Unlocking…";

    try {
      const body = new URLSearchParams({
        action: "diamondistry_unlock_painting",
        nonce: DiamondistryUnlock.nonce,
        paintingId: String(DiamondistryUnlock.paintingId),
      });
      const response = await fetch(DiamondistryUnlock.ajaxUrl, { method: "POST", credentials: "same-origin", body });
      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Unlocking failed. Please try again.");
      }

      // Reload: the server now renders the playable painting.
      window.location.reload();
    } catch (err) {
      button.disabled = false;
      button.textContent = "Try again";
      if (error) {
        error.hidden = false;
        error.textContent = err.message;
      }
    }
  });
});
