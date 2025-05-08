<?php
session_start();
if(isset($_POST['logout'])){
      
      header('location: conexion.php');
      session_destroy();
      exit;
      
    }
    if(isset($_POST['mes_patiant'])){
      header('location: mes_patiant.php');
      exit;
    }
    if(isset($_POST['Mes_Rendez_vous'])){
      header('location: Mes_Rendez_vous.php');
      exit;
    }
    if(isset($_POST['Résultats_d_Analyses'])){
      header('location: Résultats_d_Analyses.php');
      exit;
    }
    $nbpatiants=$nbrandezvous=$nbanalyse="";
    #$id_patiant=$_SESSION['id_patiant'];
    #$nbpatiants=query($sql="SELECT * FROM patiant WHERE id_patant=$id_patiant")

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Document</title>
    <style>
      /* Reset de base */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f4f6f8;
  height: 100vh;
  display: flex;
}

.container {
  display: flex;
  width: 100%;
  height: 100vh;
}

/* SIDEBAR */
.sidebar {
  background-color: #cdd3d8;
  width: 250px;
  padding: 30px 20px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.sidebar h2 {
  color: #1e2a30;
  margin-bottom: 30px;
  font-size: 22px;
}

.sidebar form {
  width: 100%;
  margin-bottom: 15px;
}

.sidebar input[type="submit"] {
  width: 100%;
  padding: 12px 20px;
  font-size: 16px;
  font-weight: bold;
  color: white;
  background: linear-gradient(135deg, #00c6ff, #0072ff);
  border: none;
  border-radius: 8px;
  cursor: pointer;
  text-align: left;
  transition: all 0.3s ease;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.sidebar input[type="submit"]:hover {
  background: linear-gradient(135deg, #0072ff, #00c6ff);
  transform: translateY(-2px);
}

/* MAIN CONTENT */
.main-content {
  flex: 1;
  padding: 40px;
  overflow-y: auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

#title {
  font-size: 26px;
  font-weight: bold;
}

.header form input[type="submit"] {
  background: linear-gradient(135deg, #00c6ff, #0072ff);
  color: white;
  padding: 10px 20px;
  font-weight: bold;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: 0.3s ease;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
}

.header form input[type="submit"]:hover {
  background: linear-gradient(135deg, #0072ff, #00c6ff);
  transform: scale(1.03);
}

/* CARDS */
.cards {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  margin-bottom: 30px;
  
  
}

.card {
  flex: 1;
  min-width: 250px;
  background-color: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.card i {
  font-size: 28px;
  margin-bottom: 10px;
}

.card h3 {
  font-size: 18px;
  margin-bottom: 8px;
}

.card p {
  font-size: 15px;
}

.fa-calendar-alt {
  color: #e74c3c;
}

.fa-calendar-check {
  color: #2ecc71;
}

/* SUMMARY */
.summary {
  background-color: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.summary h2 {
  font-size: 20px;
  margin-bottom: 10px;
}

.summary p {
  font-size: 15px;
  margin-bottom: 5px;
}


    </style>
</head>
<body>
    
    
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="UTF-8">
      <title>Dashboard Docteur</title>
      <link rel="stylesheet" href="style.css">
    </head>
    <body>
    
      <div class="container">
        <aside class="sidebar">
          <h2>Dr. Dupont</h2>
          <nav>
            <ul>
              
              <li>
              <form action="" method="POST">
                <input id="mes_patiant" type="submit" name="mes_patiant" value="Mes Patients">
              </form>
              </li>
              <li>
                <form action="dashword_medecin.php" method="POST">
                <input id="mes_patiant" type="submit" name="Mes_Rendez_vous" value="Mes Rendez-vous">
                </form>
              </li>
              <li>
                 <form action="" method="POST">
                 <input id="mes_patiant" type="submit" name="Résultats_d_Analyses" value="Résultats d'Analyses">
                 </form>
              </li>
            </ul>
          </nav>
        </aside>
    
        <main class="main-content">
          <header class="header">
            <h1 id="title">Bienvenue Dr. Dupont</h1>
            <form action="" method="POST">
            <input type="submit" name="logout" id="mes_patiant" value="Se déconnecter">
            </form>
          </header>
    
          <section class="cards">
            <div class="card">
                <i class="fas fa-calendar-alt red-icon"></i>
              <h3>Mes Patients</h3>
              <p>Nombre de patients: <strong><?= $nbpatiants ?></strong></p>
            </div>
    
            <div class="card">
                <i class="fas fa-calendar-check green-icon"></i>
              <h3>Mes Rendez-vous</h3>
              <p>Rendez-vous à venir: <strong><?= $nbrandezvous ?></strong></p>
            </div>
          </section>
    
          <section class="summary">
            <h2>Résumé</h2>
            <p>Vous avez <?= $nbrandezvous ?> rendez-vous aujourd'hui.</p>
            <p><?= $nbanalyse ?> nouveaux résultats d’analyses</p>
          </section>
    
        </main>
      </div>
    
    </body>
    </html>
    
</body>
</html>