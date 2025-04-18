# Prism Jewellery - E-commerce Store

## Description

Prism Jewellery is a dynamic and feature-rich online jewellery store designed with elegance and user experience in mind. It offers a complete e-commerce solution, allowing users to browse products, manage their shopping cart, apply discounts, and complete purchases securely. This project showcases a robust backend built with PHP and MySQL, coupled with a responsive frontend using HTML, CSS, and interactive JavaScript elements.

This application was built as a demonstration of full-stack web development skills, incorporating database management, user authentication, session handling, AJAX interactions, and order processing.

## Key Features

### User Management & Authentication

- **User Signup:** New users can create an account with validation for name, email (including uniqueness check), and password confirmation.
- **User Login:** Registered users can log in securely. Redirects logged-in users away from login/signup pages.
- **Session Management:** User sessions are maintained to keep users logged in across pages.
- **Logout:** Users can securely log out, which also persists their cart contents to the database.
- **Password Handling:** Includes validation (Note: Currently stores plain text, **should be updated to use hashing** like `password_hash` and `password_verify` for security).

### Product Catalog & Storefront

- **Homepage:** Features a main banner, promotional sections ("Less is More", "Simplicity, Refined"), a preview of designs, and customer quotes.
- **Product Store Page:** Displays a paginated grid of jewellery items fetched from the database.
- **Product Search:** Users can search for products by name in real-time (or via button click).
- **Filtering:** Products can be filtered by:
  - Category
  - Price Range (Min/Max)
  - Stock Status ("In Stock Only")
- **Sorting:** Products can be sorted by:
  - Default Order
  - Price (Low to High, High to Low)
  - Name (A to Z, Z to A)
- **Pagination:** Product results are paginated for easier Browse.
- **Product Details:** Displays product image, name, and price.
- **Stock Indicator:** Visually indicates if a product is "Out of Stock" on the store page.

### Shopping Cart

- **Add to Cart:** Users can add products to their cart directly from the store page.
- **Guest Cart:** Cart functionality is available for non-logged-in users using PHP sessions.
- **Persistent Cart:** For logged-in users, the cart contents are saved to the database upon logout and reloaded upon login.
- **Dynamic Cart Updates (AJAX):**
  - Adding items updates the cart without a full page reload.
  - Increasing/decreasing item quantity updates the cart and totals instantly.
  - Removing items updates the cart and totals instantly.
- **Quantity Management:** Users can adjust item quantities directly in the cart.
- **Cart Page:** Displays all items in the cart with details (image, name, price, quantity, subtotal).
- **Cart Summary:** Shows subtotal, applied discount, taxes, fees, and the final total amount.
- **Header Cart Count:** The number of items in the cart is dynamically updated and displayed in the header.

### Promotions & Pricing

- **Coupon System:**
  - Users can apply valid coupon codes on the cart page.
  - Coupon validation checks for code existence, activity status, expiry date, and minimum spend requirements.
  - Supports fixed amount and percentage-based discounts.
  - Applied coupons are displayed, and users can remove them.
  - Coupons are automatically re-validated/removed if cart changes make them invalid (e.g., falling below minimum spend).
- **Fee Calculation:**
  - Tax (18%) is calculated on the subtotal after discount.
  - A fixed Shipping Fee (\$49.00) is applied.
  - A fixed Platform Fee (\$3.99) is applied.

### Checkout & Order Management

- **Secure Checkout:** Requires user login to proceed.
- **Order Creation:** Creates an order record in the database, storing user ID, total amount, and status.
- **Order Items:** Saves each item in the order, including the `product_id`, `quantity`, and the `price_at_purchase` to handle potential future price changes.
- **Cart Clearing:** Automatically clears the user's session cart and persistent database cart upon successful order placement.
- **Order Confirmation Page:** Displays a summary of the successfully placed order, including order ID, date, status, total amount, and item details.

### Other Features

- **About Us Page:** A dedicated page providing information about the brand.
- **Email Collection:** A feature on the homepage to collect emails (e.g., for a waiting list) and save them to an XML file. (Note: Using a database is generally recommended over XML for scalability).
- **Responsive Design:** The layout adapts to different screen sizes (desktop, mobile) using CSS media queries.
- **Database Interaction:** Uses MySQL for storing user data, products, categories, coupons, carts, orders, and order items. Utilizes prepared statements for security (in most places).
- **Styling:** Consistent styling applied across pages using CSS, including resets and custom styles for layout, typography, and components.
- **JavaScript Interactivity:** Frontend interactions like AJAX cart updates, email saving prompts, and potential future enhancements are handled with JavaScript.

## Technologies Used

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Data Interchange:** JSON (for AJAX), XML (for email list)
- **Concepts:** MVC (implied structure), CRUD operations, Session Management, AJAX, Responsive Design, Database Transactions (in checkout)

## Setup & Installation

1. **Clone the Repository:**
   ```bash
   git clone <your-repository-url>
   cd jewellery-store
   ```
2. **Database Setup:**
   - Create a MySQL database (e.g., `jewellery_store`).
   - Import the necessary SQL schema (You'll need to create an SQL dump file for tables like `users`, `products`, `categories`, `coupons`, `user_carts`, `orders`, `order_items`).
   - Update the database credentials (servername, username, password, dbname) in all PHP files where connections are made (e.g., `login.php`, `signup.php`, `store.php`, `manage_cart.php`, etc.).
3. **Web Server:**
   - Ensure you have a web server (like Apache or Nginx) with PHP support running.
   - Place the project files in your web server's document root (e.g., `htdocs`, `www`).
4. **Access:**
   - Open your web browser and navigate to the project's URL (e.g., `http://localhost/jewellery-store/`).

## Usage

1. Navigate to the homepage (`index.php`).
2. Browse products on the Store page (`store.php`).
3. Sign up (`signup.php`) or Login (`login.php`) to manage your cart persistently and checkout.
4. Add items to the cart, view the cart (`cart_page.php`), apply coupons, and proceed to checkout (`checkout.php`).
5. View order details after checkout (`order_confirmation.php`).
6. Use the search, filter, and sort options on the store page to find specific items.

## Directory Structure

```pgsql
jewellery-store/
├── about-us.php
├── apply_coupon.php
├── cart_page.php
├── checkout.php
├── email_list.xml
├── index.php
├── login.php
├── logout.php
├── manage_cart.php
├── order_confirmation.php
├── resources/
│   ├── css/
│   │   ├── about-us.css
│   │   ├── cart_style.css
│   │   ├── reset.css
│   │   ├── store.css
│   │   └── style.css
│   ├── js/
│   │   ├── cart.js
│   │   ├── cart_actions.js
│   │   └── index.js
├── save-email.php
├── search_products.php
├── signup.php
└── store.php
```


