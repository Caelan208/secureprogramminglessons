<?php
// Controleer of de 'user' tabel al bestaat
$checkTable = $pdo->query("SHOW TABLES LIKE 'user'");
if ($checkTable->rowCount() == 0) {
    // Maak de 'user' tabel als deze nog niet bestaat
   $pdo->exec("CREATE TABLE `user` (
        `id` int NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `balance` decimal(10,2) NOT NULL,
        `isAdmin` tinyint(1) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

    // Voeg de standaardgebruikers toe met gehashte wachtwoorden
    $insertUsers = [
        ['Admin', password_hash('AlfaBankAdminAccount', PASSWORD_DEFAULT), 1000.00, 0],
        ['FerryKuhlman', password_hash('12345678', PASSWORD_DEFAULT), 1255.36, 0],
        ['Han2002', password_hash('password', PASSWORD_DEFAULT), 23424.84, 0],
        ['RoyBos', password_hash('qwerty', PASSWORD_DEFAULT), 9.23, 0],
    ];

    $stmt = $pdo->prepare("INSERT INTO `user` (`username`, `password`, `balance`, `isAdmin`) VALUES (?, ?, ?, ?)");
    foreach ($insertUsers as $insertUser) {
        $stmt->execute($insertUser);
    }
}