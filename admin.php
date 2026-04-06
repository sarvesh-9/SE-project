<?php
// ============================================
// ADMIN PANEL - MULTIPLE ADMINS SUPPORT
// Features: Add/Edit/Delete Admins, Create Events, View Logs
// ============================================

session_start();

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$database = "college_events";

// Create connection
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->select_db($database);

// Create admins table if not exists (for multiple admins)
$conn->query("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150),
    email VARCHAR(150),
    role VARCHAR(50) DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create admin logs table
$conn->query("CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    admin_username VARCHAR(100),
    action VARCHAR(255),
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check if default admin exists, if not create one
$check_admin = $conn->query("SELECT COUNT(*) as cnt FROM admins");
$admin_count = $check_admin->fetch_assoc();
if ($admin_count['cnt'] == 0) {
    $default_password = password_hash("admin123", PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, password, full_name, email, role, status) VALUES 
        ('admin', '$default_password', 'Super Admin', 'admin@college.com', 'super_admin', 'active')");
}

// Function to log admin activities
function logAdminActivity($conn, $admin_id, $admin_username, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $admin_id, $admin_username, $action, $details, $ip);
    $stmt->execute();
}

// Handle Admin Login
$login_error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($admin = $result->fetch_assoc()) {
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['full_name'];
            
            logAdminActivity($conn, $admin['id'], $admin['username'], 'LOGIN', 'Admin logged in successfully');
            header("Location: admin.php");
            exit();
        } else {
            $login_error = "Invalid password!";
        }
    } else {
        $login_error = "Invalid username or account inactive!";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['admin_id'])) {
        logAdminActivity($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'LOGOUT', 'Admin logged out');
    }
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Check if admin is logged in
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_username = $_SESSION['admin_username'] ?? '';
$current_admin_role = $_SESSION['admin_role'] ?? '';

// Handle messages
$msg = "";
$msgType = "";

// ========== CREATE EVENT (Admin only) ==========
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $date = $_POST['event_date'];
    $time = $_POST['event_time'];
    $location = trim($_POST['location']);
    $capacity = intval($_POST['capacity']);
    $category = trim($_POST['category']);
    
    if (empty($title) || empty($date) || empty($location)) {
        $msg = "Please fill required fields";
        $msgType = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, location, capacity, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssis", $title, $desc, $date, $time, $location, $capacity, $category);
        
        if ($stmt->execute()) {
            $msg = "Event created successfully!";
            $msgType = "success";
            logAdminActivity($conn, $current_admin_id, $current_admin_username, 'CREATE_EVENT', "Created event: $title");
        } else {
            $msg = "Failed to create event";
            $msgType = "error";
        }
    }
}

// ========== DELETE EVENT ==========
if ($is_admin && isset($_GET['delete_event'])) {
    $event_id = $_GET['delete_event'];
    
    // Get event title for log
    $evt = $conn->query("SELECT title FROM events WHERE id = $event_id");
    $event_title = $evt->fetch_assoc()['title'] ?? 'Unknown';
    
    $conn->query("DELETE FROM events WHERE id = $event_id");
    $msg = "Event deleted successfully!";
    $msgType = "success";
    logAdminActivity($conn, $current_admin_id, $current_admin_username, 'DELETE_EVENT', "Deleted event: $event_title");
}

// ========== ADD NEW ADMIN ==========
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    if (empty($username) || empty($password)) {
        $msg = "Username and password are required!";
        $msgType = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);
        
        if ($stmt->execute()) {
            $msg = "New admin '$username' added successfully!";
            $msgType = "success";
            logAdminActivity($conn, $current_admin_id, $current_admin_username, 'ADD_ADMIN', "Added new admin: $username ($role)");
        } else {
            if ($conn->errno == 1062) {
                $msg = "Username already exists!";
            } else {
                $msg = "Failed to add admin: " . $conn->error;
            }
            $msgType = "error";
        }
    }
}

// ========== EDIT ADMIN ==========
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_admin'])) {
    $admin_id = $_POST['admin_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE admins SET full_name = ?, email = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $full_name, $email, $role, $status, $admin_id);
    
    if ($stmt->execute()) {
        $msg = "Admin updated successfully!";
        $msgType = "success";
        logAdminActivity($conn, $current_admin_id, $current_admin_username, 'EDIT_ADMIN', "Edited admin ID: $admin_id");
    } else {
        $msg = "Failed to update admin";
        $msgType = "error";
    }
}

// ========== CHANGE PASSWORD ==========
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $admin_id = $_POST['admin_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password != $confirm_password) {
        $msg = "Passwords do not match!";
        $msgType = "error";
    } elseif (strlen($new_password) < 4) {
        $msg = "Password must be at least 4 characters!";
        $msgType = "error";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $msg = "Password changed successfully!";
            $msgType = "success";
            logAdminActivity($conn, $current_admin_id, $current_admin_username, 'CHANGE_PASSWORD', "Changed password for admin ID: $admin_id");
        } else {
            $msg = "Failed to change password";
            $msgType = "error";
        }
    }
}

