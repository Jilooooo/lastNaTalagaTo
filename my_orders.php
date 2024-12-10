<?php
session_start();
$db = new mysqli('localhost', 'root', '', 'wastewise');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Function to get orders grouped by status
function getRecentOrdersGrouped($user_id, $limit = 50) {
    global $db;
    $query = "SELECT o.id, o.total_amount, o.status, o.created_at, 
              GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name, '|||', p.image) SEPARATOR '---') as products
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN products p ON oi.product_id = p.id
              WHERE o.user_id = ? AND o.archived = 0
              GROUP BY o.id
              ORDER BY o.created_at DESC
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);

    $grouped_orders = [
        'all' => $orders,
        'to_pay' => [],
        'to_ship' => [],
        'to_receive' => [],
        'completed' => [],
        'cancelled' => [],
        'return_refund' => []
    ];

    foreach ($orders as $order) {
        switch ($order['status']) {
            case 'pending':
                $grouped_orders['to_pay'][] = $order;
                break;
            case 'processing':
                $grouped_orders['to_ship'][] = $order;
                break;
            case 'shipped':
                $grouped_orders['to_receive'][] = $order;
                break;
            case 'delivered':
                $grouped_orders['completed'][] = $order;
                break;
            case 'cancelled':
                $grouped_orders['cancelled'][] = $order;
                break;
            case 'return_pending':
            case 'return_approved':
            case 'refunded':
                $grouped_orders['return_refund'][] = $order;
                break;
        }
    }

    return $grouped_orders;
}

