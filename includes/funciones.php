<?php
// Funciones auxiliares para el chat

/* Obtiene el responsable con menos chats activos */
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

/* Crea un nuevo chat y asigna un responsable */
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error creating chat: " . $e->getMessage());
        return null;
    }
}

/* Obtiene o crea un chat para el cliente */
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

/* Guarda un mensaje en la base de datos */
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

/* Genera respuesta automática del bot usando la tabla mensajes_pred */
function generarRespuestaBot($mensaje) {
    try {
        error_log("Bot: Procesando mensaje: " . substr($mensaje, 0, 50));
        
        $db = Database::getInstance()->getConnection();
        $mensaje_lower = strtolower(trim($mensaje));
        
        // Verificar que la tabla mensajes_pred existe
        $stmt = $db->prepare("SHOW TABLES LIKE 'mensajes_pred'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            error_log("Bot: Tabla mensajes_pred no existe");
            return "Gracias por contactarnos. Un responsable te atenderá pronto.";
        }
        
        error_log("Bot: Tabla mensajes_pred encontrada");
        
        // Obtener todas las respuestas predefinidas ordenadas por prioridad
        $stmt = $db->prepare("
            SELECT palabras_clave, texto, orden
            FROM mensajes_pred 
            WHERE tipo = 'bot' 
            ORDER BY orden ASC
        ");
        $stmt->execute();
        $respuestas = $stmt->fetchAll();
        
        error_log("Bot: Encontradas " . count($respuestas) . " respuestas predefinidas");
        
        if (empty($respuestas)) {
            error_log("Bot: No hay respuestas predefinidas en la tabla");
            return "Gracias por tu mensaje. Un responsable se pondrá en contacto contigo pronto.";
        }
        
        // Buscar coincidencias
        foreach ($respuestas as $respuesta) {
            $palabras_clave = explode(',', strtolower($respuesta['palabras_clave']));
            
            error_log("Bot: Verificando palabras clave: " . $respuesta['palabras_clave']);
            
            foreach ($palabras_clave as $palabra_clave) {
                $palabra_clave = trim($palabra_clave);
                if (!empty($palabra_clave) && strpos($mensaje_lower, $palabra_clave) !== false) {
                    error_log("Bot: Coincidencia encontrada con: " . $palabra_clave);
                    return $respuesta['texto'];
                }
            }
        }
        
        // Si no encuentra coincidencias, respuesta por defecto
        error_log("Bot: No se encontraron coincidencias, usando respuesta por defecto");
        return "Gracias por tu mensaje. Un responsable se pondrá en contacto contigo pronto.";
        
    } catch (Exception $e) {
        error_log("Bot Error: " . $e->getMessage());
        error_log("Bot Stack trace: " . $e->getTraceAsString());
        return "Gracias por contactarnos. Un responsable te atenderá pronto.";
    }
}

   /* Obtiene mensajes de un chat */
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
           
           return $stmt->fetchAll(PDO::FETCH_ASSOC); // Asegúrate de que devuelva un array asociativo
       } catch (Exception $e) {
           error_log("Error getting messages: " . $e->getMessage());
           return [];
       }
   }
   

/* Marca mensajes como leídos */
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

/* Obtiene lista de chats solo para responsables */
function obtenerChatsResponsable($user_id) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                c.id,
                c.abierto,
                c.updated_at,
                u.nombre as cliente_nombre,
                u.email as cliente_email,
                (SELECT COUNT(*) FROM mensajes m WHERE m.chat_id = c.id AND m.remitente != 'resp' AND m.leido = 0) as no_leidos,
                (SELECT contenido FROM mensajes m WHERE m.chat_id = c.id ORDER BY m.fecha DESC LIMIT 1) as ultimo_mensaje,
                (SELECT fecha FROM mensajes m WHERE m.chat_id = c.id ORDER BY m.fecha DESC LIMIT 1) as fecha_ultimo_mensaje
            FROM chats c
            INNER JOIN users u ON c.cliente_id = u.id
            WHERE c.responsable_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error obteniendo chats: " . $e->getMessage());
        return [];
    }
}

/* Verifica si el usuario tiene permisos para acceder al chat */
function verificarPermisoChat($chat_id, $user_id, $user_role) {
    try {
        $db = Database::getInstance()->getConnection();
        
        if ($user_role === 'cliente') {
            $stmt = $db->prepare("SELECT id FROM chats WHERE id = ? AND cliente_id = ?");
        } else {
            $stmt = $db->prepare("SELECT id FROM chats WHERE id = ? AND responsable_id = ?");
        }
        
        $stmt->execute([$chat_id, $user_id]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        logError("Error verificando permisos: " . $e->getMessage());
        return false;
    }
}
?>
