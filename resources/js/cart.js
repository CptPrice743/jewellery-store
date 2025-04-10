document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const productGrid = document.getElementById('product-grid');
    const cartCountSpan = document.getElementById('cart-count');

    // --- Function to update Cart Count in Header ---
    function updateCartCount(count) {
        if (count !== undefined) {
            cartCountSpan.textContent = count;
        }
        // Optional: Fetch count if not provided by action response
        // fetch('manage_cart.php', {...}).then(...)
    }

    // --- Initial Cart Count ---
    // It might be better to fetch the full cart state on load
    // to initialize quantities correctly if items are already in cart.
    // For now, we'll assume cart count is updated by actions.
    // updateCartCount(); // Fetch initial count if needed

    // --- AJAX Search Logic (Keep as is) ---
    searchInput?.addEventListener('input', function() { // Added null check
        const searchTerm = searchInput.value.trim();
        fetch(`search_products.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(products => {
                if (!productGrid) return; // Check if grid exists
                productGrid.innerHTML = ''; // Clear existing products

                if (products.length > 0) {
                    products.forEach(product => {
                         const imageUrl = product.image_url || './resources/images/placeholder.jpg';
                         const card = `
                            <div class='product-card'>
                                <img src='${imageUrl}' alt='${product.name}'>
                                <h3>${product.name}</h3>
                                <p class='price'>$${product.price}</p>
                                <div class='cart-interaction' data-product-id='${product.product_id}'>
                                    <button class='add-to-cart-btn'>Add to Cart</button>
                                </div>
                            </div>`;
                        productGrid.innerHTML += card;
                    });
                } else {
                    productGrid.innerHTML = '<p>No products found.</p>';
                }
                // NOTE: We don't need to re-attach listeners due to event delegation
            })
            .catch(error => {
                console.error('Error fetching search results:', error);
                 if (productGrid) productGrid.innerHTML = '<p>Error loading products.</p>';
            });
    });


    // --- Generate Quantity Selector HTML ---
    function getQuantitySelectorHTML(productId, quantity) {
        return `
            <div class="quantity-selector">
                <button class="minus-btn" data-product-id="${productId}" aria-label="Decrease quantity">-</button>
                <span class="qty-display">${quantity}</span>
                <button class="plus-btn" data-product-id="${productId}" aria-label="Increase quantity">+</button>
            </div>
        `;
    }

    // --- Generate Add to Cart Button HTML ---
    function getAddToCartButtonHTML(productId) {
        return `<button class='add-to-cart-btn'>Add to Cart</button>`;
    }

    // --- Handle Cart Updates via AJAX ---
    function updateCartAJAX(productId, quantity, interactionElement) {
        // Add visual feedback for processing
        interactionElement.classList.add('processing');

        let action = 'update';
        if (quantity === 1 && !interactionElement.querySelector('.quantity-selector')) {
             // If the selector isn't present, the first click is always 'add'
             action = 'add';
        } else if (quantity <= 0) {
            action = 'remove';
            quantity = 0; // Ensure quantity is 0 for removal action backend might expect
        }

        fetch('manage_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=${action}&product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            interactionElement.classList.remove('processing'); // Remove processing indicator
            if (data.success) {
                updateCartCount(data.cart_count);
                // Update the UI based on the new quantity
                if (quantity > 0) {
                    interactionElement.innerHTML = getQuantitySelectorHTML(productId, quantity);
                } else {
                    interactionElement.innerHTML = getAddToCartButtonHTML(productId);
                }
            } else {
                alert('Error updating cart: ' + data.message);
                // Optional: Revert UI changes if needed, though might be complex
            }
        })
        .catch(error => {
            interactionElement.classList.remove('processing'); // Remove processing indicator
            console.error('Cart update error:', error);
            alert('Could not update cart. Please try again.');
        });
    }


    // --- Main Event Listener using Delegation ---
    if (productGrid) {
        productGrid.addEventListener('click', function(event) {
            const target = event.target;
            const interactionElement = target.closest('.cart-interaction'); // Find the parent interaction container

            if (!interactionElement) return; // Clicked outside an interaction area

            const productId = interactionElement.getAttribute('data-product-id');

            // 1. Click on initial "Add to Cart" button
            if (target.classList.contains('add-to-cart-btn')) {
                 updateCartAJAX(productId, 1, interactionElement); // Add 1 item
            }
            // 2. Click on "+" button
            else if (target.classList.contains('plus-btn')) {
                const qtySpan = interactionElement.querySelector('.qty-display');
                const currentQuantity = parseInt(qtySpan.textContent, 10);
                updateCartAJAX(productId, currentQuantity + 1, interactionElement);
            }
            // 3. Click on "-" button
            else if (target.classList.contains('minus-btn')) {
                 const qtySpan = interactionElement.querySelector('.qty-display');
                 const currentQuantity = parseInt(qtySpan.textContent, 10);
                 updateCartAJAX(productId, currentQuantity - 1, interactionElement); // AJAX function handles <= 0 case
            }
        });
    } else {
         console.error("Product grid element not found on page load.");
    }

}); // End DOMContentLoaded