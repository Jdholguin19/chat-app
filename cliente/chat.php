<?php
require_once '../config.php';
require_once '../includes/funciones.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn() || $_SESSION['user_role'] !== 'cliente') {
    redirect('../login.php?role=cliente');
}

// Obtener el chat del cliente
$chat_id = getChatParaCliente($_SESSION['user_id']);

// Verificar que se pudo crear/obtener el chat
if (!$chat_id) {
    $error_message = "Error: No se pudo crear o acceder al chat. Por favor contacte al administrador.";
}
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
        <div class="chat-header">
            <h2>Chat en Vivo</h2>
            <a href="../logout.php" class="btn btn-secondary" style="float: right;">Cerrar Sesión</a>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php else: ?>
            <div class="chat-messages" id="chat-messages">
                <div class="message bot">
                    <strong>Asistente:</strong> ¡Hola! ¿En qué puedo ayudarte hoy?
                    <div class="message-meta"><?php echo date('H:i'); ?></div>
                </div>
            </div>
            <div class="chat-input">
                <input type="text" id="message" placeholder="Escribe tu mensaje..." maxlength="500">
                <button id="send" type="button">Enviar</button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!isset($error_message)): ?>
    <script>
        const CHAT_ID = <?php echo json_encode($chat_id); ?>;
        
        // Función para mostrar errores
        function showError(message) {
            console.error('Error:', message);
            const chatMessages = document.getElementById('chat-messages');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.textContent = 'Error: ' + message;
            chatMessages.appendChild(errorDiv);
        }

        // Función para cargar mensajes
        function loadMessages() {
            fetch('../api/chat.php?action=messages&chat_id=' + chat_id)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showError('No se pudieron cargar los mensajes: ' + data.error);
                    } else {
                        // Procesar los mensajes aquí
                        displayMessages(data.mensajes);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('No se pudieron cargar los mensajes: ' + error.message);
                });
        }


        // Enviar mensaje
        document.getElementById('send').addEventListener('click', sendMessage);
        document.getElementById('message').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        function sendMessage() {
            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();

            if (message === '') {
                alert('Por favor escribe un mensaje.');
                return;
            }

            if (!CHAT_ID) {
                showError('ID de chat no válido');
                return;
            }

            // Deshabilitar input mientras se envía
            messageInput.disabled = true;
            document.getElementById('send').disabled = true;

            fetch('../api/chat.php?action=send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    chat_id: CHAT_ID, 
                    mensaje: message 
                })
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('La respuesta no es JSON válido');
                }
                
                if (!response.ok) {
                    throw new Error('Error del servidor: ' + response.status);
                }
                
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    loadMessages(); // Recargar mensajes
                } else {
                    showError(data.error || 'Error al enviar mensaje');
                }
            })
            .catch(error => {
                showError('No se pudo enviar el mensaje: ' + error.message);
            })
            .finally(() => {
                // Reactivar input
                messageInput.disabled = false;
                document.getElementById('send').disabled = false;
                messageInput.focus();
            });
        }

        // Cargar mensajes al inicio
        loadMessages();
        
        // Actualizar mensajes cada 5 segundos
        setInterval(loadMessages, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
