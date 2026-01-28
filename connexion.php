<?php
// CE CODE DOIT ÊTRE EN TOUT PREMIER DANS LE FICHIER
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

// Message de succès après création de compte
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = '✅ Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $mdp = $_POST['mdp'];
    
    // Validation
    $errors = [];
    
    // Vérifier les champs vides
    if (empty($email)) {
        $errors[] = "L'adresse email est requise.";
    }
    if (empty($mdp)) {
        $errors[] = "Le mot de passe est requis.";
    }
    
    // Vérifier l'email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Veuillez entrer une adresse email valide.";
    }
    
    // Si aucune erreur, vérifier les identifiants
    if (empty($errors)) {
        try {
            // Chercher l'utilisateur par email
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Vérifier le mot de passe
                if (password_verify($mdp, $user['mot_de_passe'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nom'] = $user['nom_complet'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];  // ← NOUVEAU : stocker le rôle
                    
                    // Redirection selon le rôle
                    if ($user['role'] == 'admin') {
                        header('Location: tableau_de_bord_admin.php');
                    } else {
                        header('Location: tableau_de_bord.php');
                    }
                    exit();
                } else {
                    $errors[] = "Mot de passe incorrect.";
                }
            } else {
                $errors[] = "Aucun compte trouvé avec cet email.";
            }
            
        } catch(PDOException $e) {
            $errors[] = "Erreur lors de la connexion : " . $e->getMessage();
        }
    }
}

