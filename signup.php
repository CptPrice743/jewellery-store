<?php
session_start(); // ADD THIS LINE

// If user is already logged in, redirect them away from signup page
if (isset($_SESSION['user_id'])) {
    header("Location: store.php"); // Or index.php
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "vyom0403";
$dbname = "jewellery_store";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$nameErr = $emailErr = $passwordErr = $confirmPasswordErr = "";
$name = $email = $password = $confirmPassword = "";
$signupSuccess = false;
$signupError = ""; // Variable to hold general signup errors like duplicate email

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Name validation
    if (empty($_POST["Name"])) {
        $nameErr = "Name is required";
    } else {
        $name = inputData($_POST["Name"]);
        // Check if name contains only letters and underscores
        if (!preg_match("/^[a-zA-Z_ ]+$/", $name)) { // Allow spaces in name
            $nameErr = "Only alphabets, spaces, and underscores are allowed.";
        }
    }

    // Email validation
    if (empty($_POST["Email"])) {
        $emailErr = "Email is required";
    } else {
        $email = inputData($_POST["Email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid email format";
        } else {
             // Check if email already exists
             $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
              if ($stmt){
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result(); // Store result to check num_rows
                if ($stmt->num_rows > 0) {
                    $emailErr = "Email already exists. Please login or use a different email.";
                }
                $stmt->close();
             } else {
                 $signupError = "Error checking email uniqueness.";
             }
        }
    }

    // Password validation
    if (empty($_POST["Password"])) {
        $passwordErr = "Password is required";
    } else {
        $raw_password = $_POST["Password"]; // Store raw password for comparison
         // *** SECURITY: Hash the password before storing ***
         // $password = password_hash(inputData($raw_password), PASSWORD_DEFAULT);
         $password = inputData($raw_password); // TEMPORARY: Store plain text (NOT RECOMMENDED)

        // Validate raw password format (optional, adjust regex as needed)
        if (!preg_match("/^[a-zA-Z0-9_@#]{6,}$/", $raw_password)) { // Example: min 6 chars
            $passwordErr = "Password must be at least 6 characters and contain only letters, numbers, or symbols (_, @, #).";
        }
    }

    // Confirm Password validation
    if (empty($_POST["ConfirmPassword"])) {
        $confirmPasswordErr = "Confirm Password is required";
    } else {
        $confirmPassword = inputData($_POST["ConfirmPassword"]);
        // Compare raw passwords
        if ($raw_password !== $confirmPassword) {
            $confirmPasswordErr = "Passwords do not match";
        }
    }

    // If there are no errors, insert the data into the database
    if (empty($nameErr) && empty($emailErr) && empty($passwordErr) && empty($confirmPasswordErr) && empty($signupError)) {
        // Use the hashed password ($password) here
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        if ($stmt) { // Check prepare success
             // Use $password which should contain the *hashed* password in a real application
            $stmt->bind_param("sss", $name, $email, $password);
            if ($stmt->execute()) {
                $signupSuccess = true;
            } else {
                error_log("Signup execution failed: " . $stmt->error);
                $signupError = "An error occurred during signup. Please try again.";
            }
            $stmt->close();
        } else {
             error_log("Signup prepare failed: " . $conn->error);
             $signupError = "An error occurred during signup. Please try again.";
        }
    }
}

function inputData($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data);
}
$conn->close(); // Close connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup Page</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./resources/css/style.css">
    <link rel="stylesheet" href="./resources/css/reset.css">
</head>
<body>
    <header>
        <div class="content">
             <a href="index.php" class="desktop logo">Prism Jewellery</a>
            <nav class="desktop">
                <ul>
                     <li><a href="./index.php">Home</a></li>
                     <li><a href="./about-us.php">About us</a></li>
                    <li><a href="https://www.instagram.com/">Follow us</a></li>
                     <li><a href="./login.php">Login</a></li>
                </ul>
            </nav>
             <nav class="mobile">
                 <ul>
                    <li><a href="./index.php">Prism Jewellery</a></li>
                    <li><a href="./about-us.php">About Us</a></li>
                    <li><a href="https://www.instagram.com/">Follow Us</a></li>
                     <li><a href="./login.php">Login</a></li>
                 </ul>
             </nav>
        </div>
    </header>
    <div class="login-container">
        <div class="centere">
            <h1>Signup</h1>
             <?php if (!empty($signupError)) : // Display general signup error ?>
                <p style="color:red; text-align: center; margin-bottom: 15px;"><?php echo $signupError; ?></p>
             <?php endif; ?>

            <?php if ($signupSuccess) : ?>
                <p style="color: green; text-align:center; padding: 20px;">Account created successfully. <a href="login.php">Click here to login</a>.</p>
            <?php else : ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="txt_field">
                        <input name="Name" type="text" placeholder="Username" value="<?php echo htmlspecialchars($name); ?>" required>
                        <span style="color:red" class="error"> <?php echo $nameErr; ?> </span>
                    </div>

                    <div class="txt_field">
                        <input name="Email" type="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                         <span style="color:red" class="error"> <?php echo $emailErr; ?> </span>
                    </div>

                    <div class="txt_field">
                        <input name="Password" type="password" placeholder="Password" required>
                         <span style="color:red" class="error"> <?php echo $passwordErr; ?> </span>
                    </div>

                    <div class="txt_field">
                        <input name="ConfirmPassword" type="password" placeholder="Confirm Password" required>
                        <span style="color:red" class="error"> <?php echo $confirmPasswordErr; ?> </span>
                    </div>

                    <input type="submit" value="Sign Up">
                    <div class="singup_link">
                        Already have an account? <a href="login.php">Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
      <footer>
      <div class="content">
        <span class="copyright">© 2024  Prism Jewellery, All Rights Reserved</span>
        <span class="location">Designed by Vyom Uchat (22BCP450)</span>
      </div>
    </footer>
</body>
</html>