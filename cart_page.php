<?php
session_start();
// Database connection needed to fetch product details based on IDs in session cart
$servername = "localhost";
$username = "root";
$password = "vyom0403";
$dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$cartItems = [];
$cartTotal = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $ids_string = implode(',', $productIds); // Create comma-separated string of IDs

    if (!empty($ids_string)) { // Ensure string is not empty
        $sql = "SELECT product_id, name, price, image_url FROM products WHERE product_id IN ($ids_string)";
        $result = $conn->query($sql);

        $productsData = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $productsData[$row['product_id']] = $row; // Store product data keyed by ID
            }
        }

        // Combine session quantities with product data
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
            } else {
                // Product ID from session not found in DB (maybe removed?), handle this case
                unset($_SESSION['cart'][$productId]); // Remove invalid item from cart
            }
        }
    } else {
        // Handle case where cart session exists but IDs string is empty (e.g., after removing last item)
        $_SESSION['cart'] = []; // Clear cart
    }
}
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
            padding-left: 5%;
            padding-right: 5%;
            padding-bottom: 2rem;

            /* Keep or adjust bottom padding as needed */
            /* Ensure margin-top: 60px; is removed */
            .empty-cart-box {
                border: 1px solid #e0e0e0;
                /* Softer border */
                background-color: #fdfdfd;
                /* Very light background */
                padding: 2.5rem;
                /* Generous internal padding */
                text-align: center;
                /* Center the text and button */
                margin-top: 3rem;
                /* Space from top */
                max-width: 550px;
                /* Limit width */
                margin-left: auto;
                /* Center the box horizontally */
                margin-right: auto;
                /* Center the box horizontally */
                border-radius: 8px;
                /* Slightly rounded corners */
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                /* Subtle shadow */
            }

            .empty-cart-box p {
                margin-bottom: 1.5rem;
                /* Space between message and button */
                font-size: 1.15em;
                /* Make text a bit larger */
                color: #555;
                /* Slightly softer text color */
            }

            /* Style the button within the empty cart box */
            .empty-cart-box .button.continue-shopping-btn {
                /* Uses base .button styles, add overrides here if needed */
                padding: 0.8rem 2rem;
                /* Example: Make padding larger */
                font-weight: bold;
            }

            .cart-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 2rem;
            }

            .cart-table th,
            .cart-table td {
                border: 1px solid #ddd;
                padding: 0.8rem;
                text-align: left;
            }

            .cart-table th {
                background-color: #f2f2f2;
            }

            .cart-table img {
                max-width: 60px;
                height: auto;
                vertical-align: middle;
                margin-right: 10px;
            }

            .cart-item-quantity input {
                width: 50px;
                padding: 0.3rem;
                text-align: center;
            }

            .cart-item-remove button {
                background: #e74c3c;
                color: white;
                border: none;
                padding: 0.3rem 0.6rem;
                cursor: pointer;
                border-radius: 3px;
            }

            .cart-total {
                text-align: right;
                margin-bottom: 1rem;
                font-size: 1.2rem;
                font-weight: bold;
            }

            .checkout-button {
                padding: 0.8rem 1.5rem;
                background-color: var(--text-dark);
                color: var(--primary-color);
                text-decoration: none;
                border-radius: 5px;
                float: right;
            }

            #cart-update-status {
                margin-top: 1rem;
                color: green;
            }
    </style>
</head>

<body>
    <header>
        <div class="content">
            <a href="index.html" class="desktop logo">Prism Jewellery</a>
            <nav class="desktop">
                <ul>
                    <li><a href="./index.html">Home</a></li>
                    <li><a href="./about-us.html">About us</a></li>
                    <li><a href="./store.php">Store</a></li>
                    <li><a href="cart_page.php">Cart (<span id="cart-count"><?php echo array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); ?></span>)</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="cart-container">
        <?php if (!empty($cartItems)): ?>
            <h1>Your Shopping Cart</h1>
            <div id="cart-update-status"></div>

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
                            <td><img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td class="cart-item-quantity">
                                <input type="number" value="<?php echo $item['quantity']; ?>" min="1" class="quantity-input">
                                <button class="update-quantity-btn">Update</button>
                            </td>
                            <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                            <td class="cart-item-remove">
                                <button class="remove-item-btn">Remove</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="cart-total">
                <strong>Total: $<?php echo number_format($cartTotal, 2); ?></strong>
            </div>
            <a href="checkout.php" class="checkout-button">Proceed to Checkout</a>
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
            const cartCountSpan = document.getElementById('cart-count'); // Header cart count

            if (cartTableBody) {
                cartTableBody.addEventListener('click', function(event) {
                    const target = event.target;
                    const cartRow = target.closest('tr');
                    if (!cartRow) return; // Click wasn't inside a row

                    const productId = cartRow.getAttribute('data-product-id');

                    // --- Update Quantity ---
                    if (target.classList.contains('update-quantity-btn')) {
                        const quantityInput = cartRow.querySelector('.quantity-input');
                        const quantity = parseInt(quantityInput.value, 10);

                        if (quantity > 0) {
                            updateCartItem(productId, quantity);
                        } else {
                            alert('Quantity must be at least 1.');
                        }
                    }

                    // --- Remove Item ---
                    if (target.classList.contains('remove-item-btn')) {
                        if (confirm('Are you sure you want to remove this item?')) {
                            updateCartItem(productId, 0); // Sending 0 quantity to remove
                        }
                    }
                });
            }

            function updateCartItem(productId, quantity) {
                cartStatusDiv.textContent = 'Updating...';
                fetch('manage_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=update&product_id=${productId}&quantity=${quantity}` // Using 'update' action
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            cartStatusDiv.textContent = data.message;
                            cartCountSpan.textContent = data.cart_count; // Update header count
                            // Reload page to reflect changes (simple approach)
                            // More advanced: Update row subtotal/total and potentially remove row via JS
                            window.location.reload();
                        } else {
                            cartStatusDiv.textContent = 'Error: ' + data.message;
                        }
                        setTimeout(() => {
                            cartStatusDiv.textContent = '';
                        }, 3000);
                    })
                    .catch(error => {
                        console.error('Error updating cart:', error);
                        cartStatusDiv.textContent = 'Error updating cart.';
                        setTimeout(() => {
                            cartStatusDiv.textContent = '';
                        }, 3000);
                    });
            }
        });
    </script>
</body>

</html>