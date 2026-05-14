<?php
require_once __DIR__ . '/../config/security.php';
security_bootstrap(false);

$_currentPage = basename($_SERVER['PHP_SELF'], '.php');
$csrfToken = get_csrf_token();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Controle de Absenteísmo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/controle_absenteismo/assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/controle_absenteismo/">
            <i class="bi bi-calendar-check-fill fs-5"></i>
            <span>Controle de Absenteísmo</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'index' ? 'active' : '' ?>"
                       href="/controle_absenteismo/">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'controle' ? 'active' : '' ?>"
                       href="/controle_absenteismo/controle.php">
                        <i class="bi bi-table me-1"></i>Controle
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'usuarios' ? 'active' : '' ?>"
                       href="/controle_absenteismo/usuarios.php">
                        <i class="bi bi-people-fill me-1"></i>Funcionários
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'relatorio' ? 'active' : '' ?>"
                       href="/controle_absenteismo/relatorio.php">
                        <i class="bi bi-bar-chart-fill me-1"></i>Relatório
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-content">
