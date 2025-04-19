<?php
session_start();

// Redirect if not logged in or cart is empty
if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
    header("Location: login.php"); // Or cart_page.php if cart is empty
    exit();
}

// Calculate total (assuming cart items have prices)
$total_price = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        // Ensure price and quantity are numeric and exist
        if (isset($item['price']) && is_numeric($item['price']) && isset($item['quantity']) && is_numeric($item['quantity'])) {
             $total_price += $item['price'] * $item['quantity'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Natural Diamonds</title>
    <link rel="stylesheet" href="resources/css/reset.css">
    <link rel="stylesheet" href="resources/css/style.css"> <!-- Main styles -->
    <link rel="stylesheet" href="resources/css/checkout.css"> <!-- Checkout specific styles -->
</head>
<body>
    <header class="site-header">
        <div class="container header-content">
            <a href="index.php" class="logo">
                <img src="resources/images/Natural_Diamonds_logo_cropped.png" alt="Natural Diamonds Logo">
            </a>
            <nav class="main-navigation">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="store.php">Store</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                    <li><a href="cart_page.php">Cart</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="signup.php">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="checkout-page container">
        <h1>Checkout</h1>

        <div class="checkout-content">
            <section class="shipping-details">
                <h2>Shipping Information</h2>
                <form action="order_confirmation.php" method="post" id="checkout-form">
                    <div class="form-group">
                        <label for="fullname">Full Name:</label>
                        <input type="text" id="fullname" name="fullname" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <input type="text" id="address" name="address" required>
                    </div>
                    <div class="form-group">
                        <label for="city">City:</label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="zipcode">Zip Code:</label>
                        <input type="text" id="zipcode" name="zipcode" required>
                    </div>
                    <div class="form-group">
                        <label for="country">Country:</label>
                        <input type="text" id="country" name="country" required>
                    </div>
                </form>
            </section>

            <section class="order-summary">
                <h2>Order Summary</h2>
                <div class="summary-details">
                    <!-- Ideally, list items here, but for simplicity, just show total -->
                    <p>Total Items: <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?></p>
                    <p>Total Price: $<?php echo number_format($total_price, 2); ?></p>
                </div>
                 <div class="payment-placeholder">
                    <h2>Payment Method</h2>
                    <p>Payment processing is not implemented in this demo.</p>
                    <p>Click "Place Order" to simulate order completion.</p>
                 </div>
            </section>
        </div>

        <div class="checkout-actions">
             <button type="submit" form="checkout-form" class="btn btn-primary">Place Order</button>
        </div>

    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Natural Diamonds. All rights reserved.</p>
            <nav class="footer-nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="store.php">Store</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                </ul>
            </nav>
        </div>
    </footer>

</body>
</html>
