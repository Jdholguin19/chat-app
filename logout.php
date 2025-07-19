<?php
require_once 'config.php';

if (isLoggedIn()) {
    // Registrar logout
    logAccess($_SESSION['user_id'], $_SESSION['user_email'], 'logout', getUser IP());
    
    // Destruir sesión
    session_destroy();
}

redirect('index.php');
?>
