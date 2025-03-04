<?php
session_start();
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'financial_empowerment';
// Create connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Register User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["register"])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email already exists
    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo "<script>alert('Email already exists!');</script>";
        exit;
    }
    $stmt->close();

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful!'); window.location.href='second.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
    $stmt->close();
    exit;
}

// ✅ Login User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login"])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user'] = $row['name'];
            echo "<script>alert('Login successful!'); window.location.href='second.php';</script>";
        } else {
            echo "<script>alert('Invalid credentials!');</script>";
        }
    } else {
        echo "<script>alert('User not found!');</script>";
    }
    $stmt->close();
}

// ✅ Save Transaction
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["addTransaction"])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "User not logged in!"]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $item = trim($_POST['item']);
    $amount = trim($_POST['amount']);
    $type = trim($_POST['type']);

    // ✅ Debugging: Check if data is received
    if (empty($item) || empty($amount) || !is_numeric($amount) || !in_array($type, ['income', 'expense'])) {
        echo json_encode(["status" => "error", "message" => "Invalid input!"]);
        exit;
    }

    // ✅ Ensure `amount` is properly formatted
    $amount = number_format((float)$amount, 2, '.', '');

    // ✅ Prepared Statement to Insert Transaction
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, item, amount, type, created_at) VALUES (?, ?, ?, ?, NOW())");

    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("isds", $user_id, $item, $amount, $type);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Transaction added successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}


// ✅ Fetch Transactions
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["fetchTransactions"])) {

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "User not logged in!"]);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // ✅ Fetch transactions
    $stmt = $conn->prepare("SELECT item, amount, type, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC");

    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    // ✅ Debugging: Check if transactions exist
    if (empty($transactions)) {
        echo json_encode(["status" => "success", "data" => []]);
    } else {
        echo json_encode(["status" => "success", "data" => $transactions]);
    }

    $stmt->close();
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Empowerment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #ff9a9e, #fad0c4);
            color: #333;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        header {
            background: #fff;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .auth-buttons .btn {
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            color: white;
            background: #ff6f61;
            margin: 5px;
            cursor: pointer;
        }
        .hero {
            padding: 50px 20px;
            color: #fff;
            background: rgba(0, 0, 0, 0.5);
            margin: 20px;
            border-radius: 10px;
        }
        .cards-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            width: 250px;
            margin: 15px;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: scale(1.05);
        }
        footer {
            background: #fff;
            padding: 10px;
            box-shadow: 0 -4px 8px rgba(0,0,0,0.1);
        }
        /* Modal styles */
        .modal {
            display: none; /* Ensures modal is hidden by default */
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
}

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            width: 300px;
        }
        .close {
            float: right;
            font-size: 20px;
            cursor: pointer;
        }
        .modal input {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .modal button {
            padding: 10px 20px;
            background: #ff6f61;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .profile-icon {
    font-size: 40px;
    cursor: pointer;
    position: absolute;
    top: 15px;
    right: 20px;
    color: #333;
    z-index: 1000;
}

.sidebar {
    position: fixed;
    left: -400px;
    top: 0;
    width: 350px;
    height: 100%;
    background: linear-gradient(135deg, #222, #444);
    color: white;
    box-shadow: 5px 0 10px rgba(0, 0, 0, 0.3);
    transition: left 0.3s ease-in-out;
    padding: 20px;
    overflow-y: auto;
    z-index: 1001;
    border-top-right-radius: 10px;
    border-bottom-right-radius: 10px;
}

.sidebar.active {
    left: 0;
}

.sidebar h2 {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 15px;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3);
    padding-bottom: 10px;
}

.sidebar p {
    text-align: center;
    font-size: 16px;
    font-weight: 500;
}

.sidebar ul {
    list-style: none;
    padding: 0;
    margin-top: 15px;
}

.sidebar ul li {
    padding: 12px;
    border-radius: 5px;
    transition: background 0.3s;
}

.sidebar ul li:hover {
    background: rgba(255, 255, 255, 0.2);
}

.sidebar ul li a {
    text-decoration: none;
    color: white;
    display: block;
    font-size: 18px;
    transition: color 0.3s;
}

.sidebar ul li a:hover {
    color: #ff6f61;
}

.logout-btn {
    display: block;
    text-align: center;
    padding: 12px;
    background: #ff6f61;
    color: white;
    text-decoration: none;
    margin-top: 20px;
    border-radius: 5px;
    font-weight: bold;
    font-size: 16px;
    width: 50px;
    transition: background 0.3s;
}

.logout-btn:hover {
    background: #e0554e;
}

.close-btn {
    font-size: 26px;
    cursor: pointer;
    position: absolute;
    top: 15px;
    right: 20px;
    color: white;
    transition: color 0.3s;
}

.close-btn:hover {
    color: #ff6f61;
}


/* Overlay effect */
.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.overlay.active {
    display: block;
}
/* Centering the Educational Content */
#educationContent {
    max-width: 800px;
    margin: 40px auto;
    background:rgb(255, 255, 255);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
}

