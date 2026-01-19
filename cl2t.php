<?php
session_start();

if(!isset($_SESSION['user_name'])){
    header('location:sign-up.php');
    exit();
}

$conn = new mysqli("localhost", "root", "", "user_db");

$user_name = $_SESSION['user_name'];
$stmt = $conn->prepare("SELECT id FROM user_form WHERE name = ?");
$stmt->bind_param("s", $user_name);
$stmt->execute();
$stmt->bind_result($teacher_id);
$stmt->fetch();
$stmt->close();

$sql = "SELECT s.*, a.title, c.course_name, u.name as student_name
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        JOIN user_form u ON s.student_id = u.id
        WHERE c.teacher_id = ?
        ORDER BY s.submitted_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$submissions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = count($submissions);
$graded = 0;
$pending = 0;

foreach($submissions as $sub) {
    if($sub['grade'] !== null) {
        $graded++;
    } else {
        $pending++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Υποβολές Φοιτητών</title>
    <link rel="stylesheet" href="css/Style1.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f0f2f5;
        }
        

        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat {
            flex: 1;
            background: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 22px;
            font-weight: bold;
            color: #3498db;
        }
        
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        th {
            background: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .grade {
            font-weight: bold;
        }
        
        .good { color: #27ae60; }
        .average { color: #f39c12; }
        .poor { color: #e74c3c; }
        
        .text-preview {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
    </style>
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
        <h2>Υποβολές Φοιτητών</h2>
        
        <div class="stats">
            <div class="stat">
                <div class="stat-number"><?php echo $total; ?></div>
                <div>Συνολικές</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?php echo $graded; ?></div>
                <div>Βαθμολογημένες</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div>Προς Βαθμολόγηση</div>
            </div>
        </div>
        
        <?php if($total > 0): ?>
            <table>
                <tr>
                    <th>Φοιτητής</th>
                    <th>Μάθημα</th>
                    <th>Εργασία</th>
                    <th>Ημ. Υποβολής</th>
                    <th>Βαθμός</th>
                    <th>Υποβολή</th>
                </tr>
                
                <?php foreach($submissions as $sub): ?>
                <tr>
                    <td><strong><?php echo $sub['student_name']; ?></strong></td>
                    <td><?php echo $sub['course_name']; ?></td>
                    <td><?php echo $sub['title']; ?></td>
                    <td><?php echo date('d/m/y H:i', strtotime($sub['submitted_at'])); ?></td>
                    <td>
                        <?php if($sub['grade'] !== null): 
                            $grade = $sub['grade'];
                            $class = ($grade >= 8) ? 'good' : (($grade >= 5) ? 'average' : 'poor');
                        ?>
                            <span class="grade <?php echo $class; ?>"><?php echo $grade; ?>/10</span>
                        <?php else: ?>
                            <span style="color:#95a5a6">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-preview"><?php echo htmlspecialchars(substr($sub['submission_text'], 0, 50)); ?>...</td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>Δεν υπάρχουν υποβολές από φοιτητές.</p>
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
  </script>
</body>
</html>