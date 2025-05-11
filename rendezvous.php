<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['patient', 'assistant', 'admin'])) {
    header("Location: connection.php");
    exit;
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
$conn->set_charset("utf8");

// Initialisation des variables
$message = '';
$msg_type = '';
$action = $_GET['action'] ?? 'create'; // 'create' ou 'manage'

// Récupération de la liste des médecins
$medecins = $conn->query("SELECT id_medecin, nom, prenom, specialite FROM Medecin ORDER BY nom, prenom");

// Récupération des rendez-vous (pour la gestion)
if ($action === 'manage' && in_array($_SESSION['role'], ['assistant', 'admin'])) {
    $where = "1=1";
    $params = [];
    $types = "";

    // Filtres
    if (!empty($_GET['medecin'])) {
        $where .= " AND r.id_medecin = ?";
        $params[] = intval($_GET['medecin']);
        $types .= "i";
    }

    if (!empty($_GET['date'])) {
        $where .= " AND DATE(r.date_heure) = ?";
        $params[] = $_GET['date'];
        $types .= "s";
    }

    if (!empty($_GET['statut'])) {
        $where .= " AND r.statut = ?";
        $params[] = $_GET['statut'];
        $types .= "s";
    }

    // Requête préparée pour les rendez-vous
    $query = "SELECT r.*, p.nom AS patient_nom, p.prenom AS patient_prenom,
                     m.nom AS medecin_nom, m.prenom AS medecin_prenom
              FROM RendezVous r
              JOIN Patient p ON r.id_patient = p.id_patient
              JOIN Medecin m ON r.id_medecin = m.id_medecin
              WHERE $where
              ORDER BY r.date_heure DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rendezvous = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Traitement du formulaire de rendez-vous
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Création d'un nouveau RDV
    if (isset($_POST['creer_rdv'])) {
        $id_medecin = intval($_POST['id_medecin']);
        $date_rdv = $_POST['date_rdv'];
        $heure = $_POST['heure'];
        $motif = trim($_POST['motif']);
        $id_patient = ($_SESSION['role'] === 'patient') ? $_SESSION['id_patient'] : intval($_POST['id_patient']);

        // Validation
        if (empty($id_medecin) || empty($date_rdv) || empty($heure) || empty($id_patient)) {
            $message = "error:Tous les champs obligatoires doivent être remplis.";
        } else {
            $date_heure = $date_rdv . ' ' . $heure . ':00';
            
            // Vérification disponibilité
            $check = $conn->prepare("SELECT id_rdv FROM RendezVous WHERE id_medecin = ? AND date_heure = ? AND statut != 'annulé'");
            $check->bind_param("is", $id_medecin, $date_heure);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                $message = "error:Ce créneau horaire n'est plus disponible.";
            } else {
                $insert = $conn->prepare("INSERT INTO RendezVous (id_patient, id_medecin, date_heure, motif, statut, date_creation) 
                                          VALUES (?, ?, ?, ?, 'prévu', NOW())");
                $insert->bind_param("iiss", $id_patient, $id_medecin, $date_heure, $motif);
                
                if ($insert->execute()) {
                    $message = "success:Rendez-vous enregistré avec succès.";
                    $_POST = array(); // Réinitialisation du formulaire
                } else {
                    $message = "error:Erreur lors de l'enregistrement.";
                }
                $insert->close();
            }
            $check->close();
        }
    }
    // Gestion des RDV (annulation/modification)
    elseif (isset($_POST['annuler_rdv'])) {
        $id_rdv = intval($_POST['id_rdv']);
        $update = $conn->prepare("UPDATE RendezVous SET statut = 'annulé' WHERE id_rdv = ?");
        $update->bind_param("i", $id_rdv);
        
        if ($update->execute()) {
            $message = "success:Rendez-vous annulé avec succès.";
        } else {
            $message = "error:Erreur lors de l'annulation.";
        }
        $update->close();
    }
}

// Traitement du message
if (!empty($message)) {
    list($msg_type, $msg_text) = explode(":", $message, 2);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ($action === 'manage') ? 'Gestion' : 'Prise' ?> de Rendez-vous</title>
  <style>
    :root {
      --primary: #1976d2;
      --primary-dark: #1565c0;
      --primary-light: #bbdefb;
      --background: #e3f2fd;
      --text: #0d47a1;
      --text-light: #ffffff;
      --error: #d32f2f;
      --success: #388e3c;
      --warning: #ffa000;
      --white: #ffffff;
      --gray: #f5f5f5;
      --border: #e0e0e0;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
      background: var(--background);
      color: #333;
      line-height: 1.6;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    
    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    
    .header-title {
      color: var(--primary);
      font-size: 28px;
    }
    
    .btn {
      padding: 10px 20px;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-primary {
      background: var(--primary);
      color: var(--white);
      border: 1px solid var(--primary);
    }
    
    .btn-primary:hover {
      background: var(--primary-dark);
      border-color: var(--primary-dark);
    }
    
    /* Navigation */
    .nav-tabs {
      display: flex;
      border-bottom: 1px solid var(--border);
      margin-bottom: 20px;
    }
    
    .nav-tab {
      padding: 10px 20px;
      cursor: pointer;
      border-bottom: 3px solid transparent;
    }
    
    .nav-tab.active {
      border-bottom-color: var(--primary);
      font-weight: 500;
    }
    
    /* Formulaire */
    .form-container {
      background: var(--white);
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--primary);
    }
    
    .form-control {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 16px;
    }
    
    /* Tableau */
    .rdv-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--white);
    }
    
    .rdv-table th, .rdv-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }
    
    .rdv-table th {
      background: var(--primary-light);
      color: var(--primary-dark);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .rdv-table {
        display: block;
        overflow-x: auto;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1 class="header-title">
        <?= ($action === 'manage') ? 'Gestion des Rendez-vous' : 'Prendre un Rendez-vous' ?>
      </h1>
      <?php if (in_array($_SESSION['role'], ['assistant', 'admin'])): ?>
        <div class="nav-tabs">
          <a href="?action=create" class="nav-tab <?= ($action === 'create') ? 'active' : '' ?>">Créer RDV</a>
          <a href="?action=manage" class="nav-tab <?= ($action === 'manage') ? 'active' : '' ?>">Gérer RDV</a>
        </div>
      <?php endif; ?>
    </div>
    
    <?php if (!empty($message)): ?>
      <div class="message message-<?= $msg_type ?>"><?= htmlspecialchars($msg_text) ?></div>
    <?php endif; ?>
    
    <?php if ($action === 'create'): ?>
      <!-- Formulaire de création de RDV -->
      <div class="form-container">
        <form method="post">
          <?php if (in_array($_SESSION['role'], ['assistant', 'admin'])): ?>
            <div class="form-group">
              <label for="id_patient" class="form-label">Patient :</label>
              <select id="id_patient" name="id_patient" class="form-control" required>
                <option value="">-- Sélectionnez un patient --</option>
                <?php 
                $patients = $conn->query("SELECT id_patient, nom, prenom FROM Patient ORDER BY nom, prenom");
                while ($p = $patients->fetch_assoc()): ?>
                  <option value="<?= $p['id_patient'] ?>">
                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          <?php endif; ?>
          
          <div class="form-group">
            <label for="id_medecin" class="form-label">Médecin :</label>
            <select id="id_medecin" name="id_medecin" class="form-control" required>
              <option value="">-- Sélectionnez un médecin --</option>
              <?php while ($m = $medecins->fetch_assoc()): ?>
                <option value="<?= $m['id_medecin'] ?>">
                  Dr. <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?> - <?= htmlspecialchars($m['specialite']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          
          <div class="form-group">
            <div class="form-label">Date et heure :</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
              <div>
                <input type="date" id="date_rdv" name="date_rdv" class="form-control" required 
                       min="<?= date('Y-m-d') ?>" 
                       max="<?= date('Y-m-d', strtotime('+3 months')) ?>">
              </div>
              <div>
                <input type="time" id="heure" name="heure" class="form-control" required 
                       min="08:00" max="18:00" step="900">
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="motif" class="form-label">Motif :</label>
            <textarea id="motif" name="motif" class="form-control" rows="3"></textarea>
          </div>
          
          <button type="submit" name="creer_rdv" class="btn btn-primary">Enregistrer le RDV</button>
        </form>
      </div>
    <?php else: ?>
      <!-- Gestion des RDV -->
      <div class="form-container">
        <form method="get" action="rendezvous.php">
          <input type="hidden" name="action" value="manage">
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div>
              <label class="form-label">Médecin :</label>
              <select name="medecin" class="form-control">
                <option value="">Tous</option>
                <?php 
                $medecins->data_seek(0); // Réinitialiser le pointeur
                while ($m = $medecins->fetch_assoc()): ?>
                  <option value="<?= $m['id_medecin'] ?>" <?= (!empty($_GET['medecin']) && $_GET['medecin'] == $m['id_medecin']) ? 'selected' : '' ?>>
                    Dr. <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            
            <div>
              <label class="form-label">Date :</label>
              <input type="date" name="date" class="form-control" value="<?= $_GET['date'] ?? '' ?>">
            </div>
            
            <div>
              <label class="form-label">Statut :</label>
              <select name="statut" class="form-control">
                <option value="">Tous</option>
                <option value="prévu" <?= (!empty($_GET['statut']) && $_GET['statut'] === 'prévu' ? 'selected' : '') ?>>Prévu</option>
                <option value="terminé" <?= (!empty($_GET['statut']) && $_GET['statut'] === 'terminé' ? 'selected' : '') ?>>Terminé</option>
                <option value="annulé" <?= (!empty($_GET['statut']) && $_GET['statut'] === 'annulé' ? 'selected' : '') ?>>Annulé</option>
              </select>
            </div>
            
            <div style="align-self: end;">
              <button type="submit" class="btn btn-primary">Filtrer</button>
              <a href="?action=manage" class="btn">Réinitialiser</a>
            </div>
          </div>
        </form>
        
        <div style="overflow-x: auto;">
          <table class="rdv-table">
            <thead>
              <tr>
                <th>Date/Heure</th>
                <th>Patient</th>
                <th>Médecin</th>
                <th>Motif</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($rendezvous)): ?>
                <?php foreach ($rendezvous as $rdv): ?>
                  <tr>
                    <td><?= date('d/m/Y H:i', strtotime($rdv['date_heure'])) ?></td>
                    <td><?= htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']) ?></td>
                    <td>Dr. <?= htmlspecialchars($rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom']) ?></td>
                    <td><?= htmlspecialchars($rdv['motif']) ?></td>
                    <td>
                      <span style="
                        padding: 4px 8px;
                        border-radius: 12px;
                        background: <?= 
                          $rdv['statut'] === 'prévu' ? '#e3f2fd' : 
                          ($rdv['statut'] === 'terminé' ? '#e8f5e9' : '#ffebee') 
                        ?>;
                        color: <?= 
                          $rdv['statut'] === 'prévu' ? '#1976d2' : 
                          ($rdv['statut'] === 'terminé' ? '#388e3c' : '#d32f2f') 
                        ?>;
                      ">
                        <?= ucfirst($rdv['statut']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($rdv['statut'] === 'prévu'): ?>
                        <form method="post" style="display: inline;">
                          <input type="hidden" name="id_rdv" value="<?= $rdv['id_rdv'] ?>">
                          <button type="submit" name="annuler_rdv" class="btn" 
                                  style="background: #ffebee; color: #d32f2f;"
                                  onclick="return confirm('Confirmer l\'annulation?')">
                            Annuler
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 20px;">
                    Aucun rendez-vous trouvé
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>