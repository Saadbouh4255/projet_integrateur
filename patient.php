<?php
session_start();

// Vérification du rôle patient
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header("Location: connection.php");
    exit;
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
$conn->set_charset("utf8");

// Récupération des infos du patient
$id_p = intval($_SESSION['id_patient']);
$stmt = $conn->prepare("
    SELECT nom, prenom, email, date_naissance, sexe, telephone, adresse 
    FROM Patient 
    WHERE id_patient = ?
");
$stmt->bind_param("i", $id_p);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Récupération des rendez-vous à venir
$rdv_stmt = $conn->prepare("
    SELECT r.date_heure, m.nom, m.prenom, r.motif 
    FROM RendezVous r
    JOIN Medecin m ON r.id_medecin = m.id_medecin
    WHERE r.id_patient = ? AND r.statut = 'prévu' AND r.date_heure > NOW()
    ORDER BY r.date_heure ASC
    LIMIT 3
");
$rdv_stmt->bind_param("i", $id_p);
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
  <title>Espace Patient - Cabinet Médical</title>
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
      background: var(--primary);
      color: var(--text-light);
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .header-info h1 {
      font-size: 24px;
      margin-bottom: 5px;
    }
    
    .header-info p {
      opacity: 0.9;
    }
    
    .header-actions {
      display: flex;
      gap: 15px;
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
      background: var(--white);
      color: var(--primary);
      border: 1px solid var(--white);
    }
    
    .btn-primary:hover {
      background: transparent;
      color: var(--white);
    }
    
    .btn-icon {
      background: rgba(255, 255, 255, 0.2);
      color: var(--white);
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
    }
    
    .btn-icon:hover {
      background: rgba(255, 255, 255, 0.3);
    }
    
    /* Cards Grid */
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }
    
    .card {
      background: var(--white);
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--border);
    }
    
    .card-title {
      font-size: 18px;
      font-weight: 600;
      color: var(--primary);
    }
    
    .card-body p {
      margin-bottom: 12px;
    }
    
    .info-label {
      font-weight: 500;
      color: var(--primary);
      display: inline-block;
      width: 120px;
    }
    
    /* Rendez-vous */
    .rdv-item {
      padding: 15px 0;
      border-bottom: 1px solid var(--border);
    }
    
    .rdv-item:last-child {
      border-bottom: none;
    }
    
    .rdv-date {
      font-weight: 600;
      color: var(--primary-dark);
      margin-bottom: 5px;
    }
    
    .rdv-doctor {
      color: #555;
      margin-bottom: 5px;
    }
    
    .rdv-motif {
      font-style: italic;
      color: #777;
    }
    
    /* Actions */
    .actions {
      display: flex;
      gap: 15px;
      margin-top: 30px;
    }
    
    .action-card {
      flex: 1;
      background: var(--white);
      border-radius: 10px;
      padding: 25px;
      text-align: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    
    .action-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
      background: var(--primary-light);
    }
    
    .action-icon {
      font-size: 36px;
      color: var(--primary);
      margin-bottom: 15px;
    }
    
    .action-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 10px;
      color: var(--primary-dark);
    }
    
    .action-btn {
      background: var(--primary);
      color: var(--white);
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      margin-top: 15px;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    .action-btn:hover {
      background: var(--primary-dark);
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- En-tête -->
    <div class="header">
      <div class="header-info">
        <h1>Bonjour, <?= htmlspecialchars($patient['prenom']) ?></h1>
        <p><?= htmlspecialchars($patient['email']) ?></p>
      </div>
      <div class="header-actions">
        <a href="dossiers.php" class="btn-icon" title="Dossier médical">📁</a>
        <a href="deconnexion.php" class="btn-icon" title="Déconnexion">🚪</a>
      </div>
    </div>
    
    <!-- Grille d'informations -->
    <div class="grid">
      <!-- Informations personnelles -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Informations personnelles</h2>
        </div>
        <div class="card-body">
          <p><span class="info-label">Nom complet :</span> <?= htmlspecialchars($patient['nom'].' '.$patient['prenom']) ?></p>
          <p><span class="info-label">Date de naissance :</span> <?= htmlspecialchars($patient['date_naissance']) ?></p>
          <p><span class="info-label">Sexe :</span> <?= htmlspecialchars($patient['sexe']) ?></p>
          <p><span class="info-label">Téléphone :</span> <?= htmlspecialchars($patient['telephone']) ?></p>
          <p><span class="info-label">Adresse :</span> <?= nl2br(htmlspecialchars($patient['adresse'])) ?></p>
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
                <div class="rdv-doctor">Dr. <?= htmlspecialchars($rdv['prenom'].' '.$rdv['nom']) ?></div>
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
        <h3 class="action-title">Prendre rendez-vous</h3>
        <p>Planifier une consultation avec un médecin</p>
        <button type="button" class="action-btn">Nouveau RDV</button>
      </a>
      
      <a href="mes-rendezvous.php" class="action-card">
        <div class="action-icon">📋</div>
        <h3 class="action-title">Mes rendez-vous</h3>
        <p>Consulter et gérer vos rendez-vous</p>
        <button type="button" class="action-btn">Voir la liste</button>
      </a>
      
      <a href="dossiers.php" class="action-card">
        <div class="action-icon">🏥</div>
        <h3 class="action-title">Dossier médical</h3>
        <p>Accéder à votre dossier médical complet</p>
        <button type="button" class="action-btn">Consulter</button>
      </a>
    </div>
  </div>
</body>
</html>