/* Back Button Styling */
#educationContent button {
    padding: 10px 20px;
    background: #ff6f61;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    transition: 0.3s;
    display: block;
    margin-left: auto;
}

#educationContent button:hover {
    background: #e65b50;
}

/* Headings */
#educationContent h2 {
    color: #333;
    font-size: 24px;
    margin-bottom: 10px;
}

#educationContent h3 {
    color: #444;
    font-size: 20px;
    margin-top: 20px;
}

/* Articles List */
#educationContent ul {
    list-style-type: none;
    padding: 0;
}

#educationContent ul li {
    margin: 10px 0;
}

#educationContent ul li a {
    text-decoration: none;
    color: #ff6f61;
    font-size: 18px;
    font-weight: bold;
    transition: 0.3s;
}

#educationContent ul li a:hover {
    color: #e65b50;
}

/* Article Content */
#articleContent {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin-top: 15px;
    display: none;
    text-align: left;
}

/* Video Container */
.video-container {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 20px;
}

/* Responsive Videos */
.video-container iframe {
    max-width: 100%;
    border-radius: 5px;
}
/* Budget Tracker Styles */
#budgetTracker {
    max-width: 800px;
    margin: 40px auto;
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
}

/* Input & Button Styles */
.tracker-form input, .tracker-form select {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.tracker-form button {
    padding: 10px 20px;
    background: #ff6f61;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.tracker-form button:hover {
    background: #e65b50;
}

/* Summary Section */
.summary {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin: 15px 0;
}

.summary p {
    font-size: 18px;
    font-weight: bold;
}

/* Transaction List */
#transactionList {
    list-style-type: none;
    padding: 0;
}

#transactionList li {
    background: #f1f1f1;
    padding: 10px;
    margin: 5px 0;
    border-radius: 5px;
    display: flex;
    justify-content: space-between;
    font-size: 16px;
}

/* Clear Button */
.clear-btn {
    padding: 10px 20px;
    background: #ff0000;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 10px;
}

.clear-btn:hover {
    background: #cc0000;
}
/* Common Styles for SIP & EMI Calculators */
.calculator-container {
    max-width: 800px;
    margin: 40px auto;
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
}

/* Input & Button Styles */
.calculator-container input {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.calculator-container button {
    padding: 10px 20px;
    background: #ff6f61;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 10px;
}

.calculator-container button:hover {
    background: #e65b50;
}

/* Result Display */
.calculator-result {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin: 15px 0;
    font-size: 18px;
    font-weight: bold;
}

/* Back Button */
.back-btn {
    padding: 10px 20px;
    background: #555;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-bottom: 10px;
}

.back-btn:hover {
    background: #333;
}
/* General Styles */
.back-btn {
    padding: 10px;
    background: #ff6f61;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-bottom: 10px;
}

.back-btn:hover {
    background: #e65b50;
}

/* Financial Planning */
#financialPlanning {
    max-width: 800px;
    margin: 40px auto;
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.goal-form input, .goal-form button {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.goal-form button {
    background: #ff6f61;
    color: white;
    border: none;
    cursor: pointer;
}

.goal-form button:hover {
    background: #e65b50;
}

/* Investment Tracker */
#investmentTracker {
    max-width: 800px;
    margin: 40px auto;
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.investment-form input, .investment-form select, .investment-form button {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.investment-form button {
    background: #ff6f61;
    color: white;
    border: none;
    cursor: pointer;
}

.investment-form button:hover {
    background: #e65b50;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th, td {
    padding: 10px;
    border: 1px solid #ccc;
    text-align: left;
}

/* Dashboard Styling */
#dashboardContent {
    max-width: 900px;
    margin: 40px auto;
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
}

/* Summary Boxes */
.dashboard-summary {
    display: flex;
    justify-content: space-between;
    margin: 20px 0;
}

.summary-box {
    flex: 1;
    padding: 15px;
    margin: 10px;
    background: #f1f1f1;
    border-radius: 8px;
    text-align: center;
}

.balance-box {
    background: #ffeb3b;
}

/* Table Styling */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

table, th, td {
    border: 1px solid #ddd;
}

th, td {
    padding: 10px;
    text-align: center;
}

th {
    background: #ff6f61;
    color: white;
}

/* Back Button */
.back-btn {
    padding: 10px 15px;
    background: #ff6f61;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-bottom: 10px;
}
#quizContainer {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    max-width: 500px;
    margin: auto;
    text-align: center;
}

#quizOptions button {
    display: block;
    width: 100%;
    margin: 5px 0;
    padding: 10px;
    background: #007bff;
    color: white;
    border: none;
    cursor: pointer;
    border-radius: 5px;
}

