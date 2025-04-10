<?php
session_start(); // ADD THIS LINE

// Database connection needed
$servername = "localhost"; $username = "root"; $password = "vyom0403"; $dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$cartItems = [];
$cartTotal = 0;
$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;
$cartCount = 0; // Initialize cart count

if ($loggedIn) {
    // Logged in: Fetch from user_carts table joined with products
    $sql = "SELECT uc.product_id, uc.quantity, p.name, p.price, p.image_url
            FROM user_carts uc
            JOIN products p ON uc.product_id = p.product_id
            WHERE uc.user_id = ?";
    $stmt = $conn->prepare($sql);
     if($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $subtotal = $row['price'] * $row['quantity'];
            $cartItems[] = [
                'id' => $row['product_id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'image' => $row['image_url'],
                'quantity' => $row['quantity'],
                'subtotal' => $subtotal
            ];
            $cartTotal += $subtotal;
            $cartCount += $row['quantity']; // Sum quantities for count
        }
        $stmt->close();
     } else {
        error_log("Failed to prepare statement in cart_page.php (logged in): " . $conn->error);
     }

} elseif (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // Not logged in: Fetch from session (existing logic, but needs product details)
    $productIds = array_keys($_SESSION['cart']);
    if (!empty($productIds)) {
        // Sanitize IDs just in case, although they come from session
        $sanitized_ids = array_map('intval', $productIds);
        $ids_string = implode(',', $sanitized_ids);

        $sql = "SELECT product_id, name, price, image_url FROM products WHERE product_id IN ($ids_string)";
        $result = $conn->query($sql); // Okay to use query directly here as IDs are integers
        $productsData = [];
         if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $productsData[$row['product_id']] = $row;
            }
        }

        foreach ($_SESSION['cart'] as $productId => $item) {
             if (isset($productsData[$productId])) {
                $product = $productsData[$productId];
                $quantity = $item['quantity'];
                $subtotal = $product['price'] * $quantity;
                $cartItems[] = [
                    'id' => $productId,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image' => $product['image_url'],
                    'quantity' => $quantity,
                    'subtotal' => $subtotal
                ];
                $cartTotal += $subtotal;
                $cartCount += $quantity; // Sum quantities for count
            } else {
                unset($_SESSION['cart'][$productId]); // Remove invalid item
            }
        }
    } else {
         $_SESSION['cart'] = []; // Ensure cart is empty if product IDs are empty
    }
}

