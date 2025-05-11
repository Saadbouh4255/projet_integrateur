<?php
session_start();
$errors = [];

// Configuration des logs
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/logs/inscription_errors.log');

// Fonction de nettoyage des entrées
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération et nettoyage des données
    $nom = test_input($_POST['nom'] ?? '');
    $prenom = test_input($_POST['prenom'] ?? '');
    $email = test_input($_POST['email'] ?? '');
    $telephone = test_input($_POST['telephone'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? '';
    $sexe = $_POST['sexe'] ?? 'Homme';
    $adresse = test_input($_POST['adresse'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validation des données
    if (empty($nom)) $errors[] = "Le nom est obligatoire.";
    if (empty($prenom)) $errors[] = "Le prénom est obligatoire.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format d'email invalide.";
    if (strlen($password) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    if ($password !== $password2) $errors[] = "Les mots de passe ne correspondent pas.";
    if (!in_array($role, ['patient', 'medecin', 'assistant'])) $errors[] = "Rôle invalide.";
    
    // Validation date de naissance
    if (!empty($date_naissance)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_naissance);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date_naissance) {
            $errors[] = "Format de date invalide (AAAA-MM-JJ)";
        }
    }

    // Validation téléphone Mauritanie (8 chiffres)
    if (!empty($telephone) && !preg_match('/^[0-9]{8}$/', $telephone)) {
        $errors[] = "Le numéro doit contenir 8 chiffres";
    }

    if (empty($errors)) {
        try {
            $conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
            $conn->set_charset("utf8mb4");
            
            if ($conn->connect_error) {
                throw new Exception("Connexion à la base de données échouée: " . $conn->connect_error);
            }

            // Vérification email existant
            $check = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
            if (!$check) throw new Exception("Erreur de préparation: " . $conn->error);
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $errors[] = "Cet email est déjà utilisé.";
            }
            $check->close();

            if (empty($errors)) {
                $conn->begin_transaction();
                
                try {
                    // 1) Insertion Utilisateur
                    $st1 = $conn->prepare("INSERT INTO utilisateur(nom, prenom, email, telephone, rôle) VALUES(?, ?, ?, ?, ?)");
                    if (!$st1) throw new Exception("Erreur de préparation: " . $conn->error);
                    
                    // Adaptation du rôle selon la base de données
                    $role_db = ($role === 'assistant') ? 'Secrétaire' : ucfirst($role);
                    if (!$st1->bind_param("sssss", $nom, $prenom, $email, $telephone, $role_db)) {
                        throw new Exception("Erreur de liaison des paramètres utilisateur: " . $st1->error);
                    }

                    if (!$st1->execute()) {
                        throw new Exception("Erreur d'insertion utilisateur: " . $st1->error);
                    }

                    $id_user = $conn->insert_id;
                    $st1->close();

                    // 2) Insertion Patient si rôle patient
                    if ($role === 'patient') {
                        $st2 = $conn->prepare("INSERT INTO patient(nom, prenom, date_naissance, sexe, adresse, telephone, email, dossier_medical) 
                                             VALUES(?, ?, ?, ?, ?, ?, ?, '')");
                        if (!$st2) throw new Exception("Erreur de préparation patient: " . $conn->error);
                        
                        if (!$st2->bind_param("sssssss", $nom, $prenom, $date_naissance, $sexe, $adresse, $telephone, $email)) {
                            throw new Exception("Erreur de liaison des paramètres patient: " . $st2->error);
                        }

                        if (!$st2->execute()) {
                            throw new Exception("Erreur d'insertion patient: " . $st2->error);
                        }

                        $id_patient = $conn->insert_id;
                        $st2->close();
                        
                        // Stockage de l'ID patient dans la session
                        $_SESSION['id_patient'] = $id_patient;
                    }

                    // 3) Insertion Connexion - Solution pour l'auto-incrément manquant
                    // D'abord trouver le prochain ID disponible
                    $res = $conn->query("SELECT MAX(id_connexion) as max_id FROM connexion");
                    $row = $res->fetch_assoc();
                    $next_id = $row['max_id'] + 1;
                    
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $st3 = $conn->prepare("INSERT INTO connexion(id_connexion, id_utilisateur, login, mot_de_passe) VALUES(?, ?, ?, ?)");
                    if (!$st3) throw new Exception("Erreur de préparation connexion: " . $conn->error);
                    
                    if (!$st3->bind_param("iiss", $next_id, $id_user, $email, $hash)) {
                        throw new Exception("Erreur de liaison des paramètres connexion: " . $st3->error);
                    }

                    if (!$st3->execute()) {
                        throw new Exception("Erreur d'insertion connexion: " . $st3->error);
                    }

                    $st3->close();
                    $conn->commit();

                    // Authentification
                    $_SESSION['id_utilisateur'] = $id_user;
                    $_SESSION['role'] = $role; // Garde 'assistant' dans la session même si c'est 'Secrétaire' en BD
                    $_SESSION['nom_complet'] = "$nom $prenom";

                    // Redirection
                    $redirects = [
                        'patient' => 'patient.php',
                        'medecin' => 'dashword_medecin.php',
                        'assistant' => 'accueil_assistant.php'
                    ];
                    
                    header("Location: ".$redirects[$role]);
                    exit;

                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = "Erreur lors de l'inscription: " . $e->getMessage();
                    error_log("Erreur transaction: " . $e->getMessage());
                }
            }
            $conn->close();
        } catch (Exception $e) {
            $errors[] = "Erreur de connexion à la base de données: " . $e->getMessage();
            error_log("Erreur DB: " . $e->getMessage());
        }
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
      --success: #388e3c;
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
    
    <form method="post" onsubmit="return validateForm()">
      <div class="form-row">
        <div class="form-group">
          <label for="nom">Nom :</label>
          <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
        </div>
        
        <div class="form-group">
          <label for="prenom">Prénom :</label>
          <input type="text" id="prenom" name="prenom" required value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="email">Email :</label>
          <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        
        <div class="form-group">
          <label for="telephone">Téléphone :</label>
          <input type="tel" id="telephone" name="telephone" pattern="[0-9]{8}" title="8 chiffres requis (ex: 12345678)" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="date_naissance">Date de naissance :</label>
          <input type="date" id="date_naissance" name="date_naissance" required value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
        </div>
        
        <div class="form-group">
          <label for="sexe">Sexe :</label>
          <select id="sexe" name="sexe" required>
            <option value="Homme" <?= ($_POST['sexe'] ?? 'Homme') === 'Homme' ? 'selected' : '' ?>>Homme</option>
            <option value="Femme" <?= ($_POST['sexe'] ?? 'Homme') === 'Femme' ? 'selected' : '' ?>>Femme</option>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label for="adresse">Adresse :</label>
        <textarea id="adresse" name="adresse" rows="3" required><?= htmlspecialchars($_POST['adresse'] ?? '') ?></textarea>
      </div>
      
      <div class="form-group">
        <label for="role">Rôle :</label>
        <select id="role" name="role" required>
          <option value="patient" <?= ($_POST['role'] ?? 'patient') === 'patient' ? 'selected' : '' ?>>Patient</option>
          <option value="medecin" <?= ($_POST['role'] ?? '') === 'medecin' ? 'selected' : '' ?>>Médecin</option>
          <option value="assistant" <?= ($_POST['role'] ?? '') === 'assistant' ? 'selected' : '' ?>>Assistant</option>
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

  <script>
    function validateForm() {
      const password = document.getElementById('password').value;
      const password2 = document.getElementById('password2').value;
      
      if (password !== password2) {
        alert("Les mots de passe ne correspondent pas");
        return false;
      }
      
      return true;
    }
  </script>
</body>
</html>