#quizOptions button:hover {
    background: #0056b3;
}

#quizResult {
    font-weight: bold;
    margin-top: 10px;
}

</style>
</head>
<body>
<header>
    &nbsp;&nbsp;<h1>Financial Empowerment for Women</h1>
    <div class="auth-buttons">
        <?php if(isset($_SESSION['user'])): ?>
            <i class="fas fa-user-circle profile-icon" onclick="toggleSidebar()"></i>
        <?php else: ?>
            <button class="btn" onclick="openModal('registerModal')">Register</button>
            <button class="btn" onclick="openModal('loginModal')">Login</button>
        <?php endif; ?>
    </div>
</header>
 <main>
    <div id="homePage">
        <section class="hero">
            <h2>Take Control of Your Financial Future</h2>
            <p>Learn, plan, and track your finances with our easy-to-use tools.</p>
        </section>
        <section class="cards-container">
        <div class="card" onclick="showSection('educationContent')">
    <h3>Educational Hub</h3>
    <p>Access financial literacy content tailored for women.</p>
</div>

<div class="card" onclick="showSection('budgetTracker')">
    <h3>Budgeting & Expense Tracker</h3>
    <p>Plan, track, and manage your expenses efficiently.</p>
</div>

<div class="card" onclick="showSection('financialPlanning')">
    <h3>Financial Planning Tools</h3>
    <p>Set financial goals and plan for the future.</p>
</div>

<div class="card" onclick="showSection('investmentTracker')">
    <h3>Investment Portfolio Tracker</h3>
    <p>Monitor your investments in one place.</p>
</div>

<div class="card" onclick="showSection('sipCalculator')">
    <h3>SIP Calculator</h3>
    <p>Calculate potential returns on investments.</p>
</div>

<div class="card" onclick="showSection('emiCalculator')">
    <h3>EMI Calculator</h3>
    <p>Compute monthly loan repayments.</p>
</div>

<div class="card" onclick="showSection('dashboardContent')">
    <h3>Dashboard</h3>
    <p>Get personalized financial insights and summaries.</p>
</div>

        </section>
        </div>
    </main>
    <!-- Educational Hub Content (Initially Hidden) -->
<div id="educationContent" style="display: none; padding: 20px;">
<button onclick="showSection('homePage')">Back to Home</button>

    <h2>Financial Education Hub</h2>
    <p>Enhance your financial literacy with our curated materials and videos.</p>
    <h3>💡 Financial Tip of the Day</h3>
<p id="financeTip"></p>

    <h3>Articles & Guides</h3>
    <ul>
        <li><a href="#" onclick="openArticle('savingTips')">Smart Saving Tips</a></li>
        <li><a href="#" onclick="openArticle('budgeting101')">Budgeting 101</a></li>
        <li><a href="#" onclick="openArticle('investingBasics')">Investing Basics</a></li>
    </ul>
    <div id="articleContent" style="display: none; padding: 20px;">
        <h3 id="articleTitle"></h3>
        <p id="articleText"></p>
    </div>
    <h3>💡 Take a Quick Finance Quiz!</h3>
    <div id="quizContainer">
        <p id="quizQuestion"></p>
        <div id="quizOptions"></div>
        <button onclick="nextQuestion()">Next</button>
        <p id="quizResult"></p>
    </div>
    <h3>Educational Videos</h3>
    <div class="video-container">
        <iframe width="560" height="315" src="https://www.youtube.com/embed/Um63OQz3bjo" frameborder="0" allowfullscreen></iframe>
        <iframe width="560" height="315" src="https://www.youtube.com/embed/tTdtzFZr1UY" frameborder="0" allowfullscreen></iframe>
    </div>
</div>
<!-- Budgeting & Expense Tracker Content (Initially Hidden) -->
<div id="budgetTracker" style="display: none; padding: 20px;">
<button onclick="showSection('homePage')" class="back-btn">Back to Home</button>

    
    <h2>Budgeting & Expense Tracker (₹)</h2>
    <p>Manage your finances efficiently by tracking your income and expenses.</p>

    <!-- Income & Expense Form -->
    <div class="tracker-form">
        <input type="text" id="item" placeholder="Enter item (e.g. Salary, Rent)" required>
        <input type="number" id="amount" placeholder="Enter amount (₹)" required>
        <select id="type">
            <option value="income">Income</option>
            <option value="expense">Expense</option>
        </select>
        <button onclick="addTransaction()">Add</button>
    </div>

    <!-- Summary -->
    <div class="summary">
        <h3>Summary</h3>
        <p><strong>Total Income:</strong> ₹<span id="totalIncome">0</span></p>
        <p><strong>Total Expenses:</strong> ₹<span id="totalExpense">0</span></p>
        <p><strong>Balance:</strong> ₹<span id="balance">0</span></p>
    </div>

    <h3>Transaction History</h3>
