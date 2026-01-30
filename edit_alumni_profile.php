<?php
$page_title = 'Edit Alumni Profile';
require_once 'includes/header.php';
require_role('Admin');

$student_id = $_GET['student_id'] ?? '';

$stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) die('Invalid student');

$student_db_id = $student['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("
        INSERT INTO alumni_profiles
        (student_id, current_company, designation, industry, linkedin_url,
         phone, email, location, higher_studies, achievements)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            current_company=VALUES(current_company),
            designation=VALUES(designation),
            industry=VALUES(industry),
            linkedin_url=VALUES(linkedin_url),
            phone=VALUES(phone),
            email=VALUES(email),
            location=VALUES(location),
            higher_studies=VALUES(higher_studies),
            achievements=VALUES(achievements)
    ");

    $stmt->bind_param(
        "isssssssss",
        $student_db_id,
        $_POST['current_company'],
        $_POST['designation'],
        $_POST['industry'],
        $_POST['linkedin_url'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['location'],
        $_POST['higher_studies'],
        $_POST['achievements']
    );

    $stmt->execute();
    header("Location: alumni_profile.php?student_id=$student_id");
    exit;
}
?>

<div class="container mt-4">
    <h4>Edit Alumni Profile</h4>

    <form method="POST" class="card shadow p-3">
        <input class="form-control mb-2" name="current_company" placeholder="Company">
        <input class="form-control mb-2" name="designation" placeholder="Designation">
        <input class="form-control mb-2" name="industry" placeholder="Industry">
        <input class="form-control mb-2" name="location" placeholder="Location">
        <input class="form-control mb-2" name="higher_studies" placeholder="Higher Studies">
        <input class="form-control mb-2" name="linkedin_url" placeholder="LinkedIn URL">
        <textarea class="form-control mb-2" name="achievements" placeholder="Achievements"></textarea>

        <button class="btn btn-success">Save Profile</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