// Close DB connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Prism Jewellery</title>
    <link rel="stylesheet" href="./resources/css/reset.css">
    <link rel="stylesheet" href="./resources/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <style>
        .cart-container {
            padding: 2rem 5%; /* Consistent padding */
             padding-top: 7rem; /* Account for fixed header */
             min-height: 60vh; /* Ensure content pushes footer down */
        }
         .cart-container h1 {
             font-size: 2rem;
             margin-bottom: 1.5rem;
             font-family: "Playfair Display", serif;
             color: var(--text-dark);
             border-bottom: 1px solid #eee;
             padding-bottom: 0.5rem;
         }

            .empty-cart-box {
                border: 1px solid #e0e0e0;
                background-color: #fdfdfd;
                padding: 2.5rem;
                text-align: center;
                margin-top: 3rem;
                max-width: 550px;
                margin-left: auto;
                margin-right: auto;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .empty-cart-box p {
                margin-bottom: 1.5rem;
                font-size: 1.15em;
                color: #555;
            }

            .empty-cart-box .button.continue-shopping-btn {
                padding: 0.8rem 2rem;
                font-weight: bold;
                 /* Inherits .button styles */
                 background-color: var(--text-dark);
                 color: var(--primary-color);
                 text-decoration: none;
                 display: inline-block; /* Allow margin auto to work */
                 border: none;
            }
             .empty-cart-box .button.continue-shopping-btn:hover {
                  background-color: #555;
             }

            .cart-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 2rem;
                 font-family: "Roboto", sans-serif;
            }

            .cart-table th,
            .cart-table td {
                border: 1px solid #ddd;
                padding: 0.8rem 1rem; /* Increased padding */
                text-align: left;
                 vertical-align: middle; /* Align content vertically */
            }

            .cart-table th {
                background-color: #f8f8f8; /* Lighter header */
                 font-weight: 600;
                 color: #333;
            }

             .cart-table td.product-info {
                 display: flex;
                 align-items: center;
             }

            .cart-table img {
                max-width: 60px;
                height: auto;
                margin-right: 15px; /* More space next to image */
                 border-radius: 3px; /* Slight rounding */
            }
             .cart-table .product-name {
                 font-weight: 500;
             }

            .cart-item-quantity {
                 text-align: center; /* Center quantity controls */
             }
             .cart-item-quantity input {
                width: 50px;
                padding: 0.5rem; /* Slightly larger input */
                text-align: center;
                 border: 1px solid #ccc;
                 border-radius: 3px;
                 margin: 0 5px; /* Space around input */
            }
             .cart-item-quantity .update-quantity-btn {
                 padding: 0.4rem 0.8rem;
                 font-size: 0.9em;
                 cursor: pointer;
                 background-color: #eee;
                 border: 1px solid #ccc;
                 border-radius: 3px;
                 margin-left: 5px;
             }
              .cart-item-quantity .update-quantity-btn:hover {
                  background-color: #ddd;
              }


            .cart-item-remove {
                 text-align: center; /* Center remove button */
             }
             .cart-item-remove .remove-item-btn {
                background: none;
                color: #e74c3c;
                border: none;
                padding: 0.3rem 0.6rem;
                cursor: pointer;
                border-radius: 3px;
                 font-size: 1.2em; /* Make icon/text larger */
                 font-weight: bold;
            }
              .cart-item-remove .remove-item-btn:hover {
                  color: #c0392b;
              }


            .cart-summary {
                 display: flex;
                 justify-content: flex-end; /* Align items to the right */
                 align-items: center;
                 margin-top: 2rem;
                 padding-top: 1rem;
                 border-top: 1px solid #eee;
                 gap: 1.5rem; /* Space between total and button */
             }

             .cart-total {
                /* text-align: right; */
                /* margin-bottom: 1rem; */
                font-size: 1.3rem; /* Larger total */
                font-weight: bold;
                 color: var(--text-dark);
            }

            .checkout-button {
                padding: 0.8rem 1.8rem; /* Larger padding */
                background-color: var(--text-dark);
                color: var(--primary-color);
                text-decoration: none;
                border-radius: 5px;
                 /* float: right; --- Replaced by flexbox */
                 border: none;
                 font-size: 1rem;
                 cursor: pointer;
                 transition: background-color 0.3s ease;
            }
             .checkout-button:hover {
                 background-color: #555;
             }

            #cart-update-status {
                margin-bottom: 1rem; /* Space below status message */
                color: green;
                 text-align: center;
                 min-height: 1.2em; /* Prevent layout shift */
                 font-style: italic;
            }
    </style>
</head>

