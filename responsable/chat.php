<?php
require_once '../config.php';
require_once '../includes/funciones.php';

// Verificar que el usuario esté logueado y sea responsable
if (!isLoggedIn() || $_SESSION['user_role'] !== 'responsable') {
    redirect('../login.php?role=responsable');
}

// Obtener el ID del chat desde la URL
$chat_id = $_GET['chat_id'] ?? null;

// Verificar que el chat_id sea válido
if (!$chat_id || !is_numeric($chat_id)) {
    $error_message = "Error: ID de chat no válido.";
} else {
    // Verificar permisos para acceder al chat
    if (!verificarPermisoChat($chat_id, $_SESSION['user_id'], 'responsable')) {
        $error_message = "Error: No tienes permisos para acceder a este chat.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Responsable</title>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>Chat en Vivo</h2>
            <a href="../logout.php" class="btn btn-secondary" style="float: right;">Cerrar Sesión</a>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php else: ?>
            <div class="chat-messages" id="chat-messages">
                <!-- Aquí se cargarán los mensajes del chat -->
            </div>
            <div class="chat-input">
                <input type="text" id="message" placeholder="Escribe tu mensaje..." maxlength="500">
                <button id="send" type="button">Enviar</button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const CHAT_ID = <?php echo json_encode($chat_id); ?>;

        // Función para cargar mensajes
        function loadMessages() {
            if (!CHAT_ID) {
                console.error('ID de chat no válido');
                return;
            }

            fetch('../api/chat.php?action=messages&chat_id=' + CHAT_ID)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error del servidor: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.mensajes) {
                        const chatMessages = document.getElementById('chat-messages');
                        chatMessages.innerHTML = ''; // Limpiar mensajes anteriores
                        
                        data.mensajes.forEach(mensaje => {
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'message ' + mensaje.remitente;
                            messageDiv.innerHTML = `
                                <strong>${mensaje.remitente === 'cliente' ? 'Cliente' : 'Responsable'}:</strong> ${mensaje.contenido}
                                <div class="message-meta">${mensaje.fecha}</div>
                            `;
                            chatMessages.appendChild(messageDiv);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error al cargar mensajes:', error);
                });
        }

        // Cargar mensajes al iniciar
        loadMessages();
    </script>
</body>
</html>
