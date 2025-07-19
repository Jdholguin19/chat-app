<?php
//Funciones auxiliares para el chat

/*Obtiene el responsable con menos chats activos*/
function getResponsableConMenosChats() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT r.user_id, u.nombre, COUNT(c.id) as total_chats
            FROM responsables r
            INNER JOIN users u ON r.user_id = u.id
            LEFT JOIN chats c ON r.user_id = c.responsable_id AND c.abierto = 1
            WHERE r.activo = 1
            GROUP BY r.user_id, u.nombre
            ORDER BY total_chats ASC
            LIMIT 1
        ");
        $stmt->execute();
        
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting responsable: " . $e->getMessage());
        return null;
    }
}

/* Crea un nuevo chat y asigna un responsable*/
function crearNuevoChat($cliente_id) {
    try {
        $db = Database::getInstance()->getConnection();
        $responsable = getResponsableConMenosChats();
        
        if (!$responsable) {
            throw new Exception("No hay responsables disponibles");
        }
        
        $db->beginTransaction();
        
        // Crear chat
        $stmt = $db->prepare("
            INSERT INTO chats (cliente_id, responsable_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$cliente_id, $responsable['user_id']]);
        $chat_id = $db->lastInsertId();
        
        // Crear asignación
        $stmt = $db->prepare("
            INSERT INTO asignaciones (cliente_id, responsable_id) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
            responsable_id = VALUES(responsable_id),
            fecha = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$cliente_id, $responsable['user_id']]);
        
        $db->commit();
        
        return $chat_id;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error creating chat: " . $e->getMessage());
        return null;
    }
}

/*Obtiene o crea un chat para el cliente*/
function getChatParaCliente($cliente_id) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Busca chat abierto existente
        $stmt = $db->prepare("
            SELECT id FROM chats 
            WHERE cliente_id = ? AND abierto = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$cliente_id]);
        $chat = $stmt->fetch();
        
        if ($chat) {
            return $chat['id'];
        }
        
        // Si no existe, crea uno nuevo
        return crearNuevoChat($cliente_id);
    } catch (Exception $e) {
        error_log("Error getting chat: " . $e->getMessage());
        return null;
    }
}

/*Guarda un mensaje en la base de datos*/
function guardarMensaje($chat_id, $remitente, $contenido) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO mensajes (chat_id, remitente, contenido) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$chat_id, $remitente, $contenido]);
        
        // Actualizar timestamp del chat
        $stmt = $db->prepare("
            UPDATE chats SET updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$chat_id]);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Error saving message: " . $e->getMessage());
        return null;
    }
}

/*Genera respuesta automática del bot*/
function generarRespuestaBot($mensaje) {
    try {
        $db = Database::getInstance()->getConnection();
        $mensaje_lower = strtolower($mensaje);
        
        // Buscar respuesta predefinida
        $stmt = $db->prepare("
            SELECT texto FROM mensajes_pred 
            WHERE tipo = 'bot' AND activo = 1
            ORDER BY orden ASC
        ");
        $stmt->execute();
        $respuestas = $stmt->fetchAll();
        
        foreach ($respuestas as $respuesta) {
            $palabras_clave = explode(',', strtolower($respuesta['texto']));
            
            foreach ($palabras_clave as $palabra) {
                if (strpos($mensaje_lower, trim($palabra)) !== false) {
                    return $respuesta['texto'];
                }
            }
        }
        
        // Respuesta por defecto si no encuentra coincidencia
        return "Gracias por tu mensaje. Un responsable se pondrá en contacto contigo pronto.";
        
    } catch (Exception $e) {
        error_log("Error generating bot response: " . $e->getMessage());
        return "Gracias por contactarnos. Un responsable te atenderá pronto.";
    }
}

/*Simula envío de WhatsApp*/
function sendWhatsApp($numero, $mensaje) {
    try {
        $log_file = getenv('WHATSAPP_LOG') ?: './logs/whatsapp.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] WhatsApp to {$numero}: {$mensaje}" . PHP_EOL;
        
        // Crear directorio si no existe
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        return true;
    } catch (Exception $e) {
        error_log("Error sending WhatsApp: " . $e->getMessage());
        return false;
    }
}

/*Obtiene mensajes de un chat*/
function getMensajesChat($chat_id, $limit = 50) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT id, remitente, contenido, fecha
            FROM mensajes 
            WHERE chat_id = ? 
            ORDER BY fecha ASC 
            LIMIT ?
        ");
        $stmt->execute([$chat_id, $limit]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting messages: " . $e->getMessage());
        return [];
    }
}

/*Marca mensajes como leídos*/
function marcarMensajesComoLeidos($chat_id, $remitente_exclude = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $sql = "UPDATE mensajes SET leido = 1 WHERE chat_id = ?";
        $params = [$chat_id];
        
        if ($remitente_exclude) {
            $sql .= " AND remitente != ?";
            $params[] = $remitente_exclude;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return true;
    } catch (Exception $e) {
        error_log("Error marking messages as read: " . $e->getMessage());
        return false;
    }
}
?>