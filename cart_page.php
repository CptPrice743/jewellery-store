<?php
session_start(); // Ensure session is started

if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to login page
    // Optional: You can add a redirect parameter to return to cart after login
    // header("Location: login.php?redirect=cart_page.php");
    header("Location: login.php");
    exit(); // Stop further script execution
}

// Database connection needed
$servername = "localhost";
$username = "root";
$password = "vyom0403";
$dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$cartItems = [];
$cartSubtotal = 0; // Price before discounts/taxes
$cartCount = 0; // Total number of items
$discountAmount = 0.00;
$couponDiscount = 0.00;
$taxAmount = 0.00; // Example: Add tax calculation if needed
$finalTotal = 0.00;
$appliedCouponCode = null;

$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;

// --- Fetch Cart Items (Combine logic for logged in / session) ---
if ($loggedIn) {
    // Logged in: Fetch from user_carts table joined with products
    $sql = "SELECT uc.product_id, uc.quantity, p.name, p.price, p.image_url
            FROM user_carts uc
            JOIN products p ON uc.product_id = p.product_id
            WHERE uc.user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $itemSubtotal = $row['price'] * $row['quantity'];
            $cartItems[] = [
                'id' => $row['product_id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'image' => $row['image_url'],
                'quantity' => $row['quantity'],
                'subtotal' => $itemSubtotal // Store item subtotal for display
            ];
            $cartSubtotal += $itemSubtotal;
            $cartCount += $row['quantity'];
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement in cart_page.php (logged in): " . $conn->error);
    }
} elseif (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // Not logged in: Fetch from session + DB lookup for details
    $productIds = array_keys($_SESSION['cart']);
    if (!empty($productIds)) {
        $sanitized_ids = array_map('intval', $productIds);
        $ids_string = implode(',', $sanitized_ids);
        $sql = "SELECT product_id, name, price, image_url FROM products WHERE product_id IN ($ids_string)";
        $result = $conn->query($sql);
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
                $itemSubtotal = $product['price'] * $quantity;
                $cartItems[] = [
                    'id' => $productId,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image' => $product['image_url'],
                    'quantity' => $quantity,
                    'subtotal' => $itemSubtotal
                ];
                $cartSubtotal += $itemSubtotal;
                $cartCount += $quantity;
            } else {
                unset($_SESSION['cart'][$productId]); // Remove invalid item
            }
        }
    } else {
        $_SESSION['cart'] = [];
    }
}

