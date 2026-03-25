<?php
// Démarrer la session
session_start();

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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $mdp = $_POST['mdp'];
    $confirmMdp = $_POST['confirm-mdp'];
    
    // Validation
    $errors = [];
    
    // Vérifier les champs vides
    if (empty($nom)) $errors[] = "Le nom complet est requis.";
    if (empty($email)) $errors[] = "L'adresse email est requise.";
    if (empty($mdp)) $errors[] = "Le mot de passe est requis.";
    if (empty($confirmMdp)) $errors[] = "La confirmation du mot de passe est requise.";
    
    // Vérifier l'email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Vérifiez votre adresse email.";
    }
    
    // Vérifier la longueur du mot de passe
    if (!empty($mdp) && strlen($mdp) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    
    // Vérifier la confirmation
    if (!empty($mdp) && !empty($confirmMdp) && $mdp !== $confirmMdp) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    
    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Cet email est déjà utilisé.";
            }
        } catch(PDOException $e) {
            $errors[] = "Erreur lors de la vérification de l'email.";
        }
    }
    
    // Si aucune erreur, créer le compte
    if (empty($errors)) {
        // Hasher le mot de passe
        $mdpHash = password_hash($mdp, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe) VALUES (?, ?, ?)");
            $stmt->execute([$nom, $email, $mdpHash]);
            
            // Message de succès
            $success_message = '✅ Compte créé avec succès ! <a href="connexion.php">Connectez-vous ici</a>';
            
            // Réinitialiser les champs
            $nom = $email = '';
            
        } catch(PDOException $e) {
            $errors[] = "Erreur lors de la création du compte : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      background-color: #F0F8FF;
      margin: 0;
      padding: 20px;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .container {
      width: 100%;
      max-width: 600px;
      background: white;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    h2 {
      text-align: center;
      color: #007BFF;
      margin-top: 0;
      margin-bottom: 25px;
    }

    .row {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }

    .field {
      flex: 1;
      min-width: 0;
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: #333;
    }

    input {
      width: 100%;
      padding: 12px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 16px;
      transition: border-color 0.3s;
    }

    input:focus {
      outline: none;
      border-color: #007BFF;
      box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
    }

    .btn {
      background-color: #007BFF;
      color: white;
      padding: 14px;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      width: 100%;
      cursor: pointer;
      transition: background-color 0.3s;
      margin-top: 10px;
    }

    .btn:hover {
      background-color: #0056b3;
    }

    .message {
      text-align: center;
      margin-top: 20px;
      font-size: 15px;
    }

    .message a {
      color: #007BFF;
      text-decoration: none;
      font-weight: bold;
    }

    .message a:hover {
      text-decoration: underline;
    }

    .error {
      color: red;
      background-color: #ffe6e6;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
      border: 1px solid red;
    }

    .success {
      color: green;
      background-color: #e6ffe6;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
      border: 1px solid green;
    }

    @media (max-width: 768px) {
      body {
        padding: 15px;
      }
      
      .container {
        padding: 20px;
      }
      
      .row {
        gap: 10px;
      }
      
      .field {
        min-width: 100%;
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 10px;
      }
      
      .container {
        padding: 15px;
      }
      
      h2 {
        font-size: 1.5rem;
      }
      
      input {
        padding: 10px;
        font-size: 14px;
      }
      
      .btn {
        padding: 12px;
        font-size: 15px;
      }
      
      .message {
        font-size: 14px;
      }
    }

    @media (max-width: 320px) {
      .container {
        padding: 10px;
      }
      
      h2 {
        font-size: 1.3rem;
      }
      
      input {
        padding: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Créer un compte</h2>

    <?php
    // Afficher le message de succès s'il existe
    if (isset($success_message)) {
        echo '<div class="success">' . $success_message . '</div>';
    }
    
    // Afficher les erreurs s'il y en a
    if (isset($errors) && !empty($errors)) {
        echo '<div class="error">';
        foreach ($errors as $error) {
            echo htmlspecialchars($error) . '<br>';
        }
        echo '</div>';
    }
    ?>

    <form method="POST" action="">
      <div class="row">
        <div class="field">
          <label for="nom">Nom complet</label>
          <input type="text" id="nom" name="nom" placeholder="Ex: Abdoulaye Mahamat" 
                 value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>" required>
        </div>
        <div class="field">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email" placeholder="exemple@mail.com" 
                 value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="mdp">Mot de passe</label>
          <input type="password" id="mdp" name="mdp" placeholder="" required>
        </div>
        <div class="field">
          <label for="confirm-mdp">Confirmer mot de passe</label>
          <input type="password" id="confirm-mdp" name="confirm-mdp" placeholder="" required>
        </div>
      </div>

      <button type="submit" class="btn">Créer un compte</button>

      <div class="message">
        Vous avez déjà un compte ? <a href="connexion.php">Se connecter</a>
      </div>
    </form>
  </div>
</body>
</html>