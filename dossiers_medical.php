dossiers:<?php
session_start();
if (!isset($_SESSION['id_patient'])) {
    header("Location: connection.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
$conn->set_charset("utf8");

$id_p = $_SESSION['id_patient'];
$patient = $conn->query("
    SELECT p.*, u.date_creation 
    FROM Patient p
    JOIN Utilisateur u ON p.email = u.email
    WHERE p.id_patient = $id_p
")->fetch_assoc();

$stmt = $conn->prepare("
    SELECT 
        r.date_heure AS date_consultation,
        CONCAT(m.prenom, ' ', m.nom) AS medecin,
        r.motif AS diagnostic,
        NULL AS traitement
    FROM rendezvous r
    JOIN medecin m ON r.id_medecin = m.id_medecin
    WHERE r.id_patient = ? 
    AND r.date_heure < NOW()
    ORDER BY r.date_heure DESC
");
$stmt->bind_param("i", $id_patient);
$stmt->execute();
$historique = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dossier Médical - Cabinet Médical</title>
  <style>
    :root {
      --primary: #1976d2;
      --primary-dark: #1565c0;
      --primary-light: #bbdefb;
      --background: #e3f2fd;
      --text: #0d47a1;
      --text-light: #ffffff;
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
      max-width: 1000px;
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
    
    .btn-back {
      background: var(--primary);
      color: var(--white);
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-back:hover {
      background: var(--primary-dark);
    }
    
    /* Patient Info */
    .patient-info {
      background: var(--white);
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .patient-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--border);
    }
    
    .patient-name {
      font-size: 22px;
      font-weight: 600;
      color: var(--primary-dark);
    }
    
    .patient-meta {
      color: #666;
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }
    
    .info-item {
      margin-bottom: 15px;
    }
    
    .info-label {
      font-weight: 500;
      color: var(--primary);
      margin-bottom: 5px;
    }
    
    /* Dossier Médical */
    .dossier-section {
      background: var(--white);
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .section-title {
      font-size: 20px;
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border);
    }
    
    .dossier-content {
      white-space: pre-line;
      line-height: 1.8;
    }
    
    /* Historique */
    .history-item {
      padding: 20px;
      border-bottom: 1px solid var(--border);
      transition: background 0.3s;
    }
    
    .history-item:hover {
      background: var(--primary-light);
    }
    
    .history-date {
      font-weight: 600;
      color: var(--primary-dark);
      margin-bottom: 5px;
    }
    
    .history-doctor {
      color: #555;
      margin-bottom: 10px;
    }
    
    .history-diagnostic {
      margin-bottom: 10px;
    }
    
    .history-treatment {
      font-style: italic;
      color: #666;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #666;
    }
    
    .empty-icon {
      font-size: 50px;
      margin-bottom: 20px;
      opacity: 0.5;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1 class="header-title">Dossier Médical</h1>
      <a href="patient.php" class="btn-back">← Retour</a>
    </div>
    
    <!-- Informations patient -->
    <div class="patient-info">
      <div class="patient-header">
        <h2 class="patient-name"><?= htmlspecialchars($patient['nom'].' '.$patient['prenom']) ?></h2>
        <div class="patient-meta">Membre depuis <?= date('d/m/Y', strtotime($patient['date_creation'])) ?></div>
      </div>
      
      <div class="info-grid">
        <div>
          <div class="info-item">
            <div class="info-label">Date de naissance</div>
            <div><?= htmlspecialchars($patient['date_naissance']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Sexe</div>
            <div><?= htmlspecialchars($patient['sexe']) ?></div>
          </div>
        </div>
        <div>
          <div class="info-item">
            <div class="info-label">Téléphone</div>
            <div><?= htmlspecialchars($patient['telephone']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Email</div>
            <div><?= htmlspecialchars($patient['email']) ?></div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Dossier médical -->
    <div class="dossier-section">
      <h3 class="section-title">Dossier médical complet</h3>
      <?php if(empty($patient['dossier_medical'])): ?>
        <div class="empty-state">
          <div class="empty-icon">📄</div>
          <p>Aucune information médicale disponible pour le moment.</p>
        </div>
      <?php else: ?>
        <div class="dossier-content"><?= nl2br(htmlspecialchars($patient['dossier_medical'])) ?></div>
      <?php endif; ?>
    </div>
    
    <!-- Historique médical -->
    <div class="dossier-section">
      <h3 class="section-title">Historique des consultations</h3>
      <?php if(empty($historique)): ?>
        <div class="empty-state">
          <div class="empty-icon">🕒</div>
          <p>Aucune consultation enregistrée dans votre historique.</p>
        </div>
      <?php else: ?>
        <?php foreach($historique as $consult): ?>
          <div class="history-item">
            <div class="history-date"><?= date('d/m/Y', strtotime($consult['date_consultation'])) ?></div>
            <div class="history-doctor">Consultation avec Dr. <?= htmlspecialchars($consult['medecin']) ?></div>
            <div class="history-diagnostic">
              <strong>Diagnostic :</strong> <?= htmlspecialchars($consult['diagnostic']) ?>
            </div>
            <div class="history-treatment">
              <strong>Traitement :</strong> <?= htmlspecialchars($consult['traitement']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>