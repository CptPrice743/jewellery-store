<?php
session_start();

// **IMPORTANT**: Ensure database credentials are correct and consistent across all files.
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Make sure this is your correct password
$dbname = "jewellery_store";

// --- Database Connection ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("DB Connection Error in apply_coupon: " . $conn->connect_error);
    $_SESSION['cart_message'] = 'Error: Could not connect to the database to apply coupon.';
    header("Location: cart_page.php"); // Redirect back
    exit();
}

// --- Input ---
$couponCode = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : null;
$removeCoupon = isset($_POST['remove_coupon']) ? $_POST['remove_coupon'] : null;

// --- Action: Remove Coupon ---
if ($removeCoupon) {
    if (isset($_SESSION['applied_coupon'])) {
        $removedCode = $_SESSION['applied_coupon']['code'];
        unset($_SESSION['applied_coupon']);
        $_SESSION['cart_message'] = 'Coupon "' . htmlspecialchars($removedCode) . '" removed successfully.';
    } else {
        $_SESSION['cart_message'] = 'No coupon was applied to remove.';
    }
    $conn->close();
    header("Location: cart_page.php");
    exit();
}

// --- Action: Apply Coupon ---
if (!empty($couponCode)) {
    // 1. Fetch Coupon Details from DB
    $stmt_coupon = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
    if (!$stmt_coupon) {
        error_log("Prepare failed (fetch coupon): " . $conn->error);
        $_SESSION['cart_message'] = 'Error checking coupon code.';
        $conn->close();
        header("Location: cart_page.php");
        exit();
    }

    $stmt_coupon->bind_param("s", $couponCode);
    $stmt_coupon->execute();
    $result = $stmt_coupon->get_result();
    $couponDetails = $result->fetch_assoc();
    $stmt_coupon->close();

    if (!$couponDetails) {
        $_SESSION['cart_message'] = 'Error: Invalid or expired coupon code entered.';
        $conn->close();
        header("Location: cart_page.php");
        exit();
    }

    // 2. Calculate Current Cart Subtotal (Needs access to cart data)
    $cartSubtotal = 0;
    $loggedIn = isset($_SESSION['user_id']);
    $userId = $loggedIn ? $_SESSION['user_id'] : null;
    $currentProductPrices = []; // To store fetched prices

    // Get all product IDs currently in the cart
    $allProductIdsInCart = [];
    if ($loggedIn) {
        $sql_ids = "SELECT product_id FROM user_carts WHERE user_id = ?";
        $stmt_ids = $conn->prepare($sql_ids);
        if($stmt_ids) {
            $stmt_ids->bind_param("i", $userId);
            $stmt_ids->execute();
            $result_ids = $stmt_ids->get_result();
            while ($row = $result_ids->fetch_assoc()) $allProductIdsInCart[] = $row['product_id'];
            $stmt_ids->close();
        }
    } else {
        $allProductIdsInCart = isset($_SESSION['cart']) ? array_keys($_SESSION['cart']) : [];
    }

     // Fetch prices for these IDs
    if (!empty($allProductIdsInCart)) {
        $ids_string = implode(',', array_map('intval', $allProductIdsInCart));
        $sql_prices = "SELECT product_id, price FROM products WHERE product_id IN ($ids_string)";
        $result_prices = $conn->query($sql_prices);
        if($result_prices) {
            while ($row = $result_prices->fetch_assoc()) {
                $currentProductPrices[$row['product_id']] = $row['price'];
            }
        } else {
             error_log("Failed to fetch current product prices for coupon check: " . $conn->error);
        }
    }

    // Calculate subtotal
    if ($loggedIn) {
        $sql_calc = "SELECT product_id, quantity FROM user_carts WHERE user_id = ?";
        $stmt_calc = $conn->prepare($sql_calc);
        if ($stmt_calc) {
            $stmt_calc->bind_param("i", $userId);
            $stmt_calc->execute();
            $result_calc = $stmt_calc->get_result();
            while ($row = $result_calc->fetch_assoc()) {
                $pid = $row['product_id'];
                if (isset($currentProductPrices[$pid])) {
                    $cartSubtotal += $currentProductPrices[$pid] * $row['quantity'];
                }
            }
            $stmt_calc->close();
        }
    } else {
        if (isset($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $pid => $item) {
                 if (isset($currentProductPrices[$pid])) {
                    $cartSubtotal += $currentProductPrices[$pid] * $item['quantity'];
                 }
            }
        }
    }

     // Check minimum spend
     if ($couponDetails['min_spend'] !== null && $cartSubtotal < $couponDetails['min_spend']) {
         $_SESSION['cart_message'] = 'Error: Cart subtotal ($' . number_format($cartSubtotal, 2) . ') does not meet the minimum spend requirement ($' . number_format($couponDetails['min_spend'], 2) . ') for coupon "' . htmlspecialchars($couponCode) . '".';
         $conn->close();
         header("Location: cart_page.php");
         exit();
     }

    // 3. Calculate Discount and Store in Session
    $couponDiscount = 0.00;
    if ($couponDetails['discount_type'] == 'percentage') {
        $couponDiscount = ($cartSubtotal * $couponDetails['discount_value'] / 100);
    } elseif ($couponDetails['discount_type'] == 'fixed') {
        $couponDiscount = $couponDetails['discount_value'];
    }
    $couponDiscount = min($couponDiscount, $cartSubtotal); // Cap discount

    $_SESSION['applied_coupon'] = [
        'code' => $couponDetails['code'],
        'discount' => round($couponDiscount, 2), // Store calculated discount amount
        'type' => $couponDetails['discount_type'],
        'value' => $couponDetails['discount_value']
        // Add other details if needed (e.g., min_spend)
    ];

    $_SESSION['cart_message'] = 'Success: Coupon "' . htmlspecialchars($couponDetails['code']) . '" applied successfully.';

} else {
    // No coupon code entered and not removing
    $_SESSION['cart_message'] = 'Please enter a coupon code to apply.';
}

$conn->close();
header("Location: cart_page.php"); // Redirect back to cart page
exit();
?>