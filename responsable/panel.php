<?php
require_once '../config.php';
require_once '../includes/funciones.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn() || $_SESSION['user_role'] !== 'responsable') {
    redirect('../login.php?role=responsable');
}

try {
    // Obtener chats asignados usando la función corregida
    $chats = obtenerChatsResponsable($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Error en panel responsable: " . $e->getMessage());
    $chats = [];
    $error_message = "Error al cargar los chats. Intenta recargar la página.";
}
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
    <div class="chat-container">
        <div class="panel-header">
            <h2>Panel de Responsable</h2>
            <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
            <a href="../logout.php" class="btn btn-secondary" style="float: right;">Cerrar Sesión</a>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($chats); ?></div>
                <div>Chats Asignados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($chats, 'no_leidos')); ?></div>
                <div>Mensajes sin Leer</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($chats, fn($c) => $c['abierto'] == 1)); ?></div>
                <div>Chats Activos</div>
            </div>
        </div>
        
        <div class="chats-list">
            <h3>Mis Chats</h3>
            
            <?php if (empty($chats)): ?>
                <div class="alert alert-info">
                    No tienes chats asignados actualmente.
                </div>
            <?php else: ?>
                <?php foreach ($chats as $chat): ?>
                    <div class="chat-item <?php echo $chat['no_leidos'] > 0 ? 'unread' : ''; ?>" onclick="openChat(<?php echo $chat['id']; ?>)">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4><?php echo htmlspecialchars($chat['cliente_nombre']); ?></h4>
                                <p style="color: #666; font-size: 14px;"><?php echo htmlspecialchars($chat['cliente_email']); ?></p>
                                
                                <?php if (!empty($chat['ultimo_mensaje'])): ?>
                                    <p><strong>Último mensaje:</strong> 
                                        <?php echo htmlspecialchars(substr($chat['ultimo_mensaje'], 0, 50)) . (strlen($chat['ultimo_mensaje']) > 50 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($chat['fecha_ultimo_mensaje']): ?>
                                    <p style="font-size: 12px; color: #999;">
                                        <?php echo date('d/m/Y H:i', strtotime($chat['fecha_ultimo_mensaje'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div style="text-align: right;">
                                <?php if ($chat['no_leidos'] > 0): ?>
                                    <span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                                        <?php echo $chat['no_leidos']; ?> sin leer
                                    </span>
                                <?php endif; ?>
                                
                                <div style="margin-top: 10px;">
                                    <span style="padding: 4px 8px; border-radius: 12px; font-size: 12px; background: <?php echo $chat['abierto'] ? '#28a745' : '#6c757d'; ?>; color: white;">
                                        <?php echo $chat['abierto'] ? 'Activo' : 'Cerrado'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openChat(chatId) {
            // Redirigir a la interfaz de chat del responsable
            window.location.href = 'chat.php?chat_id=' + chatId;
        }
        
        // Auto refresh cada 30 segundos para ver nuevos mensajes
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>