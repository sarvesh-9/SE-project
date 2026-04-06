<?php
// ============================================
// COLLEGE EVENT MANAGEMENT SYSTEM - USER SIDE
// Students can view events and register
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

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $database");
$conn->select_db($database);

// Create events table
$conn->query("CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    location VARCHAR(200) NOT NULL,
    capacity INT DEFAULT 50,
    category VARCHAR(100)
)");

// Create registrations table
$conn->query("CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    student_name VARCHAR(150) NOT NULL,
    student_email VARCHAR(150) NOT NULL,
    student_phone VARCHAR(20),
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reg (event_id, student_email)
)");

// Add sample events if empty
$check = $conn->query("SELECT COUNT(*) as cnt FROM events");
$row = $check->fetch_assoc();
if ($row['cnt'] == 0) {
    $conn->query("INSERT INTO events (title, description, event_date, event_time, location, capacity, category) VALUES
        ('Tech Fest 2025', 'Annual technology festival with coding competitions and workshops', '2025-05-15', '10:00:00', 'Main Auditorium', 100, 'Technology'),
        ('Cultural Night', 'Dance, music and drama performances', '2025-05-20', '18:00:00', 'Open Air Theatre', 150, 'Cultural'),
        ('Sports Meet', 'Cricket, football and basketball tournaments', '2025-05-25', '09:00:00', 'Sports Ground', 200, 'Sports'),
        ('Career Fair', 'Meet recruiters and learn job skills', '2025-06-01', '11:00:00', 'Seminar Hall', 80, 'Career')");
}

// Handle POST requests
$msg = "";
$msgType = "";

// Register for event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $event_id = $_POST['event_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    if (empty($name) || empty($email)) {
        $msg = "Please enter name and email";
        $msgType = "error";
    } else {
        // Check capacity
        $evt = $conn->query("SELECT capacity FROM events WHERE id = $event_id");
        $evtData = $evt->fetch_assoc();
        $regCount = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE event_id = $event_id");
        $countData = $regCount->fetch_assoc();
        
        if ($countData['total'] >= $evtData['capacity']) {
            $msg = "Event is full!";
            $msgType = "error";
        } else {
            $sql = "INSERT INTO registrations (event_id, student_name, student_email, student_phone) 
                    VALUES ($event_id, '$name', '$email', '$phone')";
            if ($conn->query($sql)) {
                $msg = "Registration successful!";
                $msgType = "success";
            } else {
                if ($conn->errno == 1062) {
                    $msg = "You are already registered for this event!";
                } else {
                    $msg = "Registration failed!";
                }
                $msgType = "error";
            }
        }
    }
}

