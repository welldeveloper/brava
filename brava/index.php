<?php
require_once __DIR__ . '/includes/auth.php';
iniciarSessao();
if (!empty($_SESSION['usuario_id'])) {
    redirecionar('pages/dashboard.php');
} else {
    redirecionar('login.php');
}
