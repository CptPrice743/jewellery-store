<?php
session_start(); // Ensure session is started

// **IMPORTANT**: Ensure database credentials are correct and consistent across all files.
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Make sure this is your correct password
$dbname = "jewellery_store";

// Establish connection ONLY IF NEEDED in this specific file load
// Connection is primarily needed if fetching product details for session cart
// or recalculating coupon validity here (though it's better handled in manage_cart/apply_coupon)
$conn = null; // Initialize connection variable

$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;

// Function to establish connection if needed
function getDbConnection($servername, $username, $password, $dbname)
{
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        // Log the error properly instead of dying on the page
        error_log("Connection failed: " . $conn->connect_error);
        // You might want to display a user-friendly error message or redirect
        die("Database connection error. Please try again later.");
    }
    return $conn;
}

$cartItems = [];
$cartSubtotal = 0;
$cartCount = 0;
$discountAmount = 0.00;
$couponDiscount = 0.00;
$taxAmount = 0.00; // Example tax - set to 0 if not used
$finalTotal = 0.00;
$appliedCouponCode = null;
$appliedCouponDetails = null; // Store full coupon details if needed later

// --- Fetch Cart Items ---
$conn = getDbConnection($servername, $username, $password, $dbname); // Get connection

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
                'subtotal' => $itemSubtotal
            ];
            $cartSubtotal += $itemSubtotal;
            $cartCount += $row['quantity'];
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement in cart_page.php (logged in): " . $conn->error);
    }
} elseif (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // Not logged in (Guest): Fetch from session + DB lookup for details
    $productIds = array_keys($_SESSION['cart']);
    if (!empty($productIds)) {
        // Ensure IDs are integers before imploding to prevent SQL injection
        $sanitized_ids = array_map('intval', $productIds);
        if (!empty($sanitized_ids)) { // Check if array is not empty after sanitization
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
                $productId = (int)$productId; // Ensure comparison is integer vs integer
                if (isset($productsData[$productId])) {
                    $product = $productsData[$productId];
                    // Ensure quantity is valid
                    $quantity = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
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
                    // Product ID from session not found in DB, remove it
                    unset($_SESSION['cart'][$productId]);
                }
            }
        } else {
            // All product IDs were invalid, clear cart
            $_SESSION['cart'] = [];
        }
    } else {
        // Cart array exists but is empty
        $_SESSION['cart'] = [];
    }
}

