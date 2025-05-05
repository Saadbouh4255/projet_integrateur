<?php
session_start();

// Vérification de l'authentification patient
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header("Location: connection.php");
    exit;
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
$conn->set_charset("utf8");

// Récupération des rendez-vous
$id_patient = intval($_SESSION['id_patient']);
$query = "
    SELECT 
        r.id_rdv,
        r.date_heure,
        r.motif,
        r.statut,
        m.id_medecin,
        m.nom AS medecin_nom,
        m.prenom AS medecin_prenom,
        m.specialite
    FROM RendezVous r
    JOIN Medecin m ON r.id_medecin = m.id_medecin
    WHERE r.id_patient = ?
    ORDER BY r.date_heure DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_patient);
$stmt->execute();
$rendezvous = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Annulation de rendez-vous
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['annuler_rdv'])) {
    $id_rdv = intval($_POST['id_rdv']);
    
    $update = $conn->prepare("
        UPDATE RendezVous 
        SET statut = 'annulé' 
        WHERE id_rdv = ? AND id_patient = ? AND statut = 'prévu'
    ");
    $update->bind_param("ii", $id_rdv, $id_patient);
    
    if ($update->execute()) {
        $message = $update->affected_rows > 0 
            ? "success:Le rendez-vous a bien été annulé."
            : "error:Impossible d'annuler ce rendez-vous.";
    } else {
        $message = "error:Une erreur est survenue lors de l'annulation.";
    }
    
    // Rafraîchir la liste des rendez-vous
    header("Location: mes-rendezvous.php");
    exit;
}

$conn->close();

// Gestion des messages
$msg_type = "";
$msg_text = "";
if (isset($_GET['message'])) {
    $parts = explode(":", $_GET['message'], 2);
    if (count($parts) === 2) {
        $msg_type = $parts[0];
        $msg_text = $parts[1];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mes Rendez-vous - Cabinet Médical</title>
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
    
    /* Message */
    .message {
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 30px;
      text-align: center;
    }
    
    .message-success {
      background: #e8f5e9;
      color: var(--success);
      border-left: 4px solid var(--success);
    }
    
    .message-error {
      background: #ffebee;
      color: var(--error);
      border-left: 4px solid var(--error);
    }
    
    /* Filtres */
    .filters {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .filter-btn {
      padding: 8px 16px;
      border-radius: 20px;
      background: var(--white);
      border: 1px solid var(--border);
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .filter-btn:hover, .filter-btn.active {
      background: var(--primary);
      color: var(--white);
      border-color: var(--primary);
    }
    
    /* Liste des RDV */
    .rdv-list {
      background: var(--white);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .rdv-item {
      padding: 20px;
      border-bottom: 1px solid var(--border);
      transition: background 0.3s;
    }
    
    .rdv-item:last-child {
      border-bottom: none;
    }
    
    .rdv-item:hover {
      background: var(--primary-light);
    }
    
    .rdv-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    .rdv-date {
      font-size: 18px;
      font-weight: 600;
      color: var(--primary-dark);
    }
    
    .rdv-status {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 500;
    }
    
    .status-prevu {
      background: #e3f2fd;
      color: var(--primary);
    }
    
    .status-termine {
      background: #e8f5e9;
      color: var(--success);
    }
    
    .status-annule {
      background: #ffebee;
      color: var(--error);
    }
    
    .rdv-doctor {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 10px;
    }
    
    .doctor-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary);
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
    }
    
    .doctor-info h3 {
      font-size: 16px;
      margin-bottom: 2px;
    }
    
    .doctor-speciality {
      font-size: 14px;
      color: #666;
    }
    
    .rdv-motif {
      margin: 15px 0;
      padding-left: 55px;
    }
    
    .rdv-actions {
      display: flex;
      gap: 10px;
      padding-left: 55px;
      flex-wrap: wrap;
    }
    
    .btn-action {
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 14px;
      transition: all 0.3s;
    }
    
    .btn-cancel {
      background: #ffebee;
      color: var(--error);
      border: 1px solid #ffcdd2;
    }
    
    .btn-cancel:hover {
      background: var(--error);
      color: var(--white);
    }
    
    .btn-details {
      background: var(--white);
      color: var(--primary);
      border: 1px solid var(--primary-light);
    }
    
    .btn-details:hover {
      background: var(--primary-light);
    }
    
    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
    }
    
    .empty-icon {
      font-size: 60px;
      color: var(--primary-light);
      margin-bottom: 20px;
    }
    
    .empty-text {
      color: #666;
      margin-bottom: 20px;
    }
    
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    
    .modal-content {
      background: var(--white);
      border-radius: 10px;
      width: 100%;
      max-width: 500px;
      padding: 30px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .modal-title {
      font-size: 20px;
      color: var(--primary);
    }
    
    .close-modal {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #666;
    }
    
    .modal-body {
      margin-bottom: 25px;
    }
    
    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .rdv-motif, .rdv-actions {
        padding-left: 0;
      }
      
      .rdv-doctor {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1 class="header-title">Mes Rendez-vous</h1>
      <a href="rendezvous.php" class="btn btn-primary">+ Nouveau RDV</a>
    </div>
    
    <?php if($msg_type && $msg_text): ?>
      <div class="message message-<?= $msg_type ?>"><?= htmlspecialchars($msg_text) ?></div>
    <?php endif; ?>
    
    <div class="filters">
      <button class="filter-btn active" data-filter="all">Tous</button>
      <button class="filter-btn" data-filter="prevu">Prévus</button>
      <button class="filter-btn" data-filter="termine">Terminés</button>
      <button class="filter-btn" data-filter="annule">Annulés</button>
    </div>
    
    <div class="rdv-list">
      <?php if(empty($rendezvous)): ?>
        <div class="empty-state">
          <div class="empty-icon">📅</div>
          <h3 class="empty-text">Vous n'avez aucun rendez-vous</h3>
          <a href="rendezvous.php" class="btn btn-primary">Prendre un rendez-vous</a>
        </div>
      <?php else: ?>
        <?php foreach($rendezvous as $rdv): ?>
          <div class="rdv-item" data-status="<?= $rdv['statut'] ?>">
            <div class="rdv-header">
              <div class="rdv-date">
                <?= date('d/m/Y à H:i', strtotime($rdv['date_heure'])) ?>
              </div>
              <div class="rdv-status status-<?= $rdv['statut'] ?>">
                <?= ucfirst($rdv['statut']) ?>
              </div>
            </div>
            
            <div class="rdv-doctor">
              <div class="doctor-avatar">
                <?= substr($rdv['medecin_prenom'], 0, 1) . substr($rdv['medecin_nom'], 0, 1) ?>
              </div>
              <div class="doctor-info">
                <h3>Dr. <?= htmlspecialchars($rdv['medecin_prenom']) . ' ' . htmlspecialchars($rdv['medecin_nom']) ?></h3>
                <div class="doctor-speciality"><?= htmlspecialchars($rdv['specialite']) ?></div>
              </div>
            </div>
            
            <?php if(!empty($rdv['motif'])): ?>
              <div class="rdv-motif">
                <strong>Motif :</strong> <?= htmlspecialchars($rdv['motif']) ?>
              </div>
            <?php endif; ?>
            
            <div class="rdv-actions">
              <?php if($rdv['statut'] === 'prévu'): ?>
                <form method="post" class="cancel-form" onsubmit="return confirm('Voulez-vous vraiment annuler ce rendez-vous ?')">
                  <input type="hidden" name="id_rdv" value="<?= $rdv['id_rdv'] ?>">
                  <button type="submit" name="annuler_rdv" class="btn-action btn-cancel">Annuler</button>
                </form>
              <?php endif; ?>
              
              <button class="btn-action btn-details rdv-details-btn" 
                      data-id="<?= $rdv['id_rdv'] ?>"
                      data-date="<?= date('d/m/Y à H:i', strtotime($rdv['date_heure'])) ?>"
                      data-doctor="Dr. <?= htmlspecialchars($rdv['medecin_prenom']) . ' ' . htmlspecialchars($rdv['medecin_nom']) ?>"
                      data-specialite="<?= htmlspecialchars($rdv['specialite']) ?>"
                      data-motif="<?= htmlspecialchars($rdv['motif']) ?>"
                      data-statut="<?= ucfirst($rdv['statut']) ?>">
                Détails
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Modal Détails RDV -->
  <div class="modal" id="rdvModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Détails du rendez-vous</h3>
        <button class="close-modal">&times;</button>
      </div>
      <div class="modal-body">
        <p><strong>Date :</strong> <span id="modal-date"></span></p>
        <p><strong>Médecin :</strong> <span id="modal-doctor"></span></p>
        <p><strong>Spécialité :</strong> <span id="modal-specialite"></span></p>
        <p><strong>Statut :</strong> <span id="modal-statut"></span></p>
        <p><strong>Motif :</strong> <span id="modal-motif"></span></p>
      </div>
      <div class="modal-footer">
        <button class="btn-action btn-details close-modal">Fermer</button>
      </div>
    </div>
  </div>
  
  <script>
    // Filtrage des RDV
    const filterButtons = document.querySelectorAll('.filter-btn');
    const rdvItems = document.querySelectorAll('.rdv-item');
    
    filterButtons.forEach(button => {
      button.addEventListener('click', () => {
        // Active le bouton cliqué
        filterButtons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        
        // Filtre les RDV
        const filter = button.dataset.filter;
        rdvItems.forEach(item => {
          if (filter === 'all' || item.dataset.status === filter) {
            item.style.display = '';
          } else {
            item.style.display = 'none';
          }
        });
      });
    });
    
    // Modal de détails
    const modal = document.getElementById('rdvModal');
    const closeButtons = document.querySelectorAll('.close-modal');
    const detailButtons = document.querySelectorAll('.rdv-details-btn');
    
    detailButtons.forEach(button => {
      button.addEventListener('click', () => {
        document.getElementById('modal-date').textContent = button.dataset.date;
        document.getElementById('modal-doctor').textContent = button.dataset.doctor;
        document.getElementById('modal-specialite').textContent = button.dataset.specialite;
        document.getElementById('modal-statut').textContent = button.dataset.statut;
        document.getElementById('modal-motif').textContent = button.dataset.motif || 'Non spécifié';
        modal.style.display = 'flex';
      });
    });
    
    closeButtons.forEach(button => {
      button.addEventListener('click', () => {
        modal.style.display = 'none';
      });
    });
    
    // Fermer la modal en cliquant à l'extérieur
    window.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  </script>
</body>
</html>