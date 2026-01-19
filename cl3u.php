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

$grades_query = "SELECT s.grade, s.feedback, s.submitted_at,
                a.title as assignment_title, a.max_points,
                c.course_name, c.course_code,
                u.name as teacher_name
                FROM submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN courses c ON a.course_id = c.id
                JOIN user_form u ON c.teacher_id = u.id
                WHERE s.student_id = ? AND s.grade IS NOT NULL
                ORDER BY s.submitted_at DESC";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grades_result = $stmt->get_result();
$grades = [];
while ($row = $grades_result->fetch_assoc()) {
    $grades[] = $row;
}
$stmt->close();

$course_stats_query = "SELECT c.course_name, c.course_code,
                      COUNT(s.id) as total_assignments,
                      AVG(s.grade) as average_grade,
                      MIN(s.grade) as min_grade,
                      MAX(s.grade) as max_grade
                      FROM submissions s
                      JOIN assignments a ON s.assignment_id = a.id
                      JOIN courses c ON a.course_id = c.id
                      WHERE s.student_id = ? AND s.grade IS NOT NULL
                      GROUP BY c.id
                      ORDER BY c.course_name";
$stmt = $conn->prepare($course_stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$course_stats_result = $stmt->get_result();
$course_stats = [];
while ($row = $course_stats_result->fetch_assoc()) {
    $course_stats[] = $row;
}
$stmt->close();

$overall_stats_query = "SELECT 
                       COUNT(*) as total_graded,
                       AVG(s.grade) as overall_average,
                       SUM(CASE WHEN s.grade >= 50 THEN 1 ELSE 0 END) as passed_assignments,
                       SUM(CASE WHEN s.grade < 50 THEN 1 ELSE 0 END) as failed_assignments
                       FROM submissions s
                       WHERE s.student_id = ? AND s.grade IS NOT NULL";
$stmt = $conn->prepare($overall_stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$overall_stats_result = $stmt->get_result();
$overall_stats = $overall_stats_result->fetch_assoc();
$stmt->close();

$recent_grades_query = "SELECT s.grade, a.title, c.course_name, s.submitted_at
                       FROM submissions s
                       JOIN assignments a ON s.assignment_id = a.id
                       JOIN courses c ON a.course_id = c.id
                       WHERE s.student_id = ? AND s.grade IS NOT NULL
                       ORDER BY s.submitted_at DESC
                       LIMIT 5";
$stmt = $conn->prepare($recent_grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_grades_result = $stmt->get_result();
$recent_grades = [];
while ($row = $recent_grades_result->fetch_assoc()) {
    $recent_grades[] = $row;
}
$stmt->close();

$conn->close();

$total_graded = $overall_stats['total_graded'] ?? 0;
$passed_count = $overall_stats['passed_assignments'] ?? 0;
$failed_count = $overall_stats['failed_assignments'] ?? 0;

$passed_percentage = $total_graded > 0 ? ($passed_count / $total_graded) * 100 : 0;
$failed_percentage = $total_graded > 0 ? ($failed_count / $total_graded) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="el">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Βαθμολογίες - Φοιτητής</title>
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
        .overall-average {
            color: #3498db;
            font-size: 3rem;
        }
        .course-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .course-stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .course-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .course-code {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .grade-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .grade-item {
            text-align: center;
            flex: 1;
        }
        .grade-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498db;
        }
        .grade-label {
            font-size: 0.8rem;
            color: #666;
        }
        .progress-chart {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .progress-bar {
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            margin: 15px 0;
            overflow: hidden;
            display: flex;
        }
        .progress-passed {
            background: #3498db;
            height: 100%;
            transition: width 0.3s ease;
        }
        .progress-failed {
            background: #e74c3c;
            height: 100%;
            transition: width 0.3s ease;
        }
        .progress-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
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
        }
        tr:hover {
            background-color: #f9f9f9;
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
        .percentage {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .recent-grades {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
        }
        .recent-grade-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            min-width: 200px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-top: 4px solid #27ae60;
        }
        .recent-grade {
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
        }
        .recent-details {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
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
        .chart-container {
            height: 300px;
            margin-top: 20px;
            position: relative;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .feedback-toggle {
            color: #3498db;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .feedback-toggle:hover {
            text-decoration: underline;
        }
        .feedback-content {
            display: none;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            white-space: pre-wrap;
            line-height: 1.6;
        }
a
        .distribution-bar {
            flex: 1;
            background: #3498db;
            border-radius: 4px 4px 0 0;
            position: relative;
            min-height: 10px;
        }
        .distribution-label {
            text-align: center;
            margin-top: 5px;
            font-size: 0.8rem;
            color: #666;
        }
        .export-btn {
            margin-top: 20px;
        }
   </style>
</head>
<body>
   <nav>
    <ul class="sidebar">
        <li><a href="user_page.php">Αρχική</a></li>
        <li><a href="cl1u.php">Εργασίες</a></li>
        <li><a href="cl2u.php">Οι Υποβολές μου</a></li>
        <li><a href="cl3u.php" class="active">Βαθμολογίες</a></li>
        <li><a href="logout.php" class="btn">Αποσύνδεση</a></li>
    </ul>
    <ul>
        <li class="hideOnMobile"><a href="user_page.php">Αρχική</a></li>
        <li class="hideOnMobile"><a href="cl1u.php">Εργασίες</a></li>
        <li class="hideOnMobile"><a href="cl2u.php">Οι Υποβολές μου</a></li>
        <li class="hideOnMobile"><a href="cl3u.php" class="active">Βαθμολογίες</a></li>
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
                <span class="stat-number overall-average">
                    <?php echo number_format($overall_stats['overall_average'] ?? 0, 1); ?>
                </span>
                <span class="stat-label">Μέσος Όρος Βαθμολογίας</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_graded; ?></span>
                <span class="stat-label">Συνολικές Βαθμολογίες</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $passed_count; ?></span>
                <span class="stat-label">Περασμένες Εργασίες</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $failed_count; ?></span>
                <span class="stat-label">Αποτυχημένες Εργασίες</span>
            </div>
        </div>

        <?php if($total_graded > 0): ?>
        <div class="section">
            <h2>Ποσοστό Επιτυχίας</h2>
            <div class="progress-chart">
                <div class="progress-bar">
                    <div class="progress-passed" style="width: <?php echo $passed_percentage; ?>%"></div>
                    <div class="progress-failed" style="width: <?php echo $failed_percentage; ?>%"></div>
                </div>
                <div class="progress-labels">
                    <span>Περασμένες: <?php echo number_format($passed_percentage, 1); ?>%</span>
                    <span>Αποτυχημένες: <?php echo number_format($failed_percentage, 1); ?>%</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(count($recent_grades) > 0): ?>
        <div class="section">
            <h2>Πρόσφατες Βαθμολογίες</h2>
            <div class="recent-grades">
                <?php foreach($recent_grades as $recent): ?>
                <div class="recent-grade-card">
                    <div class="recent-grade grade-<?php 
                        $grade = (float)$recent['grade'];
                        if ($grade >= 86) echo 'a';
                        elseif ($grade >= 71) echo 'b';
                        elseif ($grade >= 51) echo 'c';
                        else echo 'd';
                    ?>">
                        <?php echo $recent['grade']; ?>
                    </div>
                    <div class="recent-details">
                        <strong><?php echo htmlspecialchars($recent['title']); ?></strong><br>
                        <?php echo htmlspecialchars($recent['course_name']); ?><br>
                        <small><?php echo date('d/m/Y', strtotime($recent['submitted_at'])); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(count($course_stats) > 0): ?>
        <div class="section">
            <h2>Στατιστικά ανά Μάθημα</h2>
            <div class="course-stats-grid">
                <?php foreach($course_stats as $stat): ?>
                <div class="course-stat-card">
                    <div class="course-header">
                        <div class="course-title"><?php echo htmlspecialchars($stat['course_name']); ?></div>
                        <span class="course-code"><?php echo htmlspecialchars($stat['course_code']); ?></span>
                    </div>
                    <div>Σύνολο Βαθμολογημένων: <?php echo $stat['total_assignments']; ?></div>
                    <div class="grade-stats">
                        <div class="grade-item">
                            <div class="grade-value"><?php echo number_format($stat['average_grade'], 1); ?></div>
                            <div class="grade-label">Μέσος Όρος</div>
                        </div>
                        <div class="grade-item">
                            <div class="grade-value"><?php echo number_format($stat['min_grade'], 1); ?></div>
                            <div class="grade-label">Ελάχιστος</div>
                        </div>
                        <div class="grade-item">
                            <div class="grade-value"><?php echo number_format($stat['max_grade'], 1); ?></div>
                            <div class="grade-label">Μέγιστος</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>Οι Βαθμολογίες μου</h2>
            
            <?php if(count($grades) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Μάθημα</th>
                            <th>Εργασία</th>
                            <th>Καθηγητής</th>
                            <th>Ημ. Βαθμολόγησης</th>
                            <th>Βαθμός</th>
                            <th>Ανατροφοδότηση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($grades as $grade): 
                            $grade_value = (float)$grade['grade'];
                            $grade_class = '';
                            if ($grade_value >= 86) $grade_class = 'grade-a';
                            elseif ($grade_value >= 71) $grade_class = 'grade-b';
                            elseif ($grade_value >= 51) $grade_class = 'grade-c';
                            else $grade_class = 'grade-d';
                            
                            $percentage = ($grade_value / $grade['max_points']) * 100;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($grade['course_code']); ?></strong><br>
                                <small><?php echo htmlspecialchars($grade['course_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($grade['assignment_title']); ?></td>
                            <td><?php echo htmlspecialchars($grade['teacher_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($grade['submitted_at'])); ?></td>
                            <td class="grade-cell <?php echo $grade_class; ?>">
                                <?php echo $grade['grade']; ?>/<?php echo $grade['max_points']; ?><br>
                                <small>(<?php echo number_format($percentage, 1); ?>%)</small>
                            </td>
                            <td>
                                <?php if(!empty($grade['feedback'])): ?>
                                    <span class="feedback-toggle" onclick="toggleFeedback(this)">
                                        Προβολή ανατροφοδότησης
                                    </span>
                                    <div class="feedback-content">
                                        <?php echo nl2br(htmlspecialchars($grade['feedback'])); ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="export-btn">
                    <button class="btn btn-secondary" onclick="exportGradesToPDF()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                        Εξαγωγή σε PDF
                    </button>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <h3>Δεν υπάρχουν βαθμολογίες ακόμα</h3>
                    <p>Οι βαθμολογίες από τους καθηγητές θα εμφανίζονται εδώ μόλις βαθμολογηθούν οι υποβολές σας.</p>
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

    function toggleFeedback(element) {
        const feedbackContent = element.nextElementSibling;
        if (feedbackContent.style.display === 'block') {
            feedbackContent.style.display = 'none';
            element.textContent = 'Προβολή ανατροφοδότησης';
        } else {
            feedbackContent.style.display = 'block';
            element.textContent = 'Απόκρυψη ανατροφοδότησης';
        }
    }
    
    function exportGradesToPDF() {
        alert('Η λειτουργία εξαγωγής σε PDF θα είναι διαθέσιμη σύντομα!');
    }
    
    function createGradeDistribution() {
        const distributionContainer = document.getElementById('gradeDistribution');
        if (!distributionContainer) return;
        
        const categories = [
            { label: '90-100', min: 90, max: 100, color: '#27ae60' },
            { label: '80-89', min: 80, max: 89, color: '#3498db' },
            { label: '70-79', min: 70, max: 79, color: '#f1c40f' },
            { label: '60-69', min: 60, max: 69, color: '#e67e22' },
            { label: '50-59', min: 50, max: 59, color: '#d35400' },
            { label: '0-49', min: 0, max: 49, color: '#e74c3c' }
        ];
        
        const legend = document.createElement('div');
        legend.style.display = 'flex';
        legend.style.justifyContent = 'center';
        legend.style.marginTop = '20px';
        legend.style.gap = '15px';
        legend.style.flexWrap = 'wrap';
        
        categories.forEach(category => {
            const legendItem = document.createElement('div');
            legendItem.style.display = 'flex';
            legendItem.style.alignItems = 'center';
            legendItem.style.margin = '5px';
            
            const colorBox = document.createElement('div');
            colorBox.style.width = '15px';
            colorBox.style.height = '15px';
            colorBox.style.backgroundColor = category.color;
            colorBox.style.marginRight = '5px';
            colorBox.style.borderRadius = '3px';
            
            const label = document.createElement('span');
            label.textContent = category.label;
            label.style.fontSize = '0.9rem';
            label.style.color = '#666';
            
            legendItem.appendChild(colorBox);
            legendItem.appendChild(label);
            legend.appendChild(legendItem);
        });
        
        distributionContainer.parentElement.appendChild(legend);
    }
      
    window.onload = function() {
        <?php if($total_graded > 0): ?>
            createGradeDistribution();
        <?php endif; ?>
        
        setInterval(updateStatistics, 60000);
        
        setTimeout(() => {
            const bars = document.querySelectorAll('.distribution-bar');
            bars.forEach(bar => {
                const currentHeight = bar.style.height;
                bar.style.height = '0px';
                setTimeout(() => {
                    bar.style.height = currentHeight;
                }, 100);
            });
        }, 500);
    };
   </script>
</body>
</html>