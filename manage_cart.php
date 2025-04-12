<?php
// START OF SCRIPT - Suppress direct error output, use logging instead for debugging
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// **IMPORTANT**: Ensure database credentials are correct and consistent across all files.
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Make sure this is your correct password
$dbname = "jewellery_store";

// --- Initialize Response ---
// Default values assume failure until proven otherwise
$response = [
    'success' => false,
    'message' => 'An unknown error occurred while processing the cart.',
    'cart_count' => 0,
    'cart_subtotal' => 0.00,
    'cart_discount' => 0.00,
    'cart_tax' => 0.00,
    'cart_total' => 0.00,
    'item_quantity' => 0,
    'item_subtotal' => 0.00,
    'coupon_removed' => false,
    'applied_coupon_code' => null
];

// --- Helper Function: Send JSON Response and Exit ---
// This function ensures the correct header is set BEFORE any output
function sendJsonResponseAndExit($data)
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit();
}

// --- Setup Global Exception Handler (Optional but recommended) ---
// This catches fatal errors that might bypass standard try/catch
set_exception_handler(function ($exception) use ($response) {
    error_log("Uncaught Exception in manage_cart.php: " . $exception->getMessage());
    $response['message'] = "A server error occurred. Please try again."; // User-friendly message
    // Don't overwrite other fields if they were potentially calculated before the error
    sendJsonResponseAndExit($response);
});


// --- Input Validation ---
$productId = isset($_POST['product_id']) ? filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) : null;
$quantity = isset($_POST['quantity']) ? filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) : null; // Allow 0 for removal
$action = isset($_POST['action']) ? filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) : null;

if ($productId === false || $productId === null || $action === null || !in_array($action, ['add', 'update', 'remove'])) {
    $response['message'] = 'Invalid product ID or action specified.';
    sendJsonResponseAndExit($response);
}

if (($action === 'add' || $action === 'update') && ($quantity === null || $quantity === false || $quantity < 0)) {
    $response['message'] = 'Invalid quantity specified for add/update action.';
    sendJsonResponseAndExit($response);
}

if ($action === 'remove') {
    $quantity = 0;
}

// --- Determine User Status ---
$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;

// Initialize session cart for guest if it doesn't exist
if (!$loggedIn && !isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- Database Connection ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("DB Connection Error in manage_cart: " . $conn->connect_error);
    $response['message'] = 'Database connection error. Cannot update cart.';
    sendJsonResponseAndExit($response);
}
// Set charset AFTER connection
$conn->set_charset("utf8mb4");


// --- Perform Cart Operation ---
$operationSuccess = false;
$dbError = null;