// Si l'utilisateur est déjà connecté, rediriger
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header('Location: tableau_de_bord_admin.php');
    } else {
        header('Location: tableau_de_bord.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion</title>
  
  <style>
    /* Variables CSS pour une gestion cohérente */
    :root {
      --primary-color: #28A745;
      --primary-dark: #218838;
      --secondary-color: #F0F8FF;
      --text-color: #333;
      --light-gray: #f5f5f5;
      --border-color: #ddd;
      --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      --transition: all 0.3s ease;
    }

    /* Reset et styles de base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, var(--secondary-color) 0%, #e6f7ff 100%);
      color: var(--text-color);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .container {
      width: 100%;
      max-width: 450px;
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: var(--shadow);
      transition: var(--transition);
    }

    /* Animation d'apparition */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .container {
      animation: fadeIn 0.5s ease-out;
    }

    h2 {
      text-align: center;
      color: var(--primary-color);
      margin-bottom: 25px;
      font-size: 1.8rem;
      font-weight: 600;
    }

    .field {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #555;
    }

    input {
      width: 100%;
      padding: 14px;
      border-radius: 8px;
      border: 1px solid var(--border-color);
      font-size: 16px;
      transition: var(--transition);
      background-color: var(--light-gray);
    }

    input:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
      background-color: white;
    }

    input::placeholder {
      color: #999;
    }

    .btn {
      background-color: var(--primary-color);
      color: white;
      padding: 14px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      width: 100%;
      cursor: pointer;
      transition: var(--transition);
      margin-top: 10px;
    }

    .btn:hover {
      background-color: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .btn:active {
      transform: translateY(0);
    }

    /* Options supplémentaires */
    .options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 15px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .remember {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .remember input {
      width: auto;
    }

    .forgot-password {
      color: var(--primary-color);
      text-decoration: none;
      font-size: 14px;
    }

    .forgot-password:hover {
      text-decoration: underline;
    }

    /* Message d'erreur */
    .error-message {
      color: #dc3545;
      font-size: 14px;
      margin-top: 5px;
    }

    /* Message de succès */
    .success-message {
      color: #28a745;
      background-color: #d4edda;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      border: 1px solid #c3e6cb;
    }

    /* Message d'erreur général */
    .error-message-general {
      color: #dc3545;
      background-color: #f8d7da;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      border: 1px solid #f5c6cb;
    }

    /* Séparateur */
    .separator {
      text-align: center;
      margin: 20px 0;
      position: relative;
      color: #777;
    }

    .separator::before {
      content: "";
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: var(--border-color);
    }

    .separator span {
      background: white;
      padding: 0 15px;
      position: relative;
    }

    /* Boutons sociaux */
    .social-login {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }

    .social-btn {
      flex: 1;
      padding: 12px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: white;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 14px;
    }

    .social-btn:hover {
      background: var(--light-gray);
    }

    .social-btn.google {
      color: #db4437;
    }

    .social-btn.facebook {
      color: #4267B2;
    }

    /* Lien d'inscription */
    .register-link {
      text-align: center;
      margin-top: 25px;
      font-size: 14px;
    }

    .register-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
    }

    .register-link a:hover {
      text-decoration: underline;
    }

    /* Styles responsives */
    @media (max-width: 768px) {
      body {
        padding: 15px;
      }
      
      .container {
        padding: 25px;
        max-width: 400px;
      }
      
      h2 {
        font-size: 1.6rem;
      }
      
      .options {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }
      
      .social-login {
        flex-direction: column;
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 10px;
      }
      
      .container {
        padding: 20px 15px;
      }
      
      h2 {
        font-size: 1.4rem;
        margin-bottom: 20px;
      }
      
      input, .btn {
        padding: 12px;
      }
      
      .field {
        margin-bottom: 15px;
      }
    }

    @media (max-width: 320px) {
      .container {
        padding: 15px 10px;
      }
      
      h2 {
        font-size: 1.3rem;
      }
    }

    /* Mode sombre facultatif */
    @media (prefers-color-scheme: dark) {
      :root {
        --text-color: #f0f0f0;
        --light-gray: #2a2a2a;
        --border-color: #444;
      }
      
      body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
      }
      
      .container {
        background: #2d3748;
        color: var(--text-color);
      }
      
      input {
        background-color: #4a5568;
        border-color: #4a5568;
        color: white;
      }
      
      input:focus {
        background-color: #2d3748;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Connexion</h2>

    <?php
    // Afficher le message de succès s'il existe
    if (isset($success_message)) {
        echo '<div class="success-message">' . htmlspecialchars($success_message) . '</div>';
    }
    
    // Afficher les erreurs s'il y en a
    if (isset($errors) && !empty($errors)) {
        echo '<div class="error-message-general">';
        foreach ($errors as $error) {
            echo htmlspecialchars($error) . '<br>';
        }
        echo '</div>';
    }
    ?>

    <!-- Optionnel: Connexion sociale -->
    <div class="social-login">
      <button class="social-btn google">
        <i class="fab fa-google"></i> Google
      </button>
      <button class="social-btn facebook">
        <i class="fab fa-facebook-f"></i> Facebook
      </button>
    </div>

    <div class="separator">
      <span>Ou connectez-vous avec email</span>
    </div>

    <form method="POST" action="">
      <div class="field">
        <label for="email">Adresse email</label>
        <input type="email" id="email" name="email" placeholder="exemple@mail.com" 
               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
      </div>

      <div class="field">
        <label for="mdp">Mot de passe</label>
        <input type="password" id="mdp" name="mdp" placeholder="" required>
      </div>

      <div class="options">
        <div class="remember">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">Se souvenir de moi</label>
        </div>
        <a href="#" class="forgot-password">Mot de passe oublié ?</a>
      </div>

      <button type="submit" class="btn">Se connecter</button>
    </form>

    <div class="register-link">
      Pas encore de compte ? <a href="creer_compte.php">S'inscrire</a>
    </div>
  </div>

  <!-- Ajout des icônes Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <script>
    // Gestion du mode sombre
    function updateDarkMode() {
      if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.body.classList.add('dark-mode');
      } else {
        document.body.classList.remove('dark-mode');
      }
    }

    window.matchMedia('(prefers-color-scheme: dark)').addListener(updateDarkMode);
    updateDarkMode();
  </script>
</body>
</html>