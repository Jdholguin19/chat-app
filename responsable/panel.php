<?php
require_once '../config.php';
require_once '../includes/funciones.php'; // Asegúrate de incluir este archivo

// Verificar que el usuario esté logueado
if (!isLoggedIn() || $_SESSION['user_role'] !== 'responsable') {
    redirect('login.php?role=responsable');
}

// Obtener chats asignados
$chats = obtenerChats(); // Asegúrate de que esta función esté definida en funciones.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - Responsable</title>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="panel-header">
        <h2>Panel de Responsable</h2>
    </div>
    <div class="chats-list">
        <?php foreach ($chats as $chat): ?>
            <div class="chat-item <?php echo $chat['no_leidos'] > 0 ? 'unread' : ''; ?>">
                <h3>Chat con: <?php echo htmlspecialchars($chat['cliente_nombre']); ?></h3>
                <p>Último mensaje: <?php echo htmlspecialchars($chat['ultimo_mensaje']); ?></p>
                <p>No leídos: <?php echo htmlspecialchars($chat['no_leidos']); ?></p>
                <button onclick="openChat(<?php echo $chat['id']; ?>)">Abrir Chat</button>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function openChat(chatId) {
            // Redirigir a la interfaz de chat del responsable
            window.location.href = 'chat.php?chat_id=' + chatId;
        }
    </script>
</body>
</html>
