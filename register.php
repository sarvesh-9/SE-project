<?php
session_start();

/* ===== DATABASE CONNECTION ===== */
$conn = new mysqli("localhost", "root", "", "college_event");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* Create database if not exists */
$conn->query("CREATE DATABASE IF NOT EXISTS college_event");
$conn->select_db("college_event");

/* Create table if not exists */
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
)");

$message = "";

/* ===== REGISTER LOGIC ===== */
if(isset($_POST['register'])) {

    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if username exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows > 0) {
        $message = "Username already exists!";
    } else {

        $insert = $conn->prepare("INSERT INTO users (name, username, password) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $name, $username, $password);

        if($insert->execute()) {
            header("Location: login.php?success=1");
            exit();
        } else {
            $message = "Registration failed. Try again.";
        }

        $insert->close();
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - College Event System</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>

<div class="stars"></div>

<div class="top">
    <h1>COLLEGE <span>EVENT SYSTEM</span></h1>
</div>

<div class="hud">

    <div class="menu">
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </div>

    <div class="center">
        <h2>Register</h2>

        <?php if($message != "") { ?>
            <p><?php echo $message; ?></p>
        <?php } ?>

        <form method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <br><br>
            <input type="text" name="username" placeholder="Username" required>
            <br><br>
            <input type="password" name="password" placeholder="Password" required>
            <br><br>
            <button type="submit" name="register" class="btn">Register</button>
        </form>
    </div>

</div>

</body>
</html>