<body>
     <header>
        <div class="content">
            <a href="index.php" class="desktop logo">Prism Jewellery</a>
            <nav class="desktop">
                <ul>
                    <li><a href="./index.php">Home</a></li>
                    <li><a href="./about-us.php">About us</a></li>
                    <li><a href="./store.php">Store</a></li>
                    <li><a href="cart_page.php">Cart (<span id="cart-count"><?php echo $cartCount; ?></span>)</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>)</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <nav class="mobile">
                 <ul>
                     <li><a href="./index.php">Prism Jewellery</a></li>
                     <li><a href="./about-us.php">About Us</a></li>
                     <li><a href="./store.php">Store</a></li>
                     <li><a href="cart_page.php">Cart (<span id="cart-count-mobile"><?php echo $cartCount; ?></span>)</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php" class="button">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="button">Login</a></li>
                    <?php endif; ?>
                 </ul>
             </nav>
        </div>
    </header>


    <div class="cart-container">
        <h1>Your Shopping Cart</h1>
        <div id="cart-update-status"></div>

        <?php if (!empty($cartItems)): ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th colspan="2">Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr data-product-id="<?php echo $item['id']; ?>">
                             <td class="product-info">
                                 <img src="<?php echo htmlspecialchars($item['image'] ?: './resources/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                 <span class="product-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            </td>
                             <td></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td class="cart-item-quantity">
                                <input type="number" value="<?php echo $item['quantity']; ?>" min="1" class="quantity-input" aria-label="Quantity for <?php echo htmlspecialchars($item['name']); ?>">
                                <button class="update-quantity-btn" aria-label="Update quantity for <?php echo htmlspecialchars($item['name']); ?>">Update</button>
                            </td>
                            <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                            <td class="cart-item-remove">
                                <button class="remove-item-btn" aria-label="Remove <?php echo htmlspecialchars($item['name']); ?> from cart">&times;</button> </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-summary">
                <div class="cart-total">
                    <strong>Total: $<?php echo number_format($cartTotal, 2); ?></strong>
                </div>
                 <?php if ($loggedIn): // Only show checkout if logged in ?>
                    <a href="checkout.php" class="checkout-button">Proceed to Checkout</a>
                 <?php else: ?>
                     <p style="margin-right: 1rem;">Please <a href="login.php?redirect=cart_page.php">login</a> to proceed to checkout.</p>
                 <?php endif; ?>
             </div>

        <?php else: ?>
            <div class="empty-cart-box">
                <p>Your cart is empty.</p>
                <a href="store.php" class="button continue-shopping-btn">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="content">
            <span class="copyright">© 2024 Prism Jewellery, All Rights Reserved</span>
            <span class="location">Designed by Vyom Uchat (22BCP450)</span>
        </div>
    </footer>

    <script>
        // JavaScript for Cart Page Actions (Update Quantity, Remove Item)
        document.addEventListener('DOMContentLoaded', function() {
            const cartTableBody = document.querySelector('.cart-table tbody');
            const cartStatusDiv = document.getElementById('cart-update-status');
            const cartCountSpan = document.getElementById('cart-count');
            const cartCountSpanMobile = document.getElementById('cart-count-mobile');

            if (cartTableBody) {
                cartTableBody.addEventListener('click', function(event) {
                    const target = event.target;
                    const cartRow = target.closest('tr');
                    if (!cartRow) return;

                    const productId = cartRow.getAttribute('data-product-id');

                    // --- Update Quantity ---
                    if (target.classList.contains('update-quantity-btn')) {
                        const quantityInput = cartRow.querySelector('.quantity-input');
                        const quantity = parseInt(quantityInput.value, 10);

                        if (!isNaN(quantity) && quantity > 0) {
                            updateCartItem(productId, quantity);
                        } else {
                             alert('Quantity must be a number greater than 0.');
                             // Optionally reset input to previous value if possible
                        }
                    }

                    // --- Remove Item ---
                    if (target.classList.contains('remove-item-btn')) {
                        if (confirm('Are you sure you want to remove this item?')) {
                             // Send 'remove' action or quantity 0 depending on manage_cart.php logic
                             updateCartItem(productId, 0, 'remove'); // Pass 'remove' action explicitly
                        }
                    }
                });
            }

            function updateCartItem(productId, quantity, action = 'update') { // Default action is 'update'
                cartStatusDiv.textContent = 'Updating...';
                fetch('manage_cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                         // Send specific action based on button clicked
                         body: `action=${action}&product_id=${productId}&quantity=${quantity}`
                    })
                    .then(response => {
                         if (!response.ok) {
                             throw new Error(`HTTP error! status: ${response.status}`);
                         }
                         return response.json();
                     })
                    .then(data => {
                        if (data.success) {
                             cartStatusDiv.textContent = data.message;
                             // Update header counts if elements exist
                             if(cartCountSpan) cartCountSpan.textContent = data.cart_count;
                             if(cartCountSpanMobile) cartCountSpanMobile.textContent = data.cart_count;

                             // Reload page to reflect changes (simple approach)
                             window.location.reload();
                             // More advanced: Update row subtotal/total and potentially remove row via JS without reload
                        } else {
                            cartStatusDiv.textContent = 'Error: ' + (data.message || 'Could not update cart.');
                        }
                         // Clear message after a delay
                         setTimeout(() => { cartStatusDiv.textContent = ''; }, 3000);
                    })
                    .catch(error => {
                        console.error('Error updating cart:', error);
                        cartStatusDiv.textContent = 'Error communicating with server.';
                        setTimeout(() => { cartStatusDiv.textContent = ''; }, 3000);
                    });
            }
        });
    </script>
</body>

</html>