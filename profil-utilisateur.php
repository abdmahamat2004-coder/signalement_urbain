<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit();
}

// Connexion à la base de données
$host = 'localhost';
$dbname = 'mon_app';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement de la modification du profil
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_profil'])) {
    $nom_complet = trim($_POST['nom_complet']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validation
    if (empty($nom_complet)) {
        $errors[] = "Le nom complet est requis.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }
    
    // Vérifier si l'email existe déjà (sauf pour l'utilisateur actuel)
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Cet email est déjà utilisé par un autre compte.";
        }
    }
    
    // Si mot de passe fourni, le valider
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Préparer la requête de mise à jour
            if (!empty($password)) {
                // Mettre à jour avec nouveau mot de passe
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ?, mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$nom_complet, $email, $password_hash, $user_id]);
            } else {
                // Mettre à jour sans changer le mot de passe
                $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ? WHERE id = ?");
                $stmt->execute([$nom_complet, $email, $user_id]);
            }
            
            // Mettre à jour la session
            $_SESSION['user_nom'] = $nom_complet;
            $_SESSION['user_email'] = $email;
            
            $message = "✅ Profil mis à jour avec succès !";
            $message_class = "success";
            
            // Recharger les données utilisateur
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $message = "❌ Erreur lors de la mise à jour : " . $e->getMessage();
            $message_class = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_class = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profil d'utilisateur</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f0f4f8;
      margin: 0;
      padding: 20px;
    }

    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .header .back-btn {
      font-size: 18px;
      color: #333;
      cursor: pointer;
    }

    .header h2 {
      text-align: center;
      flex-grow: 1;
      margin: 0;
      color: #333;
    }

    .profile-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 30px;
    }

    .profile-icon {
      font-size: 60px;
      color: #007bff;
      background-color: #e0f0ff;
      border-radius: 50%;
      padding: 20px;
      cursor: pointer;
    }

    .user-info {
      text-align: center;
      flex: 1;
    }

    .user-info p {
      margin: 5px 0;
      font-size: 16px;
    }

    .edit-btn {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 10px 15px;
      cursor: pointer;
      border-radius: 5px;
    }

    .form-container {
      display: flex;
      justify-content: center;
      margin-top: 30px;
      gap: 20px;
      flex-wrap: wrap;
    }

    .form-section {
      background-color: white;
      padding: 20px;
      border-radius: 10px;
      width: 300px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .form-section h3 {
      margin-top: 0;
    }

    .form-section label {
      display: block;
      margin-bottom: 5px;
      margin-top: 10px;
    }

    .form-section input {
      width: 100%;
      padding: 8px;
      margin-bottom: 5px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    .save-btn {
      display: block;
      margin: 30px auto 0;
      background-color: green;
      color: white;
      padding: 12px 25px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .message {
      text-align: center;
      margin-top: 15px;
      font-weight: bold;
      padding: 10px;
      border-radius: 5px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .error {
      color: red;
      background-color: #ffe6e6;
      border: 1px solid red;
    }

    .success {
      color: green;
      background-color: #e6ffe6;
      border: 1px solid green;
    }

    input[disabled] {
      background-color: #f0f0f0;
    }
  </style>
</head>
<body>

  <div class="header">
    <a href="tableau_de_bord.php" class="back-btn">&larr; Retour</a>
    <h2>Profil d'utilisateur</h2>
    <div></div>
  </div>

  <div class="profile-top">
    <div class="profile-icon" id="profileIcon"><i class="fas fa-user"></i></div>
    <div class="user-info">
      <p><strong><?php echo htmlspecialchars($user['nom_complet']); ?></strong></p>
      <p><?php echo htmlspecialchars($user['email']); ?></p>
    </div>
    <button class="edit-btn" id="editBtn">Modifier</button>
  </div>

  <?php if ($message): ?>
  <div class="message <?php echo $message_class; ?>">
    <?php echo $message; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="form-container">
      <div class="form-section">
        <h3>Information du profil</h3>
        <label for="nom_complet">Nom complet</label>
        <input type="text" id="nom_complet" name="nom_complet" 
               value="<?php echo htmlspecialchars($user['nom_complet']); ?>" disabled required>
        
        <label for="email">Adresse Email</label>
        <input type="email" id="email" name="email" 
               value="<?php echo htmlspecialchars($user['email']); ?>" disabled required>
      </div>

      <div class="form-section">
        <h3>Détails du compte</h3>
        <label for="password">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
        <input type="password" id="password" name="password" value="" disabled>
        
        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
        <input type="password" id="confirm_password" name="confirm_password" value="" disabled>
      </div>
    </div>

    <button type="submit" class="save-btn" id="saveBtn" name="modifier_profil" style="display: none;">Enregistrer</button>
  </form>

  <script>
    const editBtn = document.getElementById("editBtn");
    const saveBtn = document.getElementById("saveBtn");
    const inputs = document.querySelectorAll("input");
    const message = document.querySelector(".message");

    editBtn.addEventListener("click", () => {
      const isDisabled = inputs[0].disabled;
      
      // Activer/désactiver tous les champs
      inputs.forEach(input => input.disabled = !isDisabled);
      
      // Changer le texte du bouton
      if (isDisabled) {
        editBtn.textContent = "Annuler";
        saveBtn.style.display = "block";
        if (message) message.style.display = "none";
      } else {
        editBtn.textContent = "Modifier";
        saveBtn.style.display = "none";
        // Réinitialiser les champs de mot de passe
        document.getElementById("password").value = "";
        document.getElementById("confirm_password").value = "";
      }
    });

    // Changement de l'icône de profil
    document.getElementById("profileIcon").addEventListener("click", () => {
      alert("Fonction pour changer l'image de profil à ajouter plus tard.");
    });

    // Validation côté client (identique à votre code)
    document.querySelector("form").addEventListener("submit", function(e) {
      const password = document.getElementById("password").value.trim();
      const confirmPassword = document.getElementById("confirm_password").value.trim();
      
      // Si un mot de passe est fourni, le valider
      if (password !== "") {
        if (password.length < 6) {
          e.preventDefault();
          alert("Le mot de passe doit contenir au moins 6 caractères.");
          return false;
        }
        
        if (password !== confirmPassword) {
          e.preventDefault();
          alert("Les mots de passe ne correspondent pas.");
          return false;
        }
      }
    });
  </script>

</body>
</html>