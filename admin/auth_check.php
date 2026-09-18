<?php
// Verificar autenticación en todas las páginas del admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Función helper para obtener nombre del admin
function getAdminName() {
    return $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
}

// Función para logout
function adminLogout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>