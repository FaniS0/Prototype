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

$conn = new mysqli("localhost", "root", "", "user_db");

$assignments_query = "SELECT a.*, c.course_name, c.course_code, 
                     u.name as teacher_name,
                     (SELECT grade FROM submissions WHERE assignment_id = a.id AND student_id = ?) as my_grade,
                     (SELECT id FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submission_id
                     FROM assignments a
                     JOIN courses c ON a.course_id = c.id
                     JOIN user_form u ON c.teacher_id = u.id
                     WHERE a.status = 'active'
                     ORDER BY a.due_date ASC";
$stmt = $conn->prepare($assignments_query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$assignments_result = $stmt->get_result();
$assignments = [];
while ($row = $assignments_result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

$courses_query = "SELECT DISTINCT c.id, c.course_name, c.course_code 
                 FROM assignments a
                 JOIN courses c ON a.course_id = c.id
                 WHERE a.status = 'active'
                 ORDER BY c.course_name";
$courses_result = $conn->query($courses_query);
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

$stats_query = "SELECT 
                COUNT(*) as total_assignments,
                SUM(CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END) as submitted_assignments,
                SUM(CASE WHEN s.grade IS NOT NULL THEN 1 ELSE 0 END) as graded_assignments
                FROM assignments a
                LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
                WHERE a.status = 'active'";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stmt->close();

$conn->close();

$pending_count = 0;
$submitted_count = 0;
$graded_count = 0;

foreach($assignments as $assignment) {
    if($assignment['my_grade'] !== null) {
        $graded_count++;
    } elseif($assignment['submission_id'] !== null) {
        $submitted_count++;
    } else {
        $pending_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Εργασίες - Φοιτητής</title>
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-top: 4px solid #3498db;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .assignment-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
            transition: transform 0.2s;
        }
        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .assignment-title {
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .course-code {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .assignment-info {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .assignment-info p {
            margin-bottom: 8px;
        }
        .deadline {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .deadline svg {
            width: 16px;
            height: 16px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-bottom: 10px;
            display: inline-block;
        }
        .status-pending {
            background: #f39c12;
            color: white;
        }
        .status-submitted {
            background: #3498db;
            color: white;
        }
        .status-graded {
            background: #27ae60;
            color: white;
        }
        .status-late {
            background: #e74c3c;
            color: white;
        }
        .grade-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .grade-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498db;
        }
        .grade-feedback {
            margin-top: 5px;
            color: #666;
            font-size: 0.9rem;
        }
        .assignment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        .action-btn-view {
            background: #3498db;
            color: white;
        }
        .action-btn-view:hover {
            background: #2980b9;
        }
        .action-btn-submit {
            background: #3498db;
            color: white;
        }
        .action-btn-submit:hover {
            background: #257bb4;
        }
        .action-btn-edit {
            background: #f39c12;
            color: white;
        }
        .action-btn-edit:hover {
            background: #d68910;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            color: #bdc3c7;
        }
        .time-remaining {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        .urgent {
            color: #e74c3c;
            font-weight: bold;
        }
        .warning {
            color: #f39c12;
            font-weight: bold;
        }
        .good {
            color: #27ae60;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
   </style>
</head>
<body>
   <nav>
    <ul class="sidebar">
        <li><a href="user_page.php">Αρχική</a></li>
        <li><a href="cl1u.php" class="active">Εργασίες</a></li>
        <li><a href="cl2u.php">Οι Υποβολές μου</a></li>
        <li><a href="cl3u.php">Βαθμολογίες</a></li>
        <li><a href="logout.php" >Αποσύνδεση</a></li>
    </ul>
    <ul>
        <li class="hideOnMobile"><a href="user_page.php">Αρχική</a></li>
        <li class="hideOnMobile"><a href="cl1u.php" class="active">Εργασίες</a></li>
        <li class="hideOnMobile"><a href="cl2u.php">Οι Υποβολές μου</a></li>
        <li class="hideOnMobile"><a href="cl3u.php">Βαθμολογίες</a></li>
        <li class="hideOnMobile"><a href="logout.php" class="btn">Αποσύνδεση</a></li>
        <li class="menu-button" onclick="showSidebar()"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000ff"><path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/></svg></a></li>
    </ul>
   </nav> 

   <div class="container">
        <div class="user-info">
            <strong>Φοιτητής:</strong> <?php echo htmlspecialchars($user_name); ?>
            <strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo $pending_count; ?></span>
                <span class="stat-label">Προς Υποβολή</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $submitted_count; ?></span>
                <span class="stat-label">Υποβλήθηκε</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $graded_count; ?></span>
                <span class="stat-label">Βαθμολογημένες</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_assignments'] ?? 0; ?></span>
                <span class="stat-label">Συνολικές Εργασίες</span>
            </div>
        </div>

        <div class="section">
            <h2>Φίλτρα Εργασιών</h2>
            <div class="filters">
                <div class="filter-group">
                    <select id="courseFilter">
                        <option value="">Όλα τα Μαθήματα</option>
                        <?php foreach($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_name']) . ' (' . htmlspecialchars($course['course_code']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter">
                        <option value="">Όλες οι Καταστάσεις</option>
                        <option value="pending">Προς Υποβολή</option>
                        <option value="submitted">Υποβλήθηκε</option>
                        <option value="graded">Βαθμολογημένες</option>
                    </select>
                    <select id="urgencyFilter">
                        <option value="">Όλες οι Προθεσμίες</option>
                        <option value="urgent">Άμεση Προθεσμία (3 ημέρες)</option>
                        <option value="upcoming">Επερχόμενη (7 ημέρες)</option>
                        <option value="future">Μελλοντική</option>
                        <option value="expired">Ληγμένη</option>
                    </select>
                    <input type="text" id="searchFilter" placeholder="Αναζήτηση εργασίας...">
                    <button class="btn" onclick="applyFilters()">Εφαρμογή Φίλτρων</button>
                    <button class="btn btn-secondary" onclick="resetFilters()">Επαναφορά</button>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Διαθέσιμες Εργασίες</h2>
            
            <?php if(count($assignments) > 0): ?>
                <div class="assignments-grid" id="assignmentsGrid">
                    <?php foreach($assignments as $assignment): 
                        $now = new DateTime();
                        $due_date = new DateTime($assignment['due_date']);
                        $interval = $now->diff($due_date);
                        $days_remaining = $interval->days;
                        $is_past_due = $now > $due_date;
                        
                        if($assignment['my_grade'] !== null) {
                            $status = 'graded';
                            $status_text = 'Βαθμολογημένη';
                        } elseif($assignment['submission_id'] !== null) {
                            $status = 'submitted';
                            $status_text = 'Υποβλήθηκε';
                        } else {
                            $status = 'pending';
                            $status_text = 'Προς Υποβολή';
                        }
                        
                        $urgency_class = '';
                        $urgency_text = '';
                        
                        if($is_past_due) {
                            $urgency_class = 'urgent';
                            $urgency_text = 'Ληγμένη προθεσμία!';
                        } elseif($days_remaining <= 3) {
                            $urgency_class = 'urgent';
                            $urgency_text = 'Άμεση προθεσμία';
                        } elseif($days_remaining <= 7) {
                            $urgency_class = 'warning';
                            $urgency_text = 'Επερχόμενη προθεσμία';
                        } else {
                            $urgency_class = 'good';
                            $urgency_text = 'Αρκετός χρόνος';
                        }
                        
                        $time_remaining = '';
                        if($is_past_due) {
                            $time_remaining = 'Η προθεσμία έληξε πριν ' . abs($days_remaining) . ' ημέρες';
                        } else {
                            $time_remaining = 'Απομένουν ' . $days_remaining . ' ημέρες';
                        }
                    ?>
                    <div class="assignment-card" 
                         data-course="<?php echo $assignment['course_id']; ?>"
                         data-status="<?php echo $status; ?>"
                         data-urgency="<?php echo $is_past_due ? 'expired' : ($days_remaining <= 3 ? 'urgent' : ($days_remaining <= 7 ? 'upcoming' : 'future')); ?>">
                        
                        <div class="assignment-header">
                            <div>
                                <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                <span class="course-code"><?php echo htmlspecialchars($assignment['course_code']); ?></span>
                            </div>
                            <span class="status-badge status-<?php echo $status; ?>"><?php echo $status_text; ?></span>
                        </div>
                        
                        <div class="assignment-info">
                            <p><strong>Μάθημα:</strong> <?php echo htmlspecialchars($assignment['course_name']); ?></p>
                            <p><strong>Καθηγητής:</strong> <?php echo htmlspecialchars($assignment['teacher_name']); ?></p>
                            <p><strong>Δημιουργήθηκε:</strong> <?php echo date('d/m/Y', strtotime($assignment['created_at'])); ?></p>
                            
                            <div class="deadline <?php echo $urgency_class; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/>
                                    <path d="M13 7h-2v6h6v-2h-4z"/>
                                </svg>
                                <span>Παράδοση: <?php echo date('d/m/Y H:i', strtotime($assignment['due_date'])); ?></span>
                            </div>
                            
                            <div class="time-remaining <?php echo $urgency_class; ?>">
                                <?php echo $urgency_text; ?> • <?php echo $time_remaining; ?>
                            </div>
                            
                            <p><strong>Μέγιστος Βαθμός:</strong> <?php echo $assignment['max_points']; ?></p>
                            
                            <?php if(!empty($assignment['description'])): ?>
                                <p><strong>Περιγραφή:</strong> <?php echo substr(htmlspecialchars($assignment['description']), 0, 100); ?>...</p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($status === 'graded'): ?>
                            <div class="grade-display">
                                <div class="grade-value">Βαθμός: <?php echo $assignment['my_grade']; ?>/<?php echo $assignment['max_points']; ?></div>
                                <div class="grade-feedback">
                                    <strong>Ανατροφοδότηση:</strong> 
                                    <?php 
                                        $conn = new mysqli("localhost", "root", "", "user_db");
                                        $feedback_query = "SELECT feedback FROM submissions WHERE id = ?";
                                        $stmt = $conn->prepare($feedback_query);
                                        $stmt->bind_param("i", $assignment['submission_id']);
                                        $stmt->execute();
                                        $stmt->bind_result($feedback);
                                        $stmt->fetch();
                                        $stmt->close();
                                        $conn->close();
                                        
                                        echo $feedback ? substr(htmlspecialchars($feedback), 0, 50) . '...' : 'Δεν υπάρχει ανατροφοδότηση';
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="assignment-actions">
                            <a href="cl2u.php?id=<?php echo $assignment['id']; ?>" class="action-btn action-btn-view">
                                Προβολή
                            </a>
                            
                            <?php if($status === 'pending'): ?>
                                <a href="cl2,5u.php?assignment_id=<?php echo $assignment['id']; ?>" class="action-btn action-btn-submit">
                                    Υποβολή
                                </a>

                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/>
                        <path d="M7 12h10v2H7zm0-4h10v2H7z"/>
                    </svg>
                    <h3>Δεν υπάρχουν διαθέσιμες εργασίες</h3>
                    <p>Οι καθηγητές δεν έχουν αναρτήσει εργασίες ακόμα ή όλες οι εργασίες έχουν ολοκληρωθεί.</p>
                </div>
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

    function applyFilters() {
        const courseFilter = document.getElementById('courseFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const urgencyFilter = document.getElementById('urgencyFilter').value;
        const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
        
        const cards = document.querySelectorAll('.assignment-card');
        
        cards.forEach(card => {
            let showCard = true;
            
            if (courseFilter && card.dataset.course !== courseFilter) {
                showCard = false;
            }
            
            if (statusFilter && card.dataset.status !== statusFilter) {
                showCard = false;
            }
            
            if (urgencyFilter && card.dataset.urgency !== urgencyFilter) {
                showCard = false;
            }
            
            if (searchFilter) {
                const cardText = card.textContent.toLowerCase();
                if (!cardText.includes(searchFilter)) {
                    showCard = false;
                }
            }
            
            card.style.display = showCard ? 'block' : 'none';
        });
    }
    
    function resetFilters() {
        document.getElementById('courseFilter').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('urgencyFilter').value = '';
        document.getElementById('searchFilter').value = '';
        
        const cards = document.querySelectorAll('.assignment-card');
        cards.forEach(card => {
            card.style.display = 'block';
        });
    }
    
    function sortByUrgency() {
        const grid = document.getElementById('assignmentsGrid');
        const cards = Array.from(grid.querySelectorAll('.assignment-card'));
        
        cards.sort((a, b) => {
            const urgencyOrder = {
                'expired': 0,
                'urgent': 1,
                'upcoming': 2,
                'future': 3
            };
            
            const aUrgency = a.dataset.urgency;
            const bUrgency = b.dataset.urgency;
            
            return urgencyOrder[aUrgency] - urgencyOrder[bUrgency];
        });
        
        cards.forEach(card => {
            grid.appendChild(card);
        });
    }
    
    window.onload = function() {
        sortByUrgency();
    };
   </script>
</body>
</html>