// Cancel registration
if (isset($_GET['cancel']) && isset($_GET['email'])) {
    $reg_id = $_GET['cancel'];
    $email = $_GET['email'];
    $conn->query("DELETE FROM registrations WHERE id = $reg_id AND student_email = '$email'");
    $msg = "Registration cancelled";
    $msgType = "success";
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Event Management - Student Portal</title>
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

        .nav-links a:hover, .nav-links a.active {
            background: #ffc107;
            color: #1a3c5e;
        }

        .admin-btn {
            background: #dc3545;
            border-radius: 5px;
        }

        .admin-btn:hover {
            background: #c82333;
            color: white !important;
        }

        /* Container */
        .container {
            max-width: 1200px;
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
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid #ffc107;
        }

        /* Event Grid */
        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .event-header {
            background: linear-gradient(135deg, #1a3c5e, #2c5282);
            color: white;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
        }

        .event-body {
            padding: 20px;
        }

        .event-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
            color: #555;
            font-size: 14px;
        }

        .event-desc {
            color: #666;
            margin: 12px 0;
            line-height: 1.5;
        }

        .seats {
            display: inline-block;
            background: #e8f0fe;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #1a3c5e;
            margin: 10px 0;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: #ffc107;
            color: #1a3c5e;
            text-align: center;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn:hover {
            background: #e0a800;
        }

        .btn-full {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Form */
        .form-card {
            background: white;
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-card h2 {
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

        /* Registration List */
        .reg-card {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .reg-info h4 {
            color: #1a3c5e;
            margin-bottom: 5px;
        }

        .reg-info p {
            color: #666;
            font-size: 13px;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-cancel:hover {
            background: #c82333;
        }

        .email-form {
            max-width: 450px;
        }

        /* Footer */
        .footer {
            background: #1a3c5e;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }

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
            max-width: 450px;
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

        @media (max-width: 700px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
            }
            .nav-links a {
                margin: 0 8px;
            }
            .event-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">🎓 <span>Campus</span>Events</div>
    <div class="nav-links">
        <a href="?page=home" class="<?php echo $page == 'home' ? 'active' : ''; ?>">Home</a>
        <a href="?page=events" class="<?php echo $page == 'events' ? 'active' : ''; ?>">All Events</a>
        <a href="?page=my" class="<?php echo $page == 'my' ? 'active' : ''; ?>">My Registrations</a>
        <a href="admin.php" class="admin-btn">🔐 Admin Panel</a>
    </div>
</nav>

<div class="container">
    <?php if ($msg != ""): ?>
        <div class="alert alert-<?php echo $msgType; ?>">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <!-- HOME PAGE -->
    <?php if ($page == 'home'): ?>
        <h1 class="section-title">🎉 Upcoming Events</h1>
        <div class="event-grid">
            <?php
            $result = $conn->query("SELECT * FROM events ORDER BY event_date ASC LIMIT 3");
            while ($event = $result->fetch_assoc()):
                $regCount = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE event_id = " . $event['id']);
                $count = $regCount->fetch_assoc();
                $left = $event['capacity'] - $count['total'];
            ?>
            <div class="event-card">
                <div class="event-header"><?php echo htmlspecialchars($event['title']); ?></div>
                <div class="event-body">
                    <div class="event-meta">📅 <?php echo date("F j, Y", strtotime($event['event_date'])); ?></div>
                    <div class="event-meta">⏰ <?php echo date("g:i A", strtotime($event['event_time'])); ?></div>
                    <div class="event-meta">📍 <?php echo htmlspecialchars($event['location']); ?></div>
                    <div class="event-desc"><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . "..."; ?></div>
                    <span class="seats">🎟️ <?php echo $left; ?> seats left</span>
                    <button class="btn" onclick="openModal(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>')">Register Now</button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <a href="?page=events" class="btn" style="display: inline-block; width: auto; padding: 10px 30px;">View All Events →</a>
        </div>

    <!-- ALL EVENTS PAGE -->
    <?php elseif ($page == 'events'): ?>
        <h1 class="section-title">📅 All Events</h1>
        <div class="event-grid">
            <?php
            $result = $conn->query("SELECT * FROM events ORDER BY event_date ASC");
            while ($event = $result->fetch_assoc()):
                $regCount = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE event_id = " . $event['id']);
                $count = $regCount->fetch_assoc();
                $left = $event['capacity'] - $count['total'];
            ?>
            <div class="event-card">
                <div class="event-header"><?php echo htmlspecialchars($event['title']); ?></div>
                <div class="event-body">
                    <div class="event-meta">📅 <?php echo date("F j, Y", strtotime($event['event_date'])); ?></div>
                    <div class="event-meta">⏰ <?php echo date("g:i A", strtotime($event['event_time'])); ?></div>
                    <div class="event-meta">📍 <?php echo htmlspecialchars($event['location']); ?></div>
                    <div class="event-meta">🏷️ <?php echo htmlspecialchars($event['category']); ?></div>
                    <div class="event-desc"><?php echo htmlspecialchars($event['description']); ?></div>
                    <span class="seats">🎟️ <?php echo $left; ?> / <?php echo $event['capacity']; ?> seats</span>
                    <button class="btn" onclick="openModal(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>')">Register Now</button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

    <!-- MY REGISTRATIONS PAGE -->
    <?php elseif ($page == 'my'): ?>
        <h1 class="section-title">📋 My Registrations</h1>
        <?php
        $showForm = true;
        $studentEmail = "";
        if (isset($_GET['email']) && !empty($_GET['email'])) {
            $studentEmail = $_GET['email'];
            $showForm = false;
            $regs = $conn->query("SELECT r.*, e.title, e.event_date, e.location FROM registrations r 
                                  JOIN events e ON r.event_id = e.id 
                                  WHERE r.student_email = '$studentEmail' 
                                  ORDER BY r.reg_date DESC");
        }
        ?>
        <?php if ($showForm): ?>
            <div class="form-card email-form">
                <h2>🔍 Find Your Events</h2>
                <form method="GET">
                    <input type="hidden" name="page" value="my">
                    <div class="form-group">
                        <label>Enter Your Email</label>
                        <input type="email" name="email" required placeholder="student@college.com">
                    </div>
                    <button type="submit" class="btn-submit">Show My Events</button>
                </form>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 20px;">
                <a href="?page=my" class="btn" style="display: inline-block; width: auto; padding: 8px 20px;">← Back</a>
            </div>
            <?php if ($regs->num_rows == 0): ?>
                <div class="alert alert-error">No registrations found for <?php echo htmlspecialchars($studentEmail); ?></div>
            <?php else: ?>
                <?php while ($reg = $regs->fetch_assoc()): ?>
                <div class="reg-card">
                    <div class="reg-info">
                        <h4><?php echo htmlspecialchars($reg['title']); ?></h4>
                        <p>📅 <?php echo date("F j, Y", strtotime($reg['event_date'])); ?> | 📍 <?php echo htmlspecialchars($reg['location']); ?></p>
                        <p>Registered on: <?php echo date("F j, Y", strtotime($reg['reg_date'])); ?></p>
                    </div>
                    <a href="?page=my&email=<?php echo urlencode($studentEmail); ?>&cancel=<?php echo $reg['id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this registration?')">Cancel</a>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Registration Modal -->
<div id="regModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Register for Event</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="event_id" id="event_id">
            <div class="form-group">
                <label>Event</label>
                <input type="text" id="event_title" disabled style="background: #f5f5f5;">
            </div>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone">
            </div>
            <button type="submit" name="register" class="btn-submit">Register Now</button>
        </form>
    </div>
</div>

<footer class="footer">
    <p>© 2025 CampusEvents - College Event Management System | Student Portal</p>
</footer>

<script>
    function openModal(id, title) {
        document.getElementById('event_id').value = id;
        document.getElementById('event_title').value = title;
        document.getElementById('regModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('regModal').style.display = 'none';
    }
    
    window.onclick = function(e) {
        if (e.target == document.getElementById('regModal')) {
            closeModal();
        }
    }
</script>

</body>
</html>