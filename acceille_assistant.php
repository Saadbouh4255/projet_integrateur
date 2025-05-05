<?php
session_start();

// Vérification du rôle assistant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'assistant') {
    header("Location: connection.php");
    exit;
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
$conn->set_charset("utf8");

// Récupération des infos de l'assistant
$id_a = intval($_SESSION['id_assistant']);
$stmt = $conn->prepare("
    SELECT nom, prenom, email, telephone 
    FROM Assistant 
    WHERE id_assistant = ?
");
$stmt->bind_param("i", $id_a);
$stmt->execute();
$assistant = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Récupération des rendez-vous à venir
$rdv_stmt = $conn->prepare("
    SELECT r.date_heure, p.nom, p.prenom, m.nom AS medecin_nom, m.prenom AS medecin_prenom, r.motif 
    FROM RendezVous r
    JOIN Patient p ON r.id_patient = p.id_patient
    JOIN Medecin m ON r.id_medecin = m.id_medecin
    WHERE r.statut = 'prévu' AND r.date_heure > NOW()
    ORDER BY r.date_heure ASC
    LIMIT 5
");
$rdv_stmt->execute();
$rendezvous = $rdv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rdv_stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Espace Assistant - Cabinet Médical</title>
  <style>
    :root {
      --primary: #4caf50;
      --primary-dark: #388e3c;
      --primary-light: #c8e6c9;
      --background: #e8f5e9;
      --text: #2e7d32;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-info">
        <h1>Bonjour, <?= htmlspecialchars($assistant['prenom']) ?></h1>
        <p>Assistant médical</p>
      </div>
      <div class="header-actions">
        <a href="gestion_rdv.php" class="btn-icon" title="Gestion RDV">📅</a>
        <a href="deconnexion.php" class="btn-icon" title="Déconnexion">🚪</a>
      </div>
    </div>
    
    <div class="grid">
      <!-- Informations personnelles -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Informations personnelles</h2>
        </div>
        <div class="card-body">
          <p><span class="info-label">Nom complet :</span> <?= htmlspecialchars($assistant['nom'].' '.$assistant['prenom']) ?></p>
          <p><span class="info-label">Email :</span> <?= htmlspecialchars($assistant['email']) ?></p>
          <p><span class="info-label">Téléphone :</span> <?= htmlspecialchars($assistant['telephone']) ?></p>
        </div>
      </div>
      
      <!-- Prochains rendez-vous -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Prochains rendez-vous</h2>
        </div>
        <div class="card-body">
          <?php if(empty($rendezvous)): ?>
            <p>Aucun rendez-vous prévu.</p>
          <?php else: ?>
            <?php foreach($rendezvous as $rdv): ?>
              <div class="rdv-item">
                <div class="rdv-date"><?= date('d/m/Y H:i', strtotime($rdv['date_heure'])) ?></div>
                <div class="rdv-patient"><?= htmlspecialchars($rdv['prenom'].' '.$rdv['nom']) ?></div>
                <div class="rdv-doctor">Dr. <?= htmlspecialchars($rdv['medecin_prenom'].' '.$rdv['medecin_nom']) ?></div>
                <div class="rdv-motif"><?= htmlspecialchars($rdv['motif']) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Actions rapides -->
    <div class="actions">
      <a href="rendezvous.php" class="action-card">
        <div class="action-icon">📅</div>
        <h3 class="action-title">Gestion des RDV</h3>
        <p>Planifier et gérer les rendez-vous</p>
        <button type="button" class="action-btn">Accéder</button>
      </a>
      
      <a href="patient.php" class="action-card">
        <div class="action-icon">👥</div>
        <h3 class="action-title">Fiches patients</h3>
        <p>Consulter et gérer les patients</p>
        <button type="button" class="action-btn">Accéder</button>
      </a>
    </div>
  </div>
</body>
</html>