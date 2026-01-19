
<?php
session_start();

@include 'config.php';

if(!isset($_SESSION['user_name'])){
   header('location:sign-up.php');
    exit();
}
$user_name = $_SESSION['user_name'];

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
    $stmt->bind_param("s", $user_name);
    $stmt->execute();
    $stmt->bind_result($id);
    $stmt->fetch();
    $stmt->close();
    $conn->close();
    
    return $id;
}

$teacher_id = getUserIdByName($user_name);

$conn = new mysqli("localhost", "root", "", "user_db");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = $_POST['submission_id'];
    $grade = $_POST['grade'];
    $feedback = $_POST['feedback'];
    
    $check_query = "SELECT s.id 
                    FROM submissions s
                    JOIN assignments a ON s.assignment_id = a.id
                    JOIN courses c ON a.course_id = c.id
                    WHERE s.id = ? AND c.teacher_id = ?";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $submission_id, $teacher_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $update_query = "UPDATE submissions SET grade = ?, feedback = ?, status = 'graded' WHERE id = ?";
        $stmt2 = $conn->prepare($update_query);
        $stmt2->bind_param("dsi", $grade, $feedback, $submission_id);
        
        if ($stmt2->execute()) {
            $success_message = "Ο βαθμός αποθηκεύτηκε επιτυχώς!";
        } else {
            $error_message = "Σφάλμα κατά την αποθήκευση του βαθμού: " . $conn->error;
        }
        $stmt2->close();
    } else {
        $error_message = "Δεν έχετε δικαίωμα να βαθμολογήσετε αυτήν την υποβολή.";
    }
    $stmt->close();
}

$pending_query = "SELECT s.*, a.title as assignment_title, a.max_points,
                 c.course_name, c.course_code,
                 u.name as student_name, u.email as student_email
                 FROM submissions s
                 JOIN assignments a ON s.assignment_id = a.id
                 JOIN courses c ON a.course_id = c.id
                 JOIN user_form u ON s.student_id = u.id
                 WHERE c.teacher_id = ? AND s.grade IS NULL
                 ORDER BY s.submitted_at ASC";
$stmt = $conn->prepare($pending_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$pending_result = $stmt->get_result();
$pending_submissions = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_submissions[] = $row;
}
$stmt->close();

$graded_query = "SELECT s.*, a.title as assignment_title, a.max_points,
                c.course_name, c.course_code,
                u.name as student_name
                FROM submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN courses c ON a.course_id = c.id
                JOIN user_form u ON s.student_id = u.id
                WHERE c.teacher_id = ? AND s.grade IS NOT NULL
                ORDER BY s.submitted_at DESC
                LIMIT 20";