<table>
    <thead>
        <tr>
            <th>Item</th>
            <th>Amount</th>
            <th>Type</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody id="transactionHistory">
        <!-- Transactions will be displayed here -->
    </tbody>
</table>


    <button onclick="clearTransactions()" class="clear-btn">Clear All</button>
</div>

<!-- SIP Calculator -->
<div id="sipCalculator" class="calculator-container" style="display: none;">
<button onclick="showSection('homePage')" class="back-btn">Back to Home</button>

    <h2>SIP Calculator</h2>
    <p>Estimate your future investment value.</p>

    <input type="number" id="sipAmount" placeholder="Monthly Investment (₹)" required>
    <input type="number" id="sipRate" placeholder="Expected Annual Return (%)" required>
    <input type="number" id="sipDuration" placeholder="Investment Duration (Years)" required>
    <button onclick="calculateSIP()">Calculate</button>

    <h3 class="calculator-result" id="sipResult"></h3>
</div>

<!-- EMI Calculator -->
<div id="emiCalculator" class="calculator-container" style="display: none;">
<button onclick="showSection('homePage')" class="back-btn">Back to Home</button>

    <h2>EMI Calculator</h2>
    <p>Calculate your monthly loan repayments.</p>

    <input type="number" id="loanAmount" placeholder="Loan Amount (₹)" required>
    <input type="number" id="interestRate" placeholder="Annual Interest Rate (%)" required>
    <input type="number" id="loanDuration" placeholder="Loan Tenure (Years)" required>
    <button onclick="calculateEMI()">Calculate</button>

    <h3 class="calculator-result" id="emiResult"></h3>
</div>
<!-- Financial Planning Tools Section (Initially Hidden) -->
<div id="financialPlanning" style="display: none; padding: 20px;">
<button onclick="showSection('homePage')" class="back-btn">Back to Home</button>

    <h2>Financial Planning Tools</h2>
    <p>Set, track, and manage your financial goals effectively.</p>

    <div class="goal-form">
        <input type="text" id="goalName" placeholder="Enter your goal (e.g. Save for vacation)">
        <input type="number" id="goalAmount" placeholder="Target Amount (₹)">
        <input type="date" id="goalDeadline">
        <button onclick="addGoal()">Add Goal</button>
    </div>

    <h3>Your Financial Goals</h3>
    <ul id="goalList"></ul>
</div>

<!-- Investment Portfolio Tracker Section (Initially Hidden) -->
<div id="investmentTracker" style="display: none; padding: 20px;">
<button onclick="showSection('homePage')" class="back-btn">Back to Home</button>

    <h2>Investment Portfolio Tracker</h2>
    <p>Monitor your investments in one place.</p>

    <div class="investment-form">
        <input type="text" id="investmentName" placeholder="Investment Name (e.g. Stocks, Bonds)">
        <input type="number" id="investmentAmount" placeholder="Investment Amount (₹)">
        <select id="investmentType">
            <option value="Stocks">Stocks</option>
            <option value="Mutual Funds">Mutual Funds</option>
            <option value="Real Estate">Real Estate</option>
            <option value="Other">Other</option>
        </select>
        <button onclick="addInvestment()">Add Investment</button>
    </div>

    <h3>Investment Portfolio</h3>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Amount (₹)</th>
                <th>Type</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="investmentList"></tbody>
    </table>

    <p><strong>Total Investment:</strong> ₹<span id="totalInvestment">0</span></p>
</div>
<div id="dashboardContent" style="display: none; padding: 20px;">
    <h2>Dashboard</h2>
    <button onclick="showSection('homePage')" class="back-btn">Back to Home</button>

    <p><strong>Total Income:</strong> ₹<span id="tIncome">0</span></p>
        <p><strong>Total Expenses:</strong> ₹<span id="tExpense">0</span></p>
        <p><strong>Balance:</strong> ₹<span id="bal">0</span></p>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="transactionHis">
            <!-- Transactions will be inserted here -->
        </tbody>
    </table>