// Wrap main logic in try block to catch DB or other exceptions
try {
    if ($loggedIn) {
        // --- Logged In User: Database Operations ---
        if ($quantity > 0) {
            // Insert or Update item in database
            $stmt = $conn->prepare("INSERT INTO user_carts (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
            if (!$stmt) throw new Exception("DB prepare error (insert/update): " . $conn->error);
            $stmt->bind_param("iiii", $userId, $productId, $quantity, $quantity);
            $operationSuccess = $stmt->execute();
            if (!$operationSuccess) $dbError = $stmt->error;
            $stmt->close();

            // ALSO update the session cart to match
            if ($operationSuccess && isset($_SESSION['cart'])) { // Check if session cart exists
                $_SESSION['cart'][$productId] = ['quantity' => $quantity];
            } elseif ($operationSuccess) { // Initialize session cart if needed
                $_SESSION['cart'] = [$productId => ['quantity' => $quantity]];
            }
        } else {
            // Remove item from database
            $stmt = $conn->prepare("DELETE FROM user_carts WHERE user_id = ? AND product_id = ?");
            if (!$stmt) throw new Exception("DB prepare error (delete): " . $conn->error);
            $stmt->bind_param("ii", $userId, $productId);
            $operationSuccess = $stmt->execute(); // Store execute result
            if (!$operationSuccess) $dbError = $stmt->error; // Store error if execution failed
            $stmt->close();

            // *** FIX: ALSO remove item from the session cart if delete was successful ***
            if ($operationSuccess && isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
            }
        }
    } else {
        // --- Guest User: Session Operations ---
        if ($quantity > 0) {
            $_SESSION['cart'][$productId] = ['quantity' => $quantity];
            $operationSuccess = true;
        } else {
            // Check if key exists before unsetting to avoid potential notices
            if (isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
            }
            $operationSuccess = true;
        }
    }

    if (!$operationSuccess) {
        throw new Exception('Failed to update cart item.' . ($dbError ? ' DB Error: ' . $dbError : ''));
    }

    // --- Recalculate Entire Cart State After Update ---
    $cartSubtotal = 0;
    $cartCount = 0;
    $itemSubtotal = 0;
    $itemPrice = 0; // Price of the specific item updated
    $currentProductPrices = [];
    $allProductIdsInCart = [];

    // Get current list of product IDs in cart (use SESSION cart as the source of truth after update)
    if (isset($_SESSION['cart'])) {
        $allProductIdsInCart = array_keys($_SESSION['cart']);
    }

    // Fetch current prices for items in cart
    if (!empty($allProductIdsInCart)) {
        // Ensure IDs are integers
        $sanitized_ids = array_map('intval', $allProductIdsInCart);
        if (!empty($sanitized_ids)) { // Proceed only if there are valid IDs
            $ids_string = implode(',', $sanitized_ids);
            $sql_prices = "SELECT product_id, price FROM products WHERE product_id IN ($ids_string)";
            $result_prices = $conn->query($sql_prices);
            if ($result_prices) {
                while ($row = $result_prices->fetch_assoc()) {
                    $currentProductPrices[$row['product_id']] = $row['price'];
                }
            } else {
                error_log("Failed to fetch current product prices: " . $conn->error);
                // Decide how to handle - maybe throw exception? For now, items might lack price.
            }
        }
    }

    // Recalculate totals based on SESSION cart using fetched prices
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $pid => $item) {
            $qty = $item['quantity'];
            // Use isset() to safely access price
            if (isset($currentProductPrices[$pid])) {
                $price = $currentProductPrices[$pid];
                $current_item_subtotal = $price * $qty;
                $cartSubtotal += $current_item_subtotal;
                $cartCount += $qty;
                if ($pid == $productId) {
                    $itemSubtotal = $current_item_subtotal;
                    $itemPrice = $price;
                }
            } else {
                error_log("Price not found for product ID (Session Cart recalc): " . $pid . " - Removing from session.");
                // Remove item from session cart if its price couldn't be fetched
                unset($_SESSION['cart'][$pid]);
            }
        }
    }


    // --- Recalculate Discount (Check coupon validity) ---
    $couponDiscount = 0.00;
    $couponWasApplied = isset($_SESSION['applied_coupon']);
    $appliedCouponCode = null; // Reset and set only if valid
    $couponRemovedFlag = false; // Local flag for this request

    if ($couponWasApplied) {
        $couponData = $_SESSION['applied_coupon'];
        $codeToCheck = $couponData['code'];

        if ($cartSubtotal > 0) {
            $stmt_coupon = $conn->prepare("SELECT discount_type, discount_value, min_spend FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
            if ($stmt_coupon) {
                $stmt_coupon->bind_param("s", $codeToCheck);
                $stmt_coupon->execute();
                $coupon_result = $stmt_coupon->get_result();
                if ($coupon_details = $coupon_result->fetch_assoc()) {
                    if ($coupon_details['min_spend'] === null || $cartSubtotal >= $coupon_details['min_spend']) {
                        // Coupon still valid
                        if ($coupon_details['discount_type'] == 'percentage') {
                            $couponDiscount = ($cartSubtotal * $coupon_details['discount_value'] / 100);
                        } elseif ($coupon_details['discount_type'] == 'fixed') {
                            $couponDiscount = $coupon_details['discount_value'];
                        }
                        $couponDiscount = min($couponDiscount, $cartSubtotal);
                        $_SESSION['applied_coupon']['discount'] = $couponDiscount; // Update session
                        $appliedCouponCode = $codeToCheck; // Keep track of valid code
                    } else {
                        // Subtotal dropped below min_spend
                        unset($_SESSION['applied_coupon']);
                        $couponRemovedFlag = true;
                    }
                } else {
                    // Coupon became invalid
                    unset($_SESSION['applied_coupon']);
                    $couponRemovedFlag = true;
                }
                $stmt_coupon->close();
            } else {
                error_log("Failed to prepare coupon re-check statement: " . $conn->error);
                unset($_SESSION['applied_coupon']); // Assume invalid on error
                $couponRemovedFlag = true;
            }
        } else {
            // Cart became empty
            unset($_SESSION['applied_coupon']);
            $couponRemovedFlag = true;
        }
    }

    // --- Final Calculations ---
    $discountAmount = $couponDiscount;
    $taxAmount = 0.00; // Add tax calculation if needed
    $finalTotal = $cartSubtotal - $discountAmount + $taxAmount;
    $finalTotal = max(0, $finalTotal); // Ensure non-negative

    // Update session totals (Optional, but can be useful)
    $_SESSION['cart_final_total'] = $finalTotal;
    $_SESSION['cart_discount_amount'] = $discountAmount;
    $_SESSION['cart_subtotal'] = $cartSubtotal;
    $_SESSION['cart_tax_amount'] = $taxAmount;

    // --- Populate Response Object with final calculated values ---
    $response['success'] = true;
    $response['message'] = $action == 'remove' || $quantity == 0 ? 'Item removed successfully.' : 'Cart updated successfully.';
    if ($couponRemovedFlag) {
        $response['message'] .= ' Applied coupon was removed as it is no longer valid for the updated cart.';
    }
    $response['cart_count'] = $cartCount;
    $response['cart_subtotal'] = round($cartSubtotal, 2);
    $response['cart_discount'] = round($discountAmount, 2);
    $response['cart_tax'] = round($taxAmount, 2);
    $response['cart_total'] = round($finalTotal, 2);
    // Return the quantity that was SET (0 for removal)
    $response['item_quantity'] = $quantity;
    // Recalculate item subtotal based on the SET quantity
    $response['item_subtotal'] = isset($itemPrice) ? round($itemPrice * $quantity, 2) : 0.00;
    $response['coupon_removed'] = $couponRemovedFlag; // Set the flag in response
    $response['applied_coupon_code'] = $appliedCouponCode; // Send current valid code or null


} catch (Exception $e) {
    // Catch exceptions from DB operations or other logic
    error_log("Error in manage_cart main try block: " . $e->getMessage());
    // Keep response['success'] = false;
    $response['message'] = "An internal error occurred while updating the cart."; // More generic message
    // Optionally, try to recalculate totals even on error if possible,
    // but it might be safer to return defaults or last known state if complex.
    // For now, we send the default initialized response with the error message.
} finally {
    // Ensure connection is closed
    if ($conn) {
        $conn->close();
    }
}

// --- Final Response ---
sendJsonResponseAndExit($response); // Use the helper function
