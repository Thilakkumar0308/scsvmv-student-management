<?php
session_start();
require_once 'config/db.php';

// 🔐 Secure Access
require_login(); 
if (!has_role('Admin') && !has_role('HOD') && !has_role('Teacher')) {
    die("Access Denied");
}

// Validate student ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Student ID");
}
$student_id = (int)$_GET['id'];

// Fetch student
$query = "
    SELECT s.*, c.class_name, c.section 
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student not found");
}

// Profile picture handling
$photo_path = $student['profile_picture'];


$student_name = $student['first_name'] . " " . $student['last_name'];
$regno = $student['student_id'];

?>
<!DOCTYPE html>
<html>
<head>

<!-- ⭐ PDF Auto Filename (Chrome uses page title) -->
<title><?php echo $student_name . " - " . $regno . " - Profile"; ?></title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #fff;
    -webkit-print-color-adjust: exact;
}

.print-container {
    width: 800px;
    margin: auto;
    padding: 20px;
    border: 5px solid #ff6600;
}

.heading {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    color: #cc0000;
    margin-top: -10px;
}

.logo-box {
    text-align: center;
    margin-bottom: 5px;
}

.sub-heading {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 15px;
}

.table-box {
    width: 100%;
    border-collapse: collapse;
    font-size: 15px;
}

.table-box td {
    border: 1px solid #ff6600;
    padding: 6px;
}

.section-title {
    background: #ff6600;
    color: white;
    font-weight: bold;
    padding: 6px;
    text-align: center;
}

.photo-box {
    width: 140px;
    height: 160px;
    border: 1px solid #ff6600;
    text-align: center;
    padding: 0;
}

.photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

</head>

<body onload="window.print()">

<div class="print-container">

    <div class="logo-box">
        <img src="assets/img/logo.png" style="width:90px; height:auto;">
    </div>

    <div class="heading">
        SRI CHANDRASEKHARENDRA SARASWATHI VISWA MAHAVIDYALAYA
    </div>

    <div class="sub-heading">STUDENT PROFORMA</div>

    <div class="section-title">PERSONAL DETAILS</div>

    <table class="table-box">
        <tr>
            <td>Register No</td>
            <td><?php echo $regno; ?></td>

            <td rowspan="6" class="photo-box">
                <?php if ($photo_path != ""): ?>
                    <img src="<?php echo $photo_path; ?>">
                <?php else: ?>
                    <br>No Image
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <td>Name</td>
            <td><?php echo $student_name; ?></td>
        </tr>

        <tr>
            <td>Course</td>
            <td><?php echo $student['class_name'] . " - " . $student['section']; ?></td>
        </tr>

        <tr>
            <td>Father's Name</td>
            <td><?php echo $student['parent_name']; ?></td>
        </tr>

        <tr>
            <td>Date of Birth</td>
            <td><?php echo $student['date_of_birth']; ?></td>
        </tr>

        <tr>
            <td>Gender</td>
            <td><?php echo $student['gender']; ?></td>
        </tr>

        <tr>
            <td>Address</td>
            <td colspan="2"><?php echo $student['address']; ?></td>
        </tr>

        <tr>
            <td>Phone</td>
            <td colspan="2"><?php echo $student['phone']; ?></td>
        </tr>

        <tr>
            <td>Email</td>
            <td colspan="2"><?php echo $student['email']; ?></td>
        </tr>
    </table>

    <br>

    <div class="section-title">ACADEMIC DETAILS</div>

    <table class="table-box">
        <tr>
            <td>Date of Admission</td>
            <td colspan="2"><?php echo $student['admission_date']; ?></td>
        </tr>
    </table>
</div>

</body>
</html>
