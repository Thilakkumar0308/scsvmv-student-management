<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';
require_once 'includes/functions.php';
require_login();

// Allow only Teacher
if (!has_role('Teacher')) {
    redirect('dashboard.php');
}

$teacher_id = $_SESSION['user_id'] ?? null;
$teacher_name = $_SESSION['full_name'] ?? 'Teacher';

// ===========================
// 1️⃣ Stats Initialization
// ===========================
$stats = [
    'total_students' => 0,
    'total_classes' => 0,
    'active_disciplinary' => 0
];

// ===========================
// 2️⃣ Fetch Teacher’s Classes
// ===========================
$teacher_classes = [];
$stmt = $conn->prepare("
    SELECT c.id, c.class_name, c.section, COUNT(s.id) AS student_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    INNER JOIN teacher_classes tc ON c.id = tc.class_id
    WHERE tc.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.class_name, c.section
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $teacher_classes[] = $row;
}
$stmt->close();

// ===========================
// 3️⃣ Total Students
// ===========================
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) AS total
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN teacher_classes tc ON c.id = tc.class_id
    WHERE tc.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stats['total_students'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// ===========================
// 4️⃣ Active Disciplinary Actions
// ===========================
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM disciplinary_actions da
    JOIN students s ON da.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN teacher_classes tc ON c.id = tc.class_id
    WHERE tc.teacher_id = ? AND da.status = 'Active'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stats['active_disciplinary'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// ===========================
// 5️⃣ Recent Disciplinary Actions
// ===========================
$recent_disciplinary = [];
$stmt = $conn->prepare("
    SELECT da.*, s.first_name, s.last_name, s.student_id, c.class_name, c.section
    FROM disciplinary_actions da
    JOIN students s ON da.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN teacher_classes tc ON c.id = tc.class_id
    WHERE tc.teacher_id = ?
    ORDER BY da.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_disciplinary[] = $row;
}
$stmt->close();

include 'includes/header.php';
?>

<div class="container-fluid mt-3">

    <!-- Welcome -->
    <div class="text-center mb-4">
        <h2 class="text-white fw-bold fs-5">
            <i class="fas fa-user-tie me-2 text-warning"></i>
            Welcome, <?= htmlspecialchars($teacher_name) ?>!
        </h2>
        <p class="text-white-50 fs-7">Here’s a summary of your classes and student activities.</p>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <?php 
        $cards = [
            ['title'=>'Students', 'icon'=>'fas fa-users', 'value'=>$stats['total_students'], 'color'=>'primary', 'link'=>'students.php?teacher_id=' . urlencode($teacher_id)],
            ['title'=>'Your Classes', 'icon'=>'fas fa-chalkboard-teacher', 'value'=>count($teacher_classes), 'color'=>'warning', 'link'=>'class_management.php'],
            ['title'=>'Active Disciplinary', 'icon'=>'fas fa-exclamation-triangle', 'value'=>$stats['active_disciplinary'], 'color'=>'danger', 'link'=>'disciplinary.php']
        ];
        foreach($cards as $card): ?>
        <div class="col-6 col-sm-6 col-md-3">
            <a href="<?= $card['link'] ?>" class="text-decoration-none">
                <div class="card stat-card border-0 text-center shadow-sm h-100">
                    <div class="card-body py-4">
                        <div class="icon-circle bg-<?= $card['color'] ?> bg-opacity-10 mb-3">
                            <i class="<?= $card['icon'] ?> text-<?= $card['color'] ?> fa-2x"></i>
                        </div>
                        <h6 class="fw-semibold text-secondary mb-1"><?= $card['title'] ?></h6>
                        <h3 class="fw-bold text-dark mb-0"><?= $card['value'] ?></h3>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">

        <!-- Teacher's Classes -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-chalkboard me-2"></i>Your Classes</h6>
                    <a href="class_management.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body p-2">
                    <?php if(empty($teacher_classes)): ?>
                        <p class="text-muted small m-0">No classes assigned.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach($teacher_classes as $class): ?>
                                <a href="students.php?class_id=<?= urlencode($class['id']) ?>"  
                                   class="list-group-item list-group-item-action border-0 mb-2 shadow-sm rounded hover-list flex-column flex-sm-row">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="d-block"><?= htmlspecialchars($class['class_name']) ?></strong>
                                            <span class="text-muted small d-block">Section: <?= htmlspecialchars($class['section']) ?></span>
                                        </div>
                                        <span class="badge bg-info mt-1 mt-sm-0">
                                            <?= htmlspecialchars($class['student_count']) ?> Students
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Disciplinary Actions -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Recent Disciplinary Actions</h6>
                    <a href="disciplinary.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body p-2">
                    <?php if(empty($recent_disciplinary)): ?>
                        <p class="text-muted small m-0">No disciplinary actions found.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach($recent_disciplinary as $disc): ?>
                                <a href="student_da_record.php?student_id=<?= urlencode($disc['student_id']) ?>" 
                                   class="list-group-item list-group-item-action border-0 mb-2 shadow-sm rounded hover-list flex-column flex-sm-row">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="d-block"><?= htmlspecialchars($disc['first_name'].' '.$disc['last_name']) ?></strong>
                                            <span class="text-muted small d-block"><b>Class :</b> <?= htmlspecialchars($disc['class_name'].' '.$disc['section']) ?></span>
                                            <span class="text-muted small d-block"><b>DA Reason :</b> <?= htmlspecialchars($disc['da_reason'] ?: 'No reason provided') ?></span>
                                            <span class="text-muted small d-block"><b>Resolved :</b> <?= htmlspecialchars($disc['resolved_reason'] ?: 'Not resolved') ?></span>
                                        </div>
                                        <span class="badge bg-<?= $disc['status']=='Active'?'danger':'secondary' ?> mt-1 mt-sm-0">
                                            <?= htmlspecialchars($disc['status']) ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Overview -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Overview</h6>
                </div>
                <div class="card-body p-3">

                    <!-- Today’s Schedule -->
                    <h6 class="fw-semibold text-primary mb-2"><i class="fas fa-calendar-day me-2"></i>Today's Classes</h6>
                    <?php if(empty($today_classes)): ?>
                        <p class="text-muted small">No classes scheduled today.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush mb-3">
                        <?php foreach($today_classes as $cls): ?>
                            <li class="list-group-item small">
                                <b><?= htmlspecialchars($cls['class_name'].' '.$cls['section']) ?></b> – <?= htmlspecialchars($cls['subject_name']) ?><br>
                                <span class="text-muted"><?= date('h:i A', strtotime($cls['start_time'])) ?> - <?= date('h:i A', strtotime($cls['end_time'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- Announcements -->
                    <h6 class="fw-semibold text-warning mb-2"><i class="fas fa-bullhorn me-2"></i>Announcements</h6>
                    <?php if(empty($announcements)): ?>
                        <p class="text-muted small">No new announcements.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush mb-3">
                        <?php foreach($announcements as $a): ?>
                            <li class="list-group-item small">
                                <b><?= htmlspecialchars($a['title']) ?></b><br>
                                <span class="text-muted"><?= htmlspecialchars($a['message']) ?></span><br>
                                <small class="text-muted"><?= date('M d, Y', strtotime($a['created_at'])) ?></small>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* Card Glow & Animation */
.stat-card {
    border-radius: 18px;
    transition: all 0.3s ease;
    background-color: #fff;
}
.stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* Icon Circle */
.icon-circle {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    background-color: rgba(255,255,255,0.3);
    transition: all 0.3s ease;
}
.stat-card:hover .icon-circle {
    transform: scale(1.1);
}

/* Hover List */
.hover-list {
    transition: 0.3s ease;
}
.hover-list:hover {
    transform: translateX(5px);
    background-color: #fff5f5;
}

/* Mobile Adjustments */
@media (max-width: 480px) {
    .card h3 { font-size: 1.1rem !important; }
    .card h6 { font-size: 0.85rem !important; }
    .list-group-item { font-size: 0.8rem; }
    .badge { font-size: 0.65rem; padding: 0.25em 0.4em; }
}
</style>

<?php include 'includes/footer.php'; ?>
