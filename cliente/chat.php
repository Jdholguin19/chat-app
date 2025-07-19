<?php
require_once '../config.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn() || $_SESSION['user_role'] !== 'cliente') {
    redirect('login.php?role=cliente');
}

// Aquí puedes agregar la lógica para mostrar el chat
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Cliente</title>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="chat-container">
        <h2>Chat en Vivo</h2>
        <div class="chat-messages" id="chat-messages">
            <!-- Aquí se cargarán los mensajes del chat -->
        </div>
        <div class="chat-input">
            <input type="text" id="message" placeholder="Escribe tu mensaje...">
            <button id="send">Enviar</button>
        </div>
    </div>

    <script>
        // Aquí puedes agregar la lógica para manejar el envío de mensajes
    </script>
</body>
</html>
