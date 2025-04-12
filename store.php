<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Use your actual password
$dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- Filter & Sort Parameters ---
$selected_category_id = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0; // 0 means 'All'
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$in_stock_only = isset($_GET['in_stock']) && $_GET['in_stock'] == '1';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$search_term = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

// --- Pagination Settings ---
$items_per_page = 12;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// --- Build SQL Query ---
$sql_base = "SELECT p.product_id, p.name, p.description, p.price, p.image_url, p.stock, p.category_id FROM products p";
$sql_where = [];
$sql_params = [];
$sql_param_types = "";

// Add search term condition
if (!empty($search_term)) {
    $sql_where[] = "p.name LIKE ?";
    $sql_params[] = "%" . $search_term . "%";
    $sql_param_types .= "s";
}

// Add category filter condition (if not 'All')
if ($selected_category_id > 0) {
    $sql_where[] = "p.category_id = ?";
    $sql_params[] = $selected_category_id;
    $sql_param_types .= "i";
}

// Add price range conditions
if ($min_price !== null) {
    $sql_where[] = "p.price >= ?";
    $sql_params[] = $min_price;
    $sql_param_types .= "d";
}
if ($max_price !== null) {
    $sql_where[] = "p.price <= ?";
    $sql_params[] = $max_price;
    $sql_param_types .= "d";
}

// Add stock condition
if ($in_stock_only) {
    $sql_where[] = "p.stock > 0";
}


// --- Count Total Products for Pagination (with filters) ---
$sql_count = "SELECT COUNT(*) as total FROM products p";
if (!empty($sql_where)) {
    $sql_count .= " WHERE " . implode(" AND ", $sql_where);
}

$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    if (!empty($sql_params)) {
        $stmt_count->bind_param($sql_param_types, ...$sql_params);
    }
    $stmt_count->execute();
    $total_result = $stmt_count->get_result();
    $total_items = $total_result ? $total_result->fetch_assoc()['total'] : 0;
    $stmt_count->close();
} else {
    error_log("Failed to prepare count statement: " . $conn->error);
    $total_items = 0;
}
$total_pages = $total_items > 0 ? ceil($total_items / $items_per_page) : 0;
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// --- Calculate Offset ---
$offset = ($current_page - 1) * $items_per_page;

// --- Add Sorting to Query ---
$sql_order_by = " ORDER BY ";
switch ($sort_order) {
    case 'price_asc':
        $sql_order_by .= "p.price ASC";
        break;
    case 'price_desc':
        $sql_order_by .= "p.price DESC";
        break;
    case 'name_asc':
        $sql_order_by .= "p.name ASC";
        break;
    case 'name_desc':
        $sql_order_by .= "p.name DESC";
        break;
    default:
        $sql_order_by .= "p.product_id ASC";
        break;
}

// --- Build Final Product Fetch Query ---
$sql_fetch = $sql_base;
if (!empty($sql_where)) {
    $sql_fetch .= " WHERE " . implode(" AND ", $sql_where);
}
$sql_fetch .= $sql_order_by;
$sql_fetch .= " LIMIT ? OFFSET ?";

$current_sql_param_types = $sql_param_types;
$current_sql_params = $sql_params;
$current_sql_param_types .= "ii";
$current_sql_params[] = $items_per_page;
$current_sql_params[] = $offset;

// --- Fetch products for the current page ---
$stmt_fetch = $conn->prepare($sql_fetch);
$products_result = null;
if ($stmt_fetch) {
    if (!empty($current_sql_params)) {
        $stmt_fetch->bind_param($current_sql_param_types, ...$current_sql_params);
    }
    $stmt_fetch->execute();
    $products_result = $stmt_fetch->get_result();
} else {
    error_log("Failed to prepare fetch statement ('$sql_fetch'): " . $conn->error);
    die("Error fetching products. Please check server logs.");
}

// --- CORRECTED: Fetch Categories for Filter Dropdown ---
$categories = [];
// Use the correct table 'categories' and column 'name'
$cat_sql = "SELECT category_id, name FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_sql); // This line should now work
if ($cat_result && $cat_result->num_rows > 0) {
    while ($row = $cat_result->fetch_assoc()) {
        // Use the correct column name 'name'
        $categories[$row['category_id']] = $row['name'];
    }
} else {
    // Log error if categories table is empty or query failed
    error_log("Could not fetch categories from the 'categories' table or table is empty. SQL: " . $cat_sql . " Error: " . $conn->error);
}


