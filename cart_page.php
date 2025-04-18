<?php
session_start(); // Ensure session is started

// **IMPORTANT**: Ensure database credentials are correct and consistent across all files.
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Make sure this is your correct password
$dbname = "jewellery_store";

// --- Define Fees and Tax Rate ---
define('TAX_RATE', 0.18); // 18%
define('SHIPPING_FEE', 49.00);
define('PLATFORM_FEE', 3.99);

// Establish connection ONLY IF NEEDED in this specific file load
$conn = null; // Initialize connection variable

$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;

// Function to establish connection if needed
function getDbConnection($servername, $username, $password, $dbname)
{
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        die("Database connection error. Please try again later.");
    }
    return $conn;
}

$cartItems = [];
$cartSubtotal = 0;
$cartCount = 0;
$discountAmount = 0.00;
$couponDiscount = 0.00;
$taxAmount = 0.00;
$shippingFee = 0.00;
$platformFee = 0.00;
$finalTotal = 0.00;
$appliedCouponCode = null;
$appliedCouponDetails = null;

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
        $sanitized_ids = array_map('intval', $productIds);
        if (!empty($sanitized_ids)) {
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
                $productId = (int)$productId;
                if (isset($productsData[$productId])) {
                    $product = $productsData[$productId];
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
                    unset($_SESSION['cart'][$productId]);
                }
            }
        } else {
            $_SESSION['cart'] = [];
        }
    } else {
        $_SESSION['cart'] = [];
    }
}

