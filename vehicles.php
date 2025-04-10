<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'vehicle_rental');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle search and filter inputs
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'Top Rated';
$rental_type = isset($_GET['rental-type']) ? $_GET['rental-type'] : '';
$seating = isset($_GET['seating']) ? $_GET['seating'] : '';

$sql = "SELECT * FROM vendors WHERE main_photo IS NOT NULL";
$params = [];
$types = '';

// Add search filter
if (!empty($search_query)) {
    $sql .= " AND business_name LIKE ?";
    $params[] = "%" . $search_query . "%";
    $types .= 's';
}

// Add rental type filter
if (!empty($rental_type) && in_array($rental_type, ['Self-Drive', 'With Driver'])) {
    $sql .= " AND rental_type = ?";
    $params[] = $rental_type;
    $types .= 's';
}

// Add seating filter
if (!empty($seating) && in_array($seating, ['4-Seater', '6-Seater', '8-Seater'])) {
    $seating_value = (int) str_replace('-Seater', '', $seating);
    $sql .= " AND seating_capacity = ?";
    $params[] = $seating_value;
    $types .= 'i';
}

// Add sorting
if ($sort === 'Top Rated') {
    $sql .= " ORDER BY rating DESC";
} elseif ($sort === 'Distance') {
    $sql .= " ORDER BY location"; // Simplified; real distance needs coordinates
}

