// resources/js/cart_actions.js
document.addEventListener("DOMContentLoaded", function () {
  const cartContainer = document.querySelector(".cart-page-container");
  const cartStatusDiv = document.getElementById("cart-update-status");
  const cartCountSpan = document.getElementById("cart-count"); // Header count (desktop)
  const cartCountSpanMobile = document.getElementById("cart-count-mobile"); // Header count (mobile)

  // Summary elements selectors
  const summarySubtotalEl = document.getElementById("summary-subtotal");
  const summaryDiscountContainer = document.querySelector(
    ".coupon-applied-line"
  ); // The whole P tag for discount
  const summaryDiscountAmountEl = document.getElementById(
    "summary-discount-amount"
  ); // Span for discount value
  const summaryTaxContainer = document.querySelector(".tax-line"); // NEW: Tax Line P tag
  const summaryTaxAmountEl = document.getElementById("summary-tax-amount"); // NEW: Tax amount span
  const summaryShippingContainer = document.querySelector(
    ".fee-line:has(#summary-shipping-fee)"
  ); // NEW: Shipping Line P tag
  const summaryShippingFeeEl = document.getElementById("summary-shipping-fee"); // NEW: Shipping fee span
  const summaryPlatformContainer = document.querySelector(
    ".fee-line:has(#summary-platform-fee)"
  ); // NEW: Platform Line P tag
  const summaryPlatformFeeEl = document.getElementById("summary-platform-fee"); // NEW: Platform fee span
  const summaryFinalTotalEl = document.getElementById("summary-final-total"); // Span for final total

  // Coupon section elements
  const couponForm = document.getElementById("coupon-form");
  const couponInput = couponForm?.querySelector('input[name="coupon_code"]');
  const couponApplyBtn = couponForm?.querySelector('button[type="submit"]');
  const removeCouponForm = document.getElementById("remove-coupon-form");

  // --- Helper: Show Status Message ---
  function showStatusMessage(message, type = "info") {
    // type can be 'info', 'success', 'error'
    if (!cartStatusDiv) return;
    cartStatusDiv.textContent = message;
    cartStatusDiv.className = type;
    cartStatusDiv.style.display = "block";

    // Auto-hide after a few seconds
    setTimeout(() => {
      cartStatusDiv.textContent = "";
      cartStatusDiv.style.display = "none";
      cartStatusDiv.className = "";
    }, 5000); // Hide after 5 seconds
  }

  // --- Helper: Update Summary Display ---
  function updateSummaryDisplay(data) {
    // Update Subtotal
    if (summarySubtotalEl)
      summarySubtotalEl.textContent = "$" + data.cart_subtotal.toFixed(2);

    // Update Discount Display
    const discountValue = data.cart_discount || 0;
    if (summaryDiscountContainer) {
      if (discountValue > 0 && data.applied_coupon_code) {
        const discountText = `Discount (${data.applied_coupon_code})`;
        const firstSpan =
          summaryDiscountContainer.querySelector("span:first-child");
        if (firstSpan) firstSpan.textContent = discountText;
        if (summaryDiscountAmountEl)
          summaryDiscountAmountEl.textContent = "-$" + discountValue.toFixed(2);
        summaryDiscountContainer.style.display = "flex"; // Show the discount line
      } else {
        summaryDiscountContainer.style.display = "none"; // Hide the discount line
      }
    }

    // NEW: Update Tax Display
    const taxValue = data.cart_tax || 0;
    if (summaryTaxContainer) {
      if (taxValue > 0) {
        if (summaryTaxAmountEl)
          summaryTaxAmountEl.textContent = "$" + taxValue.toFixed(2);
        summaryTaxContainer.style.display = "flex"; // Show line
      } else {
        summaryTaxContainer.style.display = "none"; // Hide line
      }
    }

    // NEW: Update Shipping Fee Display
    const shippingValue = data.shipping_fee || 0;
    if (summaryShippingContainer) {
      if (shippingValue > 0) {
        if (summaryShippingFeeEl)
          summaryShippingFeeEl.textContent = "$" + shippingValue.toFixed(2);
        summaryShippingContainer.style.display = "flex"; // Show line
      } else {
        summaryShippingContainer.style.display = "none"; // Hide line
      }
    }

    // NEW: Update Platform Fee Display
    const platformValue = data.platform_fee || 0;
    if (summaryPlatformContainer) {
      if (platformValue > 0) {
        if (summaryPlatformFeeEl)
          summaryPlatformFeeEl.textContent = "$" + platformValue.toFixed(2);
        summaryPlatformContainer.style.display = "flex"; // Show line
      } else {
        summaryPlatformContainer.style.display = "none"; // Hide line
      }
    }

    // Update Final Total
    if (summaryFinalTotalEl)
      summaryFinalTotalEl.textContent = "$" + data.cart_total.toFixed(2);

    // Update coupon form state based on whether a coupon is applied
    updateCouponFormState(data.applied_coupon_code);
  }

  // --- Helper: Update Coupon Form State ---
  function updateCouponFormState(appliedCode) {
    if (couponForm) {
      if (appliedCode) {
        if (couponInput) {
          couponInput.value = appliedCode;
          couponInput.readOnly = true;
        }
        if (couponApplyBtn) couponApplyBtn.disabled = true;
        if (removeCouponForm) removeCouponForm.style.display = "block"; // Show remove form
      } else {
        if (couponInput) {
          couponInput.readOnly = false;
        }
        if (couponApplyBtn) couponApplyBtn.disabled = false;
        if (removeCouponForm) removeCouponForm.style.display = "none"; // Hide remove form
      }
    }
  }

  // --- Main Update Function (via AJAX) ---
  function updateCartItem(productId, quantity, action) {
    showStatusMessage("Updating cart...", "info");

    // Disable interactive elements during update
    const interactiveElements =
      cartContainer?.querySelectorAll(
        'button:not(.go-back-link button), input[type="text"], input[type="checkbox"]'
      ) ?? [];
    interactiveElements.forEach((el) => (el.disabled = true));

    fetch("manage_cart.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=${action}&product_id=${productId}&quantity=${quantity}`,
    })
      .then((response) => {
        if (!response.ok) {
          return response.text().then((text) => {
            throw new Error(
              `HTTP error! status: ${response.status}, message: ${
                text || "Server error"
              }`
            );
          });
        }
        try {
          return response.json();
        } catch (e) {
          // Handle cases where response is not JSON (e.g., HTML error page)
          console.error("Failed to parse JSON response:", e);
          throw new Error("Received non-JSON response from server.");
        }
      })
      .then((data) => {
        // Check for success property within the JSON data
        if (data && data.success !== undefined) {
          if (data.success) {
            showStatusMessage(
              data.message || "Cart updated successfully.",
              "success"
            );

            // Update header counts
            if (cartCountSpan) cartCountSpan.textContent = data.cart_count;
            if (cartCountSpanMobile)
              cartCountSpanMobile.textContent = data.cart_count;

            // Update specific item row or remove it
            const itemBoxToUpdate = cartContainer?.querySelector(
              `.cart-item-box[data-product-id="${productId}"]`
            );
            if (itemBoxToUpdate) {
              if (data.item_quantity > 0) {
                // Item quantity updated
                const qtyDisplay =
                  itemBoxToUpdate.querySelector(".qty-display");
                const itemSubtotalP =
                  itemBoxToUpdate.querySelector(".item-subtotal p");
                if (qtyDisplay) qtyDisplay.textContent = data.item_quantity;
                if (itemSubtotalP)
                  itemSubtotalP.textContent =
                    "$" + data.item_subtotal.toFixed(2);

                // Disable minus button if quantity is 1
                const minusBtn = itemBoxToUpdate.querySelector(".minus-btn");
                if (minusBtn) minusBtn.disabled = data.item_quantity <= 1;
              } else {
                // Item removed (quantity became 0)
                itemBoxToUpdate.remove();
              }
            }

            // Update payment summary section using the helper
            updateSummaryDisplay(data);

            // Check if cart is now empty and reload if necessary
            if (data.cart_count === 0) {
              // Reload to show the empty cart message and correct summary state
              window.location.reload();
            } else {
              // Re-enable elements ONLY on success and if cart is not empty
              interactiveElements.forEach((el) => (el.disabled = false));
              // Re-apply special disabled state for minus buttons if qty is 1
              document
                .querySelectorAll(".cart-item-box .minus-btn")
                .forEach((btn) => {
                  const itemBox = btn.closest(".cart-item-box");
                  const qtyDisplay = itemBox?.querySelector(".qty-display");
                  if (qtyDisplay) {
                    btn.disabled = parseInt(qtyDisplay.textContent, 10) <= 1;
                  }
                });
              // Re-apply coupon form state
              updateCouponFormState(data.applied_coupon_code);
            }
          } else {
            // Handle specific errors from backend (data.success is false)
            showStatusMessage(
              "Error: " + (data.message || "Could not update cart."),
              "error"
            );
            // Re-enable elements after error message shown
            interactiveElements.forEach((el) => (el.disabled = false));
          }
        } else {
          // Handle cases where data is null or doesn't have 'success' property
          console.error("Invalid data structure received:", data);
          showStatusMessage(
            "Error: Received unexpected data from the server.",
            "error"
          );
          interactiveElements.forEach((el) => (el.disabled = false));
        }
      })
      .catch((error) => {
        console.error("AJAX Error:", error);
        showStatusMessage(
          `Error: ${
            error.message ||
            "Could not communicate with the server. Please try again."
          }`,
          "error"
        );
        // Re-enable elements after error message shown
        interactiveElements.forEach((el) => (el.disabled = false));
      });
    // REMOVED finally block to handle re-enabling within success/error paths
  } // End updateCartItem

  // --- Event Listener using Delegation ---
  if (cartContainer) {
    cartContainer.addEventListener("click", function (event) {
      const target = event.target;

      // Delegate clicks for item control bar buttons
      const controlButton = target.closest(".item-control-bar button");
      if (controlButton) {
        const cartItemBox = target.closest(".cart-item-box");
        if (!cartItemBox) return;

        const productId = cartItemBox.getAttribute("data-product-id");
        const currentQtySpan = cartItemBox.querySelector(".qty-display");
        const currentQuantity = parseInt(
          currentQtySpan?.textContent || "0",
          10
        );
        let action = "update"; // Default action

        // --- Plus Button ---
        if (controlButton.classList.contains("plus-btn")) {
          updateCartItem(productId, currentQuantity + 1, action);
        }
        // --- Minus Button ---
        else if (controlButton.classList.contains("minus-btn")) {
          if (currentQuantity > 1) {
            updateCartItem(productId, currentQuantity - 1, action);
          }
          // Button should be disabled if quantity is 1, no action needed
        }
        // --- Remove Button ---
        else if (controlButton.classList.contains("remove-item-btn")) {
          if (
            confirm("Are you sure you want to remove this item from your cart?")
          ) {
            action = "remove"; // Set action to remove
            updateCartItem(productId, 0, action); // Send quantity 0 for removal
          }
        }
      }
    });
  } else {
    console.error("Cart container element not found.");
  }

  // Initialize minus button states on page load
  document.querySelectorAll(".cart-item-box").forEach((itemBox) => {
    const minusBtn = itemBox.querySelector(".minus-btn");
    const qtyDisplay = itemBox.querySelector(".qty-display");
    if (minusBtn && qtyDisplay) {
      minusBtn.disabled = parseInt(qtyDisplay.textContent, 10) <= 1;
    }
  });

  // Initialize coupon form state based on initial load
  // Use the value present in the input field on load
  const initialCouponCode = couponInput?.value || null;
  updateCouponFormState(initialCouponCode);
}); // End DOMContentLoaded
