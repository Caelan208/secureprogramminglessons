<?php 
session_start();
include 'includes/db.php';
//Tables aanmaken
include 'includes/userTable.php';
include 'includes/transactionTable.php';
$maxLoginAttempts = 5;
$lockoutDuration = 300; // seconden
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_until'])) $_SESSION['lockout_until'] = 0;
$lockedOut = $_SESSION['lockout_until'] > time();
function isPasswordHash(string $password): bool { return password_get_info($password)['algo'] !== 0; }
//Controleer of post is geset
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($lockedOut) {
        $remaining = max(0, $_SESSION['lockout_until'] - time());
        $error = "Te veel mislukte inlogpogingen. Probeer het opnieuw over " . ceil($remaining / 60) . " minuten.";
    } elseif ($username === '' || $password === '') {
        $error = "Vul zowel gebruikersnaam als wachtwoord in.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $loginSuccess = false;
        if ($user) {
            $loginSuccess = isPasswordHash($user['password']) ? password_verify($password, $user['password']) : hash_equals($user['password'], $password);
            if ($loginSuccess && (!isPasswordHash($user['password']) || password_needs_rehash($user['password'], PASSWORD_DEFAULT))) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE user SET password = ? WHERE id = ?");
                $update->execute([$newHash, $user['id']]);
            }
        }
        if ($loginSuccess) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['user'] = $user;
            $_SESSION['login_attempts'] = 0;
            $_SESSION['lockout_until'] = 0;
            header("location: dashboard.php");
            exit;
        }
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= $maxLoginAttempts) {
            $_SESSION['lockout_until'] = time() + $lockoutDuration;
            $error = "Te veel mislukte inlogpogingen. Je bent tijdelijk geblokkeerd.";
        } else {
            $remaining = $maxLoginAttempts - $_SESSION['login_attempts'];
            $error = "Gebruikersnaam of wachtwoord is onjuist. Je hebt $remaining pogingen over.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Omanido</title>
    <!-- Voeg Tailwind CSS toe via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto mt-20 p-6 bg-white max-w-sm shadow-md rounded-md">
        <div class="flex justify-center">
            <img src="img/Omanido1.png" alt="Omanido Logo" class="mb-6 w-1/2"> <!-- Aanpassen van de breedte naar 1/2 van de container -->
        </div>
        <h2 class="text-lg text-center font-bold mb-6">Inloggen bij Omanido</h2>
        <form action="<? echo htmlspecialchars($_SERVER["PHP_SELF"]);  ?>" method="post">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700">Gebruikersnaam:</label>
                <input type="text" id="username" name="username" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700">Wachtwoord:</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <input type="submit" value="Inloggen" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 focus:outline-none focus:shadow-outline">
        </form>
        <a href="register.php" class="block text-center text-sm text-blue-600 hover:underline mt-4">Nog geen account? Registreer hier</a>
    </div>

    <!-- debugbar weg halen --> 
    
</body>
</html>