// Prepare and execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query($sql);
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch confirmed orders for notifications
$user_id = $_SESSION['user_id'];
$orders_stmt = $conn->prepare('SELECT o.*, v.business_name 
    FROM orders o 
    JOIN vendors v ON o.vendor_id = v.id 
    WHERE o.customer_id = ? AND o.status = "Confirmed" 
    ORDER BY o.order_date DESC');
$orders_stmt->bind_param('i', $user_id);
$orders_stmt->execute();
$confirmed_orders = $orders_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$orders_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e1e1e, #2c3e50);
            color: #fff;
            transition: background 0.5s, color 0.5s;
        }
        body.light-mode {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            color: #333;
        }
        header {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #1e1e1e88, #2c3e508e);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        header.light-mode {
            background: rgba(255, 255, 255, 0.9);
            color: #333;
        }
        .navbar {
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: space-between;
            position: relative;
        }
        .navbar h1 {
            font-size: 2rem;
            font-weight: 600;
            background: linear-gradient(90deg, #e5e1e1, #666560);
            -webkit-background-clip: text;
            color: transparent;
        }
        .search-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 40%;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        .search-bar form {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        .search-bar input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: none;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .search-bar input:focus {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
            outline: none;
        }
        .search-bar button {
            padding: 0.8rem 1.5rem;
            background: #ff6b6b;
            border: none;
            border-radius: 25px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-bar button:hover {
            background: #feca57;
            transform: scale(1.05);
        }
        .theme-toggle {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .theme-toggle:hover {
            transform: rotate(20deg);
        }
        .notification-area {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .notification-bell {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            position: relative;
            transition: color 0.3s ease;
        }
        .notification-bell:hover {
            color: #feca57;
        }
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff6b6b;
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 40px;
            right: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
        }
        .notification-dropdown.active {
            display: block;
        }
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: background 0.3s ease;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item:hover {
            background: rgba(255, 107, 107, 0.2);
        }
        .notification-item p {
            margin: 0;
            font-size: 0.9rem;
        }
        .notification-item small {
            color: #ccc;
            font-size: 0.7rem;
        }
        .container {
            display: flex;
            padding: 2rem;
            gap: 2rem;
        }
        .filters {
            width: 20%;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        .filters h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .filters form {
            display: flex;
            flex-direction: column;
        }
        .filters select {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
        }
        .filters button {
            padding: 0.8rem;
            background: #ff6b6b;
            border: none;
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filters button:hover {
            background: #feca57;
            transform: scale(1.05);
        }
        .listings {
            width: 80%;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        .listing {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .listing:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }
        .listing img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }
        .listing img:hover {
            transform: scale(1.05);
        }
        .image-gallery {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .image-gallery img {
            width: 33%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }
        .image-gallery img:hover {
            transform: scale(1.1);
        }
        .listing h3 {
            font-size: 1.3rem;
            margin: 0.5rem 0;
        }
        .listing button {
            padding: 0.6rem 1.2rem;
            margin-right: 10px;
            background: #ff6b6b;
            border: none;
            border-radius: 20px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .listing button:hover {
            background: #feca57;
            transform: scale(1.05);
        }
        .rating-form {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rating-form select {
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 0.9rem;
        }
        .rating-form button {
            padding: 0.5rem 1rem;
            background: #feca57;
            border: none;
            border-radius: 20px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .rating-form button:hover {
            background: #ff6b6b;
            transform: scale(1.05);
        }
        footer {
            text-align: center;
            padding: 2rem;
            background: rgba(15, 15, 15, 0.9);
            color: #fff;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            background: #fff;
            color: #333;
            width: 90%;
            max-width: 500px;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 15px;
            position: relative;
        }
        .modal-content h2 {
            margin-bottom: 1rem;
        }
        .modal-content input,
        .modal-content textarea {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .modal-content button {
            padding: 0.8rem 1.5rem;
            background: #ff6b6b;
            border: none;
            border-radius: 25px;
            color: #fff;
            cursor: pointer;
        }
        .modal-content button:hover {
            background: #feca57;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .notification {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 202, 87, 0.9);
            color: #333;
            padding: 1rem 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            z-index: 2000;
            font-size: 1.2rem;
            text-align: center;
            animation: fadeInOut 3s ease-in-out forwards;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .filters {
                width: 100%;
            }
            .listings {
                width: 100%;
            }
            .search-bar {
                position: static;
                transform: none;
                width: 100%;
                margin: 1rem 0;
            }
            .navbar {
                flex-direction: column;
            }
            .notification-area {
                margin-top: 1rem;
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <h1>Car Rental Services</h1>
            <div class="search-bar">
                <form method="GET" action="vehicles.php">
                    <input type="text" name="search" placeholder="Search by vendor name" value="<?php echo htmlspecialchars($search_query); ?>" aria-label="Search">
                    <button type="submit">Search</button>
                </form>
            </div>
            <div class="notification-area">
                <button class="notification-bell" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($confirmed_orders)): ?>
                        <span class="notification-count"><?php echo count($confirmed_orders); ?></span>
                    <?php endif; ?>
                </button>
                <div class="notification-dropdown">
                    <?php if (empty($confirmed_orders)): ?>
                        <div class="notification-item">No new notifications</div>
                    <?php else: ?>
                        <?php foreach ($confirmed_orders as $order): ?>
                            <div class="notification-item">
                                <p>Your booking for <?php echo htmlspecialchars($order['business_name']); ?> has been confirmed!</p>
                                <small>Date: <?php echo htmlspecialchars($order['order_date']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <aside class="filters">
            <h2>Filters</h2>
            <form method="GET" action="vehicles.php">
                <?php if (!empty($search_query)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                <?php endif; ?>
                <select id="sort" name="sort" onchange="this.form.submit()">
                    <option value="Top Rated" <?php echo $sort === 'Top Rated' ? 'selected' : ''; ?>>Top Rated</option>
                    <option value="Distance" <?php echo $sort === 'Distance' ? 'selected' : ''; ?>>Distance</option>
                </select>
                <select id="rental-type" name="rental-type" onchange="this.form.submit()">
                    <option value="" <?php echo empty($rental_type) ? 'selected' : ''; ?>>All Rental Types</option>
                    <option value="Self-Drive" <?php echo $rental_type === 'Self-Drive' ? 'selected' : ''; ?>>Self-Drive</option>
                    <option value="With Driver" <?php echo $rental_type === 'With Driver' ? 'selected' : ''; ?>>With Driver</option>
                </select>
                <select id="seating" name="seating" onchange="this.form.submit()">
                    <option value="" <?php echo empty($seating) ? 'selected' : ''; ?>>All Seating</option>
                    <option value="4-Seater" <?php echo $seating === '4-Seater' ? 'selected' : ''; ?>>4-Seater</option>
                    <option value="6-Seater" <?php echo $seating === '6-Seater' ? 'selected' : ''; ?>>6-Seater</option>
                    <option value="8-Seater" <?php echo $seating === '8-Seater' ? 'selected' : ''; ?>>8-Seater</option>
                </select>
                <button type="submit">Apply Filters</button>
            </form>
        </aside>

        <main class="listings">
            <?php if (empty($vendors)): ?>
                <p>No vehicles available at the moment<?php echo !empty($search_query) ? " matching '$search_query'" : ''; ?>.</p>
            <?php else: ?>
                <?php foreach ($vendors as $vendor): ?>
                    <div class="listing">
                        <img src="uploads/<?php echo htmlspecialchars($vendor['main_photo'] ?? 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($vendor['business_name']); ?>" loading="lazy">
                        <h3><?php echo htmlspecialchars($vendor['business_name']); ?></h3>
                        <p>Rating: <?php echo number_format($vendor['rating'] ?? 0, 1); ?> ★ (<?php echo $vendor['rating_count'] ?? 0; ?> Ratings)</p>
                        <p>Location: <?php echo htmlspecialchars($vendor['location']); ?></p>
                        <div class="image-gallery">
                            <img src="uploads/<?php echo htmlspecialchars($vendor['interior_photo'] ?? 'default.jpg'); ?>" alt="Interior view" loading="lazy">
                            <img src="uploads/<?php echo htmlspecialchars($vendor['left_photo'] ?? 'default.jpg'); ?>" alt="Side view" loading="lazy">
                            <img src="uploads/<?php echo htmlspecialchars($vendor['front_photo'] ?? 'default.jpg'); ?>" alt="Back view" loading="lazy">
                        </div>
                        <div class="rating-form">
                            <form method="POST" action="rate_vendor.php">
                                <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                <select name="rating" required>
                                    <option value="" disabled selected>Rate (1-5)</option>
                                    <option value="1">1 ★</option>
                                    <option value="2">2 ★</option>
                                    <option value="3">3 ★</option>
                                    <option value="4">4 ★</option>
                                    <option value="5">5 ★</option>
                                </select>
                                <button type="submit">Submit Rating</button>
                            </form>
                        </div>
                        <br>
                        <button class="book-btn" data-vendor-id="<?php echo $vendor['id']; ?>" data-vehicle-name="<?php echo htmlspecialchars($vendor['business_name']); ?>">Book Car</button>
                        <button class="enquiry-btn" data-vendor-id="<?php echo $vendor['id']; ?>" data-vehicle-name="<?php echo htmlspecialchars($vendor['business_name']); ?>">Send Enquiry</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Enquiry Modal -->
    <div id="enquiry-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">×</span>
            <h2>Send Enquiry for <span id="modal-vehicle-name"></span></h2>
            <form method="POST" action="send_enquiry.php">
                <input type="hidden" name="vendor_id" id="modal-vendor-id">
                <input type="email" name="email" placeholder="Your Email" required>
                <textarea name="message" placeholder="Your Message" rows="4" required></textarea>
                <button type="submit">Send</button>
            </form>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="booking-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">×</span>
            <h2>Book <span id="booking-vehicle-name"></span></h2>
            <form method="POST" action="book_car.php">
                <input type="hidden" name="vendor_id" id="booking-vendor-id">
                <input type="text" name="customer_name" placeholder="Your Name" required>
                <input type="tel" name="phone_number" placeholder="Phone Number" pattern="[0-9]{10}" required title="Enter a 10-digit phone number">
                <input type="date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                <button type="submit">Book Now</button>
            </form>
        </div>
    </div>

    <!-- Notification Popup -->
    <div id="notification" class="notification">
        Vehicle rating submitted successfully
    </div>

    <footer>
        <p>© 2025 Car Rental Services. All rights reserved.</p>
    </footer>

    <script>
        // Enquiry Modal
        const enquiryModal = document.getElementById('enquiry-modal');
        const enquiryCloseBtn = enquiryModal.querySelector('.close-btn');
        const enquiryBtns = document.querySelectorAll('.enquiry-btn');
        const modalVehicleName = document.getElementById('modal-vehicle-name');
        const modalVendorId = document.getElementById('modal-vendor-id');

        enquiryBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const vendorId = btn.getAttribute('data-vendor-id');
                const vehicleName = btn.getAttribute('data-vehicle-name');
                modalVendorId.value = vendorId;
                modalVehicleName.textContent = vehicleName;
                enquiryModal.style.display = 'block';
            });
        });

        enquiryCloseBtn.addEventListener('click', () => {
            enquiryModal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === enquiryModal) {
                enquiryModal.style.display = 'none';
            }
        });

        // Booking Modal
        const bookingModal = document.getElementById('booking-modal');
        const bookingCloseBtn = bookingModal.querySelector('.close-btn');
        const bookBtns = document.querySelectorAll('.book-btn');
        const bookingVehicleName = document.getElementById('booking-vehicle-name');
        const bookingVendorId = document.getElementById('booking-vendor-id');

        bookBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const vendorId = btn.getAttribute('data-vendor-id');
                const vehicleName = btn.getAttribute('data-vehicle-name');
                bookingVendorId.value = vendorId;
                bookingVehicleName.textContent = vehicleName;
                bookingModal.style.display = 'block';
            });
        });

        bookingCloseBtn.addEventListener('click', () => {
            bookingModal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === bookingModal) {
                bookingModal.style.display = 'none';
            }
        });

        // Notification Popup
        const notification = document.getElementById('notification');
        <?php if (isset($_GET['success']) && $_GET['success'] === 'Rating submitted successfully'): ?>
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        <?php endif; ?>

        // Notification Dropdown for Orders
        const notificationBell = document.querySelector('.notification-bell');
        const notificationDropdown = document.querySelector('.notification-dropdown');
        notificationBell.addEventListener('click', () => {
            notificationDropdown.classList.toggle('active');
        });

        window.addEventListener('click', (e) => {
            if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>