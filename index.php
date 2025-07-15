<?php
// Start output buffering
ob_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_recommendations');


// Stripe Configuration (replace with your test keys)
define('STRIPE_KEY', 'sk_test_51P...'); // Your test secret key
define('STRIPE_PUBLIC', 'pk_test_51P...'); // Your test publishable key

// Connect to Database
function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Create database tables if they don't exist

function initialize_database() {
    $conn = db_connect();
    
    // Users table
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Products table
    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        category VARCHAR(50) NOT NULL,
        image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // User ratings table
    $conn->query("CREATE TABLE IF NOT EXISTS user_ratings (
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, product_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // User views table (for tracking views)
    $conn->query("CREATE TABLE IF NOT EXISTS user_views (
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        view_count INT DEFAULT 1,
        last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, product_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    
    // Cart table
    $conn->query("CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    
    // Orders table
    $conn->query("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        stripe_payment_intent_id VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Order items table
    $conn->query("CREATE TABLE IF NOT EXISTS order_items (
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    
    // Messages table
    $conn->query("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Sample data if tables are empty
    if ($conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0] == 0) {
        $sample_products = [
            ["Smartphone X", "Latest smartphone with 6.5\" display, 128GB storage, and 48MP camera", 699.99, "Electronics", "smartphone.jpg"],
            ["Laptop Pro", "Powerful laptop with 16GB RAM, 512GB SSD, and Intel i7 processor", 1299.99, "Electronics", "laptop.jpg"],
            ["Running Shoes", "Comfortable running shoes with memory foam soles", 89.99, "Footwear", "shoes.jpg"],
            ["Wireless Headphones", "Noise-cancelling headphones with 30h battery life", 199.99, "Electronics", "headphones.jpg"],
            ["Coffee Maker", "Automatic coffee machine with programmable timer", 79.99, "Home", "coffee.jpg"],
            ["Desk Lamp", "LED desk lamp with adjustable brightness and color temperature", 39.99, "Home", "lamp.jpg"],
            ["Water Bottle", "Insulated stainless steel bottle keeps drinks cold for 24h", 24.99, "Kitchen", "bottle.jpg"],
            ["Backpack", "Durable backpack with laptop compartment and water-resistant", 59.99, "Accessories", "backpack.jpg"],
            ["Fitness Tracker", "Smartwatch with heart rate monitoring and GPS", 129.99, "Electronics", "watch.jpg"],
            ["Bluetooth Speaker", "Portable speaker with 12h battery and rich bass", 79.99, "Electronics", "speaker.jpg"],
            ["Novel: The Silent Planet", "Bestselling science fiction novel by C.S. Lewis", 14.99, "Books", "book1.jpg"],
            ["Cookbook: Healthy Recipes", "Collection of 100+ nutritious recipes", 19.99, "Books", "book2.jpg"],
            ["Yoga Mat", "Non-slip yoga mat with carrying strap", 29.99, "Fitness", "mat.jpg"],
            ["Resistance Bands", "Set of 5 resistance bands for home workouts", 34.99, "Fitness", "bands.jpg"],
            ["Scented Candle", "Lavender scented candle with 40h burn time", 16.99, "Home", "candle.jpg"]
        ];
        
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, category, image_url) VALUES (?, ?, ?, ?, ?)");
        foreach ($sample_products as $product) {
            $stmt->bind_param("ssdss", $product[0], $product[1], $product[2], $product[3], $product[4]);
            $stmt->execute();
        }
        
        // Create sample users
        $pass1 = password_hash('user123', PASSWORD_DEFAULT);
        $pass2 = password_hash('test456', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password) VALUES 
            ('john_doe', '$pass1'),
            ('jane_smith', '$pass2')
        ");
        
        // Create sample ratings
        $conn->query("INSERT INTO user_ratings (user_id, product_id, rating) VALUES
            (1, 3, 5), (1, 5, 4), (1, 8, 5), (1, 10, 3),
            (2, 1, 5), (2, 4, 4), (2, 6, 5), (2, 9, 4), (2, 12, 5)
        ");
        
        // Create sample views
        $conn->query("INSERT INTO user_views (user_id, product_id, view_count) VALUES
            (1, 1, 3), (1, 2, 2), (1, 3, 5), (1, 4, 1), (1, 5, 4),
            (2, 3, 2), (2, 5, 3), (2, 7, 1), (2, 10, 4), (2, 13, 2)
        ");
    }
    
    $conn->close();
}


// Recommendation System Class
class RecommendationSystem {
    private $conn;
    
    public function __construct() {
        $this->conn = db_connect();
    }
    
    /**
     * Calculate Pearson correlation coefficient between two users
     */

    private function calculateSimilarity($user1Id, $user2Id) {
        $stmt = $this->conn->prepare("
            SELECT 
                p1.product_id,
                p1.rating AS rating1,
                p2.rating AS rating2
            FROM user_ratings p1
            INNER JOIN user_ratings p2 
                ON p1.product_id = p2.product_id
            WHERE p1.user_id = ? 
                AND p2.user_id = ?
        ");
        
        $stmt->bind_param("ii", $user1Id, $user2Id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ratings = $result->fetch_all(MYSQLI_ASSOC);
        
        if (count($ratings) < 2) return 0; // Insufficient common ratings
        
        $sum1 = $sum2 = $sum1Sq = $sum2Sq = $pSum = 0;
        
        foreach ($ratings as $row) {
            $sum1 += $row['rating1'];
            $sum2 += $row['rating2'];
            $sum1Sq += pow($row['rating1'], 2);
            $sum2Sq += pow($row['rating2'], 2);
            $pSum += $row['rating1'] * $row['rating2'];
        }
        
        $num = $pSum - ($sum1 * $sum2 / count($ratings));
        $den = sqrt(($sum1Sq - pow($sum1, 2) / count($ratings)) * 
                   ($sum2Sq - pow($sum2, 2) / count($ratings)));
        
        return $den != 0 ? $num / $den : 0;
    }
/**
     * Get recommendations for a user
     */
    public function getRecommendations($userId, $limit = 5) {
        // Step 1: Get all users
        $result = $this->conn->query("SELECT id FROM users WHERE id != $userId");
        $allUsers = [];
        while ($row = $result->fetch_assoc()) {
            $allUsers[] = $row['id'];
        }
        
        // Step 2: Calculate similarity scores
        $similarities = [];
        foreach ($allUsers as $otherUserId) {
            $similarity = $this->calculateSimilarity($userId, $otherUserId);
            if ($similarity > 0) {
                $similarities[$otherUserId] = $similarity;
            }
        }
        
        // Sort by similarity (descending)
        arsort($similarities);
        
        // Step 3: Get top similar users
        $topSimilarUsers = array_slice($similarities, 0, 10, true);
        
        if (empty($topSimilarUsers)) return [];
        
        // Step 4: Find products rated by similar users that target user hasn't rated
        $stmt = $this->conn->prepare("
            SELECT product_id, rating 
            FROM user_ratings 
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRatings = [];
        while ($row = $result->fetch_assoc()) {
            $userRatings[$row['product_id']] = $row['rating'];
        }
        
        $productScores = [];
        $totalSimilarity = [];
        
        foreach ($topSimilarUsers as $otherUserId => $similarity) {
            $stmt = $this->conn->prepare("
                SELECT product_id, rating 
                FROM user_ratings 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $otherUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $ratings = [];
            while ($row = $result->fetch_assoc()) {
                $ratings[$row['product_id']] = $row['rating'];
            }
            
            foreach ($ratings as $productId => $rating) {
                // Skip products the user has already rated
                if (isset($userRatings[$productId])) continue;
                
                if (!isset($productScores[$productId])) {
                    $productScores[$productId] = 0;
                    $totalSimilarity[$productId] = 0;
                }
                
                // Weighted sum of ratings
                $productScores[$productId] += $rating * $similarity;
                $totalSimilarity[$productId] += $similarity;
            }
        }
        
        // Step 5: Calculate weighted average scores
        $recommendations = [];
        foreach ($productScores as $productId => $score) {
            if ($totalSimilarity[$productId] > 0) {
                $weightedScore = $score / $totalSimilarity[$productId];
                $recommendations[$productId] = $weightedScore;
            }
        }
        
        // Step 6: Sort by score (descending) and return top recommendations
        arsort($recommendations);
        return array_slice($recommendations, 0, $limit, true);
    }
    
    // Get popular products based on views
    public function getPopularProducts($limit = 5) {
        $result = $this->conn->query("
            SELECT product_id, SUM(view_count) as total_views 
            FROM user_views 
            GROUP BY product_id 
            ORDER BY total_views DESC 
            LIMIT $limit
        ");
        
        $popular = [];
        while ($row = $result->fetch_assoc()) {
            $popular[] = $row['product_id'];
        }
        return $popular;
    }
    
    // Get recently viewed products for a user
    public function getRecentlyViewed($userId, $limit = 5) {
        $stmt = $this->conn->prepare("
            SELECT product_id 
            FROM user_views 
            WHERE user_id = ? 
            ORDER BY last_viewed DESC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recent = [];
        while ($row = $result->fetch_assoc()) {
            $recent[] = $row['product_id'];
        }
        return $recent;
    }
    
    // Record a product view
    public function recordView($userId, $productId) {
        $stmt = $this->conn->prepare("
            INSERT INTO user_views (user_id, product_id) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE 
                view_count = view_count + 1,
                last_viewed = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("ii", $userId, $productId);
        $stmt->execute();
    }
}

// User Authentication Functions
function register_user($username, $password) {
    $conn = db_connect();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);
    
    if ($stmt->execute()) {
        return true;
    }
    return false;
}

function login_user($username, $password) {
    $conn = db_connect();
    
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            return true;
        }
    }
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function logout_user() {
    session_unset();
    session_destroy();
}

// Product Functions
function get_product($id) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function get_all_products() {
    $conn = db_connect();
    $result = $conn->query("SELECT * FROM products");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    return $products;
}

function get_products_by_category($category) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT * FROM products WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    return $products;
}

function rate_product($userId, $productId, $rating) {
    $conn = db_connect();
    $stmt = $conn->prepare("
        INSERT INTO user_ratings (user_id, product_id, rating) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE rating = ?
    ");
    $stmt->bind_param("iiii", $userId, $productId, $rating, $rating);
    return $stmt->execute();
}

function get_user_rating($userId, $productId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT rating FROM user_ratings WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['rating'];
    }
    return null;
}

function get_categories() {
    $conn = db_connect();
    $result = $conn->query("SELECT DISTINCT category FROM products");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
    return $categories;
}

// Cart Functions
function add_to_cart($userId, $productId, $quantity = 1) {
    $conn = db_connect();
    
    // Check if product already in cart
    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update quantity
        $row = $result->fetch_assoc();
        $newQuantity = $row['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $newQuantity, $row['id']);
        return $stmt->execute();
    } else {
        // Add new item
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $userId, $productId, $quantity);
        return $stmt->execute();
    }
}

function remove_from_cart($userId, $cartItemId) {
    $conn = db_connect();
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cartItemId, $userId);
    return $stmt->execute();
}

function update_cart_item($userId, $cartItemId, $quantity) {
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $quantity, $cartItemId, $userId);
    return $stmt->execute();
}

function get_cart_items($userId) {
    $conn = db_connect();
    $stmt = $conn->prepare("
        SELECT c.id AS cart_id, p.id, p.name, p.price, c.quantity, p.image_url 
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    return $items;
}

function get_cart_total($userId) {
    $items = get_cart_items($userId);
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function clear_cart($userId) {
    $conn = db_connect();
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    return $stmt->execute();
}

// Order Functions
function create_order($userId, $paymentIntentId, $amount) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO orders (user_id, stripe_payment_intent_id, amount) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $userId, $paymentIntentId, $amount);
    $stmt->execute();
    return $conn->insert_id;
}

function add_order_items($orderId, $items) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    
    foreach ($items as $item) {
        $stmt->bind_param("iiid", $orderId, $item['id'], $item['quantity'], $item['price']);
        $stmt->execute();
    }
}

// Contact Functions
function send_message($name, $email, $subject, $message) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $subject, $message);
    return $stmt->execute();
}

// Start session
session_start();

// Initialize database
initialize_database();

// Handle logout
if (isset($_GET['logout'])) {
    logout_user();
    header("Location: index.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                if (!empty($_POST['username']) && !empty($_POST['password']) && $_POST['password'] === $_POST['confirm_password']) {
                    if (register_user($_POST['username'], $_POST['password'])) {
                        login_user($_POST['username'], $_POST['password']);
                        header("Location: index.php");
                        exit;
                    }
                }
                break;
                
            case 'login':
                if (!empty($_POST['username']) && !empty($_POST['password'])) {
                    if (login_user($_POST['username'], $_POST['password'])) {
                        header("Location: index.php");
                        exit;
                    }
                }
                break;
                
            case 'rate':
                if (is_logged_in() && isset($_POST['product_id'], $_POST['rating'])) {
                    rate_product($_SESSION['user_id'], $_POST['product_id'], $_POST['rating']);
                    header("Location: ?page=product&id=" . $_POST['product_id']);
                    exit;
                }
                break;
                
            case 'add_to_cart':
                if (is_logged_in() && isset($_POST['product_id'])) {
                    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
                    add_to_cart($_SESSION['user_id'], $_POST['product_id'], $quantity);
                    header("Location: ?page=cart");
                    exit;
                }
                break;
                
            case 'update_cart':
                if (is_logged_in() && isset($_POST['cart_id'], $_POST['quantity'])) {
                    update_cart_item($_SESSION['user_id'], $_POST['cart_id'], $_POST['quantity']);
                    header("Location: ?page=cart");
                    exit;
                }
                break;
                
            case 'remove_from_cart':
                if (is_logged_in() && isset($_POST['cart_id'])) {
                    remove_from_cart($_SESSION['user_id'], $_POST['cart_id']);
                    header("Location: ?page=cart");
                    exit;
                }
                break;
                
            case 'checkout':
                if (is_logged_in()) {
                    header("Location: ?page=checkout");
                    exit;
                }
                break;
                
            case 'send_message':
                if (isset($_POST['name'], $_POST['email'], $_POST['subject'], $_POST['message'])) {
                    send_message(
                        htmlspecialchars($_POST['name']),
                        htmlspecialchars($_POST['email']),
                        htmlspecialchars($_POST['subject']),
                        htmlspecialchars($_POST['message'])
                    );
                    $_SESSION['message_sent'] = true;
                    header("Location: ?page=contact");
                    exit;
                }
                break;
        }
    }
}

// Get current page
$page = 'home';
if (isset($_GET['page'])) {
    $page = $_GET['page'];
}

// Define all page functions
function home_page() {
    ob_start();
    ?>
    <section class="hero">
        <div class="container">
            <h1>Smart Shopping Experience</h1>
            <p>Discover products tailored to your preferences with our intelligent recommendation system</p>
            <a href="?page=products" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Browse Products</a>
        </div>
    </section>
    
    <div class="container">
        <?php if (is_logged_in()): 
            $recSystem = new RecommendationSystem();
            $recommendations = $recSystem->getRecommendations($_SESSION['user_id'], 5);
            ?>
            <section class="recommendation-section">
                <h2 class="section-title">Recommended For You</h2>
                <p>Products we think you'll love based on your preferences</p>
                <div class="recommendation-grid">
                    <?php 
                    if (!empty($recommendations)) {
                        foreach ($recommendations as $productId => $score) {
                            $product = get_product($productId);
                            if ($product) {
                                ?>
                                <a href="?page=product&id=<?= $product['id'] ?>" class="recommendation-card">
                                    <div class="recommendation-image">
                                        <div style="width:100%;height:100%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-weight:bold;font-size:14px;">
                                            <?= substr($product['name'], 0, 20) ?>...
                                        </div>
                                    </div>
                                    <div class="recommendation-info">
                                        <div class="recommendation-name"><?= $product['name'] ?></div>
                                        <div class="recommendation-price">$<?= $product['price'] ?></div>
                                        <div class="recommendation-score">Match: <?= number_format($score * 100, 1) ?>%</div>
                                    </div>
                                </a>
                                <?php
                            }
                        }
                    } else {
                        echo "<p>Rate some products to get personalized recommendations!</p>";
                    }
                    ?>
                </div>
            </section>
            
            <section class="recommendation-section">
                <h2 class="section-title">Recently Viewed</h2>
                <div class="recommendation-grid">
                    <?php 
                    $recentlyViewed = $recSystem->getRecentlyViewed($_SESSION['user_id'], 5);
                    if (!empty($recentlyViewed)) {
                        foreach ($recentlyViewed as $productId) {
                            $product = get_product($productId);
                            if ($product) {
                                ?>
                                <a href="?page=product&id=<?= $product['id'] ?>" class="recommendation-card">
                                    <div class="recommendation-image">
                                        <div style="width:100%;height:100%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-weight:bold;font-size:14px;">
                                            <?= substr($product['name'], 0, 20) ?>...
                                        </div>
                                    </div>
                                    <div class="recommendation-info">
                                        <div class="recommendation-name"><?= $product['name'] ?></div>
                                        <div class="recommendation-price">$<?= $product['price'] ?></div>
                                    </div>
                                </a>
                                <?php
                            }
                        }
                    } else {
                        echo "<p>You haven't viewed any products yet.</p>";
                    }
                    ?>
                </div>
            </section>
        <?php else: ?>
            <section class="recommendation-section">
                <h2 class="section-title">Popular Products</h2>
                <p>Most viewed products by our customers</p>
                <div class="recommendation-grid">
                    <?php 
                    $recSystem = new RecommendationSystem();
                    $popular = $recSystem->getPopularProducts(5);
                    foreach ($popular as $productId) {
                        $product = get_product($productId);
                        if ($product) {
                            ?>
                            <a href="?page=product&id=<?= $product['id'] ?>" class="recommendation-card">
                                <div class="recommendation-image">
                                    <div style="width:100%;height:100%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-weight:bold;font-size:14px;">
                                        <?= substr($product['name'], 0, 20) ?>...
                                    </div>
                                </div>
                                <div class="recommendation-info">
                                    <div class="recommendation-name"><?= $product['name'] ?></div>
                                    <div class="recommendation-price">$<?= $product['price'] ?></div>
                                </div>
                            </a>
                            <?php
                        }
                    }
                    ?>
                </div>
            </section>
        <?php endif; ?>
        
        <section>
            <h2 class="section-title">Featured Products</h2>
            <div class="products-grid">
                <?php
                $products = get_all_products();
                // Display only 8 featured products
                $featured = array_slice($products, 0, 8);
                foreach ($featured as $product) {
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <div style="width:100%;height:100%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-weight:bold;font-size:14px;">
                                <?= $product['name'] ?>
                            </div>
                        </div>
                        <div class="product-info">
                            <div class="product-category"><?= $product['category'] ?></div>
                            <h3 class="product-name"><?= $product['name'] ?></h3>
                            <div class="product-price">$<?= $product['price'] ?></div>
                            <div class="product-rating">
                                <div class="stars">★★★★★</div>
                                <span>(24 reviews)</span>
                            </div>
                            <div class="product-actions">
                                <a href="?page=product&id=<?= $product['id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-info-circle"></i> Details
                                </a>
                                <form method="post" class="add-to-cart-form">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

function products_page() {
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $products = $category ? get_products_by_category($category) : get_all_products();
    $categories = get_categories();
    
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px;">
        <h1 class="section-title">Our Products</h1>
        
        <div class="category-list">
            <button class="category-btn <?= !$category ? 'active' : '' ?>" 
                    onclick="window.location.href='?page=products'">
                All Products
            </button>
            <?php foreach ($categories as $cat): ?>
                <button class="category-btn <?= $category === $cat ? 'active' : '' ?>" 
                        data-category="<?= $cat ?>"
                        onclick="window.location.href='?page=products&category=<?= $cat ?>'">
                    <?= $cat ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="products-grid">
            <?php
            foreach ($products as $product) {
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <div style="width:100%;height:100%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-weight:bold;font-size:14px;">
                            <?= $product['name'] ?>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?= $product['category'] ?></div>
                        <h3 class="product-name"><?= $product['name'] ?></h3>
                        <div class="product-price">$<?= $product['price'] ?></div>
                        <div class="product-rating">
                            <div class="stars">★★★★★</div>
                            <span>(24 reviews)</span>
                        </div>
                        <div class="product-actions">
                            <a href="?page=product&id=<?= $product['id'] ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-info-circle"></i> Details
                            </a>
                            <form method="post" class="add-to-cart-form">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function product_page() {
    if (isset($_GET['id'])) {
        $productId = intval($_GET['id']);
        $product = get_product($productId);
        
        if ($product) {
            // Record view if user is logged in
            if (is_logged_in()) {
                $recSystem = new RecommendationSystem();
                $recSystem->recordView($_SESSION['user_id'], $productId);
            }
            
            $userRating = null;
            if (is_logged_in()) {
                $userRating = get_user_rating($_SESSION['user_id'], $productId);
            }
            
            ob_start();
            ?>
            <div class="container" style="padding-top: 40px;">
                <div class="product-detail">
                    <div class="product-detail-image">
                        <div style="width:100%;height:100%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-weight:bold;font-size:18px;padding:20px;text-align:center;">
                            <?= $product['name'] ?>
                        </div>
                    </div>
                    <div class="product-detail-info">
                        <h1 class="product-detail-name"><?= $product['name'] ?></h1>
                        <div class="product-detail-price">$<?= $product['price'] ?></div>
                        <div class="product-detail-category"><?= $product['category'] ?></div>
                        <p class="product-detail-description"><?= $product['description'] ?></p>
                        
                        <div class="rating-section">
                            <h3>Rate this product:</h3>
                            <?php if (is_logged_in()): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="rate">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="rating-star <?= ($userRating && $userRating >= $i) ? 'active' : '' ?>" 
                                                  data-rating="<?= $i ?>">★</span>
                                        <?php endfor; ?>
                                        <input type="hidden" name="rating" id="rating-value" value="<?= $userRating ?: 0 ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-star"></i> Submit Rating
                                    </button>
                                </form>
                            <?php else: ?>
                                <p><a href="?page=login">Login</a> to rate this product</p>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 20px; font-size: 1.1rem;">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        } else {
            header("Location: ?page=products");
            exit;
        }
    } else {
        header("Location: ?page=products");
        exit;
    }
}

function login_page() {
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px;">
        <div class="auth-form">
            <h2 class="section-title">Login to Your Account</h2>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                Don't have an account? <a href="?page=register">Register here</a>
            </p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function register_page() {
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px;">
        <div class="auth-form">
            <h2 class="section-title">Create an Account</h2>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                Already have an account? <a href="?page=login">Login here</a>
            </p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function about_page() {
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px;">
        <h1 class="section-title">About ShopSmart</h1>
        <div class="about-content">
            <h2>Our Intelligent Recommendation System</h2>
            <p>ShopSmart uses advanced collaborative filtering algorithms to provide personalized product recommendations based on user behavior. Our system analyzes your product ratings and browsing history to suggest items you're likely to be interested in.</p>
            
            <p>Unlike traditional e-commerce platforms, ShopSmart learns from your preferences and the preferences of similar users to continually improve the relevance of its recommendations. The more you use our platform, the better it gets at understanding your tastes.</p>
            
            <h2>How It Works</h2>
            <p>Our recommendation engine works in three main steps:</p>
            <ol>
                <li><strong>Data Collection:</strong> We track your product views and ratings to understand your preferences.</li>
                <li><strong>Similarity Calculation:</strong> Our algorithm finds users with similar tastes to yours using Pearson correlation.</li>
                <li><strong>Recommendation Generation:</strong> We suggest products that similar users have rated highly but you haven't seen yet.</li>
            </ol>
            
            <h2>Benefits for Shoppers</h2>
            <ul>
                <li>Discover new products tailored to your tastes</li>
                <li>Save time by seeing relevant items first</li>
                <li>Get personalized deals and promotions</li>
                <li>Enjoy a shopping experience that improves over time</li>
            </ul>
            
            <p>ShopSmart is committed to using ethical AI practices. We never sell your personal data and always prioritize your privacy while delivering a superior shopping experience.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function contact_page() {
    $messageSent = false;
    if (isset($_SESSION['message_sent'])) {
        $messageSent = true;
        unset($_SESSION['message_sent']);
    }
    
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px;">
        <h1 class="section-title">Contact Us</h1>
        
        <?php if ($messageSent): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Your message has been sent successfully!
            </div>
        <?php endif; ?>
        
        <div class="contact-container">
            <div class="contact-info">
                <h3>Get in Touch</h3>
                <p><i class="fas fa-envelope"></i> support@shopsmart.com</p>
                <p><i class="fas fa-phone"></i> (800) 123-4567</p>
                <p><i class="fas fa-clock"></i> Monday-Friday: 9AM-6PM EST</p>
                <p><i class="fas fa-map-marker-alt"></i> 123 Main St, City, Country</p>
            </div>
            
            <div class="contact-form">
                <h3>Send Us a Message</h3>
                <form method="post">
                    <input type="hidden" name="action" value="send_message">
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function cart_page() {
    if (!is_logged_in()) {
        header("Location: ?page=login");
        exit;
    }
    
    $cartItems = get_cart_items($_SESSION['user_id']);
    $cartTotal = get_cart_total($_SESSION['user_id']);
    
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px;">
        <h1 class="section-title">Your Shopping Cart</h1>
        
        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-5x"></i>
                <h3>Your cart is empty</h3>
                <p>Start shopping to add items to your cart</p>
                <a href="?page=products" class="btn btn-primary">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="cart-items">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item): ?>
                            <tr>
                                <td>
                                    <div class="cart-product-info">
                                        <div class="cart-product-image">
                                            <div style="width:60px;height:60px;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-weight:bold;font-size:12px;">
                                                <?= substr($item['name'], 0, 15) ?>...
                                            </div>
                                        </div>
                                        <div class="cart-product-name"><?= htmlspecialchars($item['name']) ?></div>
                                    </div>
                                </td>
                                <td>$<?= number_format($item['price'], 2) ?></td>
                                <td>
                                    <form method="post" class="cart-quantity-form">
                                        <input type="hidden" name="action" value="update_cart">
                                        <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" class="quantity-input">
                                        <button type="submit" class="btn btn-sm btn-outline">Update</button>
                                    </form>
                                </td>
                                <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="cart-summary">
                    <div class="cart-total">
                        <h3>Cart Total: $<?= number_format($cartTotal, 2) ?></h3>
                    </div>
                    <div class="cart-actions">
                        <a href="?page=products" class="btn btn-outline">Continue Shopping</a>
                        <form method="post">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="btn btn-primary">Proceed to Checkout</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function checkout_page() {
    if (!is_logged_in()) {
        header("Location: ?page=login");
        exit;
    }
    
    $cartItems = get_cart_items($_SESSION['user_id']);
    $cartTotal = get_cart_total($_SESSION['user_id']);
    
    if (empty($cartItems)) {
        header("Location: ?page=cart");
        exit;
    }
    
    // Create Payment Intent using cURL
    $url = 'https://api.stripe.com/v1/payment_intents';
    $amount = $cartTotal * 100; // Convert to cents
    
    $data = [
        'amount' => $amount,
        'currency' => 'usd',
        'metadata[user_id]' => $_SESSION['user_id']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_KEY . ':');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        die("Error creating payment intent. Please try again later.");
    }
    
    $paymentIntent = json_decode($response);
    
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px;">
        <h1 class="section-title">Secure Checkout</h1>
        
        <div class="checkout-container">
            <div class="checkout-summary">
                <h3>Order Summary</h3>
                <ul>
                    <?php foreach ($cartItems as $item): ?>
                        <li>
                            <?= htmlspecialchars($item['name']) ?> 
                            <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="checkout-total">
                    <h3>Total: <span>$<?= number_format($cartTotal, 2) ?></span></h3>
                </div>
            </div>
            
            <div class="checkout-form">
                <h3>Payment Information</h3>
                <p>Your payment details are processed securely by Stripe</p>
                
                <form id="payment-form">
                    <div id="card-element" class="stripe-card-element"></div>
                    <div id="card-errors" role="alert" class="stripe-errors"></div>
                    <button type="submit" class="btn btn-primary" id="submit-button">
                        <i class="fas fa-lock"></i> Pay $<?= number_format($cartTotal, 2) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        const stripe = Stripe('<?= STRIPE_PUBLIC ?>');
        const elements = stripe.elements();
        const cardElement = elements.create('card');
        cardElement.mount('#card-element');
        
        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitButton.disabled = true;
            
            const { paymentIntent, error } = await stripe.confirmCardPayment(
                '<?= $paymentIntent->client_secret ?>', {
                    payment_method: {
                        card: cardElement
                    }
                }
            );
            
            if (error) {
                document.getElementById('card-errors').textContent = error.message;
                submitButton.disabled = false;
            } else {
                // Payment succeeded
                window.location.href = '?page=order_success&payment_intent=' + paymentIntent.id;
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

function order_success_page() {
    if (!is_logged_in() || !isset($_GET['payment_intent'])) {
        header("Location: ?page=home");
        exit;
    }
    
    $paymentIntentId = $_GET['payment_intent'];
    
    // Process order
    $conn = db_connect();
    $cartItems = get_cart_items($_SESSION['user_id']);
    $cartTotal = get_cart_total($_SESSION['user_id']);
    
    // Create order
    $orderId = create_order($_SESSION['user_id'], $paymentIntentId, $cartTotal);
    
    // Add order items
    $items = [];
    foreach ($cartItems as $item) {
        $items[] = [
            'id' => $item['id'],
            'quantity' => $item['quantity'],
            'price' => $item['price']
        ];
    }
    add_order_items($orderId, $items);
    
    // Clear cart
    clear_cart($_SESSION['user_id']);
    
    ob_start();
    ?>
    <div class="container" style="padding-top: 40px; text-align: center;">
        <div class="order-success">
            <i class="fas fa-check-circle fa-5x text-success"></i>
            <h1>Thank You for Your Order!</h1>
            <p>Your payment was processed successfully.</p>
            <p>Order ID: #<?= htmlspecialchars($paymentIntentId) ?></p>
            <div class="success-actions">
                <a href="?page=products" class="btn btn-outline">Continue Shopping</a>
                <a href="?page=home" class="btn btn-primary">Back to Home</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Get page content
function get_page_content($page) {
    switch($page) {
        case 'home': return home_page();
        case 'products': return products_page();
        case 'product': return product_page();
        case 'login': return login_page();
        case 'register': return register_page();
        case 'about': return about_page();
        case 'contact': return contact_page();
        case 'cart': return cart_page();
        case 'checkout': return checkout_page();
        case 'order_success': return order_success_page();
        default: return home_page();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopSmart - Intelligent Product Recommendations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --warning: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .nav-links {
        align-items:center
           
            
        }
        
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo i {
            font-size: 28px;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
                     
        }
        
        .nav-links li {
            margin-left: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .nav-links a i {
            font-size: 18px;
        }
        
        .auth-buttons {
            display: flex;
            gap: 10px;
            margin-left: 20px;
        }
          .auth-buttons span{
                align-items:center;
                top:10px;
                position: relative;
             }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.9rem;
        }
        
        .btn-danger {
            background-color: #e53e3e;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--dark);
        }
        
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 60px 0;
            text-align: center;
            margin-bottom: 40px;
            border-radius: 0 0 20px 20px;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }
        
        .section-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--dark);
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .product-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-weight: bold;
            overflow: hidden;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-name {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .product-price {
            font-weight: bold;
            color: var(--primary);
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: var(--gray);
        }
        
        .stars {
            color: #FFD700;
            margin-right: 10px;
        }
        
        .product-category {
            font-size: 0.9rem;
            background-color: var(--light);
            color: var(--gray);
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .product-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .add-to-cart-form {
            display: inline;
        }
        
        .recommendation-section {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
        }
        
        .recommendation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .recommendation-card {
            background-color: var(--light);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .recommendation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .recommendation-image {
            height: 120px;
            background-color: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            overflow: hidden;
        }
        
        .recommendation-info {
            padding: 15px;
        }
        
        .recommendation-name {
            font-size: 0.9rem;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .recommendation-price {
            font-weight: bold;
            color: var(--primary);
        }
        
        .recommendation-score {
            font-size: 0.8rem;
            color: var(--success);
            margin-top: 5px;
            font-weight: 500;
        }
        
        .auth-form {
            max-width: 400px;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .product-detail {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            margin: 40px 0;
        }
        
        @media (min-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .product-detail-image {
            background-color: #f5f5f5;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .rating-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: var(--light);
            border-radius: 8px;
        }
        
        .rating-stars {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .rating-star {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .rating-star:hover, .rating-star.active {
            color: #FFD700;
        }
        
        footer {
            background-color: var(--dark);
            color: white;
            padding: 40px 0;
            margin-top: 60px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }
        
        .footer-section h3 {
            margin-bottom: 20px;
            font-size: 1.2rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 30px;
            height: 2px;
            background-color: var(--primary);
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section a {
            color: #ddd;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer-section a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-section a i {
            width: 20px;
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #aaa;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
        
        .page-content {
            min-height: calc(100vh - 300px);
        }
        
        .category-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .category-btn {
            padding: 8px 20px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .category-btn:hover, .category-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .about-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            line-height: 1.8;
        }
        
        .about-content h2 {
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        a {
            text-decoration: none;
        }
        
        .about-content p {
            margin-bottom: 15px;
        }
        
        /* Cart Styles */
        .empty-cart {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        
        .empty-cart i {
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .cart-items table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .cart-items th, .cart-items td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .cart-items th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .cart-product-info {
            display: flex;
            align-items: center;
        }
        
        .cart-product-image {
            width: 60px;
            height: 60px;
            background: #e0e7ff;
            border-radius: 4px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-weight: bold;
        }
        
        .quantity-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cart-summary {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .cart-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* Checkout Styles */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .checkout-summary, .checkout-form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        
        .checkout-summary h3, .checkout-form h3 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .checkout-summary ul {
            list-style: none;
            margin-bottom: 20px;
        }
        
        .checkout-summary li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .checkout-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: bold;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .stripe-card-element {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
            background: white;
        }
        
        .stripe-errors {
            color: #e53e3e;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .order-success {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            text-align: center;
        }
        
        .order-success i {
            color: #38a169;
            margin-bottom: 20px;
            font-size: 3rem;
        }
        
        .success-actions {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* Contact Styles */
        .contact-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 768px) {
            .contact-container {
                grid-template-columns: 1fr 2fr;
            }
        }
        
        .contact-info, .contact-form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        
        /* Responsive Navbar */
        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .nav-links-container {
                position: fixed;
                top: 70px;
               
                justify-content: space-between;
                left: 0;
                width: 100%;
                background: white;
                box-shadow: 0 10px 10px rgba(0, 0, 0, 0.1);
                height: 0;
                overflow: hidden;
                transition: height 0.3s ease;
                z-index: 99;
            }
            
            .nav-links-container.active {
                height: auto;
                padding: 20px 0;
            }
            
            .nav-links {
                flex-direction: column;
                padding: 0 20px;
               align-items:start;
           
        
              
                
            }
            
            .nav-links li {
                margin: 10px 0;
            }
            
            .auth-buttons {
                flex-direction: column;
                padding: 0 -20px;
                gap: 10px;
                margin-top: 20px;
            }
           
        }
        
        /* Responsive Tables */
        @media (max-width: 768px) {
            .cart-items table {
                display: block;
                overflow-x: auto;
            }
            
            .cart-summary {
                flex-direction: column;
                align-items: stretch;
            }
            
            .cart-actions {
                justify-content: center;
            }
        }
        
        /* Responsive Product Grid */
        @media (max-width: 576px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .recommendation-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="?page=home" class="logo">
                    <span>ShopSmart</span>
                </a>
                
                <button class="mobile-menu-btn" id="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="nav-links-container" id="nav-links-container">
                    <ul class="nav-links">
                        <li><a href="?page=home"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="?page=products"><i class="fas fa-shopping-bag"></i> Products</a></li>
                        <li><a href="?page=about"><i class="fas fa-info-circle"></i> About</a></li>
                        <li><a href="?page=contact"><i class="fas fa-envelope"></i> Contact</a></li>
                        <li><a href="?page=cart"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                     <div class="auth-buttons">
                        <?php if (is_logged_in()): ?>
                            <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                            <a href="?logout" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        <?php else: ?>
                            <a href="?page=login" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Login</a>
                            <a href="?page=register" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</a>
                        <?php endif; ?>
                    </div>
                    </ul>
                   
                </div>
                
            </nav>
        </div>
    </header>

    <div class="page-content">
        <?= get_page_content($page) ?>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>ShopSmart</h3>
                    <p>Intelligent e-commerce platform with personalized recommendations powered by AI.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="?page=home"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="?page=products"><i class="fas fa-chevron-right"></i> Products</a></li>
                        <li><a href="?page=about"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="?page=contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                        <li><a href="?page=cart"><i class="fas fa-chevron-right"></i> Cart</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Customer Service</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Shipping Policy</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Returns & Refunds</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> 123 Main St, City</a></li>
                        <li><a href="#"><i class="fas fa-phone"></i> (123) 456-7890</a></li>
                        <li><a href="#"><i class="fas fa-envelope"></i> info@shopsmart.com</a></li>
                        <li><a href="#"><i class="fas fa-clock"></i> Mon-Fri: 9AM-6PM</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 ShopSmart. All rights reserved. | AI-Powered E-Commerce Recommendation System</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const navLinksContainer = document.getElementById('nav-links-container');
        
        mobileMenuToggle.addEventListener('click', () => {
            navLinksContainer.classList.toggle('active');
        });
        
        // Star rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating-star');
            const ratingInput = document.getElementById('rating-value');
            
            if (stars.length && ratingInput) {
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.getAttribute('data-rating');
                        ratingInput.value = rating;
                        
                        stars.forEach(s => {
                            if (s.getAttribute('data-rating') <= rating) {
                                s.classList.add('active');
                            } else {
                                s.classList.remove('active');
                            }
                        });
                    });
                });
            }
            
            // Set active category button
            const urlParams = new URLSearchParams(window.location.search);
            const category = urlParams.get('category');
            if (category) {
                const buttons = document.querySelectorAll('.category-btn');
                buttons.forEach(btn => {
                    if (btn.getAttribute('data-category') === category) {
                        btn.classList.add('active');
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php
// Flush the output buffer
ob_end_flush();
?>
