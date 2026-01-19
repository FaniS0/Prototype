<?php
session_start();

include 'config.php';

if(!isset($_SESSION['user_name'])){
    header('location:sign-up.php');
    exit();
}

$conn = new mysqli("localhost", "root", "", "user_db");

$user_name = $_SESSION['user_name'];
$sql = "SELECT id FROM user_form WHERE name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_name);
$stmt->execute();
$stmt->bind_result($teacher_id);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_assignment'])) {
    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $max_points = $_POST['max_points'] ?? 100;
    
    $stmt = $conn->prepare("INSERT INTO assignments (course_id, title, description, teacher_id, due_date, max_points) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issisi", $course_id, $title, $description, $teacher_id, $due_date, $max_points);
    
    if($stmt->execute()){
        $message = "Η εργασία αναρτήθηκε επιτυχώς!";
    } else {
        $error = "Σφάλμα: " . $conn->error;
    }
    $stmt->close();
}

$sql = "SELECT * FROM courses WHERE teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sql = "SELECT a.*, c.course_name FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        WHERE a.teacher_id = ? 
        ORDER BY a.due_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignments_result = $stmt->get_result();
$assignments = $assignments_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαχείριση Εργασιών</title>
       <link rel="stylesheet" href="css/Style1.css">
    <link rel="stylesheet" href="css/style3.css">
</head>
<body>
    <nav>
   <ul class="sidebar">
        <li><a href="user_page.php">Αρχική</a></li>
        <li><a href="cl1t.php">Διαχείριση Εργασιών</a></li>
        <li><a href="cl2t.php">Υποβολές Φοιτητών</a></li>
        <li><a href="cl3t.php">Βαθμολόγηση</a></li>
        <li><a href="logout.php" class="btn">Αποσύνδεση</a></li>
    </ul>
    <ul>
        <li class="hideOnMobile"><a href="user_page.php">Αρχική</a></li>
        <li class="hideOnMobile"><a href="cl1t.php" class="active">Διαχείριση Εργασιών</a></li>
        <li class="hideOnMobile"><a href="cl2t.php">Υποβολές Φοιτητών</a></li>
        <li class="hideOnMobile"><a href="cl3t.php">Βαθμολόγηση</a></li>
        <li class="hideOnMobile"><a href="logout.php" class="btn">Αποσύνδεση</a></li>
        <li class="menu-button" onclick="showSidebar()"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000ff"><path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/></svg></a></li>
    </ul>
   </nav>  

    <div class="container">
        <h1>Καλώς ήρθατε, <?php echo $user_name; ?></h1>
        
        <?php if(isset($message)): ?>
            <p class="success"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <h2>Νέα Εργασία</h2>
        <form method="POST">
            <div>
                <label>Μάθημα:</label>
                <select name="course_id" required>
                    <option value="">Επιλέξτε...</option>
                    <?php foreach($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo $course['course_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Τίτλος:</label>
                <input type="text" name="title" required>
            </div>
            
            <div>
                <label>Περιγραφή:</label>
                <textarea name="description" required></textarea>
            </div>
            
            <div>
                <label>Παράδοση:</label>
                <input type="datetime-local" name="due_date" required>
            </div>
            
            <div>
                <label>Μέγιστος Βαθμός:</label>
                <input type="number" name="max_points" value="100" min="1">
            </div>
            
            <button type="submit" name="post_assignment">Ανάρτηση</button>
        </form>

        <h2>Υπάρχουσες Εργασίες</h2>
        <?php if(count($assignments) > 0): ?>
            <table>
                <tr>
                    <th>Τίτλος</th>
                    <th>Μάθημα</th>
                    <th>Παράδοση</th>
                    <th>Μέγιστος Βαθμός</th>
                </tr>
                <?php foreach($assignments as $assignment): ?>
                <tr>
                    <td><?php echo $assignment['title']; ?></td>
                    <td><?php echo $assignment['course_name']; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($assignment['due_date'])); ?></td>
                    <td><?php echo $assignment['max_points']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>Δεν υπάρχουν εργασίες ακόμα.</p>
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
  </script>
</body>
</html>