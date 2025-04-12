// resources/js/cart_actions.js
document.addEventListener('DOMContentLoaded', function() {
    const cartContainer = document.querySelector('.cart-page-container'); // Use the main container for delegation
    const cartStatusDiv = document.getElementById('cart-update-status');
    const cartCountSpan = document.getElementById('cart-count');
    const cartCountSpanMobile = document.getElementById('cart-count-mobile');

    // Selectors for summary elements
    const summarySubtotalEl = document.getElementById('summary-subtotal');
    const summaryDiscountEl = document.getElementById('summary-discount-amount');
    const summaryCouponDiscountEl = document.getElementById('summary-coupon-discount'); // May not exist initially
    const summaryTaxEl = document.getElementById('summary-tax-amount');
    const summaryFinalTotalEl = document.getElementById('summary-final-total');

    if (cartContainer) {
        cartContainer.addEventListener('click', function(event) {
            const target = event.target;
            const cartItemBox = target.closest('.cart-item-box'); // Find parent item box

            if (!cartItemBox) return; // Exit if click wasn't inside an item box

            const productId = cartItemBox.getAttribute('data-product-id');
            const currentQtySpan = cartItemBox.querySelector('.qty-display');
            const currentQuantity = parseInt(currentQtySpan?.textContent || '0', 10);

            // --- Plus Button ---
            if (target.classList.contains('plus-btn')) {
                updateCartItem(productId, currentQuantity + 1, 'update');
            }

            // --- Minus Button ---
            else if (target.classList.contains('minus-btn')) {
                if (currentQuantity > 1) {
                    updateCartItem(productId, currentQuantity - 1, 'update');
                } else {
                    // If current quantity is 1, clicking minus means remove
                    if (confirm('Remove this item from your cart?')) {
                        updateCartItem(productId, 0, 'remove');
                    }
                }
            }

            // --- Remove Button ---
            else if (target.classList.contains('remove-item-btn')) {
                if (confirm('Are you sure you want to remove this item?')) {
                    updateCartItem(productId, 0, 'remove');
                }
            }
        });
    }

    function updateCartItem(productId, quantity, action) {
        if (cartStatusDiv) {
             cartStatusDiv.textContent = 'Updating...';
             cartStatusDiv.className = ''; // Clear previous classes
             cartStatusDiv.style.display = 'block';
         }

        // Disable buttons during update (optional but good UX)
        const buttons = cartContainer.querySelectorAll('button');
        buttons.forEach(btn => btn.disabled = true);

        fetch('manage_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-form-urlencoded' },
            body: `action=${action}&product_id=${productId}&quantity=${quantity}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // --- Update UI Dynamically (Instead of full reload) ---
            if (data.success) {
                 if (cartStatusDiv) {
                     cartStatusDiv.textContent = data.message || 'Cart updated successfully.';
                     cartStatusDiv.className = 'success'; // Add success class
                 }
                 // Update header counts
                 if (cartCountSpan) cartCountSpan.textContent = data.cart_count;
                 if (cartCountSpanMobile) cartCountSpanMobile.textContent = data.cart_count;

                 // Update specific item row or remove it
                 const itemBoxToUpdate = cartContainer.querySelector(`.cart-item-box[data-product-id="${productId}"]`);
                 if (itemBoxToUpdate) {
                     if (data.item_quantity > 0) { // Item quantity updated
                         itemBoxToUpdate.querySelector('.qty-display').textContent = data.item_quantity;
                         itemBoxToUpdate.querySelector('.item-subtotal p').textContent = '$' + data.item_subtotal.toFixed(2);
                     } else { // Item removed
                         itemBoxToUpdate.remove();
                     }
                 }

                 // Update payment summary
                 if (summarySubtotalEl) summarySubtotalEl.textContent = '$' + data.cart_subtotal.toFixed(2);
                 if (summaryDiscountEl) summaryDiscountEl.textContent = '-$' + data.cart_discount.toFixed(2); // Total discount
                 if (summaryTaxEl) summaryTaxEl.textContent = '$' + data.cart_tax.toFixed(2);
                 if (summaryFinalTotalEl) summaryFinalTotalEl.textContent = '$' + data.cart_total.toFixed(2);

                 // Update coupon display (More complex - might need a dedicated element or reload for simplicity)
                 // If coupon was invalidated, show message
                 if (data.coupon_removed) {
                    if (cartStatusDiv) { // Append message
                        cartStatusDiv.textContent += ' Applied coupon was removed due to cart changes.';
                    }
                    // Remove coupon display elements if they exist
                    document.getElementById('coupon-form')?.reset(); // Clear input
                    document.getElementById('remove-coupon-form')?.remove();
                    document.querySelector('.coupon-applied-line')?.remove();
                 }

                 // Check if cart is now empty
                 if (data.cart_count === 0) {
                    // Reload the page to show the empty cart message correctly
                    window.location.reload();
                 }


            } else {
                 if (cartStatusDiv) {
                     cartStatusDiv.textContent = 'Error: ' + (data.message || 'Could not update cart.');
                     cartStatusDiv.className = 'error'; // Add error class
                 }
            }

            // Clear message after a delay
            setTimeout(() => {
                 if (cartStatusDiv) {
                     cartStatusDiv.textContent = '';
                     cartStatusDiv.style.display = 'none';
                     cartStatusDiv.className = '';
                 }
             }, 4000); // Increased delay slightly

        })
        .catch(error => {
            console.error('Error updating cart:', error);
             if (cartStatusDiv) {
                cartStatusDiv.textContent = 'Error communicating with server.';
                cartStatusDiv.className = 'error';
                cartStatusDiv.style.display = 'block';
                setTimeout(() => {
                    cartStatusDiv.textContent = '';
                    cartStatusDiv.style.display = 'none';
                    cartStatusDiv.className = '';
                 }, 4000);
             }
        })
        .finally(() => {
             // Re-enable buttons
             buttons.forEach(btn => btn.disabled = false);
        });
    } // End updateCartItem

    // Optional: Add AJAX for coupon form submission if desired
    // const couponForm = document.getElementById('coupon-form');
    // couponForm?.addEventListener('submit', function(e) {
    //     e.preventDefault();
    //     // Add fetch logic similar to updateCartItem but targeting apply_coupon.php
    //     // Update summary elements and potentially add/remove coupon display on success/failure
    //     // Example: Use FormData to get form data easily
    //     // const formData = new FormData(couponForm);
    //     // fetch('apply_coupon.php', { method: 'POST', body: formData }) ...
    // });

}); // End DOMContentLoaded