</div>

 <!-- Registration Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('registerModal')">&times;</span>
            <h2>Register</h2>
            <form method="POST">
                <input type="text" name="name" placeholder="Full Name" required><br>
                <input type="email" name="email" placeholder="Email" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <button type="submit" name="register">Register</button>
            </form>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('loginModal')">&times;</span>
            <h2>Login</h2>
            <form method="POST">
                <input type="email" name="email" placeholder="Email" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </div>
 <script>
        document.addEventListener("DOMContentLoaded", function () {
    function openModal(id) {
        document.getElementById(id).style.display = "flex";
    }

    function closeModal(id) {
        document.getElementById(id).style.display = "none";
    }

    // Close modal when clicking outside the content
    window.onclick = function (event) {
        if (event.target.classList.contains("modal")) {
            event.target.style.display = "none";
        }
    };

    window.openModal = openModal;
    window.closeModal = closeModal;
});
function toggleSidebar() {
    var sidebar = document.getElementById("sidebar");
    var overlay = document.getElementById("overlay");

    if (sidebar.classList.contains("active")) {
        sidebar.classList.remove("active");
        overlay.classList.remove("active");
    } else {
        sidebar.classList.add("active");
        overlay.classList.add("active");
    }
}
// Sample Articles
const articles = {
    savingTips: {
        title: "Smart Saving Tips",
        text: "Learn how to manage your savings effectively using simple and proven techniques."
    },
    budgeting101: {
        title: "Budgeting 101",
        text: "Discover the best practices to create and maintain a monthly budget."
    },
    investingBasics: {
        title: "Investing Basics",
        text: "Understand the basics of investing to grow your wealth over time."
    }
};

function openArticle(articleKey) {
    const article = articles[articleKey];
    if (article) {
        document.getElementById("articleTitle").textContent = article.title;
        document.getElementById("articleText").textContent = article.text;
        document.getElementById("articleContent").style.display = "block";
    }
}

let transactions = [];

// Function to Add Income/Expense
function addTransaction() {
    let item = document.getElementById("item").value;
    let amount = parseFloat(document.getElementById("amount").value);
    let type = document.getElementById("type").value;

    if (item === "" || isNaN(amount)) {
        alert("Please enter valid details!");
        return;
    }

    let transaction = { item, amount, type };
    transactions.push(transaction);
    updateUI();
}

// Function to Update Summary & Transactions List
function updateUI() {
    let totalIncome = 0;
    let totalExpense = 0;

    document.getElementById("transactionList").innerHTML = "";

    transactions.forEach((txn, index) => {
        if (txn.type === "income") {
            totalIncome += txn.amount;
        } else {
            totalExpense += txn.amount;
        }

        let txnElement = document.createElement("li");
        txnElement.innerHTML = `${txn.item} - ₹${txn.amount} <button onclick="deleteTransaction(${index})">❌</button>`;
        document.getElementById("transactionList").appendChild(txnElement);
    });

    document.getElementById("totalIncome").innerText = totalIncome;
    document.getElementById("totalExpense").innerText = totalExpense;
    document.getElementById("balance").innerText = totalIncome - totalExpense;

    // Clear input fields
    document.getElementById("item").value = "";
    document.getElementById("amount").value = "";
}

// Function to Delete a Transaction
function deleteTransaction(index) {
    transactions.splice(index, 1);
    updateUI();
}

// Function to Clear All Transactions
function clearTransactions() {
    transactions = [];
    updateUI();
}
function addTransaction() {
    let item = document.getElementById("item");
    let amount = document.getElementById("amount");
    let type = document.getElementById("type").value;

    if (!item.value.trim() || isNaN(amount.value) || amount.value <= 0) {
        alert("Please enter a valid item and amount!");
        return;
    }

    let formData = new FormData();
    formData.append("addTransaction", true);
    formData.append("item", item.value.trim());
    formData.append("amount", parseFloat(amount.value));
    formData.append("type", type);

    fetch("second.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.status === "success") {
            // ✅ Clear input fields after successful addition
            item.value = "";
            amount.value = "";
            document.getElementById("type").selectedIndex = 0; // Reset select dropdown
            fetchTransactions(); // Refresh transaction history
        }
    })
    .catch(error => console.error("Error:", error));
}


