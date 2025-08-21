<?php
// Paramètres de connexion à la base de données Agriflow
$host = 'localhost';
$db   = 'agriflow';
$user = 'root'; // À adapter si besoin
$pass = '';
$charset = 'utf8mb4';

// Fichier SQL à exécuter
$sqlFile = __DIR__ . '/../migration.sql';

try {
    // Connexion PDO
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Lire le fichier SQL
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Impossible de lire le fichier $sqlFile");
    }

    // Découper les requêtes (attention : basique, ne gère pas les délimiteurs complexes)
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    $success = 0;
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
            $success++;
        }
    }

    echo "<p style='color:green'>Migration terminée avec succès ($success requêtes exécutées).</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur PDO : " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Erreur : " . $e->getMessage() . "</p>";
} 