$grouped_orders = getRecentOrdersGrouped($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Wastewise E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Global Styles */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 5.5rem;
            background-color: #2f855a;
            z-index: 50;
        }

        /* Sidebar */
        #sidebar {
            position: fixed;
            top: 10%;
            left: 0;
            width: 16rem;
            height: calc(100% - 4rem);
            background-color: #2f855a;
            color: white;
            overflow-y: auto;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            z-index: 40;
        }

        body.sidebar-open #sidebar {
            transform: translateX(0);
        }

        /* Main Content */
        main {
            margin-top: 0;
            margin-left: 0;
            padding: 1rem;
            flex: 1;
            overflow-y: auto;
            transition: margin-left 0.3s ease-in-out;
        }

        body.sidebar-open main {
            margin-left: 16rem;
        }

    /* Footer */
    footer {
        background-color: #2f855a;
        color: white;
        text-align: center;
        padding: 1rem 0;
        z-index: 30;
    }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Header -->
    <header class="bg-green-700 text-white py-6 sticky top-0 z-30">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <h1 class="text-3xl font-bold mb-4 md:mb-0">Wastewise E-commerce</h1>
            </div>
        </div>
    </header>

    <!-- Sidebar Toggle Button -->
    <button class="fixed top-4 left-4 z-50 bg-green-600 text-white p-2 rounded-full shadow-lg" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav id="sidebar" class="fixed bg-green-800 text-white p-5">
        <div class="flex flex-col h-full">
            <!-- Add the Welcome message at the top -->
            <div class="flex items-center justify-center py-2 mb-4">
                <span class="bg-green-900 text-white py-2 px-4 rounded-full text-center">
                    <i class="fas fa-user mr-2"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
            
            <!-- Sidebar links -->
            <div class="flex-grow mt-4"> <!-- Add margin for spacing -->
                <a href="home.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
                <a href="my_orders.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-shopping-bag mr-2"></i> My Orders
                </a>
                <a href="cart.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-shopping-cart mr-2"></i> Cart
                </a>
            </div>

            <!-- Logout link -->
            <div>
                <a href="logout.php" class="block py-2 px-4 hover:bg-green-700 rounded transition duration-200">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <h1 class="text-3xl font-bold mb-8 text-center text-green-800">My Orders</h1>
        
        <!-- Order Status Tabs -->
        <div class="mb-8 overflow-x-auto">
            <nav class="flex border-b border-gray-200" aria-label="Order status">
                <?php
                $tabs = [
                    'all' => 'All',
                    'to_pay' => 'To Pay',
                    'to_ship' => 'To Ship',
                    'to_receive' => 'To Receive',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'return_refund' => 'Return/Refund'
                ];
                ?>
                <?php foreach ($tabs as $key => $label): ?>
                    <button onclick="switchTab('<?= $key ?>')" 
                            class="tab-button flex-shrink-0 py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap
                                   <?= $key === 'all' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>"
                            data-tab="<?= $key ?>">
                        <?= $label ?>
                        <?php if (!empty($grouped_orders[$key])): ?>
                            <span class="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">
                                <?= count($grouped_orders[$key]) ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Order Lists -->
        <?php foreach ($tabs as $key => $label): ?>
            <div id="<?= $key ?>-orders" class="order-section <?= $key === 'all' ? 'block' : 'hidden' ?>">
                <?php if (empty($grouped_orders[$key])): ?>
                    <p class="text-center text-gray-600">No orders found in this section.</p>
                <?php else: ?>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($grouped_orders[$key] as $order): 
                            $products = explode('---', $order['products']);
                        ?>
                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <h4 class="text-xl font-semibold">Order #<?= $order['id'] ?></h4>
                                    <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full
                                        <?php
                                        switch($order['status']) {
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'processing': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'shipped': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                            case 'return_pending':
                                            case 'return_approved':
                                            case 'refunded':
                                                echo 'bg-orange-100 text-orange-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </span>
                                </div>
                                <p class="text-gray-600 mb-2">Date: <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
                                <p class="text-gray-600 mb-4">Total: ₱<?= number_format($order['total_amount'], 2) ?></p>
                                <div class="mb-4">
                                    <h5 class="font-semibold mb-2">Products:</h5>
                                    <div class="grid grid-cols-2 gap-2">
                                        <?php 
                                        $displayedProducts = array_slice($products, 0, 4);
                                        foreach ($displayedProducts as $product):
                                            list($productInfo, $productImage) = explode('|||', $product);
                                        ?>
                                            <div class="flex items-center space-x-2">
                                                <img src="<?= htmlspecialchars($productImage) ?>" alt="Product" class="w-10 h-10 object-cover rounded">
                                                <span class="text-sm"><?= htmlspecialchars($productInfo) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($products) > 4): ?>
                                        <p class="text-sm text-gray-500 mt-2">and <?= count($products) - 4 ?> more item(s)</p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex justify-between items-center">
                                    <a href="order_details.php?id=<?= $order['id'] ?>" class="text-blue-600 hover:text-blue-800">View Details</a>
                                    <?php if ($order['status'] == 'processing' || $order['status'] == 'shipped'): ?>
                                        <button onclick="cancelOrder(<?= $order['id'] ?>)" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Cancel Order</button>
                                    <?php elseif ($order['status'] == 'delivered'): ?>
                                        <button onclick="requestReturn(<?= $order['id'] ?>)" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">Request Return</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Wastewise E-commerce. All rights reserved.</p>
            <p>Committed to a sustainable future through recycling and eco-friendly shopping.</p>
        </div>
    </footer>

    <script>
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }

        function switchTab(tabId) {
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                if (button.dataset.tab === tabId) {
                    button.classList.remove('border-transparent', 'text-gray-500');
                    button.classList.add('border-green-500', 'text-green-600');
                } else {
                    button.classList.remove('border-green-500', 'text-green-600');
                    button.classList.add('border-transparent', 'text-gray-500');
                }
            });

            // Show/hide order sections
            document.querySelectorAll('.order-section').forEach(section => {
                section.classList.toggle('hidden', section.id !== `${tabId}-orders`);
            });
        }

        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order cancelled successfully');
                        location.reload();
                    } else {
                        alert('Failed to cancel the order. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }

        function requestReturn(orderId) {
            if (confirm('Are you sure you want to request a return for this order?')) {
                fetch('request_return.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Return request submitted successfully');
                        location.reload();
                    } else {
                        alert('Failed to submit return request. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
    </script>
</body>
</html>
