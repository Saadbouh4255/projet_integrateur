<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        .tou{
            width:30%;
            margin-left:200px;
            margin-top:20px;
        }
        #table{
            
            border-radius: 3px;
            border: 2px solid black;
        }
        th{
            
            height: 30px;
            border-radius: 2px;
            background-color: rgb(75, 75, 249);
        }
        td{
            
            height: 25px;
            border-radius: 2px;
            text-align: center;
        }
        h1{
            margin-left:550px;
        }
    </style>
</head>
<body>
    <h1>Les patients:</h1>
    <div class="tou">
<table id="table"  border="2">
    <tr id="tableaudedonnee">
        <th>ID</th><th>Nom</th><th>Prénom</th><th>date_naissance</th><th>sexe</th><th>adresse</th><th>telephone</th><th>email</th><th>dossier_medical</th><th>Supprimer</th><th>Modifier</th>
    </tr>
    </div>
    
    <?php
    
    $id_medecin=[];
    session_start();
    
    $servername="localhost";
    $username="root";
    $password="";
    $dbname="gestion_cabinet_medical";
    $conn=mysqli_connect($servername,$username,$password,$dbname);
    
    $id_medecin=$_SESSION['id_utilisateur'];
    
    
    $result = $conn->query("SELECT DISTINCT Patient.*
                            FROM Patient
                            JOIN Traitement ON Patient.id_patient = Traitement.id_patient
                            WHERE Traitement.id_medecin = $id_medecin;");
    if (!$result) {
        die("Erreur dans la requête : " . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        echo "
        <tr>
            <td>{$row['id_patient']}</td>
            <td>{$row['nom']}</td>
            <td>{$row['prenom']}</td>
            <td>{$row['date_naissance']}</td>
            <td>{$row['sexe']}</td>
            <td>{$row['adresse']}</td>
            <td>{$row['telephone']}</td>
            <td>{$row['email']}</td>
            <td>{$row['dossier_medical']}</td>
            

            
            <td>
                <form method='post'>
                    <input type='hidden' name='id' value='{$row['id_patient']}'>
                    <button type='submit' name='supprimer' value='supprimer' id='supprimer'>Supprimer</button>
                </form>
            </td>
            <td>
                <form method='post'>
                    <input type='hidden' name='id' value='{$row['id_patient']}'>
                    <button type='submit' name='modifier' value='modifier' id='modifier'>Modifier</button>
                </form>
            </td>
        </tr>";
    }
    

    ?>

</body>
</html>