<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ARMS Login</title>
</head>
<body>

<h2>ARMS Login</h2>

<form action="auth/login.php" method="POST">

    <input
        type="email"
        name="email"
        placeholder="Email"
        required>

    <br><br>

    <input
        type="password"
        name="password"
        placeholder="Password"
        required>

    <br><br>

    <button type="submit">
        Login
    </button>

</form>

</body>
</html>