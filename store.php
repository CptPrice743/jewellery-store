<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to login page
    header("Location: login.php");
    exit(); // Stop further script execution
}


// --- Pagination Settings ---
$items_per_page = 12; // Show 12 products per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// Database connection (reuse your connection logic from login.php)
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Use your actual password
$dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Count Total Products for Pagination ---
$total_items_sql = "SELECT COUNT(*) as total FROM products";
$total_result = $conn->query($total_items_sql);
$total_items = $total_result ? $total_result->fetch_assoc()['total'] : 0; // Handle query failure
$total_pages = ceil($total_items / $items_per_page);

// --- Calculate Offset for SQL Query ---
$offset = ($current_page - 1) * $items_per_page;

// --- Fetch products for the current page ---
// Use prepared statement for security
$sql = "SELECT product_id, name, description, price, image_url FROM products LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$products_result = null; // Initialize result variable
if ($stmt) { // Check if prepare was successful
    // Bind parameters: 'i' for integer
    $stmt->bind_param("ii", $items_per_page, $offset);
    $stmt->execute();
    $products_result = $stmt->get_result(); // Get result set from prepared statement
} else {
    error_log("Failed to prepare statement in store.php: " . $conn->error);
}


// --- Get Cart Count for Header (Uses SESSION cart primarily now) ---
$cartCount = 0;
if (isset($_SESSION['cart'])) { // Check session cart first
    foreach ($_SESSION['cart'] as $item) {
        if (isset($item['quantity'])) {
            $cartCount += $item['quantity'];
        }
    }
}
// Note: DB count logic removed here as session should be the source of truth after login.
// Ensure login.php correctly populates $_SESSION['cart'].
// --- End Cart Count ---


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prism Jewellery Store</title>
    <link rel="stylesheet" href="./resources/css/reset.css">
    <link rel="stylesheet" href="./resources/css/style.css">
    <link rel="stylesheet" href="./resources/css/store.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php" class="button">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="button">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="store-container">
        <div class="store-header">
            <h1>Our Collection</h1>
            <input type="text" id="search-input" class="search-bar" placeholder="Search for products...">
        </div>
        <div id="cart-status"></div>
        <div class="product-grid" id="product-grid">
            <?php
            // Use $products_result which was fetched earlier
            if ($products_result && $products_result->num_rows > 0) {
                while ($row = $products_result->fetch_assoc()) {
                    $productId = $row['product_id']; // Get product ID

                    echo "<div class='product-card'>";
                    echo "<img src='" . htmlspecialchars($row["image_url"] ?: './resources/images/placeholder.jpg') . "' alt='" . htmlspecialchars($row["name"]) . "' loading='lazy'>"; // Added placeholder fallback
                    echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                    echo "<p class='price'>$" . number_format($row["price"], 2) . "</p>"; // Format price

                    // --- Cart Interaction Area (Conditional Rendering) ---
                    echo "<div class='cart-interaction' data-product-id='" . $productId . "'>";

                    // Check if item is in session cart
                    if (isset($_SESSION['cart'][$productId]) && isset($_SESSION['cart'][$productId]['quantity'])) {
                        $quantityInCart = $_SESSION['cart'][$productId]['quantity'];
                        // Render the quantity selector HTML
                        echo "<div class='quantity-selector'>";
                        echo "<button class='minus-btn' data-product-id='" . $productId . "' aria-label='Decrease quantity'>-</button>";
                        echo "<span class='qty-display'>" . $quantityInCart . "</span>";
                        echo "<button class='plus-btn' data-product-id='" . $productId . "' aria-label='Increase quantity'>+</button>";
                        echo "</div>";
                    } else {
                        // Render the "Add to Cart" button HTML
                        echo "<button class='add-to-cart-btn'>Add to Cart</button>";
                    }

                    echo "</div>"; // End cart-interaction div
                    echo "</div>"; // End product-card div
                }
            } else {
                // Check if it's page 1 with no results or a later page
                if ($current_page == 1) {
                    echo "<p>No products found matching your criteria.</p>"; // More specific message if search is active
                } else {
                    echo "<p>No more products found.</p>";
                }
            }
            if ($stmt) $stmt->close(); // Close the prepared statement if it was created
            ?>
        </div>
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($current_page > 1): ?>
                    <a href="store.php?page=<?php echo $current_page - 1; ?>" class="prev-next-link"><span>&laquo; Previous</span></a>
                <?php else: ?>
                    <span class="disabled prev-next-disabled"><span>&laquo; Previous</span></span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php // Simple pagination: Show only current, first, last, and nearby pages
                    $showPage = false;
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                        $showPage = true;
                    } elseif (($i == $current_page - 3 && $i > 1) || ($i == $current_page + 3 && $i < $total_pages)) {
                        // Show ellipsis if there's a gap
                        echo "<span style='border: none; background: none; padding: 0.6rem 0.2rem;'>...</span>";
                    }
                    ?>
                    <?php if ($showPage): ?>
                        <?php if ($i == $current_page): ?>
                            <strong><?php echo $i; ?></strong>
                        <?php else: ?>
                            <a href="store.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="store.php?page=<?php echo $current_page + 1; ?>" class="prev-next-link"><span>Next &raquo;</span></a>
                <?php else: ?>
                    <span class="disabled prev-next-disabled"><span>Next &raquo;</span></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
    <footer>
        <div class="content">
            <span class="copyright">© 2024 Prism Jewellery, All Rights Reserved</span>
            <span class="location">Designed by Vyom Uchat (22BCP450)</span>
        </div>
    </footer>
    <script src="resources/js/cart.js"></script>
</body>

</html>
<?php
$conn->close(); // Close the database connection
?>