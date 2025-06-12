<?php
require_once 'config.php';
requireAuth();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_game'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_price = (float)$_POST['base_price'];
        $stock = (int)$_POST['stock'];
        
        $stmt = $conn->prepare("INSERT INTO games (name, description, base_price, stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $name, $description, $base_price, $stock);
        
        if ($stmt->execute()) {
            $success_message = "Game added successfully!";
        } else {
            $error_message = "Error adding game: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_game'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_price = (float)$_POST['base_price'];
        $stock = (int)$_POST['stock'];
        
        $stmt = $conn->prepare("UPDATE games SET name = ?, description = ?, base_price = ?, stock = ? WHERE id = ?");
        $stmt->bind_param("ssdii", $name, $description, $base_price, $stock, $id);
        
        if ($stmt->execute()) {
            $success_message = "Game updated successfully!";
        } else {
            $error_message = "Error updating game: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_game'])) {
        $id = (int)$_POST['id'];
        
        // Check if game has transactions
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE game_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            $error_message = "Cannot delete game with existing transactions!";
        } else {
            $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Game deleted successfully!";
            } else {
                $error_message = "Error deleting game: " . $conn->error;
            }
        }
    }
}

// Get games with pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if ($search) {
    $where_clause = "WHERE name LIKE ? OR description LIKE ?";
    $params = ["%$search%", "%$search%"];
    $types = 'ss';
}

// Get total games
$total_query = "SELECT COUNT(*) as total FROM games $where_clause";
if ($params) {
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->bind_param($types, ...$params);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
} else {
    $total_result = $conn->query($total_query);
}
$total = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Get games
$games_query = "SELECT * FROM games $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
if ($params) {
    $games_stmt = $conn->prepare($games_query);
    $games_stmt->bind_param($types, ...$params);
    $games_stmt->execute();
    $games = $games_stmt->get_result();
} else {
    $games = $conn->query($games_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Product | Game Top-Up MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        
        .content-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .product-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .product-table th, .product-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .product-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }
        
        .product-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #17a673;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: var(--dark);
            border-radius: 5px;
        }
        
        .pagination a:hover {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .current {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .results-info {
            margin-bottom: 15px;
            color: var(--secondary);
            font-size: 14px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            font-size: 20px;
            color: var(--dark);
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--secondary);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="transaction_history.php"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li class="active"><a href="manage_product.php"><i class="fas fa-boxes"></i> Manage Product</a></li>
                    <li><a href="sales_report.php"><i class="fas fa-chart-bar"></i> Sales Report</a></li>
                    <li><a href="accounts.php"><i class="fas fa-users"></i> Accounts</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="top-bar">
                <div class="search-box">
                    <input type="text" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" id="globalSearch">
                    <i class="fas fa-search"></i>
                </div>
                <div class="user-profile">
                    <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>

            <h1>Manage Products</h1>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Add Product Button -->
            <button class="btn btn-success" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Product
            </button>

            <!-- Products Table -->
            <div class="content-card">
                <div class="results-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> products
                </div>
                
                <div class="table-responsive">
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Base Price</th>
                                <th>Stock</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($games->num_rows > 0): ?>
                                <?php while($game = $games->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($game['id']); ?></td>
                                        <td><?php echo htmlspecialchars($game['name']); ?></td>
                                        <td><?php echo htmlspecialchars($game['description']); ?></td>
                                        <td>IDR <?php echo number_format($game['base_price'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($game['stock']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($game['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-primary" onclick="openEditModal(
                                                <?php echo $game['id']; ?>,
                                                '<?php echo addslashes($game['name']); ?>',
                                                '<?php echo addslashes($game['description']); ?>',
                                                <?php echo $game['base_price']; ?>,
                                                <?php echo $game['stock']; ?>
                                            )">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-danger" onclick="confirmDelete(<?php echo $game['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                                        <p>No products found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">&laquo; First</a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">&lsaquo; Previous</a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next &rsaquo;</a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Last &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Product</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="add_name">Game Name</label>
                    <input type="text" id="add_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="add_description">Description</label>
                    <textarea id="add_description" name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="add_base_price">Base Price (IDR)</label>
                    <input type="number" id="add_base_price" name="base_price" min="0" step="1000" required>
                </div>
                <div class="form-group">
                    <label for="add_stock">Stock</label>
                    <input type="number" id="add_stock" name="stock" min="0" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_game" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Product</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-group">
                    <label for="edit_name">Game Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_base_price">Base Price (IDR)</label>
                    <input type="number" id="edit_base_price" name="base_price" min="0" step="1000" required>
                </div>
                <div class="form-group">
                    <label for="edit_stock">Stock</label>
                    <input type="number" id="edit_stock" name="stock" min="0" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_game" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Delete</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <p>Are you sure you want to delete this product? This action cannot be undone.</p>
            <form method="POST" action="">
                <input type="hidden" id="delete_id" name="id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_game" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(id, name, description, base_price, stock) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_base_price').value = base_price;
            document.getElementById('edit_stock').value = stock;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function confirmDelete(id) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Global search functionality
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchValue = this.value;
                window.location.href = 'manage_product.php?search=' + encodeURIComponent(searchValue);
            }
        });
    </script>
</body>
</html>