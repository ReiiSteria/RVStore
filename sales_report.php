<?php
require_once 'config.php';
requireAuth();
requireAdmin(); // Typically sales report is for admin

// Get date range (default to last 30 days)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get sales summary
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_sales,
        AVG(amount) as average_sale
    FROM transactions
    WHERE status = 'success'
    AND DATE(transaction_time) BETWEEN ? AND ?
");
$summary_stmt->bind_param("ss", $date_from, $date_to);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();
$summary_stmt->close();

// Get sales by game
$games_stmt = $conn->prepare("
    SELECT 
        g.name as game_name,
        COUNT(t.id) as transaction_count,
        SUM(t.amount) as total_sales
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    WHERE t.status = 'success'
    AND DATE(t.transaction_time) BETWEEN ? AND ?
    GROUP BY g.name
    ORDER BY total_sales DESC
");
$games_stmt->bind_param("ss", $date_from, $date_to);
$games_stmt->execute();
$games_result = $games_stmt->get_result();
$sales_by_game = $games_result->fetch_all(MYSQLI_ASSOC);
$games_stmt->close();

// Get sales by payment method
$pm_stmt = $conn->prepare("
    SELECT 
        pm.name as payment_method_name,
        COUNT(t.id) as transaction_count,
        SUM(t.amount) as total_sales
    FROM transactions t
    JOIN payment_methods pm ON t.payment_method_id = pm.id
    WHERE t.status = 'success'
    AND DATE(t.transaction_time) BETWEEN ? AND ?
    GROUP BY pm.name
    ORDER BY total_sales DESC
");
$pm_stmt->bind_param("ss", $date_from, $date_to);
$pm_stmt->execute();
$pm_result = $pm_stmt->get_result();
$sales_by_payment_method = $pm_result->fetch_all(MYSQLI_ASSOC);
$pm_stmt->close();


// Sales data for chart (daily sales for selected range)
$chartLabels = [];
$chartData = [];
$daily_sales_stmt = $conn->prepare("
    SELECT DATE(transaction_time) as sale_date, SUM(amount) as daily_revenue 
    FROM transactions 
    WHERE status = 'success' AND DATE(transaction_time) BETWEEN ? AND ?
    GROUP BY sale_date 
    ORDER BY sale_date ASC
");
$daily_sales_stmt->bind_param("ss", $date_from, $date_to);
$daily_sales_stmt->execute();
$daily_sales_result = $daily_sales_stmt->get_result();

$daily_sales = [];
while($row = $daily_sales_result->fetch_assoc()) {
    $daily_sales[$row['sale_date']] = $row['daily_revenue'];
}
$daily_sales_stmt->close();

// Populate chart data for all days in the range
$current_date = strtotime($date_from);
$end_date = strtotime($date_to);

while ($current_date <= $end_date) {
    $date_str = date('Y-m-d', $current_date);
    $chartLabels[] = date('d M', $current_date);
    $chartData[] = isset($daily_sales[$date_str]) ? round($daily_sales[$date_str] / 1000000, 2) : 0; // Convert to millions
    $current_date = strtotime('+1 day', $current_date);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="style.css"> <style>
        /* General styling for admin pages - copied for consistency */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            margin: 0;
            display: flex;
        }
        .sidebar {
            width: 250px;
            background-color: #4e73df;
            color: white;
            padding: 20px;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #fff;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            margin-bottom: 15px;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background-color: #2e59d9;
        }
        .main-content {
            margin-left: 250px;
            flex-grow: 1;
            padding: 20px;
            width: calc(100% - 250px);
        }
        .header {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-info .avatar {
            width: 40px;
            height: 40px;
            background-color: #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            font-size: 1.2em;
        }
        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #555;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .table th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table tr:hover {
            background-color: #f1f1f1;
        }
        .badge {
            display: inline-block;
            padding: .35em .65em;
            font-size: .75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .375rem;
        }
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-info { background-color: #17a2b8; color: white; }

        /* Filter and Search styles */
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .filter-group button {
            padding: 10px 15px;
            background-color: #4e73df;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .filter-group button:hover {
            background-color: #2e59d9;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #e9f0f9;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .summary-card .title {
            font-size: 1em;
            color: #666;
            margin-bottom: 10px;
        }
        .summary-card .value {
            font-size: 1.8em;
            font-weight: bold;
            color: #4e73df;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="accounts.php"><i class="fas fa-users"></i> Manage Accounts</a></li>
            <li><a href="manage_product.php"><i class="fas fa-gamepad"></i> Manage Products</a></li>
            <li><a href="transaction_history.php"><i class="fas fa-history"></i> Transaction History</a></li>
            <li><a href="sales_report.php" class="active"><i class="fas fa-chart-line"></i> Sales Report</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Sales Report</h1>
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Sales Overview</h3>
            </div>
            <form method="GET" action="" class="filter-section">
                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn">Apply Filter</button>
                </div>
                <div class="filter-group">
                    <button type="button" class="btn" onclick="window.location.href='sales_report.php'">Reset Dates</button>
                </div>
            </form>

            <div class="summary-cards">
                <div class="summary-card">
                    <div class="title">Total Transactions</div>
                    <div class="value"><?php echo number_format($summary['total_transactions']); ?></div>
                </div>
                <div class="summary-card">
                    <div class="title">Total Sales</div>
                    <div class="value"><?php echo formatCurrency($summary['total_sales']); ?></div>
                </div>
                <div class="summary-card">
                    <div class="title">Average Sale</div>
                    <div class="value"><?php echo formatCurrency($summary['average_sale']); ?></div>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Daily Sales Chart</h3>
                </div>
                <canvas id="salesChart" style="max-height: 400px;"></canvas>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Sales By Game</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Game Name</th>
                                <th>Transactions</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_by_game)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No sales by game found for this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_by_game as $sale) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sale['game_name']); ?></td>
                                        <td><?php echo $sale['transaction_count']; ?></td>
                                        <td><?php echo formatCurrency($sale['total_sales']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Sales By Payment Method</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th>Transactions</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_by_payment_method)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No sales by payment method found for this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_by_payment_method as $sale) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sale['payment_method_name']); ?></td>
                                        <td><?php echo $sale['transaction_count']; ?></td>
                                        <td><?php echo formatCurrency($sale['total_sales']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var ctx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Sales (in Millions IDR)',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.5)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'IDR ' + value + 'M';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true // Show legend for the bar chart
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'IDR ' + (context.raw * 1000000).toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>