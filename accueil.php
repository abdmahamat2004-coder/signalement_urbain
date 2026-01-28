<?php
require_once '../backend/config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Signalement Urbain</title>

  <style>
    /* Reset et base */
    body, html {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      height: 100%;
    }

    /* Navbar */
    header {
      position: absolute;
      width: 100%;
      top: 0;
      z-index: 2;
    }

    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 40px;
      background-color: white;
    }

    .nav-left a,
    .nav-right a {
      text-decoration: none;
      margin-right: 20px;
      font-weight: bold;
      color: #333;
    }

    .btn-connexion {
      background-color: #3498db;
      color: white;
      padding: 10px 16px;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
    }

    .btn-red {
      background-color: #dc3545;
      color: white;
      padding: 10px 16px;
      border-radius: 6px;
      margin-right: 10px;
    }

    /* Section Hero */
    .hero {
      background-image: url("images/fond-accueil.jpg"); /* Ton image ici */
      background-size: cover;
      background-position: center;
      height: 100vh;
      position: relative;
    }

    .hero-overlay {
      background: rgba(0, 0, 0, 0.5); /* fond noir semi-transparent */
      color: white;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 0 20px;
    }

    .hero h1 {
      font-size: 48px;
      font-weight: bold;
      line-height: 1.4;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .navbar {
        flex-direction: column;
        padding: 15px;
      }

      .nav-left, .nav-right {
        margin: 10px 0;
      }

      .hero h1 {
        font-size: 32px;
      }
    }

    @media (max-width: 480px) {
      .hero h1 {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <header>
    <nav class="navbar">
      <div class="nav-left">
        <a href="#">Accueil</a>
        <a href="#">À propos</a>
      </div>
      <div class="nav-right">
        <a href="#" class="btn-red">Signaler un problème</a>
        <a href="connexion.html" class="btn-connexion">Connexion</a>
        
      </div>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-overlay">
      <h1>Bienvenue sur votre plateforme<br>de signalement urbain</h1>
    </div>
  </section>
</body>
</html>
