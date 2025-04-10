<?php
session_start(); // Start session to manage user login and cart

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
$total_items = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// --- Calculate Offset for SQL Query ---
$offset = ($current_page - 1) * $items_per_page;

// --- Fetch products for the current page ---
// Use prepared statement for security
$sql = "SELECT product_id, name, description, price, image_url FROM products LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
// Bind parameters: 'i' for integer
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result(); // Get result set from prepared statement

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

        .search-bar {
            margin-bottom: 2rem;
            padding: 0.5rem;
            width: 50%;
            max-width: 400px;
        }

        .product-grid {
            display: grid;
            /* Adjust minmax based on desired card size & image aspect ratio */
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2rem;
            /* Adjust gap as needed */
            margin-bottom: 2rem;
            /* Add space before pagination */
        }

        .product-card {
            /* Basic structure and appearance */
            border: 1px solid #e0e0e0;
            /* Lighter border like the image */
            border-radius: 4px;
            /* Subtle rounding */
            padding: 1rem;
            /* Internal spacing */
            text-align: left;
            /* Align text to the left */
            background-color: var(--white);
            /* Start with white background */
            position: relative;
            /* Needed for potential future absolute elements if any */
            overflow: hidden;
            /* Ensures content stays within borders */

            /* Hover effect */
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            /* Smooth transition for background and shadow */
        }

        .product-card:hover {
            background-color: var(--primary-color);
            /* Change background on hover */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            /* Optional: slightly enhance shadow on hover */
        }

        .product-card img {
            display: block;
            /* Ensure image is block-level */
            width: 100%;
            /* Make image fill the card width */
            height: 250px;
            /* Set a fixed height - ADJUST AS NEEDED based on your images */
            object-fit: cover;
            /* Cover the area, cropping if needed */
            margin-bottom: 1rem;
            /* Space below image */
            border-radius: 0;
            /* No border-radius on image itself */
            background-color: #eee;
            /* Add a light background color for loading phase */
        }

        /* 1. Name */
        .product-card h3 {
            font-size: 1.0rem;
            /* Adjust size as needed */
            font-weight: normal;
            /* Normal weight like the example */
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            /* Space below name */
            line-height: 1.4;
        }

        /* 2. Price */
        .product-card p.price {
            font-size: 1.2rem;
            /* Make price slightly bigger than name */
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 1rem;
            /* Space below price */
        }

        .add-to-cart-btn {
            padding: 0.5rem 1rem;
            background-color: var(--text-dark);
            color: var(--primary-color);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: auto;
            /* Ensure button width is based on content */
            display: inline-block;
            /* Allows centering if text-align: center is on parent */
            margin-top: auto;
            /* Push to bottom if card uses flex */
        }

        .add-to-cart-btn:hover {
            background-color: #555;
            /* Darken hover color */
        }

        #cart-status {
            /* Style for cart messages */
            margin-top: 1rem;
            color: green;
            text-align: center;
        }

        /* --- Pagination Styles --- */
        .pagination {
            text-align: center;
            padding: 1rem 0;
            margin-top: 2rem;
            /* Add space above pagination */
        }

        /* General style for ALL pagination elements (numbers, links, disabled) */
        .pagination a,
        .pagination strong,
        .pagination span {
            /* Includes the outer span for disabled items */
            display: inline-block;
            padding: 0.6rem 1rem;
            margin: 0 0.25rem;
            border: 1px solid #ddd;
            /* Default border for numbers */
            text-decoration: none;
            color: var(--text-dark);
            background-color: var(--white);
            /* Default background for numbers */
            border-radius: 4px;
            font-family: "Roboto", sans-serif;
            font-size: 0.9rem;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
            vertical-align: middle;
            /* Align elements nicely */
        }

        /* Hover effect ONLY for clickable links (numbers and prev/next) */
        .pagination a:hover {
            background-color: var(--primary-color);
            color: var(--text-dark);
            border-color: #ccc;
        }

        /* Style for the current page number (keeps the box) */
        .pagination strong {
            background-color: var(--text-dark);
            color: var(--primary-color);
            border-color: var(--text-dark);
            font-weight: bold;
            cursor: default;
        }

        /* --- Specific styles for Prev/Next --- */

        /* Remove border and background for active Prev/Next links */
        .pagination a.prev-next-link {
            border: none;
            /* Remove the border */
            background-color: transparent;
            /* Remove the background */
            padding: 0.6rem 0.5rem;
            /* Adjust padding if needed, less horizontal needed without box */
            color: var(--text-dark);
            /* Ensure default text color */
            transition: color 0.3s ease;
            /* Transition only the color */
        }

        /* Hover effect for active Prev/Next links - change text color */
        .pagination a.prev-next-link:hover {
            color: var(--primary-color);
            /* Change text color on hover */
            background-color: transparent;
            /* Ensure background remains transparent on hover */
            border-color: transparent;
            /* Ensure border remains transparent on hover */
        }

        /* Remove border and background for disabled Prev/Next */
        .pagination span.prev-next-disabled {
            border: none;
            /* Remove the border */
            background-color: transparent;
            /* Remove the background */
            padding: 0.6rem 0.5rem;
            /* Match active link padding */
            cursor: default;
            /* The inner span still controls the text color */
        }

        /* Style for the text inside disabled Prev/Next */
        .pagination span.prev-next-disabled span {
            color: #aaa;
            /* Greyed-out text */
            /* Reset any padding/margin inherited if needed, usually fine */
            padding: 0;
            margin: 0;
            border: none;
            background-color: transparent;
        }

        /* --- Store Header Styling (Add/Modify in style.css) --- */

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
        }

        /* Style for the search bar within the store header */
        .store-header .search-bar {
            /* Be specific to avoid affecting other search bars */
            width: 35%;
            /* Adjust width as needed */
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
        }

        /* Optional: Add a focus style to the search bar */
        .store-header .search-bar:focus {
            outline: none;
            /* Remove default browser outline */
            border-color: var(--text-dark);
            /* Darken border on focus */
            box-shadow: 0 0 0 2px rgba(44, 39, 36, 0.1);
            /* Subtle glow, using text-dark with alpha */
        }

        /* --- End Store Header Styling --- */

        /* --- Remove or Adjust old .search-bar rule if it conflicts --- */
        /* If you had a general .search-bar rule outside the <style> tag in store.php,
   you might want to remove its width/margin properties if they interfere
   with the flexbox layout */

        /* Example: If you had this in style.css before: */
        /*
.search-bar {
    margin-bottom: 2rem;
    padding: 0.5rem;
    width: 50%; <---- REMOVE or adjust if needed
    max-width: 400px;
}
*/
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
                    <li><a href="cart_page.php">Cart (<span id="cart-count">0</span>)</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
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
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='product-card'>";
                    // --- MODIFIED IMG TAG ---
                    echo "<img src='" . htmlspecialchars($row["image_url"]) . "' alt='" . htmlspecialchars($row["name"]) . "' loading='lazy' width='300' height='250'>"; // Added loading='lazy' and width/height
                    echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                    // echo "<p>" . htmlspecialchars($row["description"]) . "</p>";
                    echo "<p class='price'>$" . htmlspecialchars($row["price"]) . "</p>";

                    echo "<div class='cart-interaction' data-product-id='" . $row["product_id"] . "'>";
                    echo "<button class='add-to-cart-btn'>Add to Cart</button>";
                    echo "</div>"; // End cart-interaction div

                    echo "</div>"; // End product-card div
                }
            } else {
                // Check if it's page 1 with no results or a later page
                if ($current_page == 1) {
                    echo "<p>No products found.</p>";
                } else {
                    echo "<p>No more products found.</p>";
                }
            }
            $stmt->close(); // Close the prepared statement
            ?>
        </div>
        <div class="pagination">
            <?php if ($total_pages > 1): // Only show pagination if there's more than one page 
            ?>
                <?php if ($current_page > 1): // Show 'Previous' link if not on page 1 
                ?>
                    <a href="store.php?page=<?php echo $current_page - 1; ?>" class="prev-next-link"><span>&laquo; Previous</span></a> <?php // Added class="prev-next-link" 
                                                                                                                                        ?>
                <?php else: ?>
                    <span class="disabled prev-next-disabled"><span>&laquo; Previous</span></span> <?php // Added class="prev-next-disabled" 
                                                                                                    ?>
                <?php endif; ?>

                <?php // Page Number Links (optional: limit the number shown for many pages) 
                ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <strong><?php echo $i; ?></strong> <?php // Current page 
                                                            ?>
                    <?php else: ?>
                        <a href="store.php?page=<?php echo $i; ?>"><?php echo $i; ?></a> <?php // Other pages 
                                                                                            ?>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): // Show 'Next' link if not on the last page 
                ?>
                    <a href="store.php?page=<?php echo $current_page + 1; ?>" class="prev-next-link"><span>Next &raquo;</span></a> <?php // Added class="prev-next-link" 
                                                                                                                                    ?>
                <?php else: ?>
                    <span class="disabled prev-next-disabled"><span>Next &raquo;</span></span> <?php // Added class="prev-next-disabled" 
                                                                                                ?>
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