$stmt = $conn->prepare($graded_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$graded_result = $stmt->get_result();
$graded_submissions = [];
while ($row = $graded_result->fetch_assoc()) {
    $graded_submissions[] = $row;
}
$stmt->close();

$stats_query = "SELECT c.course_name, 
                COUNT(s.id) as total_submissions,
                AVG(s.grade) as average_grade,
                MIN(s.grade) as min_grade,
                MAX(s.grade) as max_grade
                FROM courses c
                LEFT JOIN assignments a ON c.id = a.course_id
                LEFT JOIN submissions s ON a.id = s.assignment_id AND s.grade IS NOT NULL
                WHERE c.teacher_id = ?
                GROUP BY c.id
                ORDER BY c.course_name";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$course_stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $course_stats[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="el">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Βαθμολόγηση - Καθηγητής</title>
   <link rel="stylesheet" href="css/Style1.css">
   <style>
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        h3 {
            color: #34495e;
            margin-bottom: 15px;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .submission-card {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .submission-info {
            color: #666;
            margin-bottom: 15px;
        }
        .submission-text {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
        .grade-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-top: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        input[type="number"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
        }
        .grade-cell {
            font-weight: bold;
        }
        .grade-a {
            color: #27ae60;
        }
        .grade-b {
            color: #3498db;
        }
        .grade-c {
            color: #f39c12;
        }
        .grade-d {
            color: #e74c3c;
        }
        .course-stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        .course-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .progress-bar {
            height: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }
        .progress {
            height: 100%;
            background: #3498db;
            border-radius: 5px;
        }
        .no-submissions {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
            font-style: italic;
        }
        .toggle-button {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
        }
        .toggle-button:hover {
            text-decoration: underline;
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
   </nav> 

   <div class="container">
        <div class="user-info" style="margin-bottom: 30px;">
            <strong>Καθηγητής:</strong> <?php echo htmlspecialchars($user_name); ?>
            <strong>ID:</strong> <?php echo htmlspecialchars($teacher_id); ?>
        </div>

        <?php if(isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo count($pending_submissions); ?></span>
                <span class="stat-label">Προς Βαθμολόγηση</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo count($graded_submissions); ?></span>
                <span class="stat-label">Βαθμολογημένες</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo count($course_stats); ?></span>
                <span class="stat-label">Μαθήματα</span>
            </div>
        </div>

        <div class="section">
            <h2>Στατιστικά Βαθμολογιών ανά Μάθημα</h2>
            
            <?php if(count($course_stats) > 0): ?>
                <?php foreach($course_stats as $stat): ?>
                    <div class="course-stat-card">
                        <div class="course-stat-header">
                            <h3><?php echo htmlspecialchars($stat['course_name']); ?></h3>
                            <span>Σύνολο Υποβολών: <?php echo $stat['total_submissions']; ?></span>
                        </div>
                        
                        <?php if($stat['total_submissions'] > 0): ?>
                            <div>
                                <strong>Μέσος Όρος:</strong> 
                                <?php echo number_format($stat['average_grade'], 2); ?>/100
                            </div>
                            <div>
                                <strong>Εύρος Βαθμολογίας:</strong> 
                                <?php echo $stat['min_grade']; ?> - <?php echo $stat['max_grade']; ?>
                            </div>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $stat['average_grade']; ?>%;"></div>
                            </div>
                        <?php else: ?>
                            <p>Δεν υπάρχουν βαθμολογημένες υποβολές για αυτό το μάθημα.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Δεν υπάρχουν δεδομένα βαθμολογιών.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Υποβολές προς Βαθμολόγηση</h2>
            
            <?php if(count($pending_submissions) > 0): ?>
                <?php foreach($pending_submissions as $submission): ?>
                    <div class="submission-card">
                        <div class="submission-header">
                            <div>
                                <h3><?php echo htmlspecialchars($submission['assignment_title']); ?></h3>
                                <div class="submission-info">
                                    <strong>Μάθημα:</strong> <?php echo htmlspecialchars($submission['course_name']); ?> 
                                    (<?php echo htmlspecialchars($submission['course_code']); ?>)<br>
                                    <strong>Φοιτητής:</strong> <?php echo htmlspecialchars($submission['student_name']); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($submission['student_email']); ?><br>
                                    <strong>Υποβλήθηκε:</strong> <?php echo date('d/m/Y H:i', strtotime($submission['submitted_at'])); ?>
                                </div>
                            </div>
                            <button class="toggle-button" onclick="toggleGradeForm(<?php echo $submission['id']; ?>)">
                                Βαθμολόγηση
                            </button>
                        </div>
                        
                        <div class="submission-text">
                            <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                        </div>
                        
                        <div id="grade-form-<?php echo $submission['id']; ?>" class="grade-form" style="display: none;">
                            <form method="POST" action="">
                                <input type="hidden" name="grade_submission" value="1">
                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="grade-<?php echo $submission['id']; ?>">Βαθμός (0-<?php echo $submission['max_points']; ?>):</label>
                                    <input type="number" id="grade-<?php echo $submission['id']; ?>" 
                                           name="grade" min="0" max="<?php echo $submission['max_points']; ?>" 
                                           step="0.5" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="feedback-<?php echo $submission['id']; ?>">Ανατροφοδότηση:</label>
                                    <textarea id="feedback-<?php echo $submission['id']; ?>" 
                                              name="feedback" 
                                              placeholder="Παρατηρήσεις, συμβουλές, επικαιροποιήσεις..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success">Αποθήκευση Βαθμού</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-submissions">
                    <p>Δεν υπάρχουν υποβολές προς βαθμολόγηση.</p>
                    <p>Όλες οι υποβολές έχουν βαθμολογηθεί!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Πρόσφατες Βαθμολογημένες Υποβολές</h2>
            
            <?php if(count($graded_submissions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Μάθημα</th>
                            <th>Εργασία</th>
                            <th>Φοιτητής</th>
                            <th>Υποβλήθηκε</th>
                            <th>Βαθμός</th>
                            <th>Ανατροφοδότηση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($graded_submissions as $submission): 
                            $grade_class = '';
                            if ($submission['grade'] >= 86) $grade_class = 'grade-a';
                            elseif ($submission['grade'] >= 71) $grade_class = 'grade-b';
                            elseif ($submission['grade'] >= 51) $grade_class = 'grade-c';
                            else $grade_class = 'grade-d';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($submission['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($submission['assignment_title']); ?></td>
                            <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($submission['submitted_at'])); ?></td>
                            <td class="grade-cell <?php echo $grade_class; ?>">
                                <?php echo $submission['grade']; ?>/<?php echo $submission['max_points']; ?>
                            </td>
                            <td>
                                <?php if(!empty($submission['feedback'])): ?>
                                    <button class="toggle-button" onclick="toggleFeedback(<?php echo $submission['id']; ?>)">
                                        Προβολή
                                    </button>
                                    <div id="feedback-<?php echo $submission['id']; ?>" style="display: none; margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                        <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Δεν υπάρχουν βαθμολογημένες υποβολές.</p>
            <?php endif; ?>
        </div>
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

    function toggleGradeForm(submissionId) {
        const form = document.getElementById('grade-form-' + submissionId);
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth' });
        } else {
            form.style.display = 'none';
        }
    }
    
    function toggleFeedback(submissionId) {
        const feedback = document.getElementById('feedback-' + submissionId);
        if (feedback.style.display === 'none' || feedback.style.display === '') {
            feedback.style.display = 'block';
        } else {
            feedback.style.display = 'none';
        }
    }
    
    window.onload = function() {
        const pendingForms = document.querySelectorAll('.grade-form');
        if (pendingForms.length > 0) {
            const firstForm = pendingForms[0];
            const submissionId = firstForm.id.split('-')[2];
            toggleGradeForm(submissionId);
        }
    };
   </script>
</body>
</html>