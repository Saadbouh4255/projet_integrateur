<?php
session_start();
$errors = [];

// Fonction de nettoyage des entrées
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération des données
    $nom = test_input($_POST['nom']);
    $prenom = test_input($_POST['prenom']);
    $email = test_input($_POST['email']);
    $telephone = test_input($_POST['telephone']);
    $date_naissance = $_POST['date_naissance'];
    $sexe = $_POST['sexe'];
    $adresse = test_input($_POST['adresse']);
    $role = $_POST['role'];
    
    $password = $_POST['password'];
    $password2 = $_POST['password2'];

    // Validation
    if (empty($nom) || empty($prenom)) $errors[] = "Nom et prénom obligatoires.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";
    if (strlen($password) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    if ($password !== $password2) $errors[] = "Les mots de passe ne correspondent pas.";
    if (!in_array($role, ['patient', 'medecin', 'assistant'])) $errors[] = "Rôle invalide.";

    try {
        $conn = new mysqli("localhost","root","","gestion_cabinet_medical");
        $conn->set_charset("utf8");
        
        // Vérification email existant
        $check = $conn->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Cet email est déjà utilisé.";
        }
        $check->close();

        // Vérifications spécifiques selon le rôle
        if ($role === 'medecin') {
            $chk = $conn->prepare("SELECT COUNT(*) FROM Medecin WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            $chk->bind_result($cnt);
            $chk->fetch();
            $chk->close();
            if ($cnt === 0) {
                $errors[] = "Vous n'êtes pas référencé comme médecin. Contactez l'administrateur.";
            }
        }
        
        if ($role === 'assistant') {
            $chk = $conn->prepare("SELECT COUNT(*) FROM Assistant WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            $chk->bind_result($cnt);
            $chk->fetch();
            $chk->close();
            if ($cnt === 0) {
                $errors[] = "Vous n'êtes pas référencé comme assistant. Contactez l'administrateur.";
            }
        }

        if (empty($errors)) {
            // 1) Insertion Utilisateur
            $st1 = $conn->prepare("
                INSERT INTO utilisateur(nom, prenom, email, telephone, role, actif, date_creation)
                VALUES(?, ?, ?, ?, ?, 1, NOW())
            ");
            $st1->bind_param("sssss", $nom, $prenom, $email, $telephone, $role);
            $st1->execute();
            $id_user = $st1->insert_id;
            $st1->close();

            // 2) Insertion dans la table spécifique selon le rôle
            switch ($role) {
                case 'patient':
                    $st2 = $conn->prepare("
                        INSERT INTO Patient(nom, prenom, date_naissance, sexe, telephone, adresse, email, dossier_medical)
                        VALUES(?, ?, ?, ?, ?, ?, ?, '')
                    ");
                    $st2->bind_param("sssssss", $nom, $prenom, $date_naissance, $sexe, $telephone, $adresse, $email);
                    $st2->execute();
                    $_SESSION['id_patient'] = $st2->insert_id;
                    $st2->close();
                    break;
                    
                case 'assistant':
                    $st2 = $conn->prepare("
                        INSERT INTO Assistant(nom, prenom, telephone, email)
                        VALUES(?, ?, ?, ?)
                    ");
                    $st2->bind_param("ssss", $nom, $prenom, $telephone, $email);
                    $st2->execute();
                    $_SESSION['id_assistant'] = $st2->insert_id;
                    $st2->close();
                    break;
            }

            // 3) Insertion Connexion
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st3 = $conn->prepare("
                INSERT INTO Connexion(id_utilisateur, nom_utilisateur, mot_de_passe)
                VALUES(?, ?, ?)
            ");
            $st3->bind_param("iss", $id_user, $email, $hash);
            $st3->execute();
            $st3->close();

            // Authentification et redirection
            $_SESSION['id_utilisateur'] = $id_user;
            $_SESSION['role'] = $role;
            $_SESSION['nom_complet'] = $nom.' '.$prenom;

            $redirects = [
                'patient' => 'patient.php',
                'medecin' => 'dashboard_medecin.php',
                'assistant' => 'accueil_assistant.php'
            ];
            
            header("Location: ".$redirects[$role]);
            exit;
        }
        $conn->close();
    } catch (Exception $e) {
        $errors[] = "Erreur technique. Veuillez réessayer plus tard.";
        error_log("Erreur inscription: ".$e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscription - Cabinet Médical</title>
  <style>
    :root {
      --primary: #1976d2;
      --primary-dark: #1565c0;
      --primary-light: #bbdefb;
      --background: #e3f2fd;
      --text: #0d47a1;
      --error: #d32f2f;
      --white: #ffffff;
      --gray: #f5f5f5;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      background: var(--background);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }
    
    .register-container {
      background: var(--white);
      width: 100%;
      max-width: 600px;
      padding: 40px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .logo {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .logo h1 {
      color: var(--primary);
      font-size: 28px;
      margin-bottom: 10px;
    }
    
    .form-row {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .form-group {
      flex: 1;
      margin-bottom: 15px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--text);
      font-weight: 500;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--primary-light);
      border-radius: 6px;
      font-size: 16px;
      transition: border 0.3s;
      background: var(--gray);
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      background: var(--white);
    }
    
    .btn {
      width: 100%;
      padding: 12px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.3s;
      margin-top: 10px;
    }
    
    .btn:hover {
      background: var(--primary-dark);
    }
    
    .error-list {
      color: var(--error);
      background: #ffebee;
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 20px;
      list-style-type: none;
    }
    
    .error-list li {
      margin-bottom: 5px;
    }
    
    .links {
      text-align: center;
      margin-top: 20px;
    }
    
    .links a {
      color: var(--primary);
      text-decoration: none;
      font-size: 14px;
      transition: color 0.3s;
    }
    
    .links a:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="logo">
      <h1>Créer un compte</h1>
    </div>
    
    <?php if(!empty($errors)): ?>
      <ul class="error-list">
        <?php foreach($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    
    <form method="post">
      <div class="form-row">
        <div class="form-group">
          <label for="nom">Nom :</label>
          <input type="text" id="nom" name="nom" required>
        </div>
        
        <div class="form-group">
          <label for="prenom">Prénom :</label>
          <input type="text" id="prenom" name="prenom" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="email">Email :</label>
          <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
          <label for="telephone">Téléphone :</label>
          <input type="tel" id="telephone" name="telephone">
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="date_naissance">Date de naissance :</label>
          <input type="date" id="date_naissance" name="date_naissance">
        </div>
        
        <div class="form-group">
          <label for="sexe">Sexe :</label>
          <select id="sexe" name="sexe">
            <option value="Homme">Homme</option>
            <option value="Femme">Femme</option>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label for="adresse">Adresse :</label>
        <textarea id="adresse" name="adresse" rows="3"></textarea>
      </div>
      
      <div class="form-group">
        <label for="role">Rôle :</label>
        <select id="role" name="role" required>
          <option value="patient" selected>Patient</option>
          <option value="medecin">Médecin</option>
          <option value="assistant">Assistant</option>
        </select>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="password">Mot de passe :</label>
          <input type="password" id="password" name="password" required minlength="8">
        </div>
        
        <div class="form-group">
          <label for="password2">Confirmer le mot de passe :</label>
          <input type="password" id="password2" name="password2" required minlength="8">
        </div>
      </div>
      
      <button type="submit" class="btn">S'inscrire</button>
    </form>
    
    <div class="links">
      <a href="connection.php">Déjà un compte ? Se connecter</a>
    </div>
  </div>
</body>
</html>