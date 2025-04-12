<?php
session_start(); // ADD THIS LINE

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


// --- Get Cart Count for Header ---
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    // Logged in: Get count from database
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM user_carts WHERE user_id = ?");
    if ($count_stmt) {
        $count_stmt->bind_param("i", $_SESSION['user_id']);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $cartCount = $count_result['total'] ?? 0;
        $count_stmt->close();
    }
} else {
    // Not logged in: Get count from session
    $cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
}
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <style>
        /* Add specific styles for the store page */
        .store-container {
            padding: 2rem 5%;
            /* Ensure padding-top accounts for fixed header */
            padding-top: 7rem;
            /* Adjust based on actual header height */
        }

        /* --- Store Header Styling --- */
        .store-header {
            display: flex;
            /* Enable Flexbox */
            justify-content: space-between;
            /* Push items to opposite ends */
            align-items: center;
            /* Vertically align items in the middle */
            margin-bottom: 2.5rem;
            /* Space below the header section */
            padding-bottom: 1rem;
            /* Optional: add padding below */
            border-bottom: 1px solid #eee;
            /* Optional: subtle line below header */
            flex-wrap: wrap;
            /* Allow wrapping on smaller screens */
            gap: 1rem;
            /* Add gap between items when wrapping */
        }

        /* Style for the "Our Collection" title */
        .store-header h1 {
            font-family: "Playfair Display", serif;
            /* Use the site's display font */
            font-size: 2.5rem;
            /* Make the title significantly larger */
            color: var(--text-dark);
            /* Use the standard dark text color */
            margin: 0;
            /* Remove default margin */
            font-weight: 600;
            /* Adjust weight as needed */
            flex-grow: 1;
            /* Allow title to take available space */
        }

        /* Style for the search bar within the store header */
        .store-header .search-bar {
            /* Be specific */
            width: auto;
            /* Adjust width automatically */
            min-width: 250px;
            /* Minimum width */
            max-width: 350px;
            /* Max width */
            padding: 0.8rem 1rem;
            /* Comfortable padding */
            border: 1px solid #ccc;
            /* Subtle border */
            border-radius: 4px;
            /* Slightly rounded corners */
            font-family: "Roboto", sans-serif;
            /* Use a readable sans-serif font */
            font-size: 0.9rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            /* Smooth transition */
            margin: 0;
            /* Remove default margin */
            flex-basis: 300px;
            /* Base width before growing/shrinking */
            flex-grow: 1;
            /* Allow search bar to grow */
        }

        /* Optional: Add a focus style to the search bar */
        .store-header .search-bar:focus {
            outline: none;
            /* Remove default browser outline */
            border-color: var(--text-dark);
            /* Darken border on focus */
            box-shadow: 0 0 0 2px rgba(44, 39, 36, 0.1);
            /* Subtle glow */
        }

        /* --- End Store Header Styling --- */


        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
            /* Add space before pagination */
        }

        .product-card {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            text-align: left;
            background-color: var(--white);
            position: relative;
            overflow: hidden;
            display: flex;
            /* Use flexbox for vertical alignment */
            flex-direction: column;
            /* Stack items vertically */
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            background-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .product-card img {
            display: block;
            width: 100%;
            height: 250px;
            object-fit: cover;
            margin-bottom: 1rem;
            background-color: #eee;
        }

        .product-card h3 {
            font-size: 1.0rem;
            font-weight: normal;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            line-height: 1.4;
            flex-grow: 1;
            /* Allow name to take up space */
        }

        .product-card p.price {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        /* Container for button OR quantity selector */
        .cart-interaction {
            margin-top: auto;
            /* Pushes button/selector to the bottom */
            text-align: center;
            /* Center button/selector */
        }


        #cart-status {
            margin-top: 1rem;
            color: green;
            text-align: center;
            min-height: 1.2em;
            /* Prevent layout shift when message appears/disappears */
        }

        /* --- Pagination Styles --- */
        .pagination {
            text-align: center;
            padding: 1rem 0;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination strong,
        .pagination span {
            display: inline-block;
            padding: 0.6rem 1rem;
            margin: 0 0.25rem;
            border: 1px solid #ddd;
            text-decoration: none;
            color: var(--text-dark);
            background-color: var(--white);
            border-radius: 4px;
            font-family: "Roboto", sans-serif;
            font-size: 0.9rem;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
            vertical-align: middle;
        }

        .pagination a:hover {
            background-color: var(--primary-color);
            color: var(--text-dark);
            border-color: #ccc;
        }

        .pagination strong {
            background-color: var(--text-dark);
            color: var(--primary-color);
            border-color: var(--text-dark);
            font-weight: bold;
            cursor: default;
        }

        .pagination a.prev-next-link {
            border: none;
            background-color: transparent;
            padding: 0.6rem 0.5rem;
            color: var(--text-dark);
            transition: color 0.3s ease;
        }

        .pagination a.prev-next-link:hover {
            color: var(--primary-color);
            background-color: transparent;
            border-color: transparent;
        }

        .pagination span.prev-next-disabled {
            border: none;
            background-color: transparent;
            padding: 0.6rem 0.5rem;
            cursor: default;
        }

        .pagination span.prev-next-disabled span {
            color: #aaa;
            padding: 0;
            margin: 0;
            border: none;
            background-color: transparent;
        }

        /* --- CSS for Quantity Selector (from style.css) --- */
        .quantity-selector {
            display: inline-flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-top: 0.5rem;
        }

        .quantity-selector button {
            background-color: #f8f8f8;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            line-height: 1;
            padding: 0.5rem 0.9rem;
            font-weight: bold;
            color: #555;
        }

        .quantity-selector button:hover {
            background-color: #eee;
        }

        .quantity-selector .qty-display {
            padding: 0.5rem 1rem;
            font-size: 1rem;
            min-width: 25px;
            text-align: center;
            font-weight: bold;
            background-color: var(--text-dark);
            color: var(--primary-color);
        }

        .quantity-selector .minus-btn {
            border-radius: 4px 0 0 4px;
        }

        .quantity-selector .plus-btn {
            border-radius: 0 4px 4px 0;
        }

        .add-to-cart-btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background-color: var(--text-dark);
            color: var(--primary-color);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.3s ease;
            width: auto;
            margin-top: 0.5rem;
        }

        .add-to-cart-btn:hover {
            background-color: #555;
        }

        .cart-interaction.processing * {
            opacity: 0.5;
            pointer-events: none;
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
                    echo "<div class='product-card'>";
                    echo "<img src='" . htmlspecialchars($row["image_url"] ?: './resources/images/placeholder.jpg') . "' alt='" . htmlspecialchars($row["name"]) . "' loading='lazy'>"; // Added placeholder fallback
                    echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                    echo "<p class='price'>$" . number_format($row["price"], 2) . "</p>"; // Format price

                    // --- Cart Interaction Area ---
                    echo "<div class='cart-interaction' data-product-id='" . $row["product_id"] . "'>";
                    // Check if item is in cart (requires cart data to be available here)
                    // This logic is complex here, better handled by cart.js fetching initial state
                    // For now, always show "Add to Cart" and let cart.js handle updates
                    echo "<button class='add-to-cart-btn'>Add to Cart</button>";
                    // Or dynamically load quantity selector if needed (requires more setup)
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
                    <?php if ($i == $current_page): ?>
                        <strong><?php echo $i; ?></strong>
                    <?php else: ?>
                        <a href="store.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
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
    <script>
        // Optional: Script to update header cart count dynamically after AJAX updates
        // You might need to modify cart.js to trigger a custom event or use a MutationObserver
        // Example using custom event (modify cart.js to dispatch this event):
        // document.addEventListener('cartUpdated', function(e) {
        //    const newCount = e.detail.cart_count;
        //    const cartCountSpan = document.getElementById('cart-count');
        //    const cartCountSpanMobile = document.getElementById('cart-count-mobile');
        //    if (cartCountSpan) cartCountSpan.textContent = newCount;
        //    if (cartCountSpanMobile) cartCountSpanMobile.textContent = newCount;
        // });
    </script>
</body>

</html>
<?php
$conn->close(); // Close the database connection
?>