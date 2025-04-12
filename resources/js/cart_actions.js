// resources/js/cart_actions.js
document.addEventListener('DOMContentLoaded', function() {
    const cartContainer = document.querySelector('.cart-page-container');
    const cartStatusDiv = document.getElementById('cart-update-status');
    const cartCountSpan = document.getElementById('cart-count'); // Header count (desktop)
    const cartCountSpanMobile = document.getElementById('cart-count-mobile'); // Header count (mobile)

    // Summary elements selectors
    const summarySubtotalEl = document.getElementById('summary-subtotal');
    const summaryDiscountContainer = document.querySelector('.coupon-applied-line'); // The whole P tag for discount
    const summaryDiscountAmountEl = document.getElementById('summary-discount-amount'); // Span for discount value
    const summaryTaxEl = document.getElementById('summary-tax-amount'); // Span for tax value
    const summaryFinalTotalEl = document.getElementById('summary-final-total'); // Span for final total

    // Coupon section elements
    const couponForm = document.getElementById('coupon-form');
    const couponInput = couponForm?.querySelector('input[name="coupon_code"]');
    const couponApplyBtn = couponForm?.querySelector('button[type="submit"]');
    const removeCouponForm = document.getElementById('remove-coupon-form');

    // --- Helper: Show Status Message ---
    function showStatusMessage(message, type = 'info') { // type can be 'info', 'success', 'error'
        if (!cartStatusDiv) return;
        cartStatusDiv.textContent = message;
        cartStatusDiv.className = type; // Set class based on type
        cartStatusDiv.style.display = 'block';

        // Auto-hide after a few seconds
        setTimeout(() => {
            cartStatusDiv.textContent = '';
            cartStatusDiv.style.display = 'none';
            cartStatusDiv.className = '';
        }, 5000); // Hide after 5 seconds
    }

    // --- Helper: Update Summary Display ---
    function updateSummaryDisplay(data) {
        if (summarySubtotalEl) summarySubtotalEl.textContent = '$' + data.cart_subtotal.toFixed(2);
        if (summaryTaxEl) summaryTaxEl.textContent = '$' + data.cart_tax.toFixed(2); // Update if using tax
        if (summaryFinalTotalEl) summaryFinalTotalEl.textContent = '$' + data.cart_total.toFixed(2);

        // Update discount display
        const discountValue = data.cart_discount || 0;
        if (summaryDiscountContainer) {
            if (discountValue > 0 && data.applied_coupon_code) {
                 // Ensure the discount amount span exists or create it if needed
                 let discountAmountSpan = summaryDiscountContainer.querySelector('#summary-discount-amount');
                 if (!discountAmountSpan) {
                      // Create the span if it's missing (e.g., first time discount applied)
                      // This part is complex to do perfectly dynamically, might need adjustments
                      // For simplicity, assume the span exists and just update text
                      // A better approach might be to have the P tag always exist but hidden
                 }

                // Update the text content
                const discountText = `Discount (${data.applied_coupon_code})`;
                summaryDiscountContainer.querySelector('span:first-child').textContent = discountText;
                if (summaryDiscountAmountEl) summaryDiscountAmountEl.textContent = '-$' + discountValue.toFixed(2);
                summaryDiscountContainer.style.display = 'flex'; // Show the discount line
            } else {
                summaryDiscountContainer.style.display = 'none'; // Hide the discount line
            }
        }

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
                 // Show remove button if it exists, otherwise page needs reload from apply_coupon.php
                 if(removeCouponForm) removeCouponForm.style.display = 'block';

            } else {
                if (couponInput) {
                     // Keep potentially entered text unless explicitly cleared by success message?
                     // couponInput.value = ''; // Or keep user input?
                     couponInput.readOnly = false;
                }
                if (couponApplyBtn) couponApplyBtn.disabled = false;
                // Hide remove button
                if(removeCouponForm) removeCouponForm.style.display = 'none';
            }
        }
    }


    // --- Main Update Function (via AJAX) ---
    function updateCartItem(productId, quantity, action) {
        showStatusMessage('Updating cart...', 'info');

        // Disable interactive elements during update
        const interactiveElements = cartContainer.querySelectorAll('button, input');
        interactiveElements.forEach(el => el.disabled = true);

        fetch('manage_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            // Ensure 'action' matches expected values in manage_cart.php
            body: `action=${action}&product_id=${productId}&quantity=${quantity}`
        })
        .then(response => {
            if (!response.ok) {
                // Try to get error text from response if possible
                return response.text().then(text => {
                     throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showStatusMessage(data.message || 'Cart updated successfully.', 'success');

                // Update header counts
                if (cartCountSpan) cartCountSpan.textContent = data.cart_count;
                if (cartCountSpanMobile) cartCountSpanMobile.textContent = data.cart_count;

                // Update specific item row or remove it
                const itemBoxToUpdate = cartContainer.querySelector(`.cart-item-box[data-product-id="${productId}"]`);
                if (itemBoxToUpdate) {
                    if (data.item_quantity > 0) { // Item quantity updated
                        itemBoxToUpdate.querySelector('.qty-display').textContent = data.item_quantity;
                        itemBoxToUpdate.querySelector('.item-subtotal p').textContent = '$' + data.item_subtotal.toFixed(2);
                        // Disable minus button if quantity is 1
                        const minusBtn = itemBoxToUpdate.querySelector('.minus-btn');
                        if(minusBtn) minusBtn.disabled = (data.item_quantity <= 1);
                    } else { // Item removed (quantity became 0)
                        itemBoxToUpdate.remove();
                    }
                }

                // Update payment summary section using the helper
                updateSummaryDisplay(data);

                // Check if cart is now empty
                if (data.cart_count === 0) {
                    // Option 1: Reload the page to show empty cart message from PHP
                    window.location.reload();
                    // Option 2: Dynamically insert the empty cart message (more complex)
                    // const orderColumn = document.querySelector('.order-summary-column');
                    // if(orderColumn) orderColumn.innerHTML = '<div class="empty-cart-box">...</div>';
                    // document.querySelector('.payment-summary-column')?.remove(); // Remove summary column
                }

            } else {
                // Handle specific errors from backend
                showStatusMessage('Error: ' + (data.message || 'Could not update cart.'), 'error');
                 // Re-enable elements after error message shown
                 interactiveElements.forEach(el => el.disabled = false);
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            showStatusMessage('Error: Could not communicate with the server. Please try again.', 'error');
             // Re-enable elements after error message shown
             interactiveElements.forEach(el => el.disabled = false);
        })
        .finally(() => {
            // Re-enable interactive elements (if not already enabled on error)
            // Make sure this doesn't conflict with error handling re-enabling
             if (!cartStatusDiv.classList.contains('error')) { // Only re-enable if no error occurred OR success
                 interactiveElements.forEach(el => {
                     // Special handling for minus button based on quantity
                     if(el.classList.contains('minus-btn')) {
                         const itemBox = el.closest('.cart-item-box');
                         const qtyDisplay = itemBox?.querySelector('.qty-display');
                         if(qtyDisplay) {
                             el.disabled = (parseInt(qtyDisplay.textContent, 10) <= 1);
                         } else {
                             el.disabled = false; // Default if qty not found
                         }
                     } else {
                         el.disabled = false;
                     }
                 });
                 // Re-apply coupon form state
                 updateCouponFormState(couponInput?.value); // Use current input value unless cleared
             }
        });
    } // End updateCartItem

    // --- Event Listener using Delegation ---
    if (cartContainer) {
        cartContainer.addEventListener('click', function(event) {
            const target = event.target;
            const controlBar = target.closest('.item-control-bar'); // Target the control bar specifically

            if (!controlBar) return; // Exit if click wasn't inside the control bar

            const cartItemBox = target.closest('.cart-item-box');
            if (!cartItemBox) return; // Should always find this if controlBar was found

            const productId = cartItemBox.getAttribute('data-product-id');
            const currentQtySpan = cartItemBox.querySelector('.qty-display');
            const currentQuantity = parseInt(currentQtySpan?.textContent || '0', 10);
            let action = 'update'; // Default action

            // --- Plus Button ---
            if (target.classList.contains('plus-btn')) {
                updateCartItem(productId, currentQuantity + 1, action);
            }

            // --- Minus Button ---
            else if (target.classList.contains('minus-btn')) {
                if (currentQuantity > 1) {
                    updateCartItem(productId, currentQuantity - 1, action);
                }
                // If quantity is 1, the button should be disabled by UI updates/initial load
                // No need for explicit removal confirmation here as button is disabled
            }

            // --- Remove Button (inside control bar) ---
            else if (target.classList.contains('remove-item-btn') || target.closest('.remove-item-btn')) {
                if (confirm('Are you sure you want to remove this item from your cart?')) {
                     action = 'remove'; // Set action to remove
                     updateCartItem(productId, 0, action); // Send quantity 0 for removal
                }
            }
        });
    } else {
        console.error("Cart container element not found.");
    }

    // Initialize minus button states on page load
    document.querySelectorAll('.cart-item-box').forEach(itemBox => {
        const minusBtn = itemBox.querySelector('.minus-btn');
        const qtyDisplay = itemBox.querySelector('.qty-display');
        if(minusBtn && qtyDisplay) {
            minusBtn.disabled = (parseInt(qtyDisplay.textContent, 10) <= 1);
        }
    });

    // Initialize coupon form state based on initial load
    updateCouponFormState(couponInput?.value);


}); // End DOMContentLoaded