function fetchTransactions() {
    console.log("Fetching transactions...");

    fetch("second.php?fetchTransactions=true")
    .then(response => response.text())  // First, fetch as text to debug
    .then(data => {
        console.log("Raw Response:", data);  // Log full response for debugging

        try {
            let jsonData = JSON.parse(data); // Parse JSON
            console.log("Parsed Data:", jsonData);

            if (jsonData.status !== "success") {
                console.error("❌ Error:", jsonData.message);
                return;
            }

            let transactions = jsonData.data;
            let transactionHistory = document.getElementById("transactionHistory");
            transactionHistory.innerHTML = "";

            if (!transactions || transactions.length === 0) {
                transactionHistory.innerHTML = "<tr><td colspan='4'>No transactions found</td></tr>";
                return;
            }

            let totalIncome = 0, totalExpense = 0;

            transactions.forEach(transaction => {
                let row = document.createElement("tr");
                row.innerHTML = `
                    <td>${transaction.item}</td>
                    <td>₹${transaction.amount}</td>
                    <td>${transaction.type === "income" ? "✅ Income" : "❌ Expense"}</td>
                    <td>${transaction.date}</td>
                `;
                transactionHistory.appendChild(row);

                // Calculate totals
                if (transaction.type === "income") {
                    totalIncome += parseFloat(transaction.amount);
                } else {
                    totalExpense += parseFloat(transaction.amount);
                }
            });

            document.getElementById("totalIncome").textContent = totalIncome.toFixed(2);
            document.getElementById("totalExpense").textContent = totalExpense.toFixed(2);
            document.getElementById("balance").textContent = (totalIncome - totalExpense).toFixed(2);

        } catch (error) {
            console.error("❌ JSON Parse Error:", error, "\n🔍 Response Data:", data);
        }
    })
    .catch(error => console.error("❌ Fetch Error:", error));
}

// ✅ Clear Transactions
function clearTransactions() {
    if (!confirm("Are you sure you want to delete all transactions?")) return;

    fetch("second.php?clearTransactions=true", { method: "GET" })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.status === "success") {
            fetchTransactions(); // Refresh table after clearing
        }
    })
    .catch(error => console.error("Error clearing transactions:", error));
}

// ✅ Fetch transactions when page loads
document.addEventListener("DOMContentLoaded", fetchTransactions);

// Function to calculate SIP returns
function calculateSIP() {
    let monthlyInvestment = parseFloat(document.getElementById("sipAmount").value);
    let rateOfReturn = parseFloat(document.getElementById("sipRate").value) / 100 / 12;
    let tenure = parseInt(document.getElementById("sipDuration").value) * 12; // Convert years to months

    if (isNaN(monthlyInvestment) || isNaN(rateOfReturn) || isNaN(tenure) || tenure <= 0) {
        alert("Please enter valid inputs!");
        return;
    }

    let sipValue = monthlyInvestment * ((Math.pow(1 + rateOfReturn, tenure) - 1) / rateOfReturn) * (1 + rateOfReturn);
    document.getElementById("sipResult").innerText = `Estimated SIP Maturity Amount: ₹${sipValue.toFixed(2)}`;
}

// Function to calculate EMI
function calculateEMI() {
    let loanAmount = parseFloat(document.getElementById("loanAmount").value);
    let annualInterest = parseFloat(document.getElementById("interestRate").value) / 100 / 12; // Monthly interest
    let tenure = parseInt(document.getElementById("loanDuration").value) * 12; // Convert years to months

    if (isNaN(loanAmount) || isNaN(annualInterest) || isNaN(tenure) || tenure <= 0) {
        alert("Please enter valid inputs!");
        return;
    }

    let emi = (loanAmount * annualInterest * Math.pow(1 + annualInterest, tenure)) / (Math.pow(1 + annualInterest, tenure) - 1);
    document.getElementById("emiResult").innerText = `Your Monthly EMI: ₹${emi.toFixed(2)}`;
}
// Add Goal
function addGoal() {
    const goalName = document.getElementById('goalName').value;
    const goalAmount = document.getElementById('goalAmount').value;
    const goalDeadline = document.getElementById('goalDeadline').value;

    if (!goalName || !goalAmount || !goalDeadline) {
        alert('Please fill in all fields.');
        return;
    }

    const goalList = document.getElementById('goalList');
    const listItem = document.createElement('li');
    listItem.innerHTML = `
        ${goalName} - ₹${goalAmount} (Deadline: ${goalDeadline}) 
        <button onclick="removeGoal(this)">Delete</button>
    `;
    goalList.appendChild(listItem);

    // Clear inputs
    document.getElementById('goalName').value = '';
    document.getElementById('goalAmount').value = '';
    document.getElementById('goalDeadline').value = '';
}
// Remove Goal
function removeGoal(button) {
    button.parentElement.remove();
}

