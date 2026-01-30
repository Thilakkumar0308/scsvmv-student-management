<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';
require_once 'includes/functions.php';
require_login();

// Only allow HOD or Admin
if (!has_role('HOD') && !has_role('Admin')) {
    redirect('dashboard.php');
}

$hod_id = $_SESSION['user_id'];

// Get HOD's department
$hod_department = null;
$stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->bind_param("i", $hod_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $hod_department = $row['department_id'];
}

// Fetch statistics
$stats = [];

// Total students
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE c.department_id = ?
");
$stmt->bind_param("i", $hod_department);
$stmt->execute();
$stats['total_students'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Total classes
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM classes WHERE department_id = ?");
$stmt->bind_param("i", $hod_department);
$stmt->execute();
$stats['total_classes'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Total teachers
$stmt = $conn->prepare("SELECT COUNT(*) AS total_teachers FROM users WHERE role='Teacher' AND department_id = ?");
$stmt->bind_param("i", $hod_department);
$stmt->execute();
$stats['total_teachers'] = $stmt->get_result()->fetch_assoc()['total_teachers'] ?? 0;

// Active disciplinary actions
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM disciplinary_actions da
    JOIN students s ON da.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE c.department_id = ? AND da.status='Active'
");
$stmt->bind_param("i", $hod_department);
$stmt->execute();
$stats['active_disciplinary'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Recent disciplinary actions
$recent_disciplinary = [];
$stmt = $conn->prepare("
    SELECT da.*, s.first_name, s.last_name, s.student_id, c.class_name, c.section
    FROM disciplinary_actions da
    JOIN students s ON da.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE c.department_id = ?
    ORDER BY da.created_at DESC
");
$stmt->bind_param("i", $hod_department);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_disciplinary[] = $row;
}

// Total classes
$total_classes = [];
$stmt = $conn->prepare("
    SELECT c.id, c.class_name, c.section, c.department_id, COUNT(s.id) AS student_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    WHERE c.department_id = ?
    GROUP BY c.id
    ORDER BY c.class_name, c.section
");
$stmt->bind_param("i", $hod_department);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $total_classes[] = $row;
}

// Worst Students (Most Disciplinary Actions)
$worst_students = [];
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.first_name,
        s.last_name,
        s.student_id,
        c.class_name,
        c.section,
        COUNT(da.id) AS da_count
    FROM students s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN disciplinary_actions da ON da.student_id = s.id
    WHERE c.department_id = ?
    GROUP BY s.id
    HAVING da_count > 0
    ORDER BY da_count DESC
");
$stmt->bind_param("i", $hod_department);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $worst_students[] = $row;
}

include 'includes/header.php';
?>

<div class="container-fluid mt-3">

    <!-- Welcome -->
    <div class="text-center mb-4">
        <h2 class="text-white fw-bold fs-5">
            <i class="fas fa-user-tie me-2 text-warning"></i>
            Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!
        </h2>
        <p class="text-white-50 fs-7">Here’s a summary of your department activities.</p>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <?php 
        $cards = [
            ['title'=>'Students', 'icon'=>'fas fa-users', 'value'=>$stats['total_students'], 'color'=>'primary', 'link'=>'students.php'],
            ['title'=>'Total Classes', 'icon'=>'fas fa-chalkboard-teacher', 'value'=>$stats['total_classes'], 'color'=>'warning', 'link'=>'class_management.php'],
            ['title'=>'Total Faculty', 'icon'=>'fas fa-user-graduate', 'value'=>$stats['total_teachers'], 'color'=>'success', 'link'=>'users.php?department=' . urlencode($hod_department)],
            ['title'=>'Active Disciplinary', 'icon'=>'fas fa-exclamation-triangle', 'value'=>$stats['active_disciplinary'], 'color'=>'danger', 'link'=>'disciplinary.php'],
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
        
        <!-- Total Classes -->
<div class="col-12 col-lg-4">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Total Classes</h6>
            <a href="class_management.php" class="btn btn-sm btn-light">View All</a>
        </div>
        <div class="card-body p-2">
            <?php if(empty($total_classes)): ?>
                <p class="text-muted small m-0">No classes found.</p>
            <?php else: ?>
                <div class="list-group class-scroll-box">
                    <?php foreach($total_classes as $class): ?>
                        <a href="students.php?class_id=<?= urlencode($class['id']) ?>"  
                           class="list-group-item list-group-item-action border-0 mb-2 shadow-sm rounded hover-glow flex-column flex-sm-row">
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
                        <p class="text-muted small m-0">No disciplinary actions.</p>
                    <?php else: ?>
                        <div class="list-group class-scroll-box">
                            <?php foreach($recent_disciplinary as $disc): ?>
                                <a href="student_da_record.php?student_id=<?= urlencode($disc['student_id']) ?>" 
                                   class="list-group-item list-group-item-action border-0 mb-2 shadow-sm rounded hover-glow flex-column flex-sm-row">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="d-block"><?= htmlspecialchars($disc['first_name'].' '.$disc['last_name']) ?></strong>
                                            <span class="text-muted small d-block"><b>Class :</b> <?= htmlspecialchars($disc['class_name'].' '.$disc['section']) ?></span>
                                            <span class="text-muted small d-block"><b>DA Reason :</b> <?= htmlspecialchars($disc['da_reason'] ?: 'No reason provided') ?></span>
                                            <span class="text-muted small d-block"><b>Resolved Reason :</b> <?= htmlspecialchars($disc['resolved_reason'] ?: 'No reason provided') ?></span>
                                        </div>
                                        <span class="badge bg-<?= $disc['status']=='Active'?'danger':'secondary' ?> mt-1 mt-sm-0"><?= htmlspecialchars($disc['status']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
        <!-- Overall Stats -->
<!-- Worst Students (Most DA Records) -->
<div class="col-12 col-lg-4">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="fas fa-user-slash me-2"></i> Worst Students (Most DA)
            </h6>
        </div>

        <div class="card-body p-2">
            <?php if(empty($worst_students)): ?>
                <p class="text-muted small m-0">No disciplinary actions recorded.</p>
            <?php else: ?>
                <div class="list-group class-scroll-box">
                    <?php foreach($worst_students as $ws): ?>
                        <a href="student_info.php?id=<?= $ws['id'] ?>" 
                           class="list-group-item list-group-item-action border-0 mb-2 shadow-sm rounded hover-glow">

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block">
                                        <?= htmlspecialchars($ws['first_name'] . ' ' . $ws['last_name']); ?>
                                    </strong>
                                    <span class="text-muted small d-block">
                                        Reg: <?= htmlspecialchars($ws['student_id']); ?>
                                    </span>
                                    <span class="text-muted small d-block">
                                        <?= htmlspecialchars($ws['class_name'].' '.$ws['section']); ?>
                                    </span>
                                </div>

                                <span class="badge bg-danger">
                                    <?= $ws['da_count'] ?> DA
                                </span>
                            </div>

                        </a>
                    <?php endforeach; ?>
                </div>
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

/* List Items */
.hover-list {
    transition: 0.3s ease;
}
.hover-list:hover {
    transform: translateX(5px);
    background-color: #fff5f5;
}

/* Rounded Header Fix */
.card-header.rounded-top-4 {
    border-top-left-radius: 1rem !important;
    border-top-right-radius: 1rem !important;
}

/* Mobile Adjustments */
@media (max-width: 480px) {
    .card h3 { font-size: 1.1rem !important; }
    .card h6 { font-size: 0.85rem !important; }
    .list-group-item { font-size: 0.8rem; }
    .badge { font-size: 0.65rem; padding: 0.25em 0.4em; }
}

/* Scroll Box for Lists */
.class-scroll-box {
    max-height: 250px;      /* adjust height */
    overflow-y: auto;       /* vertical scroll */
    overflow-x: hidden;     /* hide horizontal scroll */
    padding-right: 5px;
}

/* Optional: smooth clean white scrollbar */
.class-scroll-box::-webkit-scrollbar {
    width: 6px;
}
.class-scroll-box::-webkit-scrollbar-thumb {
    background: #bbb;
    border-radius: 3px;
}
.class-scroll-box::-webkit-scrollbar-track {
    background: #f1f1f1;
}
</style>

<?php include 'includes/footer.php'; ?>
