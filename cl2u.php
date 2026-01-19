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

$submissions_query = "SELECT s.*, a.title as assignment_title, a.due_date, a.max_points,
                     c.course_name, c.course_code,
                     u.name as teacher_name
                     FROM submissions s
                     JOIN assignments a ON s.assignment_id = a.id
                     JOIN courses c ON a.course_id = c.id
                     JOIN user_form u ON c.teacher_id = u.id
                     WHERE s.student_id = ?
                     ORDER BY s.submitted_at DESC";
$stmt = $conn->prepare($submissions_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$submissions_result = $stmt->get_result();
$submissions = [];
while ($row = $submissions_result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();

$stats_query = "SELECT 
                COUNT(*) as total_submissions,
                SUM(CASE WHEN s.grade IS NOT NULL THEN 1 ELSE 0 END) as graded_submissions,
                AVG(s.grade) as average_grade
                FROM submissions s
                WHERE s.student_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stmt->close();

$courses_query = "SELECT DISTINCT c.id, c.course_name, c.course_code 
                 FROM submissions s
                 JOIN assignments a ON s.assignment_id = a.id
                 JOIN courses c ON a.course_id = c.id
                 WHERE s.student_id = ?
                 ORDER BY c.course_name";
$stmt = $conn->prepare($courses_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses_result = $stmt->get_result();
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

$conn->close();

$graded_count = 0;
$submitted_count = 0;
$late_count = 0;

foreach($submissions as $submission) {
    if($submission['grade'] !== null) {
        $graded_count++;
    } elseif($submission['status'] === 'late') {
        $late_count++;
    } else {
        $submitted_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Οι Υποβολές μου - Φοιτητής</title>
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
        .average-grade {
            color: #3498db;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-block;
            text-align: center;
            min-width: 100px;
        }
        .status-submitted {
            background: #3498db;
            color: white;
        }
        .status-graded {
            background: #3498db;
            color: white;
        }
        .status-late {
            background: #e74c3c;
            color: white;
        }
        .grade-cell {
            font-weight: bold;
            text-align: center;
        }
        .grade-a {
            color: #3498db;
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
        .submission-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .view-more {
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .view-more:hover {
            text-decoration: underline;
        }
        .actions {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            text-align: center;
        }
        .action-btn-view {
            background: #3498db;
            color: white;
        }
        .action-btn-view:hover {
            background: #2980b9;
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
            padding: 50px;
            color: #7f8c8d;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            color: #bdc3c7;
        }
        .progress-chart {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .progress-bar {
            height: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            margin: 15px 0;
            overflow: hidden;
        }
        .progress {
            height: 100%;
            background: #3498db;
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        .progress-labels {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.9rem;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .feedback-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #666;
        }
        .export-btn {
            margin-top: 20px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        .close:hover {
            color: #000;
        }
        .modal-body {
            margin-top: 20px;
            white-space: pre-wrap;
            line-height: 1.6;
            font-family: monospace;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
        }
    
        .grade-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
        <div class="user-info">
            <strong>Φοιτητής:</strong> <?php echo htmlspecialchars($user_name); ?>
            <strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_submissions'] ?? 0; ?></span>
                <span class="stat-label">Συνολικές Υποβολές</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $graded_count; ?></span>
                <span class="stat-label">Βαθμολογημένες</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $submitted_count; ?></span>
                <span class="stat-label">Υποβλήθηκε</span>
            </div>
            <div class="stat-card">
                <span class="stat-number average-grade">
                    <?php echo number_format($stats['average_grade'] ?? 0, 1); ?>
                </span>
                <span class="stat-label">Μέσος Όρος Βαθμολογίας</span>
            </div>
        </div>

        <?php if($stats['total_submissions'] > 0): ?>
  
        <?php endif; ?>

        <div class="section">
            <h2>Φίλτρα</h2>
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
                        <option value="submitted">Υποβλήθηκε</option>
                        <option value="graded">Βαθμολογημένη</option>
                        <option value="late">Καθυστερημένη</option>
                    </select>
                    <select id="gradeFilter">
                        <option value="">Όλοι οι Βαθμοί</option>
                        <option value="excellent">Άριστα (86-100)</option>
                        <option value="very-good">Πολύ Καλά (71-85)</option>
                        <option value="good">Καλά (51-70)</option>
                        <option value="pass">Αρκετά (50)</option>
                        <option value="fail">Ανεπαρκώς (0-49)</option>
                    </select>
                    <input type="text" id="searchFilter" placeholder="Αναζήτηση εργασίας...">
                    <button class="btn" onclick="applyFilters()">Εφαρμογή Φίλτρων</button>
                    <button class="btn btn-secondary" onclick="resetFilters()">Επαναφορά</button>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Οι Υποβολές μου</h2>
            
            <?php if(count($submissions) > 0): ?>
                <table id="submissionsTable">
                    <thead>
                        <tr>
                            <th>Μάθημα</th>
                            <th>Εργασία</th>
                            <th>Υποβλήθηκε</th>
                            <th>Κατάσταση</th>
                            <th>Βαθμός</th>
                            <th>Υποβολή</th>
                            <th>Ανατροφοδότηση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($submissions as $submission): 
                            $grade_class = '';
                            if ($submission['grade'] !== null) {
                                $grade = (float)$submission['grade'];
                                if ($grade >= 86) $grade_class = 'grade-a';
                                elseif ($grade >= 71) $grade_class = 'grade-b';
                                elseif ($grade >= 51) $grade_class = 'grade-c';
                                else $grade_class = 'grade-d';
                            }
                            
                            $status = $submission['status'];
                            if($submission['grade'] !== null) {
                                $status = 'graded';
                            }
                        ?>
                        
                            <td>
                                <strong><?php echo htmlspecialchars($submission['course_code']); ?></strong><br>
                                <small><?php echo htmlspecialchars($submission['course_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($submission['assignment_title']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($submission['submitted_at'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php 
                                    switch($status) {
                                        case 'submitted': echo 'Υποβλήθηκε'; break;
                                        case 'graded': echo 'Βαθμολογημένη'; break;
                                        case 'late': echo 'Καθυστερημένη'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="grade-cell <?php echo $grade_class; ?>">
                                <?php 
                                if($submission['grade'] !== null) {
                                    echo $submission['grade'] . '/' . $submission['max_points'];
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="submission-text">
                                    <?php echo substr(htmlspecialchars($submission['submission_text']), 0, 50); ?>...
                                </div>
                               
                            </td>
                            <td class="feedback-preview">
                                <?php 
                                if(!empty($submission['feedback'])) {
                                    echo substr(htmlspecialchars($submission['feedback']), 0, 30) . '...';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                           
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php else: ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                    </svg>
                    <h3>Δεν έχετε υποβάλει ακόμα εργασίες</h3>
                    <p>Όταν υποβάλετε εργασίες, θα εμφανίζονται εδώ.</p>
                    <a href="student_assignments.php" class="btn" style="margin-top: 20px;">
                        Προβολή Εργασιών
                    </a>
                </div>
            <?php endif; ?>
        </div>
   </div>

   <div id="submissionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div id="modalContent"></div>
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

    // Συναρτήσεις για φίλτρα
    function applyFilters() {
        const courseFilter = document.getElementById('courseFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const gradeFilter = document.getElementById('gradeFilter').value;
        const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
        
        const rows = document.querySelectorAll('#submissionsTable tbody tr');
        
        rows.forEach(row => {
            let showRow = true;
            
            // Φίλτρο μαθήματος
            if (courseFilter && row.dataset.course !== courseFilter) {
                showRow = false;
            }
            
            // Φίλτρο κατάστασης
            if (statusFilter && row.dataset.status !== statusFilter) {
                showRow = false;
            }
            
            // Φίλτρο βαθμού
            if (gradeFilter && row.dataset.grade !== '0') {
                const grade = parseFloat(row.dataset.grade);
                if (grade > 0) {
                    switch(gradeFilter) {
                        case 'excellent':
                            if (grade < 86) showRow = false;
                            break;
                        case 'very-good':
                            if (grade < 71 || grade > 85) showRow = false;
                            break;
                        case 'good':
                            if (grade < 51 || grade > 70) showRow = false;
                            break;
                        case 'pass':
                            if (grade !== 50) showRow = false;
                            break;
                        case 'fail':
                            if (grade >= 50) showRow = false;
                            break;
                    }
                } else {
                    if (gradeFilter !== 'ungraded') showRow = false;
                }
            }
            
            // Φίλτρο αναζήτησης
            if (searchFilter) {
                const rowText = row.textContent.toLowerCase();
                if (!rowText.includes(searchFilter)) {
                    showRow = false;
                }
            }
            
            row.style.display = showRow ? '' : 'none';
        });
    }
    
    function resetFilters() {
        document.getElementById('courseFilter').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('gradeFilter').value = '';
        document.getElementById('searchFilter').value = '';
        
        const rows = document.querySelectorAll('#submissionsTable tbody tr');
        rows.forEach(row => {
            row.style.display = '';
        });
    }
    
    setInterval(() => {
        console.log('Ελέγχοντας για νέες βαθμολογίες...');
    }, 30000);
   </script>
</body>
</html>