// Add Investment
let totalInvestment = 0;
function addInvestment() {
    const investmentName = document.getElementById('investmentName').value;
    const investmentAmount = document.getElementById('investmentAmount').value;
    const investmentType = document.getElementById('investmentType').value;

    if (!investmentName || !investmentAmount) {
        alert('Please enter investment details.');
        return;
    }

    const investmentList = document.getElementById('investmentList');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${investmentName}</td>
        <td>₹${investmentAmount}</td>
        <td>${investmentType}</td>
        <td><button onclick="removeInvestment(this, ${investmentAmount})">Delete</button></td>
    `;
    investmentList.appendChild(row);

    totalInvestment += parseFloat(investmentAmount);
    document.getElementById('totalInvestment').innerText = totalInvestment;

    // Clear inputs
    document.getElementById('investmentName').value = '';
    document.getElementById('investmentAmount').value = '';
}

// Remove Investment
function removeInvestment(button, amount) {
    button.parentElement.parentElement.remove();
    totalInvestment -= amount;
    document.getElementById('totalInvestment').innerText = totalInvestment;
}

// Show Dashboard and Fetch Data
function showDashboard() {
    document.getElementById("homePage").style.display = "none";
    document.getElementById("dashboardContent").style.display = "block";
 fetchDashboardData();
}

function fetchDashboardData() {
    console.log("📊 Fetching dashboard data...");

    fetch("second.php?fetchTransactions=true")
        .then(response => response.json())
        .then(jsonData => {
            console.log("✅ API Response:", jsonData);

            if (jsonData.status !== "success") {
                console.error("❌ Error:", jsonData.message);
                return;
            }

            let transactions = jsonData.data;
            let transactionHistory = document.getElementById("transactionHis");
            transactionHistory.innerHTML = ""; // Clear old transactions

            console.log("🛠 Clearing and Updating Transaction Table...");

            if (!transactions || transactions.length === 0) {
                console.warn("⚠️ No transactions found. Displaying default message.");
                transactionHistory.innerHTML = "<tr><td colspan='4'>No transactions found</td></tr>";
                document.getElementById("totalIncome").textContent = "₹0.00";
                document.getElementById("totalExpense").textContent = "₹0.00";
                document.getElementById("balance").textContent = "₹0.00";
                return;
            }

            let tIncome = 0, tExpense = 0;

            transactions.forEach(transaction => {
                console.log(`📌 Adding Row: ${transaction.item} - ₹${transaction.amount} (${transaction.type})`);

                let row = document.createElement("tr");
                row.innerHTML = `
                    <td>${transaction.item}</td>
                    <td>₹${parseFloat(transaction.amount).toFixed(2)}</td>
                    <td class="${transaction.type === 'income' ? 'income-text' : 'expense-text'}">
                        ${transaction.type === "income" ? "✅ Income" : "❌ Expense"}
                    </td>
                    <td>${transaction.created_at}</td>
                `;
                transactionHistory.appendChild(row);

                // ✅ Calculate totals dynamically
                if (transaction.type === "income") {
                    tIncome += parseFloat(transaction.amount);
                } else {
                    tExpense += parseFloat(transaction.amount);
                }
            });

            let bal = tIncome - tExpense;

            // ✅ Update Dashboard Values
            document.getElementById("tIncome").textContent = `${tIncome.toFixed(2)}`;
            document.getElementById("tExpense").textContent = `${tExpense.toFixed(2)}`;
            document.getElementById("bal").textContent = `${bal.toFixed(2)}`;

            console.log("✅ Final Table Content:", transactionHistory.innerHTML);
            console.log(`📊 Income: ₹${totalIncome}, Expense: ₹${totalExpense}, Balance: ₹${balance}`);
        })
        .catch(error => console.error("❌ Fetch Error:", error));
}

// ✅ Fetch data when page loads
document.addEventListener("DOMContentLoaded", fetchDashboardData);

// Sample Quiz Questions
const quizQuestions = [
    {
        question: "What is the best way to start saving money?",
        options: ["Spend first, save later", "Save first, spend later", "Keep money in cash only", "Buy expensive items"],
        correct: 1
    },
    {
        question: "What does 'budgeting' help with?",
        options: ["Spending more", "Tracking income and expenses", "Ignoring financial goals", "Making quick purchases"],
        correct: 1
    },
    {
        question: "Which of these is an investment?",
        options: ["Buying a smartphone", "Putting money in a savings account", "Purchasing company stocks", "Shopping for clothes"],
        correct: 2
    }
];

let currentQuestion = 0;
let score = 0;

// Load Quiz Question
function loadQuiz() {
    let quizContainer = document.getElementById("quizContainer");
    let quizQuestion = document.getElementById("quizQuestion");
    let quizOptions = document.getElementById("quizOptions");
    let quizResult = document.getElementById("quizResult");

    if (currentQuestion < quizQuestions.length) {
        let q = quizQuestions[currentQuestion];
        quizQuestion.textContent = q.question;
        quizOptions.innerHTML = "";

        q.options.forEach((option, index) => {
            let btn = document.createElement("button");
            btn.textContent = option;
            btn.onclick = () => checkAnswer(index);
            quizOptions.appendChild(btn);
        });

        quizResult.textContent = "";
    } else {
        quizContainer.innerHTML = `<h3>🎉 Quiz Completed!</h3>
            <p>Your Score: ${score} / ${quizQuestions.length}</p>
            <button onclick="restartQuiz()">Retry Quiz</button>`;
    }
}

// Check Answer
function checkAnswer(selected) {
    if (selected === quizQuestions[currentQuestion].correct) {
        score++;
        document.getElementById("quizResult").textContent = "✅ Correct!";
    } else {
        document.getElementById("quizResult").textContent = "❌ Incorrect!";
    }
    currentQuestion++;
}

// Move to Next Question
function nextQuestion() {
    loadQuiz();
}

// Restart Quiz
function restartQuiz() {
    currentQuestion = 0;
    score = 0;
    loadQuiz();
}

// Load quiz when entering Educational Hub
function showEducationHub() {
    document.getElementById("homePage").style.display = "none";
    document.getElementById("educationContent").style.display = "block";
    loadQuiz(); // Start quiz when opening the hub
}

function showSection(sectionId) {
    // Hide all sections
    document.getElementById("homePage").style.display = "none";
    document.getElementById("educationContent").style.display = "none";
    document.getElementById("budgetTracker").style.display = "none";
    document.getElementById("financialPlanning").style.display = "none";
    document.getElementById("investmentTracker").style.display = "none";
    document.getElementById("sipCalculator").style.display = "none";
    document.getElementById("emiCalculator").style.display = "none";
    document.getElementById("dashboardContent").style.display = "none";

    // Show the selected section
    if (sectionId === "educationContent") {
        document.getElementById("educationContent").style.display = "block";
    } else if (sectionId === "budgetTracker") {
        document.getElementById("budgetTracker").style.display = "block";
    } else if (sectionId === "financialPlanning") {
        document.getElementById("financialPlanning").style.display = "block";
    } else if (sectionId === "investmentTracker") {
        document.getElementById("investmentTracker").style.display = "block";
    } else if (sectionId === "sipCalculator") {
        document.getElementById("sipCalculator").style.display = "block";
    } else if (sectionId === "emiCalculator") {
        document.getElementById("emiCalculator").style.display = "block";
    } else if (sectionId === "dashboardContent") {
        document.getElementById("dashboardContent").style.display = "block";
        fetchDashboardData(); // Fetch dashboard data
    } else {
        // If "homePage" is selected or no match, show homePage
        document.getElementById("homePage").style.display = "block";
    }

    // Scroll to top after switching sections
    window.scrollTo(0, 0);
}
const financeTips = [
    "Start saving at least 20% of your income.",
    "Create a budget and stick to it!",
    "Invest in assets that grow in value over time.",
    "Use the 50/30/20 rule for budgeting.",
    "Avoid unnecessary debt and track your expenses."
];

document.getElementById("financeTip").textContent =
    financeTips[Math.floor(Math.random() * financeTips.length)];


 </script>
 <!-- Profile Sidebar -->
 <div id="sidebar" class="sidebar">
    <span class="close-btn" onclick="toggleSidebar()">&times;</span>
    <h2>Profile</h2>
    <p><strong>Name:</strong> <?php echo $_SESSION['user'] ?? 'Guest'; ?></p>
    <p><strong>Email:</strong> <?php echo $_SESSION['email'] ?? 'Not Available'; ?></p>

    <h2>Quick Access</h2>
    <ul>
        <li><a href="#" onclick="showSection('educationContent')">Educational Hub</a></li>
        <li><a href="#" onclick="showSection('budgetTracker')">Budgeting & Expense Tracker</a></li>
        <li><a href="#" onclick="showSection('financialPlanning')">Financial Planning Tools</a></li>
        <li><a href="#" onclick="showSection('sipCalculator')">SIP Calculator</a></li>
        <li><a href="#" onclick="showSection('emiCalculator')">EMI Calculator</a></li>
        <li><a href="#" onclick="showSection('investmentTracker')">Investment Portfolio Tracker</a></li>
        <li><a href="#" onclick="showSection('dashboardContent')">Dashboard</a></li>
    </ul>

    <a href="?logout=true" class="logout-btn">Logout</a>
</div>


<!-- Overlay -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<!-- Profile Icon -->
<?php if(isset($_SESSION['user'])): ?>
    <i class="fas fa-user-circle profile-icon" onclick="toggleSidebar()"></i>
<?php endif; ?>
</body>
</html>