// --- Apply Coupon Discount (Check Session) ---
if (isset($_SESSION['applied_coupon']) && $cartSubtotal > 0) {
    $couponData = $_SESSION['applied_coupon'];
    $appliedCouponCode = $couponData['code'];

    // Re-validate the coupon
    $stmt_coupon = $conn->prepare("SELECT discount_type, discount_value, min_spend FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
    if ($stmt_coupon) {
        $stmt_coupon->bind_param("s", $appliedCouponCode);
        $stmt_coupon->execute();
        $coupon_result = $stmt_coupon->get_result();
        if ($coupon_details = $coupon_result->fetch_assoc()) {
            if ($coupon_details['min_spend'] === null || $cartSubtotal >= $coupon_details['min_spend']) {
                if ($coupon_details['discount_type'] == 'percentage') {
                    $couponDiscount = ($cartSubtotal * $coupon_details['discount_value'] / 100);
                } elseif ($coupon_details['discount_type'] == 'fixed') {
                    $couponDiscount = $coupon_details['discount_value'];
                }
                $couponDiscount = min($couponDiscount, $cartSubtotal);
                $_SESSION['applied_coupon']['discount'] = $couponDiscount;
                $appliedCouponDetails = $coupon_details;
            } else {
                unset($_SESSION['applied_coupon']);
                $appliedCouponCode = null;
                $couponDiscount = 0;
                // Set message to be displayed on page load
                $_SESSION['temp_cart_message'] = ['text' => 'Coupon removed: Cart subtotal is below the minimum spend requirement.', 'type' => 'error'];
            }
        } else {
            unset($_SESSION['applied_coupon']);
            $appliedCouponCode = null;
            $couponDiscount = 0;
            // Set message to be displayed on page load
            $_SESSION['temp_cart_message'] = ['text' => 'Applied coupon is no longer valid.', 'type' => 'error'];
        }
        $stmt_coupon->close();
    } else {
        unset($_SESSION['applied_coupon']);
        $appliedCouponCode = null;
        $couponDiscount = 0;
        error_log("Failed to prepare coupon check statement: " . $conn->error);
    }
} else {
    unset($_SESSION['applied_coupon']); // Ensure cleared if cart empty or no coupon
    $couponDiscount = 0;
    $appliedCouponCode = null;
}

// --- Calculate Final Total ---
$discountAmount = round($couponDiscount, 2);
$subtotalAfterDiscount = $cartSubtotal - $discountAmount;

// Calculate Tax only if there are items and subtotal positive after discount
$taxAmount = 0.00;
if ($cartCount > 0 && $subtotalAfterDiscount > 0) {
    $taxAmount = round($subtotalAfterDiscount * TAX_RATE, 2);
}

// Determine Shipping and Platform fees (only if cart not empty)
$shippingFee = ($cartCount > 0) ? SHIPPING_FEE : 0.00;
$platformFee = ($cartCount > 0) ? PLATFORM_FEE : 0.00;

// Calculate Final Total
$finalTotal = $subtotalAfterDiscount + $taxAmount + $shippingFee + $platformFee;
$finalTotal = max(0, round($finalTotal, 2)); // Ensure non-negative and round

// Store calculated values in session for potential use in checkout
$_SESSION['cart_final_total'] = $finalTotal;
$_SESSION['cart_subtotal'] = $cartSubtotal;
$_SESSION['cart_discount_amount'] = $discountAmount;
$_SESSION['cart_tax_amount'] = $taxAmount;

// Close DB connection
if ($conn) {
    $conn->close();
}

// --- Cart Messages (Handle both session redirect messages and internal messages) ---
$cartMessage = '';
$messageType = 'info'; // Default type

// Check for messages set during coupon validation within this script load
if (isset($_SESSION['temp_cart_message'])) {
    $cartMessage = $_SESSION['temp_cart_message']['text'];
    $messageType = $_SESSION['temp_cart_message']['type'];
    unset($_SESSION['temp_cart_message']); // Clear after reading
}
// Check for messages set by apply_coupon.php before redirecting here
elseif (isset($_SESSION['cart_message'])) {
    $cartMessage = $_SESSION['cart_message'];
    // Determine type based on content (simple check)
    if (stripos($cartMessage, 'error') !== false || stripos($cartMessage, 'invalid') !== false || stripos($cartMessage, 'failed') !== false) {
        $messageType = 'error';
    } elseif (stripos($cartMessage, 'success') !== false || stripos($cartMessage, 'applied') !== false) {
        $messageType = 'success';
    } elseif (stripos($cartMessage, 'removed') !== false) {
        $messageType = 'info';
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
    <link rel="stylesheet" href="./resources/css/universal.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,600,700" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
    <style>
        /* Add style for the new fee lines if needed */
        .payment-summary-box .fee-line span:last-child,
        .payment-summary-box .tax-line span:last-child {
            color: var(--text-dark);
            /* Ensure fees have standard text color */
            font-weight: 500;
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
                    <?php if ($loggedIn): ?>
                        <li><a href="logout.php">Logout</a></li>
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
                <div id="cart-update-status" class="<?php echo $messageType; ?>" style="<?php echo empty($cartMessage) ? 'display: none;' : 'display: block;'; ?>">
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
                    <?php // Only show empty cart box if no other message is being displayed
                    if (empty($cartMessage)): ?>
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
                        <form id="remove-coupon-form" method="POST" action="apply_coupon.php" style="margin-top: 10px; <?php echo !$appliedCouponCode ? 'display: none;' : 'display: block;'; ?>">
                            <input type="hidden" name="remove_coupon" value="1">
                            <button type="submit" class="remove-coupon-btn">Remove Coupon (<?php echo htmlspecialchars($appliedCouponCode ?? ''); ?>)</button>
                        </form>
                    </div>

                    <div class="payment-summary-box">
                        <h2>Payment Summary</h2>
                        <p><span>Subtotal</span> <span id="summary-subtotal">$<?php echo number_format($cartSubtotal, 2); ?></span></p>

                        <p class="coupon-applied-line" style="<?php echo ($discountAmount <= 0) ? 'display: none;' : 'display: flex;'; ?>">
                            <span>Discount <?php echo $appliedCouponCode ? '(' . htmlspecialchars($appliedCouponCode) . ')' : ''; ?></span>
                            <span id="summary-discount-amount">-$<?php echo number_format($discountAmount, 2); ?></span>
                        </p>

                        <p class="tax-line" style="<?php echo ($taxAmount <= 0) ? 'display: none;' : 'display: flex;'; ?>">
                            <span>Tax (18%)</span>
                            <span id="summary-tax-amount">$<?php echo number_format($taxAmount, 2); ?></span>
                        </p>

                        <p class="fee-line" style="<?php echo ($shippingFee <= 0) ? 'display: none;' : 'display: flex;'; ?>">
                            <span>Shipping Fee</span>
                            <span id="summary-shipping-fee">$<?php echo number_format($shippingFee, 2); ?></span>
                        </p>

                        <p class="fee-line" style="<?php echo ($platformFee <= 0) ? 'display: none;' : 'display: flex;'; ?>">
                            <span>Platform Fee</span>
                            <span id="summary-platform-fee">$<?php echo number_format($platformFee, 2); ?></span>
                        </p>

                        <hr>
                        <p class="total-amount"><span>Total Amount</span> <span id="summary-final-total">$<?php echo number_format($finalTotal, 2); ?></span></p>

                        <?php if ($loggedIn): ?>
                            <form action="checkout.php" method="POST">
                                <button type="submit" class="proceed-button" <?php echo ($cartCount <= 0) ? 'disabled' : ''; ?>>PROCEED TO CHECKOUT</button>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusDiv = document.getElementById('cart-update-status');
            // Check if the div exists and has content rendered by PHP
            if (statusDiv && statusDiv.textContent.trim() !== '') {
                // Set a timer to hide it after 5 seconds
                setTimeout(() => {
                    statusDiv.textContent = ''; // Clear the text
                    statusDiv.style.display = 'none'; // Hide the element
                    statusDiv.className = ''; // Reset classes
                }, 5000); // 5000 milliseconds = 5 seconds
            }
        });
    </script>
</body>

</html>