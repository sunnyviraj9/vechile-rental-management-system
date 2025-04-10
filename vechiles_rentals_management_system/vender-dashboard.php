<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'vehicle_rental');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Fetch all vehicle profiles for this user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT * FROM vendors WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$vehicle_profiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch orders
$orders_stmt = $conn->prepare('SELECT * FROM orders WHERE vendor_id IN (SELECT id FROM vendors WHERE user_id = ?) ORDER BY order_date ASC');
$orders_stmt->bind_param('i', $user_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$orders_stmt->close();

// Fetch enquiries
$enquiries_stmt = $conn->prepare('SELECT e.id, e.email, e.message, e.created_at, v.business_name 
    FROM enquiries e 
    JOIN vendors v ON e.vendor_id = v.id 
    WHERE v.user_id = ? 
    ORDER BY e.created_at DESC');
$enquiries_stmt->bind_param('i', $user_id);
$enquiries_stmt->execute();
$enquiries = $enquiries_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$enquiries_stmt->close();

// Fetch analytics data (last 4 months)
$today = new DateTime();
$months = [];
$bookings_data = array_fill(0, 4, 0); // Bookings per month
$revenue_data = array_fill(0, 4, 0);  // Revenue per month (proxy: order count)

for ($i = 3; $i >= 0; $i--) {
    $date = clone $today;
    $date->modify("-$i months");
    $months[] = $date->format('M Y'); // e.g., "Jan 2025"
}

foreach ($orders as $order) {
    $order_date = new DateTime($order['order_date']);
    for ($i = 0; $i < 4; $i++) {
        $month_start = clone $today;
        $month_start->modify("-$i months")->modify('first day of this month');
        $month_end = clone $month_start;
        $month_end->modify('last day of this month');
        
        if ($order_date >= $month_start && $order_date <= $month_end) {
            $bookings_data[3 - $i]++; // Increment bookings
            $revenue_data[3 - $i]++;  // Proxy: 1 order = 1 unit
        }
    }
}

// Convert to JSON for JavaScript
$months_json = json_encode($months);
$bookings_json = json_encode($bookings_data);
$revenue_json = json_encode($revenue_data);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - Car Rental Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #1e1e1e, #2c3e50); color: #fff; transition: background 0.5s, color 0.5s; min-height: 100vh; }
        body.light-mode { background: linear-gradient(135deg, #f5f7fa, #c3cfe2); color: #333; }
        header { position: sticky; top: 0; background: linear-gradient(135deg, rgba(30, 30, 30, 0.8), rgba(44, 62, 80, 0.8)); backdrop-filter: blur(10px); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; z-index: 1000; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); }
        header.light-mode { background: rgba(255, 255, 255, 0.9); color: #333; }
        .navbar h1 { font-size: 2rem; font-weight: 600; background: linear-gradient(90deg, rgb(255, 255, 255), rgb(84, 83, 77)); -webkit-background-clip: text; color: transparent; }
        .theme-toggle { background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; transition: transform 0.3s ease; }
        .theme-toggle:hover { transform: rotate(20deg); }
        .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100%; background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); padding: 2rem 1rem; z-index: 999; }
        .sidebar.light-mode { background: rgba(255, 255, 255, 0.9); }
        .sidebar h2 { font-size: 1.5rem; margin-bottom: 2rem; text-align: center; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin: 1rem 0; }
        .sidebar ul li a { color: #fff; text-decoration: none; font-size: 1.1rem; display: flex; align-items: center; padding: 0.8rem; border-radius: 10px; transition: background 0.3s ease; }
        .sidebar.light-mode ul li a { color: #333; }
        .sidebar ul li a:hover { background: rgba(255, 107, 107, 0.2); }
        .sidebar ul li a i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 2rem; }
        .dashboard-section { background: rgba(254, 254, 254, 0.05); padding: 2rem; border-radius: 15px; backdrop-filter: blur(10px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); margin-bottom: 2rem; }
        .dashboard-section.light-mode { background: rgba(255, 255, 255, 0.9); }
        .dashboard-section h2 { font-size: 1.8rem; margin-bottom: 1rem; }
        .vehicle-list { margin-bottom: 2rem; }
        .vehicle-item { background: rgba(255, 255, 255, 0.1); padding: 1rem; border-radius: 10px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .vehicle-item img { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; }
        .vehicle-item-details { flex-grow: 1; margin-left: 1rem; }
        .vehicle-item-actions { display: flex; gap: 0.5rem; }
        .vehicle-form { display: flex; flex-wrap: wrap; gap: 1rem; }
        .vehicle-form input, .vehicle-form select, .vehicle-form textarea { width: calc(50% - 0.5rem); padding: 0.8rem; border: none; border-radius: 10px; background: rgba(255, 255, 255, 0.1); color: #fff; font-size: 1rem; }
        .vehicle-form textarea { width: 100%; resize: vertical; }
        .vehicle-form button { padding: 0.8rem 1.5rem; background: #ff6b6b; border: none; border-radius: 25px; color: #fff; cursor: pointer; transition: all 0.3s ease; }
        .vehicle-form button:hover { background: #feca57; transform: scale(1.05); }
        .image-upload-section { width: 100%; display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
        .image-upload { width: calc(25% - 0.75rem); display: flex; flex-direction: column; align-items: center; }
        .image-upload label { margin-bottom: 0.5rem; font-size: 0.9rem; text-align: center; }
        .image-upload input[type="file"] { width: 100%; padding: 0.5rem; background: rgba(255, 255, 255, 0.1); border-radius: 10px; color: #fff; font-size: 0.9rem; }
        .image-preview { width: 100%; height: 100px; margin-top: 0.5rem; border-radius: 10px; object-fit: cover; display: none; }
        .image-preview.show { display: block; }
        .edit-btn, .delete-btn { padding: 0.5rem 1rem; border: none; border-radius: 20px; color: #fff; cursor: pointer; transition: all 0.3s ease; }
        .edit-btn { background: #feca57; }
        .edit-btn:hover { background: #ff6b6b; transform: scale(1.05); }
        .delete-btn { background: #ff6b6b; }
        .delete-btn:hover { background: #e63946; transform: scale(1.05); }
        .orders-table { width: 100%; border-collapse: collapse; background: rgba(255, 255, 255, 0.05); border-radius: 10px; overflow: hidden; }
        .orders-table th, .orders-table td { padding: 1rem; text-align: left; }
        .orders-table th { background: #ff6b6b; color: #fff; }
        .orders-table td { border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .orders-table select { padding: 0.5rem; border: none; border-radius: 5px; background: rgba(255, 255, 255, 0.1); color: #fff; }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .chart-container { background: rgba(255, 255, 255, 0.05); padding: 1.5rem; border-radius: 15px; backdrop-filter: blur(10px); }
        .notifications { max-height: 300px; overflow-y: auto; }
        .notification { padding: 1rem; background: rgba(255, 107, 107, 0.1); border-radius: 10px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .notification button { background: #ff6b6b; border: none; padding: 0.5rem 1rem; border-radius: 20px; color: #fff; cursor: pointer; }
        /* Notification Popup Styles */
        .popup-notification { 
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
        .popup-error { 
            background: rgba(255, 107, 107, 0.9); 
        }
        @keyframes fadeInOut { 
            0% { opacity: 0; } 
            10% { opacity: 1; } 
            90% { opacity: 1; } 
            100% { opacity: 0; display: none; } 
        }
        @media (max-width: 768px) { .sidebar { width: 200px; } .main-content { margin-left: 200px; } .vehicle-form input, .vehicle-form select { width: 100%; } .image-upload { width: calc(50% - 0.5rem); } .analytics-grid { grid-template-columns: 1fr; } }
        @media (max-width: 480px) { .sidebar { width: 180px; } .main-content { margin-left: 180px; } .image-upload { width: 100%; } }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <h1>Vendor Dashboard</h1>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <h2>Menu</h2>
        <ul>
            <li><a href="#vehicles"><i class="fas fa-car"></i> Vehicles</a></li>
            <li><a href="#orders"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="#analytics"><i class="fas fa-chart-line"></i> Analytics</a></li>
            <li><a href="#notifications"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content" id="main-content">
        <!-- Vehicles Section -->
        <section id="vehicles" class="dashboard-section">
            <h2>Your Vehicles</h2>
            <div class="vehicle-list">
                <?php if (empty($vehicle_profiles)): ?>
                    <p>No vehicles added yet.</p>
                <?php else: ?>
                    <?php foreach ($vehicle_profiles as $vehicle): ?>
                        <div class="vehicle-item">
                            <img src="uploads/<?php echo htmlspecialchars($vehicle['main_photo'] ?? 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($vehicle['business_name']); ?>">
                            <div class="vehicle-item-details">
                                <h3><?php echo htmlspecialchars($vehicle['business_name']); ?></h3>
                                <p>Location: <?php echo htmlspecialchars($vehicle['location']); ?></p>
                                <p>Rental Type: <?php echo htmlspecialchars($vehicle['rental_type']); ?></p>
                                <p>Seating Capacity: <?php echo htmlspecialchars($vehicle['seating_capacity']); ?></p>
                                <p>Description: <?php echo htmlspecialchars($vehicle['description'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="vehicle-item-actions">
                                <button class="edit-btn" onclick="editVehicle(<?php echo $vehicle['id']; ?>)">Edit</button>
                                <form method="POST" action="delete_profile.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this vehicle?');">
                                    <input type="hidden" name="vendor_id" value="<?php echo $vehicle['id']; ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2>Add New Vehicle</h2>
            <form class="vehicle-form" method="POST" action="save_profile.php" enctype="multipart/form-data">
                <input type="text" name="business_name" placeholder="Vehicle Name (e.g., Toyota Innova)" required>
                <input type="text" name="location" placeholder="Location (e.g., Jadcherla, Mahabubnagar)" required>
                <select name="rental_type" id="rental-type" required>
                    <option value="" disabled selected>Rental Type</option>
                    <option value="Self-Drive">Self-Drive</option>
                    <option value="With Driver">With Driver</option>
                </select>
                <select name="seating_capacity" id="seating" required>
                    <option value="" disabled selected>Seating Capacity</option>
                    <option value="4-Seater">4-Seater</option>
                    <option value="6-Seater">6-Seater</option>
                    <option value="8-Seater">8-Seater</option>
                </select>
                <textarea name="description" placeholder="Description (e.g., mileage, features)" rows="4"></textarea>
                <div class="image-upload-section">
                    <div class="image-upload">
                        <label for="main-photo">Main Photo</label>
                        <input type="file" accept="image/*" id="main-photo" name="main_photo" required>
                        <img class="image-preview" id="main-photo-preview" alt="Main Photo Preview">
                    </div>
                    <div class="image-upload">
                        <label for="left-photo">Left Side Photo</label>
                        <input type="file" accept="image/*" id="left-photo" name="left_photo">
                        <img class="image-preview" id="left-photo-preview" alt="Left Side Photo Preview">
                    </div>
                    <div class="image-upload">
                        <label for="front-photo">Front Photo</label>
                        <input type="file" accept="image/*" id="front-photo" name="front_photo">
                        <img class="image-preview" id="front-photo-preview" alt="Front Photo Preview">
                    </div>
                    <div class="image-upload">
                        <label for="interior-photo">Interior Photo</label>
                        <input type="file" accept="image/*" id="interior-photo" name="interior_photo">
                        <img class="image-preview" id="interior-photo-preview" alt="Interior Photo Preview">
                    </div>
                </div>
                <button type="submit">Add Vehicle</button>
            </form>

            <?php $edit_vehicle = null; if (isset($_GET['edit_id'])): ?>
                <?php $edit_id = $_GET['edit_id']; ?>
                <?php foreach ($vehicle_profiles as $vehicle): ?>
                    <?php if ($vehicle['id'] == $edit_id) { $edit_vehicle = $vehicle; break; } ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($edit_vehicle): ?>
                <h2>Edit Vehicle</h2>
                <form class="vehicle-form" method="POST" action="save_profile.php" enctype="multipart/form-data">
                    <input type="hidden" name="vendor_id" value="<?php echo $edit_vehicle['id']; ?>">
                    <input type="text" name="business_name" placeholder="Vehicle Name" value="<?php echo htmlspecialchars($edit_vehicle['business_name']); ?>" required>
                    <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($edit_vehicle['location']); ?>" required>
                    <select name="rental_type" required>
                        <option value="" disabled>Rental Type</option>
                        <option value="Self-Drive" <?php echo $edit_vehicle['rental_type'] === 'Self-Drive' ? 'selected' : ''; ?>>Self-Drive</option>
                        <option value="With Driver" <?php echo $edit_vehicle['rental_type'] === 'With Driver' ? 'selected' : ''; ?>>With Driver</option>
                    </select>
                    <select name="seating_capacity" required>
                        <option value="" disabled>Seating Capacity</option>
                        <option value="4-Seater" <?php echo $edit_vehicle['seating_capacity'] === '4-Seater' ? 'selected' : ''; ?>>4-Seater</option>
                        <option value="6-Seater" <?php echo $edit_vehicle['seating_capacity'] === '6-Seater' ? 'selected' : ''; ?>>6-Seater</option>
                        <option value="8-Seater" <?php echo $edit_vehicle['seating_capacity'] === '8-Seater' ? 'selected' : ''; ?>>8-Seater</option>
                    </select>
                    <textarea name="description" placeholder="Description" rows="4"><?php echo htmlspecialchars($edit_vehicle['description'] ?? ''); ?></textarea>
                    <div class="image-upload-section">
                        <div class="image-upload">
                            <label for="edit-main-photo">Main Photo</label>
                            <input type="file" accept="image/*" id="edit-main-photo" name="main_photo">
                            <img class="image-preview show" id="edit-main-photo-preview" src="uploads/<?php echo htmlspecialchars($edit_vehicle['main_photo']); ?>" alt="Main Photo Preview">
                        </div>
                        <div class="image-upload">
                            <label for="edit-left-photo">Left Side Photo</label>
                            <input type="file" accept="image/*" id="edit-left-photo" name="left_photo">
                            <img class="image-preview <?php echo $edit_vehicle['left_photo'] ? 'show' : ''; ?>" id="edit-left-photo-preview" src="<?php echo $edit_vehicle['left_photo'] ? 'uploads/' . htmlspecialchars($edit_vehicle['left_photo']) : ''; ?>" alt="Left Side Photo Preview">
                        </div>
                        <div class="image-upload">
                            <label for="edit-front-photo">Front Photo</label>
                            <input type="file" accept="image/*" id="edit-front-photo" name="front_photo">
                            <img class="image-preview <?php echo $edit_vehicle['front_photo'] ? 'show' : ''; ?>" id="edit-front-photo-preview" src="<?php echo $edit_vehicle['front_photo'] ? 'uploads/' . htmlspecialchars($edit_vehicle['front_photo']) : ''; ?>" alt="Front Photo Preview">
                        </div>
                        <div class="image-upload">
                            <label for="edit-interior-photo">Interior Photo</label>
                            <input type="file" accept="image/*" id="edit-interior-photo" name="interior_photo">
                            <img class="image-preview <?php echo $edit_vehicle['interior_photo'] ? 'show' : ''; ?>" id="edit-interior-photo-preview" src="<?php echo $edit_vehicle['interior_photo'] ? 'uploads/' . htmlspecialchars($edit_vehicle['interior_photo']) : ''; ?>" alt="Interior Photo Preview">
                        </div>
                    </div>
                    <button type="submit">Update Vehicle</button>
                    <a href="vender-dashboard.php" style="margin-left: 1rem; color: #feca57; text-decoration: none;">Cancel Edit</a>
                </form>
            <?php endif; ?>
        </section>

        <!-- Orders Section -->
        <section id="orders" class="dashboard-section">
            <h2>Order Status</h2>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Car Type</th>
                        <th>Date</th>
                        <th>Phone Number</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="orders-body">
                    <?php foreach ($orders as $order): ?>
                        <tr data-order-id="<?php echo $order['id']; ?>">
                            <td>#<?php echo sprintf('%03d', $order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['car_type'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['order_date'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['phone_number'] ?? 'N/A'); ?></td>
                            <td>
                                <form method="POST" action="update_order.php" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="Pending" <?php echo ($order['status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Confirmed" <?php echo ($order['status'] ?? '') === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="Completed" <?php echo ($order['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Cancelled" <?php echo ($order['status'] ?? '') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- Analytics Section -->
        <section id="analytics" class="dashboard-section">
            <h2>Analytics</h2>
            <div class="analytics-grid">
                <div class="chart-container">
                    <canvas id="bookings-chart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="revenue-chart"></canvas>
                </div>
            </div>
        </section>

        <!-- Notifications Section -->
        <section id="notifications" class="dashboard-section">
            <h2>Notifications</h2>
            <div class="notifications" id="notifications-list">
                <?php foreach ($enquiries as $enquiry): ?>
                    <div class="notification">
                        <p>Enquiry for <?php echo htmlspecialchars($enquiry['business_name']); ?>: <?php echo htmlspecialchars($enquiry['message']); ?> <br>From: <?php echo htmlspecialchars($enquiry['email']); ?> (<?php echo $enquiry['created_at']; ?>)</p>
                        <button onclick="dismissNotification(this)">Dismiss</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <!-- Popup Notifications -->
    <div id="success-notification" class="popup-notification">
        Order status updated successfully
    </div>
    <div id="error-notification" class="popup-notification popup-error">
        Failed to update order status
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.min.js"></script>
    <script>
        const toggleButton = document.querySelector('.theme-toggle');
        toggleButton.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');
            document.querySelector('.sidebar').classList.toggle('light-mode');
            document.querySelectorAll('.dashboard-section').forEach(section => section.classList.toggle('light-mode'));
            toggleButton.innerHTML = document.body.classList.contains('light-mode') ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        });

        function setupImagePreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            input.addEventListener('change', () => {
                const file = input.files[0];
                if (file) {
                    preview.src = URL.createObjectURL(file);
                    preview.classList.add('show');
                }
            });
        }
        setupImagePreview('main-photo', 'main-photo-preview');
        setupImagePreview('left-photo', 'left-photo-preview');
        setupImagePreview('front-photo', 'front-photo-preview');
        setupImagePreview('interior-photo', 'interior-photo-preview');
        setupImagePreview('edit-main-photo', 'edit-main-photo-preview');
        setupImagePreview('edit-left-photo', 'edit-left-photo-preview');
        setupImagePreview('edit-front-photo', 'edit-front-photo-preview');
        setupImagePreview('edit-interior-photo', 'edit-interior-photo-preview');

        function editVehicle(id) {
            window.location.href = `vender-dashboard.php?edit_id=${id}#vehicles`;
        }

        function dismissNotification(button) {
            button.parentElement.remove();
        }

        // Analytics Charts
        const months = <?php echo $months_json; ?>;
        const bookingsData = <?php echo $bookings_json; ?>;
        const revenueData = <?php echo $revenue_json; ?>;

        const bookingsChart = new Chart(document.getElementById('bookings-chart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Bookings',
                    data: bookingsData,
                    borderColor: '#ff6b6b',
                    backgroundColor: 'rgba(255, 107, 107, 0.2)',
                    fill: true
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });

        const revenueChart = new Chart(document.getElementById('revenue-chart'), {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Revenue (Units)',
                    data: revenueData,
                    backgroundColor: '#feca57'
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });

        // Popup Notification Logic
        const successNotification = document.getElementById('success-notification');
        const errorNotification = document.getElementById('error-notification');
        <?php if (isset($_GET['success']) && $_GET['success'] === 'Order status updated successfully'): ?>
            successNotification.style.display = 'block';
            setTimeout(() => {
                successNotification.style.display = 'none';
            }, 3000);
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'Failed to update order status'): ?>
            errorNotification.style.display = 'block';
            setTimeout(() => {
                errorNotification.style.display = 'none';
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>