// --- Apply Coupon Discount (Check Session) ---
if (isset($_SESSION['applied_coupon']) && $cartSubtotal > 0) {
    $couponData = $_SESSION['applied_coupon'];
    $appliedCouponCode = $couponData['code'];

    // Re-validate the coupon against the database and current subtotal
    $stmt_coupon = $conn->prepare("SELECT discount_type, discount_value, min_spend FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
    if ($stmt_coupon) {
        $stmt_coupon->bind_param("s", $appliedCouponCode);
        $stmt_coupon->execute();
        $coupon_result = $stmt_coupon->get_result();
        if ($coupon_details = $coupon_result->fetch_assoc()) {
            // Check minimum spend if applicable
            if ($coupon_details['min_spend'] === null || $cartSubtotal >= $coupon_details['min_spend']) {
                // Calculate discount
                if ($coupon_details['discount_type'] == 'percentage') {
                    $couponDiscount = ($cartSubtotal * $coupon_details['discount_value'] / 100);
                } elseif ($coupon_details['discount_type'] == 'fixed') {
                    $couponDiscount = $coupon_details['discount_value'];
                }
                // Ensure discount doesn't exceed subtotal
                $couponDiscount = min($couponDiscount, $cartSubtotal);
                $_SESSION['applied_coupon']['discount'] = $couponDiscount; // Update session discount amount
                $appliedCouponDetails = $coupon_details; // Store details for display if needed
            } else {
                // Subtotal below minimum spend, invalidate coupon
                unset($_SESSION['applied_coupon']);
                $appliedCouponCode = null;
                $couponDiscount = 0;
                $_SESSION['cart_message'] = 'Coupon removed: Cart subtotal is below the minimum spend requirement.';
            }
        } else {
            // Coupon became invalid (expired, deactivated) since last applied
            unset($_SESSION['applied_coupon']);
            $appliedCouponCode = null;
            $couponDiscount = 0;
            $_SESSION['cart_message'] = 'Applied coupon is no longer valid.';
        }
        $stmt_coupon->close();
    } else {
        // DB error fetching coupon
        unset($_SESSION['applied_coupon']);
        $appliedCouponCode = null;
        $couponDiscount = 0;
        error_log("Failed to prepare coupon check statement: " . $conn->error);
    }
} else {
    // No coupon in session or cart is empty
    unset($_SESSION['applied_coupon']); // Ensure it's cleared if cart became empty
    $couponDiscount = 0;
    $appliedCouponCode = null;
}

// --- Calculate Final Total ---
$discountAmount = $couponDiscount; // Total discount is currently just the coupon
$finalTotal = $cartSubtotal - $discountAmount + $taxAmount;
$finalTotal = max(0, $finalTotal); // Ensure total doesn't go below zero

// Store calculated values in session for potential use in checkout
$_SESSION['cart_final_total'] = $finalTotal;
$_SESSION['cart_subtotal'] = $cartSubtotal;
$_SESSION['cart_discount_amount'] = $discountAmount; // Store total discount
$_SESSION['cart_tax_amount'] = $taxAmount; // Store tax

// Close DB connection
if ($conn) {
    $conn->close();
}

// --- Cart Messages (e.g., from coupon application/removal) ---
$cartMessage = '';
$messageType = 'info'; // Default type
if (isset($_SESSION['cart_message'])) {
    $cartMessage = $_SESSION['cart_message'];
    // Determine message type based on content (simple check)
    if (stripos($cartMessage, 'error') !== false || stripos($cartMessage, 'invalid') !== false || stripos($cartMessage, 'failed') !== false) {
        $messageType = 'error';
    } elseif (stripos($cartMessage, 'success') !== false || stripos($cartMessage, 'applied') !== false) {
        $messageType = 'success';
    } elseif (stripos($cartMessage, 'removed') !== false) {
        $messageType = 'info'; // Use info for removal confirmation
    }
    unset($_SESSION['cart_message']); // Clear message after reading
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
        <a href="store.php" class="go-back-link">&larr; Continue Shopping</a>

        <div class="cart-grid">

            <div class="order-summary-column">
                <h1>SHOPPING CART</h1>
                <div id="cart-update-status" class="<?php echo $messageType; ?>" style="<?php echo empty($cartMessage) ? 'display: none;' : ''; ?>">
                    <?php echo htmlspecialchars($cartMessage); ?>
                </div>

                <?php if (!empty($cartItems)): ?>
                    <p class="item-count-text">You have <?php echo $cartCount; ?> item(s) in your cart</p>

                    <div class="cart-items-list">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item-box" data-product-id="<?php echo $item['id']; ?>">
                                <img src="<?php echo htmlspecialchars($item['image'] ?: './resources/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                <div class="item-details">
                                    <h2><?php echo htmlspecialchars($item['name']); ?></h2>
                                    <p class="price">$<?php echo number_format($item['price'], 2); ?></p>
                                    <div class="item-control-bar">
                                        <button class="minus-btn" data-product-id="<?php echo $item['id']; ?>" aria-label="Decrease quantity" <?php echo ($item['quantity'] <= 1) ? 'disabled' : ''; ?>>-</button>
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
                    <?php if (empty($cartMessage)): // Show default empty message only if no other message exists 
                    ?>
                        <div class="empty-cart-box">
                            <p>Your cart is empty.</p>
                            <a href="store.php" class="button continue-shopping-btn">Start Shopping</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($cartItems)): ?>
                <div class="payment-summary-column">
                    <div class="coupon-section">

                        <form id="coupon-form" method="POST" action="apply_coupon.php">
                            <input type="text" name="coupon_code" placeholder="Enter Coupon Code" value="<?php echo htmlspecialchars($appliedCouponCode ?? ''); ?>" <?php echo $appliedCouponCode ? 'readonly' : ''; ?>>
                            <button type="submit" <?php echo $appliedCouponCode ? 'disabled' : ''; ?>>APPLY</button>
                        </form>
                        <?php if ($appliedCouponCode): ?>
                            <form id="remove-coupon-form" method="POST" action="apply_coupon.php" style="margin-top: 10px;">
                                <input type="hidden" name="remove_coupon" value="1">
                                <button type="submit" class="remove-coupon-btn">Remove Coupon (<?php echo htmlspecialchars($appliedCouponCode); ?>)</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="payment-summary-box">
                        <h2>Payment Summary</h2>
                        <p><span>Subtotal</span> <span id="summary-subtotal">$<?php echo number_format($cartSubtotal, 2); ?></span></p>
                        <?php if ($discountAmount > 0): ?>
                            <p class="coupon-applied-line">
                                <span>Discount <?php echo $appliedCouponCode ? '(' . htmlspecialchars($appliedCouponCode) . ')' : ''; ?></span>
                                <span id="summary-discount-amount">-$<?php echo number_format($discountAmount, 2); ?></span>
                            </p>
                        <?php endif; ?>
                        <hr>
                        <p class="total-amount"><span>Total Amount</span> <span id="summary-final-total">$<?php echo number_format($finalTotal, 2); ?></span></p>

                        <?php if ($loggedIn): ?>
                            <form action="checkout.php" method="POST">
                                <button type="submit" class="proceed-button">PROCEED TO CHECKOUT</button>
                            </form>
                        <?php else: ?>
                            <p class="login-prompt">Please <a href="login.php?redirect=cart_page.php">login</a> or <a href="signup.php">sign up</a> to proceed.</p>
                            <button class="proceed-button disabled" disabled>PROCEED TO CHECKOUT</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <footer>
        <div class="content">
            <span class="copyright">© <?php echo date("Y"); ?> Prism Jewellery, All Rights Reserved</span>
            <span class="location">Designed by Vyom Uchat (22BCP450)</span>
        </div>
    </footer>

    <script src="resources/js/cart_actions.js"></script>

</body>

</html>