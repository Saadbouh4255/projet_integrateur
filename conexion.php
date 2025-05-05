<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$erreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Connexion à la base de données
        $conn = new mysqli("localhost","root","","gestion_cabinet_medical");
        $conn->set_charset("utf8");

        // Récupération des données du formulaire
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Requête préparée pour éviter les injections SQL
        $stmt = $conn->prepare("
            SELECT u.id_utilisateur, c.mot_de_passe, u.role, u.nom, u.prenom 
            FROM Utilisateur u
            JOIN Connexion c USING(id_utilisateur)
            WHERE u.email = ? AND u.actif = 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        // Vérification de l'utilisateur
        if (!$user) {
            $erreur = "Email non trouvé ou compte désactivé.";
        }
        elseif (!password_verify($password, $user['mot_de_passe'])) {
            $erreur = "Mot de passe incorrect.";
        }
        else {
            // Création de la session
            $_SESSION['id_utilisateur'] = $user['id_utilisateur'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nom_complet'] = $user['nom'].' '.$user['prenom'];

            // Récupération de l'ID spécifique selon le rôle
            switch ($user['role']) {
                case 'patient':
                    $r = $conn->prepare("SELECT id_patient FROM Patient WHERE email = ?");
                    $r->bind_param("s", $email);
                    $r->execute();
                    $_SESSION['id_patient'] = $r->get_result()->fetch_assoc()['id_patient'];
                    $r->close();
                    break;
                    
                case 'assistant':
                    $r = $conn->prepare("SELECT id_assistant FROM Assistant WHERE email = ?");
                    $r->bind_param("s", $email);
                    $r->execute();
                    $_SESSION['id_assistant'] = $r->get_result()->fetch_assoc()['id_assistant'];
                    $r->close();
                    break;
                    
                case 'medecin':
                    $r = $conn->prepare("SELECT id_medecin FROM Medecin WHERE email = ?");
                    $r->bind_param("s", $email);
                    $r->execute();
                    $_SESSION['id_medecin'] = $r->get_result()->fetch_assoc()['id_medecin'];
                    $r->close();
                    break;
            }

            // Redirection selon le rôle
            $redirects = [
                'patient' => 'patient.php',
                'medecin' => 'dashboard_medecin.php',
                'admin' => 'admin.php',
                'assistant' => 'accueil_assistant.php'
            ];
            
            header("Location: ".$redirects[$user['role']]);
            exit;
        }
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $erreur = "Erreur de connexion, réessayez plus tard.";
        error_log("Erreur connexion: ".$e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - Cabinet Médical</title>
  <style>
    :root {
      --primary: #1976d2;
      --primary-dark: #1565c0;
      --primary-light: #bbdefb;
      --background: #e3f2fd;
      --text: #0d47a1;
      --error: #d32f2f;
      --white: #ffffff;
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
    
    .login-container {
      background: var(--white);
      width: 100%;
      max-width: 450px;
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
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--text);
      font-weight: 500;
    }
    
    .form-group input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--primary-light);
      border-radius: 6px;
      font-size: 16px;
      transition: border 0.3s;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: var(--primary);
    }
    
    .btn {
      width: 100%;
      padding: 12px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;1`
      font-weight: 500;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    .btn:hover {
      background: var(--primary-dark);
    }
    
    .error-message {
      color: var(--error);
      text-align: center;
      margin: 15px 0;
      font-size: 14px;
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
  <div class="login-container">
    <div class="logo">
      <h1>Cabinet Médical</h1>
    </div>
    
    <?php if($erreur): ?>
      <div class="error-message"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
    
    <form method="post">
      <div class="form-group">
        <label for="email">Email :</label>
        <input type="email" id="email" name="email" required>
      </div>
      
      <div class="form-group">
        <label for="password">Mot de passe :</label>
        <input type="password" id="password" name="password" required>
      </div>
      
      <button type="submit" class="btn">Se connecter</button>
    </form>
    
    <div class="links">
      <a href="inscription.php">Créer un compte</a> | 
      <a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a>
    </div>
  </div>
</body>
</html>