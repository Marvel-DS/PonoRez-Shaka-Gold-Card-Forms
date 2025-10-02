export function initGuestTypes() {
  // Select the guest types section; exit early if not found
  const section = document.querySelector("[data-guest-types]");
  if (!section) return;

  // Retrieve the API URL from data attribute (set via PHP)
  const apiUrl = section.dataset.apiGuestTypes;
  if (!apiUrl) {
    console.error("API URL for guest types is not defined.");
    return;
  }

  // Fetch guest types data from API
  fetch(apiUrl)
    .then(res => {
      if (!res.ok) {
        throw new Error(`Network response was not ok (status: ${res.status})`);
      }
      return res.json();
    })
    .then(data => {
      // Validate response structure
      if (!data || data.status !== "ok" || !Array.isArray(data.guestTypes)) {
        console.warn("Guest types data is malformed or empty.");
        return;
      }

      // Cache DOM elements for each guest type to minimize queries
      data.guestTypes.forEach(gt => {
        const guestTypeId = String(gt.id);
        const trimmedName = gt.name && gt.name.trim() !== "" ? gt.name.trim() : null;
        const trimmedDesc = gt.description && gt.description.trim() !== "" ? gt.description.trim() : null;
        // Update price element if present
        const priceEl = section.querySelector(`[data-guest-price="${guestTypeId}"]`);
        if (priceEl) {
          // Format price fallback to 0.00 if invalid
          const price = parseFloat(gt.price);
          const displayPrice = isNaN(price) ? "0.00" : price.toFixed(2);
          priceEl.textContent = `$${displayPrice}`;
          priceEl.setAttribute("aria-label", `Price: $${displayPrice}`);
        }

        // Update name fallback using PHP fallback rules
        section.querySelectorAll(`[data-fallback-name="${guestTypeId}"]`).forEach(nameFallbackEl => {
          const currentNameFallback = nameFallbackEl.textContent.trim();
          if (currentNameFallback === "" || currentNameFallback === "--") {
            if (trimmedName) {
              nameFallbackEl.textContent = trimmedName;
            }
          }
        });

        if (trimmedName) {
          section.querySelectorAll(`[data-guest-type-id="${guestTypeId}"]`).forEach(el => {
            if (el.tagName === "INPUT") {
              el.setAttribute("aria-label", trimmedName);
            }
          });
        }

        // Update description fallback using identical logic
        section.querySelectorAll(`[data-fallback-desc="${guestTypeId}"]`).forEach(descFallbackEl => {
          const currentDescFallback = descFallbackEl.textContent.trim();
          if (currentDescFallback === "" || currentDescFallback === "--") {
            if (trimmedDesc) {
              descFallbackEl.textContent = trimmedDesc;
            }
          }
        });

      });
    })
    .catch(err => {
      console.error("Error fetching guest types data:", err);
    });
}
