<?php
session_start();
include 'includes/db.php';

function validateTransactionInput($bedrag, $omschrijving, $ontvanger) {
    $errors = [];
    $bedrag = trim((string)$bedrag);
    $omschrijving = trim(strip_tags((string)$omschrijving));
    $ontvanger = trim((string)$ontvanger);

    if ($bedrag === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $bedrag)) {
        $errors[] = "Voer een geldig positief bedrag in met maximaal 2 decimalen.";
    } else {
        $amountValue = (float)$bedrag;
        if ($amountValue <= 0) {
            $errors[] = "Het bedrag moet groter zijn dan 0.";
        }
    }

    if ($ontvanger === '') {
        $errors[] = "Voer een ontvanger in.";
    }

    if ($omschrijving === '') {
        $errors[] = "Voer een omschrijving in.";
    } elseif (mb_strlen($omschrijving) > 500) {
        $errors[] = "De omschrijving is te lang.";
    }

    return [
        'errors' => $errors,
        'bedrag' => $bedrag,
        'omschrijving' => $omschrijving,
        'ontvanger' => $ontvanger,
    ];
}

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true){
    header("location: index.php");
    exit;
}

// als button is ingedrukt
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $ontvanger = $_POST['ontvanger'] ?? '';
    $bedrag = $_POST['bedrag'] ?? '';
    $omschrijving = $_POST['omschrijving'] ?? '';

    $validation = validateTransactionInput($bedrag, $omschrijving, $ontvanger);
    $bedrag = $validation['bedrag'];
    $omschrijving = $validation['omschrijving'];
    $ontvanger = $validation['ontvanger'];

    if (empty($validation['errors'])) {
        // Controleer of de ontvanger bestaat
        $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ?");
        $stmt->execute([$ontvanger]);
        $ontvangerRecord = $stmt->fetch();

        if($stmt->rowCount() == 1) {
            if ($ontvangerRecord['id'] == $_SESSION['user']['id']) {
                $error = "Je kunt geen geld naar jezelf overmaken.";
            } else {
                $currentBalance = (float)$_SESSION['user']['balance'];
                $amountValue = (float)$bedrag;

                // Controleer of de gebruiker genoeg saldo heeft
                if($currentBalance >= $amountValue) {
                    // Zet de transactie in de database
                    $stmt = $pdo->prepare("INSERT INTO transaction (sender, receiver, amount, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user']['id'], $ontvangerRecord['id'], $amountValue, $omschrijving]);

                    // Haal het saldo van de ontvanger op
                    $stmt = $pdo->prepare("SELECT balance FROM user WHERE username = ?");
                    $stmt->execute([$ontvangerRecord['username']]);
                    $saldo = $stmt->fetchColumn();

                    // Bereken het nieuwe saldo van de ontvanger
                    $saldo = $saldo + $amountValue;

                    // Update het saldo van de ontvanger
                    $stmt = $pdo->prepare("UPDATE user SET balance = ? WHERE username = ?");
                    $stmt->execute([$saldo, $ontvangerRecord['username']]);

                    // Bereken het nieuwe saldo van de ingelogde gebruiker
                    $stmt = $pdo->prepare("SELECT balance FROM user WHERE id = ?");
                    $stmt->execute([$_SESSION['user']['id']]);

                    //Bereken het nieuwe saldo van de ingelogde gebruiker
                    $saldo = $stmt->fetchColumn();
                    $saldo = $saldo - $amountValue;

                    // Update het saldo van de ingelogde gebruiker
                    $stmt = $pdo->prepare("UPDATE user SET balance = ? WHERE id = ?");
                    $stmt->execute([$saldo, $_SESSION['user']['id']]);

                    $success = "Het bedrag is succesvol overgemaakt";
                } else {
                    $error = "Je hebt niet genoeg saldo om dit bedrag over te maken";
                }
            }
        } else {
            $error = "Deze gebruiker bestaat niet";
        }
    } else {
        $error = implode(' ', $validation['errors']);
    }

}

include 'includes/db.php';

// Haal het saldo van de ingelogde gebruiker op
$stmt = $pdo->prepare("SELECT balance FROM user WHERE id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$saldo = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Omanido</title>
    <!-- Voeg Tailwind CSS toe via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto p-4">
        <div class="flex flex-wrap -mx-2">
            <!-- Saldo Kaart -->
            <div class="w-full md:w-1/3 px-2 mb-4">
                <div class="bg-white p-6 rounded-lg shadow-md h-full flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-xl mb-2">Mijn Saldo</h3>
                        <p class="text-sm text-gray-600 mb-4">Actueel Beschikbaar Saldo</p>
                    </div>
                    <p class="text-4xl font-bold mb-4 <?php echo $saldo >= 0 ? 'text-green-500' : 'text-red-500'; ?> self-center">
                        €<?php echo number_format($saldo, 2, ',', '.'); ?>
                    </p>
                    <div class="text-center">
                        <a href="transacties.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                            Transactieoverzicht
                        </a>
                    </div>
                </div>
            </div>


            <!-- Overdrachtsformulier Kaart -->
            <div class="w-full md:w-2/3 px-2 mb-4">
                <div class="bg-white p-6 rounded-lg shadow-md h-full"> <!-- Verhoogde padding van p-4 naar p-6 -->
                    <h3 class="font-bold text-xl mb-4">Geld Overmaken</h3>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post">
                        <div class="mb-4">
                            <label for="ontvanger" class="block text-sm font-medium text-gray-700">Ontvanger:</label>
                            <input type="text" id="ontvanger" name="ontvanger" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <div class="mb-4">
                            <label for="bedrag" class="block text-sm font-medium text-gray-700">Bedrag(€):</label>
                            <input type="number" id="bedrag" name="bedrag" step="0.01" min="0.01" inputmode="decimal" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <div class="mb-4">
                            <label for="omschrijving" class="block text-sm font-medium text-gray-700">Omschrijving:</label>
                            <input type="text" id="omschrijving" name="omschrijving" maxlength="500" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <input type="submit" value="Overmaken" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 focus:outline-none focus:shadow-outline">
                        <?php
                            if(isset($error)) {
                                echo '<p class="text-red-500 text-sm mt-2">' . $error . '</p>';
                            }
                            if(isset($success)) {
                                echo '<p class="text-green-500 text-sm mt-2">' . $success . '</p>';
                            }
                        ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
