<?php
session_start();

@include 'config.php';

if(!isset($_SESSION['user_name'])){
   header('location:sign-up.php');
   exit();
}


$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_type'] ?? 'Teacher';

function getUserRole($user_name) {
    @include 'config.php';
    
    global $conn;
    
    if(!$conn) {
        return 'user';
    }
    
    $sql = "SELECT user_type FROM user_form WHERE name = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return 'user';
    }
    
    $stmt->bind_param("s", $user_name);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();
    
    return $role;
}

$user_type = getUserRole($user_name);

function getUserIdByName($user_name) {
     $host = "localhost";
    $username = "root";
    $password = "";
    $database = "user_db";
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        return false;
    }
    
    $sql = "SELECT id FROM user_form WHERE name = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $conn->close();
        return false;
    }
    
    $stmt->bind_param("s", $user_name);
    $stmt->execute();
    $stmt->bind_result($id);
    $stmt->fetch();
    $stmt->close();
    $conn->close();
    
    return $id;
}

$user_id = getUserIdByName($user_name);



?>

<!DOCTYPE html>
<html lang="el">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Σελίδα Χρήστη - <?php echo $user_type; ?></title>
   <link rel="stylesheet" href="css/Style1.css">
   <link rel="stylesheet" href="css/style3.css">
   <style>
       
   </style>
</head>
<body>
  
  <nav>
    <ul class="sidebar">
        <li><a href="user_page.php">Αρχική</a></li>
        <?php if($user_type === 'admin'): ?>
            <li><a href="cl1t.php">Διαχείριση Εργασιών</a></li>
            <li><a href="cl2t.php">Υποβολές Φοιτητών</a></li>
            <li><a href="cl3t.php">Βαθμολόγηση</a></li>
        <?php else: ?>
            <li><a href="cl1u.php">Εργασίες</a></li>
            <li><a href="cl2u.php">Οι Υποβολές μου</a></li>
            <li><a href="cl3u.php">Βαθμολογίες</a></li>
        <?php endif; ?>
        <li><a href="logout.php" class="btn">Αποσύνδεση</a></li>
    </ul>
    <ul>
        <li class="hideOnMobile"><a href="user_page.php">Αρχική</a></li>
        <?php if($user_type === 'admin'): ?>
            <li class="hideOnMobile"><a href="cl1t.php">Διαχείριση Εργασιών</a></li>
            <li class="hideOnMobile"><a href="cl2t.php">Υποβολές Φοιτητών</a></li>
            <li class="hideOnMobile"><a href="cl3t.php">Βαθμολόγηση</a></li>
        <?php else: ?>
            <li class="hideOnMobile"><a href="cl1u.php">Εργασίες</a></li>
            <li class="hideOnMobile"><a href="cl2u.php">Οι Υποβολές μου</a></li>
            <li class="hideOnMobile"><a href="cl3u.php">Βαθμολογίες</a></li>
        <?php endif; ?>
        <li class="hideOnMobile"><a href="logout.php" class="btn">Αποσύνδεση</a></li>
        <li class="menu-button" onclick=showSidebar() ><a href="#">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000ff"><path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/></svg>
        </a></li>
    </ul>
</nav>
   
   <div class="dashboard-container">
        <div class="welcome-section">
            <h1>Καλώς ορίσατε, <?php echo htmlspecialchars($user_name); ?>!</h1>
        </div>
        <div class="user-info-panel">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">ID Χρήστη:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_id); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Όνομα:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ρόλος:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_type); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ημερομηνία:</span>
                    <span class="info-value"><?php echo date('d/m/Y'); ?></span>
                </div>
            </div>
        </div>

        <?php if($user_type === 'admin'): ?>
            <div class="features-grid"> 
                <div class="feature-card">
                    <h3>Διαχείριση Εργασιών</h3>
                    <p>Αναρτήστε νέες εργασίες για τα μαθήματα σας, επεξεργαστείτε υπάρχουσες και διαχειριστείτε τις προθεσμίες.</p>
                    <a href="cl1t.php" class="feature-btn">Διαχείριση Εργασιών</a>
                </div>
                
                <div class="feature-card">
                    <h3>Υποβολές Φοιτητών</h3>
                    <p>Προβάλετε όλες τις υποβολές των φοιτητών, φιλτράρετε ανά μάθημα και διαχειριστείτε τις καταστάσεις τους.</p>
                    <a href="cl2t.php" class="feature-btn">Προβολή Υποβολών</a>
                </div>
                
                <div class="feature-card">
                    <h3>Βαθμολόγηση</h3>
                    <p>Βαθμολογήστε τις υποβολές των φοιτητών, δώστε ανατροφοδότηση και παρακολουθήστε τα στατιστικά βαθμολογιών.</p>
                    <a href="cl3t.php" class="feature-btn">Βαθμολόγηση</a>
                </div>
            </div>
        <?php else: ?>
            <div class="features-grid">
                <div class="feature-card">
                    <h3>Εργασίες</h3>
                    <p>Προβάλετε τις εργασίες που έχουν αναρτηθεί από τους καθηγητές και υποβάλετε τις λύσεις σας.</p>
                    <a href="cl1u.php" class="feature-btn">Προβολή Εργασιών</a>
                </div>
                
                <div class="feature-card">
                    <h3>Οι Υποβολές μου</h3>
                    <p>Δείτε τις υποβολές που έχετε κάνει, επεξεργαστείτε τες και παρακολουθήστε την κατάστασή τους.</p>
                    <a href="cl2u.php" class="feature-btn">Προβολή Υποβολών</a>
                </div>
                
                <div class="feature-card">
                    <h3>Βαθμολογίες</h3>
                    <p>Προβάλετε τους βαθμούς σας, τα στατιστικά και την ανατροφοδότηση από τους καθηγητές.</p>
                    <a href="cl3u.php" class="feature-btn">Προβολή Βαθμολογιών</a>
                </div>
            </div>
        <?php endif; ?>
   </div>

   <script>
    function showSidebar(){
        const sidebar = document.querySelector('.sidebar')
        sidebar.style.display = 'flex'
    }
    
    function hideSidebar(){
        const sidebar = document.querySelector('.sidebar')
        sidebar.style.display = 'none'
    }
    
    document.addEventListener('click', (event) => {
        const sidebar = document.querySelector('.sidebar');
        const menuButton = document.querySelector('.menu-button');
        
        if (sidebar.style.display === 'flex' && 
            !sidebar.contains(event.target) && 
            !menuButton.contains(event.target)) {
            sidebar.style.display = 'none';
        }
    });
   </script>
</body>
</html>