<?php
require_once '../config.php';
require_once '../includes/funciones.php'; // Asegúrate de incluir este archivo

// Verificar que el usuario esté logueado
if (!isLoggedIn() || $_SESSION['user_role'] !== 'cliente') {
    redirect('login.php?role=cliente');
}

// Obtener el chat del cliente
$chat_id = getChatParaCliente($_SESSION['user_id']);
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
        // Función para cargar mensajes
        function loadMessages() {
            fetch('../api/chat.php?action=messages&chat_id=<?php echo $chat_id; ?>')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const chatMessages = document.getElementById('chat-messages');
                        chatMessages.innerHTML = ''; // Limpiar mensajes anteriores
                        data.mensajes.forEach(mensaje => {
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'message ' + mensaje.remitente;
                            messageDiv.innerHTML = `<strong>${mensaje.remitente}</strong>: ${mensaje.contenido}`;
                            chatMessages.appendChild(messageDiv);
                        });
                        chatMessages.scrollTop = chatMessages.scrollHeight; // Desplazar hacia abajo
                    } else {
                        console.error(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ocurrió un error al cargar los mensajes. Intenta nuevamente.');
                });
        }

        // Enviar mensaje
        document.getElementById('send').addEventListener('click', function() {
            const messageInput = document.getElementById('message');
            const message = messageInput.value;

            if (message.trim() === '') {
                alert('Por favor escribe un mensaje.');
                return;
            }

            fetch('../api/chat.php?action=send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ chat_id: '<?php echo $chat_id; ?>', mensaje: message })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    loadMessages(); // Cargar mensajes después de enviar
                    messageInput.value = ''; // Limpiar el input
                } else {
                    alert(data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al enviar el mensaje. Intenta nuevamente.');
            });
        });

        // Cargar mensajes al inicio
        loadMessages();
        setInterval(loadMessages, 5000); // Cargar mensajes cada 5 segundos
    </script>
</body>
</html>
