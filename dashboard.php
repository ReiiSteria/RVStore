<?php
require_once 'config.php';
requireAuth();

// Get stats
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total_orders, SUM(amount) as total_sales FROM transactions WHERE status = 'success'");
$stats = $result->fetch_assoc();

$result = $conn->query("SELECT COUNT(DISTINCT user_id) as total_customers FROM transactions");
$stats['total_customers'] = $result->fetch_assoc()['total_customers'];

$result = $conn->query("SELECT SUM(stock) as total_stock FROM games");
$stats['total_stock'] = $result->fetch_assoc()['total_stock'];

// Get recent transactions
$transactions = $conn->query("
    SELECT t.id, g.name as game_name, t.amount, pm.name as payment_method, t.transaction_time, t.status 
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    JOIN payment_methods pm ON t.payment_method_id = pm.id
    ORDER BY t.transaction_time DESC 
    LIMIT 5
");

// Get top games
$top_games = $conn->query("
    SELECT g.name, COUNT(t.id) as transaction_count 
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    GROUP BY g.name 
    ORDER BY transaction_count DESC 
    LIMIT 5
");

// Get sales data for chart
$sales_data = $conn->query("
    SELECT 
        DATE_FORMAT(transaction_time, '%Y-%m') as month,
        SUM(amount) as total_sales
    FROM transactions
    WHERE status = 'success'
    GROUP BY DATE_FORMAT(transaction_time, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | RVStore </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #3a5bc7;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #4e73df;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            transition: all 0.3s;
        }
        
        .logo {
            text-align: center;
            padding: 10px 20px;
            font-size: 20px;
            font-weight: bold;
            color: white;
            margin-bottom: 30px;
        }
        
        .sidebar nav ul {
            list-style: none;
        }
        
        .sidebar nav ul li {
            margin-bottom: 5px;
        }
        
        .sidebar nav ul li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar nav ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar nav ul li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar nav ul li.active a {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 10px 0;
        }
        
        .search-box {
            position: relative;
            width: 300px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-profile i {
            font-size: 24px;
            color: var(--secondary);
        }
        
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--secondary);
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card p {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }
        
        /* Content Rows */
        .content-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .content-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .content-card.wide {
            grid-column: span 2;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-header h2 {
            font-size: 18px;
            color: var(--dark);
        }
        
        .period-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
            font-size: 14px;
        }
        
        /* Chart Placeholder */
        .chart-placeholder {
            height: 300px;
            position: relative;
        }
        
        /* Game Distribution */
        .game-distribution {
            margin-top: 20px;
        }
        
        .game-item {
            margin-bottom: 15px;
        }
        
        .game-item span {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .progress-bar {
            height: 10px;
            background-color: var(--primary);
            border-radius: 5px;
        }
        
        /* Country List */
        .country-list {
            list-style: none;
        }
        
        .country-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .country-list li:last-child {
            border-bottom: none;
        }
        
        .percentage {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Activity Chart */
        .activity-chart {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 20px;
            margin-top: 20px;
        }
        
        .activity-bar {
            flex: 1;
            background-color: var(--primary);
            border-radius: 5px;
            position: relative;
        }
        
        .time-labels {
            position: absolute;
            bottom: -25px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
        }
        
        /* Recent Transactions */
        .recent-transactions {
            margin-top: 20px;
        }
        
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .transaction-table th, .transaction-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .transaction-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-badge.pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .status-badge.success {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status-badge.failed {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .view-all {
            text-align: right;
            margin-top: 15px;
        }
        
        .view-all a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="logo">RVStore</div>
            <nav>
                <ul>
                    <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="transaction_history.php"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li><a href="manage_product.php"><i class="fas fa-boxes"></i> Manage Product</a></li>
                    <li><a href="sales_report.php"><i class="fas fa-chart-bar"></i> Sales Report</a></li>
                    <li><a href="accounts.php"><i class="fas fa-users"></i> Accounts</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="top-bar">
                <div class="search-box">
                    <input type="text" placeholder="Search...">
                    <i class="fas fa-search"></i>
                </div>
                <div class="user-profile">
                    <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>

            <h1>Dashboard</h1>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Sales</h3>
                    <p>IDR <?php echo number_format($stats['total_sales'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <p><?php echo number_format($stats['total_orders'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Customers</h3>
                    <p><?php echo number_format($stats['total_customers'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Stock</h3>
                    <p><?php echo number_format($stats['total_stock'] ?? 0, 0, ',', '.'); ?></p>
                </div>
            </div>

            <!-- Recent Transactions Section -->
            <div class="content-card wide">
                <h2>Recent Transactions</h2>
                <div class="table-responsive">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Game</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions->num_rows > 0): ?>
                                <?php while($row = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['game_name']); ?></td>
                                        <td>IDR <?php echo number_format($row['amount'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($row['transaction_time'])); ?></td>
                                        <td><span class="status-badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No recent transactions</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="view-all">
                    <a href="transaction_history.php">View All Transactions →</a>
                </div>
            </div>

            <!-- Main Content Sections -->
            <div class="content-row">
                <!-- Sales Report Section -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Sales Report</h2>
                        <select class="period-select">
                            <option>Last 12 Months</option>
                            <option>Last 6 Months</option>
                            <option>Last 3 Months</option>
                        </select>
                    </div>
                    <div class="chart-placeholder">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Game Distribution Section -->
                <div class="content-card">
                    <h2>Top Games</h2>
                    <div class="game-distribution">
                        <?php if ($top_games->num_rows > 0): ?>
                            <?php 
                            $max_count = 0;
                            $top_games_data = [];
                            while($game = $top_games->fetch_assoc()) {
                                $top_games_data[] = $game;
                                if ($game['transaction_count'] > $max_count) {
                                    $max_count = $game['transaction_count'];
                                }
                            }
                            ?>
                            <?php foreach ($top_games_data as $game): ?>
                                <div class="game-item">
                                    <span><?php echo htmlspecialchars($game['name']); ?></span>
                                    <div class="progress-bar" style="width: <?php echo ($game['transaction_count'] / $max_count) * 100; ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No game data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="content-row">
                <!-- Users by Country Section -->
                <div class="content-card">
                    <h2>Users by Country</h2>
                    <ul class="country-list">
                        <li>
                            <span>Indonesia</span>
                            <span class="percentage">65%</span>
                        </li>
                        <li>
                            <span>Malaysia</span>
                            <span class="percentage">20%</span>
                        </li>
                        <li>
                            <span>Singapore</span>
                            <span class="percentage">15%</span>
                        </li>
                    </ul>
                </div>

                <!-- Activity Section -->
                <div class="content-card">
                    <h2>Activity</h2>
                    <div class="activity-chart">
                        <div class="activity-bar" style="height: 60%"></div>
                        <div class="activity-bar" style="height: 30%"></div>
                        <div class="activity-bar" style="height: 45%"></div>
                        <div class="activity-bar" style="height: 20%"></div>
                        <div class="time-labels">
                            <span>00</span>
                            <span>06</span>
                            <span>12</span>
                            <span>18</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    $sales_data_array = [];
                    while($row = $sales_data->fetch_assoc()) {
                        $sales_data_array[] = $row;
                        echo "'" . date('M Y', strtotime($row['month'] . '-01')) . "',";
                    }
                    ?>
                ].reverse(),
                datasets: [{
                    label: 'Sales (IDR)',
                    data: [
                        <?php 
                        foreach (array_reverse($sales_data_array) as $row) {
                            echo ($row['total_sales'] / 1000000) . ",";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: '#fff',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'IDR ' + value + 'M';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'IDR ' + (context.raw * 1000000).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>

    <button class="chatbot-toggler" onclick="toggleChatbot()">
    <i class="fas fa-comment-dots"></i>
    <span class="material-symbols-outlined">close</span>
</button>
<div class="chatbot-popup">
    <div class="chatbot-header">
        <h2>RVStore AI Assistant</h2>
        <span class="close-btn" onclick="toggleChatbot()">&times;</span>
    </div>
    <div class="chatbot-body" id="chatbot-messages">
        <div class="chatbot-message bot-message">Hello! How can I help you today?</div>
    </div>
    <div class="chatbot-footer">
        <input type="text" id="chatbot-input" placeholder="Type your message...">
        <button id="chatbot-send-btn"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<style>
    /* Chatbot CSS */
    .chatbot-toggler {
        position: fixed;
        bottom: 30px;
        right: 30px;
        height: 50px;
        width: 50px;
        color: #fff;
        background: #4e73df;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
        z-index: 999; /* Ensure it's on top */
        border: none;
        font-size: 1.5rem; /* Larger icon */
    }
    .chatbot-toggler:hover {
        background: #2e59d9;
    }
    .chatbot-toggler span {
        position: absolute;
    }
    .chatbot-toggler .fas.fa-comment-dots {
        opacity: 1;
    }
    .chatbot-toggler .material-symbols-outlined {
        opacity: 0;
    }
    .chatbot-toggler.show .fas.fa-comment-dots {
        opacity: 0;
    }
    .chatbot-toggler.show .material-symbols-outlined {
        opacity: 1;
    }

    .chatbot-popup {
        position: fixed;
        bottom: 90px;
        right: 30px;
        width: 350px;
        height: 450px;
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: scale(0.5);
        opacity: 0;
        pointer-events: none;
        transition: all 0.3s ease;
        z-index: 999;
    }
    .chatbot-popup.show {
        transform: scale(1);
        opacity: 1;
        pointer-events: auto;
    }

    .chatbot-header {
        background: #4e73df;
        color: #fff;
        padding: 15px;
        text-align: center;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        position: relative;
    }
    .chatbot-header h2 {
        margin: 0;
        font-size: 1.2em;
    }
    .chatbot-header .close-btn {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 1.5em;
        cursor: pointer;
        color: #fff;
    }

    .chatbot-body {
        flex-grow: 1;
        padding: 15px;
        overflow-y: auto;
        background-color: #f0f2f5;
        border-bottom: 1px solid #eee;
    }

    .chatbot-message {
        margin-bottom: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        max-width: 80%;
        word-wrap: break-word;
    }
    .user-message {
        background-color: #4e73df;
        color: #fff;
        align-self: flex-end; /* Push to the right */
        margin-left: auto;
    }
    .bot-message {
        background-color: #e0e0e0;
        color: #333;
        align-self: flex-start; /* Push to the left */
        margin-right: auto;
    }

    .chatbot-footer {
        display: flex;
        padding: 15px;
        background: #fff;
        border-bottom-left-radius: 15px;
        border-bottom-right-radius: 15px;
    }
    .chatbot-footer input {
        flex-grow: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 20px;
        margin-right: 10px;
        font-size: 0.9em;
    }
    .chatbot-footer button {
        background: #4e73df;
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 1.1em;
    }
    .chatbot-footer button:hover {
        background: #2e59d9;
    }
</style>

<script>
    const chatbotToggler = document.querySelector(".chatbot-toggler");
    const chatbotPopup = document.querySelector(".chatbot-popup");
    const chatbotInput = document.getElementById("chatbot-input");
    const chatbotSendBtn = document.getElementById("chatbot-send-btn");
    const chatbotMessages = document.getElementById("chatbot-messages");

    function toggleChatbot() {
        chatbotPopup.classList.toggle("show");
        chatbotToggler.classList.toggle("show");
        if (chatbotPopup.classList.contains("show")) {
            chatbotInput.focus();
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight; // Scroll to bottom on open
        }
    }

    async function sendMessage() {
        const message = chatbotInput.value.trim();
        if (message === "") return;

        appendMessage(message, 'user-message');
        chatbotInput.value = "";
        chatbotInput.disabled = true;
        chatbotSendBtn.disabled = true;

        appendMessage('Typing...', 'bot-message', true); // Show typing indicator

        try {
            const response = await fetch('chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: message })
            });
            const data = await response.json();

            // Remove typing indicator
            const typingIndicator = chatbotMessages.querySelector('.typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }

            if (data.reply) {
                appendMessage(data.reply, 'bot-message');
            } else {
                appendMessage('Error: ' + (data.error || 'Something went wrong.'), 'bot-message');
            }
        } catch (error) {
            console.error('Error sending message to API:', error);
            const typingIndicator = chatbotMessages.querySelector('.typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
            appendMessage('Error: Could not connect to the assistant.', 'bot-message');
        } finally {
            chatbotInput.disabled = false;
            chatbotSendBtn.disabled = false;
            chatbotInput.focus();
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight; // Scroll to bottom
        }
    }

    function appendMessage(message, type, isTyping = false) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('chatbot-message', type);
        if (isTyping) {
            messageDiv.classList.add('typing-indicator');
            messageDiv.innerHTML = `
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            `;
        } else {
            messageDiv.textContent = message;
        }
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight; // Auto-scroll to latest message
    }

    chatbotSendBtn.addEventListener('click', sendMessage);
    chatbotInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
</script>
</body>
</html>