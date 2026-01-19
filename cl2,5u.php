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

$student_id = getUserIdByName($user_name);
$assignment_id = $_GET['assignment_id'] ?? 0;

if(!$assignment_id) {
    header('Location: cl1u.php');
    exit();
}

$conn = new mysqli("localhost", "root", "", "user_db");

$assignment_query = "SELECT a.*, c.course_name, c.course_code, 
                    u.name as teacher_name,
                    (SELECT grade FROM submissions WHERE assignment_id = a.id AND student_id = ?) as my_grade,
                    (SELECT id FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submission_id
                    FROM assignments a
                    JOIN courses c ON a.course_id = c.id
                    JOIN user_form u ON c.teacher_id = u.id
                    WHERE a.id = ? AND a.status = 'active'";
$stmt = $conn->prepare($assignment_query);
$stmt->bind_param("iii", $student_id, $student_id, $assignment_id);
$stmt->execute();
$assignment_result = $stmt->get_result();
$assignment = $assignment_result->fetch_assoc();
$stmt->close();

if(!$assignment) {
    header('Location: cl1u.php');
    exit();
}

$now = new DateTime();
$due_date = new DateTime($assignment['due_date']);
$is_past_due = $now > $due_date;

$existing_submission = null;
if($assignment['submission_id']) {
    $submission_query = "SELECT * FROM submissions WHERE id = ?";
    $stmt = $conn->prepare($submission_query);
    $stmt->bind_param("i", $assignment['submission_id']);
    $stmt->execute();
    $submission_result = $stmt->get_result();
    $existing_submission = $submission_result->fetch_assoc();
    $stmt->close();
}

$error = '';
$success = '';
$submission_text = $existing_submission['submission_text'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_text = $_POST['submission_text'] ?? '';
    
    if(empty(trim($submission_text))) {
        $error = 'Παρακαλώ συμπληρώστε την απάντησή σας';
    } else {
        if($existing_submission) {
            $update_query = "UPDATE submissions SET submission_text = ?, submitted_at = NOW(), status = ? WHERE id = ?";
            $status = $is_past_due ? 'late' : 'submitted';
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssi", $submission_text, $status, $existing_submission['id']);
            
            if($stmt->execute()) {
                $success = 'Η υποβολή ενημερώθηκε επιτυχώς!';
                $existing_submission['submission_text'] = $submission_text;
                $existing_submission['submitted_at'] = date('Y-m-d H:i:s');
            } else {
                $error = 'Σφάλμα κατά την ενημέρωση της υποβολής: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $insert_query = "INSERT INTO submissions (assignment_id, student_id, submission_text, status) VALUES (?, ?, ?, ?)";
            $status = $is_past_due ? 'late' : 'submitted';
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("iiss", $assignment_id, $student_id, $submission_text, $status);
            
            if($stmt->execute()) {
                $success = 'Η υποβολή αποθηκεύτηκε επιτυχώς!';
                $existing_submission = [
                    'submission_text' => $submission_text,
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'grade' => null,
                    'feedback' => null
                ];
            } else {
                $error = 'Σφάλμα κατά την υποβολή: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();

$interval = $now->diff($due_date);
$days_remaining = $interval->days;
$hours_remaining = $interval->h;
$minutes_remaining = $interval->i;
?>
<!DOCTYPE html>
<html lang="el">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Υποβολή Εργασίας - Φοιτητής</title>
   <link rel="stylesheet" href="css/Style1.css">
   <style>
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        .assignment-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .assignment-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .course-info {
            color: #666;
            margin-bottom: 15px;
        }
        .deadline-info {
            background: <?php echo $is_past_due ? '#f8d7da' : '#d4edda'; ?>;
            border: 1px solid <?php echo $is_past_due ? '#f5c6cb' : '#c3e6cb'; ?>;
            color: <?php echo $is_past_due ? '#721c24' : '#3498db'; ?>;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .deadline-info h4 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .deadline-info svg {
            width: 20px;
            height: 20px;
        }
        .description-box {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #3498db;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: bold;
            font-size: 1.1rem;
        }
        textarea {
            width: 100%;
            min-height: 300px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            line-height: 1.6;
            resize: vertical;
            font-family: inherit;
        }
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2276ad;
        }
        .btn-secondary {
            background: #7f8c8d;
            color: white;
        }
        .btn-secondary:hover {
            background: #6c7b7d;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .btn-warning:hover {
            background: #d68910;
        }
        .submission-status {
            background: #e8f4fc;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
            border-left: 4px solid #3498db;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .status-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .status-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .status-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .grade-display {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
        }
        .grade-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }
        .feedback-box {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            margin-top: 15px;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .character-counter {
            text-align: right;
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .nav-links {
            margin-bottom: 20px;
        }
        .nav-links a {
            color: #3498db;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .word-count {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
        }
   </style>
</head>
<body>
   <nav>
      <ul class="sidebar">
        <li><a href="user_page.php">Αρχική</a></li>
        <li><a href="cl1u.php">Εργασίες</a></li>
        <li><a href="cl2u.php" class="active">Οι Υποβολές μου</a></li>
        <li><a href="cl3u.php">Βαθμολογίες</a></li>
        <li><a href="logout.php" class="btn">Αποσύνδεση</a></li>
    </ul>
    <ul>
        <li class="hideOnMobile"><a href="user_page.php">Αρχική</a></li>
        <li class="hideOnMobile"><a href="cl1u.php">Εργασίες</a></li>
        <li class="hideOnMobile"><a href="cl2u.php" class="active">Οι Υποβολές μου</a></li>
        <li class="hideOnMobile"><a href="cl3u.php">Βαθμολογίες</a></li>
        <li class="hideOnMobile"><a href="logout.php" class="btn">Αποσύνδεση</a></li>
        <li class="menu-button" onclick="showSidebar()"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000ff"><path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/></svg></a></li>
    </ul>
   </nav> 

   <div class="container">
        <div class="nav-links">
            <a href="cl1u.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                </svg>
                Επιστροφή στις Εργασίες
            </a>
        </div>

        <div class="section">
            <div class="assignment-info">
                <div class="assignment-header">
                    <div>
                        <h1 class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                        <div class="course-info">
                            <strong>Μάθημα:</strong> <?php echo htmlspecialchars($assignment['course_name']); ?> 
                            (<?php echo htmlspecialchars($assignment['course_code']); ?>)<br>
                            <strong>Καθηγητής:</strong> <?php echo htmlspecialchars($assignment['teacher_name']); ?><br>
                            <strong>Μέγιστος Βαθμός:</strong> <?php echo $assignment['max_points']; ?><br>
                            <strong>Δημιουργήθηκε:</strong> <?php echo date('d/m/Y', strtotime($assignment['created_at'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="deadline-info">
                    <h4>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/>
                            <path d="M13 7h-2v6h6v-2h-4z"/>
                        </svg>
                        <?php if($is_past_due): ?>
                            Η προθεσμία έληξε στις <?php echo date('d/m/Y H:i', strtotime($assignment['due_date'])); ?>
                        <?php else: ?>
                            Παράδοση: <?php echo date('d/m/Y H:i', strtotime($assignment['due_date'])); ?>
                        <?php endif; ?>
                    </h4>
                    <p>
                        <?php if($is_past_due): ?>
                            <strong>Η προθεσμία έχει λήξει πριν <?php echo $days_remaining; ?> ημέρες!</strong>
                            Μπορείτε ακόμα να υποβάλετε, αλλά η υποβολή θα σημειωθεί ως καθυστερημένη.
                        <?php else: ?>
                            <strong>Απομένουν: <?php echo $days_remaining; ?> ημέρες, <?php echo $hours_remaining; ?> ώρες, <?php echo $minutes_remaining; ?> λεπτά</strong>
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if(!empty($assignment['description'])): ?>
                    <h4>Περιγραφή Εργασίας:</h4>
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($assignment['my_grade'] !== null): ?>
                <div class="alert alert-warning">
                    <strong>Προσοχή:</strong> Αυτή η εργασία έχει ήδη βαθμολογηθεί. Η επεξεργασία της υποβολής δεν θα επηρεάσει τον βαθμό.
                </div>
            <?php endif; ?>
            
            <?php if($existing_submission): ?>
                <div class="submission-status">
                    <h3>Στάτους Υποβολής</h3>
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-label">Τελευταία Υποβολή</div>
                            <div class="status-value"><?php echo date('d/m/Y H:i', strtotime($existing_submission['submitted_at'])); ?></div>
                        </div>
                        <div class="status-item">
                            <div class="status-label">Κατάσταση</div>
                            <div class="status-value">
                                <?php 
                                if($assignment['my_grade'] !== null) {
                                    echo 'Βαθμολογημένη';
                                } elseif($is_past_due && $existing_submission['submitted_at'] > $assignment['due_date']) {
                                    echo 'Καθυστερημένη';
                                } else {
                                    echo 'Υποβλήθηκε';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="status-item">
                            <div class="status-label">Χαρακτήρες</div>
                            <div class="status-value"><?php echo strlen($existing_submission['submission_text']); ?></div>
                        </div>
                    </div>
                    
                    <?php if($assignment['my_grade'] !== null): ?>
                        <div class="grade-display">
                            <div class="grade-value">Βαθμός: <?php echo $assignment['my_grade']; ?>/<?php echo $assignment['max_points']; ?></div>
                            <?php if(!empty($existing_submission['feedback'])): ?>
                                <h4>Ανατροφοδότηση:</h4>
                                <div class="feedback-box">
                                    <?php echo nl2br(htmlspecialchars($existing_submission['feedback'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="submission_text">Η απάντησή σας:</label>
                    <textarea id="submission_text" name="submission_text" placeholder="Γράψτε την απάντησή σας εδώ..."><?php echo htmlspecialchars($submission_text); ?></textarea>
                    <div class="character-counter">
                        Χαρακτήρες: <span id="charCount">0</span>
                    </div>
                    <div class="word-count">
                        Λέξεις: <span id="wordCount">0</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                        <?php echo $existing_submission ? 'Ενημέρωση Υποβολής' : 'Υποβολή Εργασίας'; ?>
                    </button>
                    
                    <a href="cl2u.php" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                        Ακύρωση
                    </a>
                    
                    <?php if($existing_submission): ?>
                        <a href="student_view_submission.php?id=<?php echo $assignment['submission_id']; ?>" class="btn btn-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            Προβολή
                        </a>
                    <?php endif; ?>
                </div>
            </form>
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

    const textarea = document.getElementById('submission_text');
    const charCount = document.getElementById('charCount');
    const wordCount = document.getElementById('wordCount');
    
    function updateCounters() {
        const text = textarea.value;
        
        charCount.textContent = text.length;
        
        const words = text.trim().split(/\s+/).filter(word => word.length > 0);
        wordCount.textContent = words.length;
        
        if(text.length > 5000) {
            charCount.style.color = '#e74c3c';
        } else if(text.length > 3000) {
            charCount.style.color = '#f39c12';
        } else {
            charCount.style.color = '#27ae60';
        }
    }
    
    updateCounters();
    
    textarea.addEventListener('input', updateCounters);
    
    let saveTimeout;
    textarea.addEventListener('input', () => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            localStorage.setItem('draft_<?php echo $assignment_id; ?>_<?php echo $student_id; ?>', textarea.value);
        }, 2000);
    });
    
    window.onload = function() {
        const savedDraft = localStorage.getItem('draft_<?php echo $assignment_id; ?>_<?php echo $student_id; ?>');
        if(savedDraft && !textarea.value.trim()) {
            if(confirm('Βρέθηκε αποθηκευμένο προσχέδιο. Θέλετε να το φορτώσετε;')) {
                textarea.value = savedDraft;
                updateCounters();
            }
        }
    };
    
    document.querySelector('form').addEventListener('submit', function(e) {
        if(!textarea.value.trim()) {
            e.preventDefault();
            alert('Η υποβολή δεν μπορεί να είναι κενή!');
            return;
        }
        
        if(confirm('Είστε σίγουρος ότι θέλετε να υποβάλετε την εργασία;')) {
            localStorage.removeItem('draft_<?php echo $assignment_id; ?>_<?php echo $student_id; ?>');
        } else {
            e.preventDefault();
        }
    });
    
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }
    
    textarea.addEventListener('input', function() {
        autoResize(this);
    });
    
    autoResize(textarea);
   </script>
</body>
</html>