// --- Apply Coupon Discount (Check Session) ---
if (isset($_SESSION['applied_coupon']) && $cartSubtotal > 0) {
    $couponData = $_SESSION['applied_coupon'];
    $appliedCouponCode = $couponData['code'];

    // Recalculate discount based on current cart subtotal
    // Fetch coupon details again to ensure they are current
    $stmt_coupon = $conn->prepare("SELECT discount_type, discount_value FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
    if ($stmt_coupon) {
        $stmt_coupon->bind_param("s", $appliedCouponCode);
        $stmt_coupon->execute();
        $coupon_result = $stmt_coupon->get_result();
        if ($coupon_details = $coupon_result->fetch_assoc()) {
            if ($coupon_details['discount_type'] == 'percentage') {
                $couponDiscount = ($cartSubtotal * $coupon_details['discount_value'] / 100);
            } elseif ($coupon_details['discount_type'] == 'fixed') {
                $couponDiscount = $coupon_details['discount_value'];
            }
            // Ensure discount doesn't exceed subtotal
            $couponDiscount = min($couponDiscount, $cartSubtotal);
            $_SESSION['applied_coupon']['discount'] = $couponDiscount; // Update session discount amount
        } else {
            // Coupon became invalid since last applied
            unset($_SESSION['applied_coupon']);
            $appliedCouponCode = null;
            $couponDiscount = 0;
            // Set a message maybe? $_SESSION['cart_message'] = 'Applied coupon is no longer valid.';
        }
        $stmt_coupon->close();
    } else {
        // DB error fetching coupon
        unset($_SESSION['applied_coupon']);
        $appliedCouponCode = null;
        $couponDiscount = 0;
    }
} else {
    // No coupon in session or cart is empty
    unset($_SESSION['applied_coupon']); // Ensure it's cleared if cart became empty
    $couponDiscount = 0;
    $appliedCouponCode = null;
}


// --- Calculate Final Total ---
// Example: Add other discounts or fees here if needed
$discountAmount = $couponDiscount; // Total discount is currently just the coupon
$finalTotal = $cartSubtotal - $discountAmount + $taxAmount;
$finalTotal = max(0, $finalTotal); // Ensure total doesn't go below zero

// Store final total in session for checkout process
$_SESSION['cart_final_total'] = $finalTotal;
$_SESSION['cart_discount_amount'] = $discountAmount;

// Close DB connection
$conn->close();

// --- Cart Messages (e.g., from coupon application) ---
$cartMessage = '';
if (isset($_SESSION['cart_message'])) {
    $cartMessage = $_SESSION['cart_message'];
    unset($_SESSION['cart_message']); // Clear message after displaying
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Prism Jewellery</title>
    <link rel="stylesheet" href="./resources/css/reset.css">
    <link rel="stylesheet" href="./resources/css/style.css">
    <link rel="stylesheet" href="./resources/css/cart_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,600,700" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
</head>

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
                    <?php if ($loggedIn): ?>
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
                    <?php if ($loggedIn): ?>
                        <li><a href="logout.php" class="button">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="button">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="cart-page-container">
        <a href="store.php" class="go-back-link">&larr; Go Back</a>

        <div class="cart-grid">

            <div class="order-summary-column">
                <h1>ORDER SUMMARY</h1>
                <?php if (!empty($cartItems)): ?>
                    <p class="item-count-text">You are buying (<?php echo $cartCount; ?>) items</p>
                    <div id="cart-update-status" class="<?php echo !empty($cartMessage) ? (strpos($cartMessage, 'Error') !== false || strpos($cartMessage, 'invalid') !== false ? 'error' : 'success') : ''; ?>">
                        <?php echo htmlspecialchars($cartMessage); ?>
                    </div>

                    <div class="cart-items-list">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item-box" data-product-id="<?php echo $item['id']; ?>">
                                <img src="<?php echo htmlspecialchars($item['image'] ?: './resources/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                <div class="item-details">
                                    <h2><?php echo htmlspecialchars($item['name']); ?></h2>
                                    <p class="price">$<?php echo number_format($item['price'], 2); ?></p>
                                    <div class="item-control-bar">
                                        <button class="minus-btn" data-product-id="<?php echo $item['id']; ?>" aria-label="Decrease quantity">-</button>
                                        <span class="qty-display"><?php echo $item['quantity']; ?></span>
                                        <button class="plus-btn" data-product-id="<?php echo $item['id']; ?>" aria-label="Increase quantity">+</button>
                                        <button class="remove-item-btn" data-product-id="<?php echo $item['id']; ?>" aria-label="Remove item">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="item-subtotal">
                                    <p>$<?php echo number_format($item['subtotal'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <div class="empty-cart-box">
                        <p>Your cart is empty.</p>
                        <a href="store.php" class="button continue-shopping-btn">Continue Shopping</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($cartItems)): ?>
                <div class="payment-summary-column">
                    <div class="coupon-section">
                        <form id="coupon-form" method="POST" action="apply_coupon.php">
                            <input type="text" name="coupon_code" placeholder="Enter Coupon Code" value="<?php echo htmlspecialchars($appliedCouponCode ?? ''); ?>">
                            <button type="submit">APPLY COUPON</button>
                        </form>
                        <?php if ($appliedCouponCode): ?>
                            <form id="remove-coupon-form" method="POST" action="apply_coupon.php" style="margin-top: 5px;">
                                <input type="hidden" name="remove_coupon" value="1">
                                <button type="submit" class="remove-coupon-btn">Remove Coupon</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="payment-summary-box">
                        <h2>Payment Summary</h2>
                        <p><span>Price (<?php echo $cartCount; ?> items)</span> <span id="summary-subtotal">$<?php echo number_format($cartSubtotal, 2); ?></span></p>
                        <p><span>Discount</span> <span id="summary-discount-amount">-$<?php echo number_format($discountAmount, 2); // Total discounts 
                                                                                        ?></span></p>
                        <?php if ($couponDiscount > 0): ?>
                            <p class="coupon-applied-line"><span>Coupon Discount Applied</span> <span id="summary-coupon-discount">-$<?php echo number_format($couponDiscount, 2); ?></span></p>
                        <?php endif; ?>
                        <p><span>Tax</span> <span id="summary-tax-amount">$<?php echo number_format($taxAmount, 2); ?></span></p>
                        <hr>
                        <p class="total-amount"><span>Total Amount</span> <span id="summary-final-total">$<?php echo number_format($finalTotal, 2); ?></span></p>

                        <?php if ($loggedIn): // Only show checkout if logged in 
                        ?>
                            <form action="checkout.php" method="POST">
                                <button type="submit" class="proceed-button">PROCEED TO PAYMENT</button>
                            </form>
                        <?php else: ?>
                            <p class="login-prompt">Please <a href="login.php?redirect=cart_page.php">login</a> to proceed to payment.</p>
                            <button class="proceed-button disabled" disabled>PROCEED TO PAYMENT</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <footer>
        <div class="content">
            <span class="copyright">© 2024 Prism Jewellery, All Rights Reserved</span>
            <span class="location">Designed by Vyom Uchat (22BCP450)</span>
        </div>
    </footer>

    <script src="resources/js/cart_actions.js"></script>

</body>

</html>