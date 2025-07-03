<?php
session_start();

if (!isset($_SESSION["conexion"])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET["tabla"])) {
    die("⚠️ Tabla no especificada.");
}

$tabla = $_GET["tabla"];
$conexion = $_SESSION["conexion"];
$dsn = "{$conexion['tipo']}:host={$conexion['host']};port={$conexion['puerto']};dbname={$conexion['base']}";
$usuario = $conexion["usuario"];
$clave = $conexion["clave"];

try {
    $pdo = new PDO($dsn, $usuario, $clave);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT * FROM `$tabla`");
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnas = array_keys($resultados[0] ?? []);

    $relQuery = $pdo->prepare("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = :base
        AND TABLE_NAME = :tabla
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $relQuery->execute(['base' => $conexion['base'], 'tabla' => $tabla]);
    $relaciones = $relQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("❌ Error al acceder a la tabla: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de <?= htmlspecialchars($tabla) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
        }
        th {
            background-color: #002855;
            color: white;
        }
        td span.relacion {
            display: block;
            font-size: 12px;
            color: #444;
        }
        a {
            text-decoration: none;
            color: #00427a;
        }
        .acciones a {
            margin-right: 10px;
        }
        .top-nav {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h2>Gestión de <?= htmlspecialchars($tabla) ?></h2>

    <div class="top-nav">
        <a href="insert.php?tabla=<?= urlencode($tabla) ?>">➕ Crear nuevo</a> |
        <a href="tables.php">⬅️ Volver</a>
    </div>

    <table>
        <thead>
            <tr>
                <?php foreach ($columnas as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultados as $fila): ?>
                <tr>
                    <?php foreach ($fila as $campo => $valor): ?>
                        <td>
                            <?php
                            $rel = null;
                            foreach ($relaciones as $r) {
                                if ($r['COLUMN_NAME'] === $campo) {
                                    $rel = $r;
                                    break;
                                }
                            }

                            if ($rel) {
                                $refTable = $rel['REFERENCED_TABLE_NAME'];
                                $refCol = $rel['REFERENCED_COLUMN_NAME'];

                                $relStmt = $pdo->prepare("SELECT * FROM `$refTable` WHERE `$refCol` = ? LIMIT 1");
                                $relStmt->execute([$valor]);
                                $relData = $relStmt->fetch(PDO::FETCH_ASSOC);

                                echo htmlspecialchars($valor);
                                if ($relData) {
                                    $desc = '';
                                    foreach ($relData as $k => $v) {
                                        if ($k !== $refCol && is_string($v)) {
                                            $desc = $v;
                                            break;
                                        }
                                    }
                                    echo "<span class='relacion'>→ $desc</span>";
                                }
                            } else {
                                echo htmlspecialchars($valor);
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="acciones">
                        <a href="edit.php?tabla=<?= urlencode($tabla) ?>&id=<?= urlencode($fila[$columnas[0]]) ?>">✏️ Editar</a>
                        <a href="delete.php?tabla=<?= urlencode($tabla) ?>&id=<?= urlencode($fila[$columnas[0]]) ?>" onclick="return confirm('¿Eliminar este registro?')">🗑️ Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