// --- Get Cart Count for Header ---
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if (isset($item['quantity'])) {
            $cartCount += $item['quantity'];
        }
    }
}

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
                    <?php if (isset($_SESSION['user_id'])) : ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else : ?>
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
            <form method="GET" action="store.php" class="search-form">
                <input type="text" name="search" id="search-input" class="search-bar" placeholder="Search products..." value="<?php echo htmlspecialchars($search_term); ?>">
                <input type="hidden" name="category" value="<?php echo $selected_category_id; ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">
                <input type="hidden" name="min_price" value="<?php echo htmlspecialchars($min_price ?? ''); ?>">
                <input type="hidden" name="max_price" value="<?php echo htmlspecialchars($max_price ?? ''); ?>">
                <input type="hidden" name="in_stock" value="<?php echo $in_stock_only ? '1' : '0'; ?>">
                <button type="submit" class="search-button">Search</button>
            </form>
        </div>

        <div class="filter-sort-bar">
            <form method="GET" action="store.php" id="filter-sort-form">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">

                <div class="filter-group">
                    <label for="category-select">Category:</label>
                    <select name="category" id="category-select">
                        <option value="0" <?php echo ($selected_category_id == 0) ? 'selected' : ''; ?>>All Categories</option>
                        <?php foreach ($categories as $cat_id => $cat_name) : // Loop through fetched ID => Name array 
                        ?>
                            <option value="<?php echo $cat_id; ?>" <?php echo ($selected_category_id == $cat_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($cat_name)); // Display the actual name 
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group price-filter">
                    <label>Price:</label>
                    <input type="number" name="min_price" placeholder="Min $" step="0.01" min="0" value="<?php echo htmlspecialchars($min_price ?? ''); ?>" class="price-input">
                    <span>-</span>
                    <input type="number" name="max_price" placeholder="Max $" step="0.01" min="0" value="<?php echo htmlspecialchars($max_price ?? ''); ?>" class="price-input">
                </div>

                <div class="filter-group stock-filter">
                    <input type="checkbox" name="in_stock" id="in_stock" value="1" <?php echo $in_stock_only ? 'checked' : ''; ?>>
                    <label for="in_stock">In Stock Only</label>
                </div>

                <div class="filter-group">
                    <label for="sort-select">Sort by:</label>
                    <select name="sort" id="sort-select">
                        <option value="default" <?php echo ($sort_order == 'default') ? 'selected' : ''; ?>>Default</option>
                        <option value="price_asc" <?php echo ($sort_order == 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo ($sort_order == 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name_asc" <?php echo ($sort_order == 'name_asc') ? 'selected' : ''; ?>>Name: A to Z</option>
                        <option value="name_desc" <?php echo ($sort_order == 'name_desc') ? 'selected' : ''; ?>>Name: Z to A</option>
                    </select>
                </div>

                <button type="submit" class="apply-filters-btn">Apply Filters</button>
            </form>
        </div>


        <div id="cart-status"></div>
        <div class="product-grid" id="product-grid">
            <?php
            if ($products_result && $products_result->num_rows > 0) {
                while ($row = $products_result->fetch_assoc()) {
                    $productId = $row['product_id'];
                    echo "<div class='product-card'>";
                    // Display "Out of Stock" overlay if stock is 0 or less
                    if ($row['stock'] <= 0) {
                        echo "<div class='stock-overlay'>Out of Stock</div>";
                    }
                    echo "<img src='" . htmlspecialchars($row["image_url"] ?: './resources/images/placeholder.jpg') . "' alt='" . htmlspecialchars($row["name"]) . "' loading='lazy'>";
                    echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                    echo "<p class='price'>$" . number_format($row["price"], 2) . "</p>";

                    // Cart Interaction Area (Checks SESSION cart)
                    echo "<div class='cart-interaction' data-product-id='" . $productId . "'>";
                    // Only show cart buttons if in stock
                    if ($row['stock'] > 0) {
                        if (isset($_SESSION['cart'][$productId]) && isset($_SESSION['cart'][$productId]['quantity'])) {
                            $quantityInCart = $_SESSION['cart'][$productId]['quantity'];
                            echo "<div class='quantity-selector'>";
                            echo "<button class='minus-btn' data-product-id='" . $productId . "' aria-label='Decrease quantity'>-</button>";
                            echo "<span class='qty-display'>" . $quantityInCart . "</span>";
                            echo "<button class='plus-btn' data-product-id='" . $productId . "' aria-label='Increase quantity'>+</button>";
                            echo "</div>";
                        } else {
                            echo "<button class='add-to-cart-btn'>Add to Cart</button>";
                        }
                    } else {
                        echo "<button class='add-to-cart-btn' disabled>Out of Stock</button>"; // Disabled button
                    }
                    echo "</div>"; // End cart-interaction
                    echo "</div>"; // End product-card
                }
            } else {
                echo "<p>No products found matching your criteria.</p>";
            }
            if ($stmt_fetch) $stmt_fetch->close();
            ?>
        </div>

        <div class="pagination">
            <?php if ($total_pages > 1) : ?>
                <?php
                // Build base URL for pagination links, preserving ALL filters/sort/search
                $pagination_params = [];
                if (!empty($search_term)) $pagination_params['search'] = $search_term;
                if ($selected_category_id > 0) $pagination_params['category'] = $selected_category_id;
                if ($min_price !== null) $pagination_params['min_price'] = $min_price;
                if ($max_price !== null) $pagination_params['max_price'] = $max_price;
                if ($in_stock_only) $pagination_params['in_stock'] = '1';
                if ($sort_order !== 'default') $pagination_params['sort'] = $sort_order;

                $base_query_string = http_build_query($pagination_params);
                $base_pagination_url = "store.php?" . $base_query_string . (empty($base_query_string) ? '' : '&');
                ?>
                <?php if ($current_page > 1) : ?>
                    <a href="<?php echo $base_pagination_url; ?>page=<?php echo $current_page - 1; ?>" class="prev-next-link"><span>&laquo; Previous</span></a>
                <?php else : ?>
                    <span class="disabled prev-next-disabled"><span>&laquo; Previous</span></span>
                <?php endif; ?>

                <?php // Simple pagination: Show only current, first, last, and nearby pages
                $ellipsis_threshold = 2;
                $show_first = false;
                $show_last = false;
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $ellipsis_threshold && $i <= $current_page + $ellipsis_threshold)) {
                        if ($i == $current_page) {
                            echo '<strong>' . $i . '</strong>';
                        } else {
                            echo '<a href="' . $base_pagination_url . 'page=' . $i . '">' . $i . '</a>';
                        }
                        if ($i == 1) $show_first = true;
                        if ($i == $total_pages) $show_last = true;
                    } elseif ($i == 1 + $ellipsis_threshold + 1 && !$show_first) {
                        echo "<span class='ellipsis'>...</span>";
                    } elseif ($i == $total_pages - $ellipsis_threshold && !$show_last) {
                        echo "<span class='ellipsis'>...</span>";
                    }
                }
                ?>


                <?php if ($current_page < $total_pages) : ?>
                    <a href="<?php echo $base_pagination_url; ?>page=<?php echo $current_page + 1; ?>" class="prev-next-link"><span>Next &raquo;</span></a>
                <?php else : ?>
                    <span class="disabled prev-next-disabled"><span>Next &raquo;</span></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
    <footer>
        <div class="content">
            <span class="copyright">© <?php echo date("Y"); ?> Prism Jewellery, All Rights Reserved</span>
            <span class="location">Designed by Vyom Uchat (22BCP450)</span>
        </div>
    </footer>

    <script src="resources/js/cart.js"></script>
    <script>
        // Removed the JS auto-submit for dropdowns as the button is clearer
        /*
        document.getElementById('category-select')?.addEventListener('change', function() {
            document.getElementById('filter-sort-form').submit();
        });
         document.getElementById('sort-select')?.addEventListener('change', function() {
            document.getElementById('filter-sort-form').submit();
        });
        document.getElementById('in_stock')?.addEventListener('change', function() {
            document.getElementById('filter-sort-form').submit();
        });
        */
    </script>

</body>

</html>
<?php
$conn->close();
?>