// ========== DELETE ADMIN ==========
if ($is_admin && isset($_GET['delete_admin']) && $current_admin_role == 'super_admin') {
    $admin_id = $_GET['delete_admin'];
    
    // Don't allow deleting yourself
    if ($admin_id == $current_admin_id) {
        $msg = "You cannot delete your own account!";
        $msgType = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        if ($stmt->execute()) {
            $msg = "Admin deleted successfully!";
            $msgType = "success";
            logAdminActivity($conn, $current_admin_id, $current_admin_username, 'DELETE_ADMIN', "Deleted admin ID: $admin_id");
        } else {
            $msg = "Failed to delete admin";
            $msgType = "error";
        }
    }
}

// Get all events for admin view
$events_list = $conn->query("SELECT * FROM events ORDER BY event_date ASC");

// Get all admins (for super admin view)
$admins_list = null;
if ($current_admin_role == 'super_admin') {
    $admins_list = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
}

// Get admin logs
$logs_list = null;
if ($current_admin_role == 'super_admin') {
    $logs_list = $conn->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 50");
}

// Get current admin data for profile
$current_admin_data = null;
if ($is_admin) {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->bind_param("i", $current_admin_id);
    $stmt->execute();
    $current_admin_data = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Multi Admin System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f0f2f5;
        }

        /* Navigation */
        .navbar {
            background: #1a3c5e;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
        }

        .logo span {
            color: #ffc107;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 15px;
            border-radius: 5px;
            transition: 0.3s;
        }

        .nav-links a:hover {
            background: #ffc107;
            color: #1a3c5e;
        }

        .user-btn {
            background: #28a745;
        }

        .logout-btn {
            background: #dc3545;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Messages */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Section Title */
        .section-title {
            font-size: 28px;
            color: #1a3c5e;
            margin: 25px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid #ffc107;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        /* Login Form */
        .login-card {
            background: white;
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .login-card h2 {
            text-align: center;
            color: #1a3c5e;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ffc107;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #1a3c5e;
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: #0f2b44;
        }

        .btn-primary {
            background: #28a745;
        }

        .btn-primary:hover {
            background: #218838;
        }

        /* Form Card */
        .form-card {
            background: white;
            max-width: 600px;
            margin: 0 0 30px 0;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-card h3 {
            color: #1a3c5e;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Two Column Layout */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 900px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }

        /* Event Table */
        .event-table, .admin-table, .log-table {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #1a3c5e;
            color: white;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .delete-btn, .edit-btn {
            padding: 5px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            margin-right: 5px;
            display: inline-block;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .edit-btn {
            background: #ffc107;
            color: #1a3c5e;
        }

        .edit-btn:hover {
            background: #e0a800;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-active { background: #28a745; color: white; }
        .badge-inactive { background: #dc3545; color: white; }
        .badge-super_admin { background: #ffc107; color: #1a3c5e; }
        .badge-admin { background: #17a2b8; color: white; }
        .badge-tech { background: #667eea; color: white; }
        .badge-cultural { background: #f093fb; color: white; }
        .badge-sports { background: #4facfe; color: white; }
        .badge-career { background: #43e97b; color: white; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            max-width: 500px;
            width: 90%;
            padding: 25px;
            border-radius: 15px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }

        /* Profile Card */
        .profile-card {
            background: linear-gradient(135deg, #1a3c5e, #2c5282);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .profile-card h3 {
            margin-bottom: 10px;
        }

        .profile-card p {
            margin: 5px 0;
            opacity: 0.9;
        }

        /* Footer */
        .footer {
            background: #1a3c5e;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }

        @media (max-width: 700px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
            }
            .nav-links a {
                margin: 0 8px;
            }
            th, td {
                padding: 8px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">👥 <span>Multi-Admin</span> Panel</div>
    <div class="nav-links">
        <a href="index.php">🏠 Student Portal</a>
        <?php if ($is_admin): ?>
            <a href="#events">📅 Events</a>
            <?php if ($current_admin_role == 'super_admin'): ?>
                <a href="#admins">👥 Admins</a>
                <a href="#logs">📋 Logs</a>
            <?php endif; ?>
            <a href="#profile" class="user-btn">👤 <?php echo htmlspecialchars($current_admin_username); ?></a>
            <a href="?logout" class="logout-btn" onclick="return confirm('Logout?')">🚪 Logout</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <?php if ($msg != ""): ?>
        <div class="alert alert-<?php echo $msgType; ?>">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <?php if (!$is_admin): ?>
        <!-- Admin Login Form -->
        <div class="login-card">
            <h2>🔐 Admin Login</h2>
            <?php if ($login_error): ?>
                <div class="alert alert-error"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="admin_login" class="btn-submit">Login</button>
            </form>
            <p style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
                Default: username = admin, password = admin123
            </p>
        </div>

    <?php else: ?>
        
        <!-- Profile Section -->
        <div id="profile" class="profile-card">
            <h3>Welcome, <?php echo htmlspecialchars($current_admin_data['full_name'] ?: $current_admin_username); ?>!</h3>
            <p>👤 Username: <?php echo htmlspecialchars($current_admin_username); ?></p>
            <p>⭐ Role: <?php echo ucfirst($current_admin_role); ?></p>
            <p>📧 Email: <?php echo htmlspecialchars($current_admin_data['email'] ?? 'Not set'); ?></p>
        </div>

        <div class="two-column">
            <!-- Left Column: Create Event Form -->
            <div>
                <div class="form-card" id="events">
                    <h3>✨ Create New Event</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Event Title *</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Event Date *</label>
                            <input type="date" name="event_date" required>
                        </div>
                        <div class="form-group">
                            <label>Event Time *</label>
                            <input type="time" name="event_time" required>
                        </div>
                        <div class="form-group">
                            <label>Location *</label>
                            <input type="text" name="location" required>
                        </div>
                        <div class="form-group">
                            <label>Capacity</label>
                            <input type="number" name="capacity" value="50">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option>Technology</option>
                                <option>Cultural</option>
                                <option>Sports</option>
                                <option>Career</option>
                            </select>
                        </div>
                        <button type="submit" name="create_event" class="btn-submit">➕ Create Event</button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Change Password -->
            <div>
                <div class="form-card">
                    <h3>🔐 Change Your Password</h3>
                    <form method="POST">
                        <input type="hidden" name="admin_id" value="<?php echo $current_admin_id; ?>">
                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" name="new_password" required minlength="4">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password *</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn-submit btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Manage Events Section -->
        <h2 class="section-title">📋 Manage Events</h2>
        <div class="event-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Capacity</th>
                        <th>Category</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($event = $events_list->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $event['id']; ?></td>
                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                        <td><?php echo date("M j, Y", strtotime($event['event_date'])); ?></td>
                        <td><?php echo date("g:i A", strtotime($event['event_time'])); ?></td>
                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                        <td><?php echo $event['capacity']; ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($event['category']); ?>">
                                <?php echo $event['category']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?delete_event=<?php echo $event['id']; ?>" class="delete-btn" onclick="return confirm('Delete this event? All registrations will be removed.')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Super Admin: Manage Admins Section -->
        <?php if ($current_admin_role == 'super_admin'): ?>
            <div id="admins" class="two-column">
                <!-- Add Admin Form -->
                <div>
                    <div class="form-card">
                        <h3>➕ Add New Admin</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" required minlength="4">
                            </div>
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email">
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <select name="role">
                                    <option value="admin">Admin</option>
                                    <option value="super_admin">Super Admin</option>
                                </select>
                            </div>
                            <button type="submit" name="add_admin" class="btn-submit">Add Admin</button>
                        </form>
                    </div>
                </div>

                <!-- Admins List -->
                <div>
                    <div class="form-card">
                        <h3>👥 Existing Admins</h3>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($admin = $admins_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $admin['id']; ?></td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['full_name'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $admin['role']; ?>">
                                                <?php echo $admin['role']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $admin['status']; ?>">
                                                <?php echo $admin['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($admin['id'] != $current_admin_id): ?>
                                                <a href="?delete_admin=<?php echo $admin['id']; ?>" class="delete-btn" onclick="return confirm('Delete this admin?')">Delete</a>
                                            <?php else: ?>
                                                <span style="color: #999;">Current</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Admin Modal -->
            <div id="editAdminModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>✏️ Edit Admin</h3>
                        <span class="close" onclick="closeEditModal()">&times;</span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="admin_id" id="edit_admin_id">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_email">
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" id="edit_role">
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <button type="submit" name="edit_admin" class="btn-submit">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Activity Logs Section -->
            <div id="logs">
                <h2 class="section-title">📋 Admin Activity Logs</h2>
                <div class="log-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs_list->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date("M j, H:i", strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['admin_username']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><?php echo $log['ip_address']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="btn-submit" style="display: inline-block; width: auto; padding: 10px 25px; text-decoration: none;">← Back to Student Portal</a>
        </div>
    <?php endif; ?>
</div>

<footer class="footer">
    <p>© 2025 CampusEvents - Multi-Admin Panel | Secure Event Management System</p>
</footer>

<script>
    function openEditModal(id, name, email, role, status) {
        document.getElementById('edit_admin_id').value = id;
        document.getElementById('edit_full_name').value = name || '';
        document.getElementById('edit_email').value = email || '';
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_status').value = status;
        document.getElementById('editAdminModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editAdminModal').style.display = 'none';
    }
    
    window.onclick = function(e) {
        const modal = document.getElementById('editAdminModal');
        if (e.target == modal) {
            closeEditModal();
        }
    }
</script>

</body>
</html>