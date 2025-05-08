<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
<table border="1">
    <tr id="tableaudedonnee">
        <th>ID</th><th>Nom</th><th>Prénom</th><th>Supprimer</th><th>Modifier</th>
    </tr>

    <?php
    $result = $conn->query("SELECT * FROM employe");
    if (!$result) {
        die("Erreur dans la requête : " . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        echo "
        <tr>
            <td>{$row['id']}</td>
            <td>{$row['nom']}</td>
            <td>{$row['prenom']}</td>
            
            <td>
                <form method='post'>
                    <input type='hidden' name='id' value='{$row['id']}'>
                    <button type='submit' name='supprimer' value='supprimer' id='supprimer'>Supprimer</button>
                </form>
            </td>
            <td>
                <form method='post'>
                    <input type='hidden' name='id' value='{$row['id']}'>
                    <button type='submit' name='modifier' value='modifier' id='modifier'>Modifier</button>
                </form>
            </td>
        </tr>";
    }
    ?>
</body>
</html>