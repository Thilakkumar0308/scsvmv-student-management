<?php
// Check if the form was submitted using the POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user_input = htmlspecialchars($_POST['user_input']);
    $required_value = "asdf;lkj";

    if ($user_input === $required_value) {
        echo "<p style='color: green;'>Validation successful. The input is correct.</p>";
    } else {
        echo "<p style='color: red;'>Validation failed. Only the value 'asdf;lkj' is allowed.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Restricted Input</title>

    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const allowed = "asdf;lkj";
            const input = document.getElementById("user_input");

            input.addEventListener("input", function() {
                let current = input.value;

                // If current typed characters don't match the start of allowed string → revert
                if (!allowed.startsWith(current)) {
                    input.value = current.slice(0, -1);
                }
            });

        });
    </script>

</head>
<body>

<h3>Enter the required value:</h3>

<form method="POST" action="">
    <label for="user_input">Input:</label>
    <input type="text" id="user_input" name="user_input" required autocomplete="off">

    <br><br>

    <button type="submit">Submit</button>
</form>

</body>
</html>
