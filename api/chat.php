<?php
require_once '../config.php';
require_once '../includes/funciones.php';

// Habilitar logging de errores más detallado
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Verificar y crear el directorio de logs si no existe
$logDir = dirname(getenv('LOG_PATH') . 'apii_errors.log');
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true); // Crear el directorio si no existe
}

// Configurar headers para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// Función mejorada para logging de errores
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] API Error: $message";
    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context);
    }
    error_log($logMessage, 3, getenv('LOG_PATH') . 'apii_errors.log'); // Cambia el nombre del archivo si lo deseas
}

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    sendJsonResponse(['error' => 'No autorizado'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            handlePostRequest($action);
            break;
        case 'GET':
            handleGetRequest($action);
            break;
        default:
            sendJsonResponse(['error' => 'Método no permitido'], 405);
    }
} catch (Exception $e) {
    logError("Excepción general: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'action' => $action,
        'method' => $method,
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    sendJsonResponse(['error' => 'Error interno del servidor'], 500);
}

/* Maneja requests POST */
function handlePostRequest($action) {
    switch ($action) {
        case 'send':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logError("JSON inválido: " . json_last_error_msg());
                sendJsonResponse(['error' => 'JSON inválido'], 400);
            }
            enviarMensaje($input);
            break;
        default:
            sendJsonResponse(['error' => 'Acción no válida'], 400);
    }
}

/* Maneja requests GET */
function handleGetRequest($action) {
    switch ($action) {
        case 'messages':
            obtenerMensajes();
            break;
        case 'chats':
            obtenerChats();
            break;
        default:
            sendJsonResponse(['error' => 'Acción no válida'], 400);
    }
}

/* Envíar un mensaje */
function enviarMensaje($input) {
    try {
        logError("Iniciando envío de mensaje", ['input' => $input]);
        
        // Validar entrada
        if (!is_array($input)) {
            logError("Datos de entrada no son array");
            sendJsonResponse(['error' => 'Datos inválidos'], 400);
        }
        
        // Validar que chat_id y mensaje estén presentes
        $chat_id = $input['chat_id'] ?? null;
        $mensaje = trim($input['mensaje'] ?? '');
        
        if (is_null($chat_id) || empty($mensaje)) {
            logError("chat_id o mensaje faltante", ['chat_id' => $chat_id, 'mensaje' => $mensaje]);
            sendJsonResponse(['error' => 'chat_id y mensaje son requeridos'], 400);
        }
        
        // Sanitizar entradas
        $chat_id = cleanInput($chat_id);
        $mensaje = cleanInput($mensaje);
        
        $user_role = $_SESSION['user_role'];
        $user_id = $_SESSION['user_id'];
        
        logError("Datos extraídos", [
            'chat_id' => $chat_id,
            'mensaje_length' => strlen($mensaje),
            'user_role' => $user_role,
            'user_id' => $user_id
        ]);
        
        // Validaciones básicas
        if (strlen($mensaje) > 500) {
            sendJsonResponse(['error' => 'El mensaje es demasiado largo (máximo 500 caracteres)'], 400);
        }
        
        // Si es cliente y no tiene chat_id, obtener o crear chat
        if ($user_role === 'cliente' && !$chat_id) {
            logError("Cliente sin chat_id, obteniendo chat");
            $chat_id = getChatParaCliente($user_id);
            if (!$chat_id) {
                logError("No se pudo obtener/crear chat para cliente", ['user_id' => $user_id]);
                sendJsonResponse(['error' => 'No se pudo crear el chat'], 500);
            }
            logError("Chat obtenido/creado", ['chat_id' => $chat_id]);
        }
        
        // Verificar que chat_id sea válido
        if (!$chat_id || !is_numeric($chat_id)) {
            logError("Chat ID inválido", ['chat_id' => $chat_id]);
            sendJsonResponse(['error' => 'ID de chat inválido'], 400);
        }
        
        // Verificar permisos del chat
        if (!verificarPermisoChat($chat_id, $user_id, $user_role)) {
            logError("Sin permisos para chat", ['chat_id' => $chat_id, 'user_id' => $user_id]);
            sendJsonResponse(['error' => 'Sin permisos para este chat'], 403);
        }
        
        // Guardar mensaje del usuario
        $remitente = $user_role === 'cliente' ? 'cliente' : 'resp';
        logError("Guardando mensaje", ['remitente' => $remitente, 'chat_id' => $chat_id]);
        
        $mensaje_id = guardarMensaje($chat_id, $remitente, $mensaje);
        
        if (!$mensaje_id) {
            logError("Error al guardar mensaje en BD");
            sendJsonResponse(['error' => 'Error al guardar el mensaje'], 500);
        }
        
        logError("Mensaje guardado exitosamente", ['mensaje_id' => $mensaje_id]);
        
        $response = [
            'success' => true,
            'mensaje_id' => $mensaje_id,
            'chat_id' => $chat_id,
            'mensaje' => $mensaje,
            'remitente' => $remitente,
            'fecha' => date('Y-m-d H:i:s')
        ];
        
        // Si es cliente, generar respuesta del bot
        if ($user_role === 'cliente') {
            try {
                logError("Generando respuesta del bot");
                $respuesta_bot = generarRespuestaBot($mensaje);
                logError("Respuesta bot generada", ['respuesta' => substr($respuesta_bot, 0, 50) . '...']);
                
                $bot_mensaje_id = guardarMensaje($chat_id, 'bot', $respuesta_bot);
                
                if ($bot_mensaje_id) {
                    $response['bot_response'] = [
                        'mensaje_id' => $bot_mensaje_id,
                        'contenido' => $respuesta_bot,
                        'remitente' => 'bot',
                        'fecha' => date('Y-m-d H:i:s')
                    ];
                    logError("Respuesta bot guardada", ['bot_mensaje_id' => $bot_mensaje_id]);
                } else {
                    logError("Error al guardar respuesta del bot");
                }
            } catch (Exception $e) {
                logError("Error generando respuesta bot: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                // No fallar por esto, continuar sin respuesta bot
            }
        }
        
        logError("Enviando respuesta exitosa");
        sendJsonResponse($response, 200);
    } catch (Exception $e) {
        logError("Error en enviarMensaje: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'input' => $input ?? 'null'
        ]);
        sendJsonResponse(['error' => 'Error interno del servidor'], 500);
    }
}

   /* Obtiene mensajes de un chat */
   function obtenerMensajes() {
       try {
           $chat_id = $_GET['chat_id'] ?? null;
           $user_id = $_SESSION['user_id'];
           $user_role = $_SESSION['user_role'];
           
           if (!$chat_id) {
               // Si es cliente, obtener su chat
               if ($user_role === 'cliente') {
                   $chat_id = getChatParaCliente($user_id);
                   if (!$chat_id) {
                       sendJsonResponse(['error' => 'No se pudo obtener el chat'], 500);
                   }
               } else {
                   sendJsonResponse(['error' => 'chat_id requerido'], 400);
               }
           }
           
           if (!verificarPermisoChat($chat_id, $user_id, $user_role)) {
               sendJsonResponse(['error' => 'Sin permisos para este chat'], 403);
           }
           
           $mensajes = getMensajesChat($chat_id);
           
           // Marcar mensajes como leídos
           marcarMensajesComoLeidos($chat_id, $user_role === 'cliente' ? 'cliente' : 'resp');
           
           sendJsonResponse([
               'success' => true,
               'chat_id' => $chat_id,
               'mensajes' => $mensajes
           ], 200);
       } catch (Exception $e) {
           logError("Error obteniendo mensajes: " . $e->getMessage());
           sendJsonResponse(['error' => 'Error interno del servidor'], 500);
       }
   }
   

/* Obtiene lista de chats solo para responsables */
function obtenerChats() {
    try {
        if ($_SESSION['user_role'] !== 'responsable') {
            sendJsonResponse(['error' => 'Solo disponible para responsables'], 403);
        }
        
        $user_id = $_SESSION['user_id'];
        $chats = obtenerChatsResponsable($user_id);
        
        sendJsonResponse([
            'success' => true,
            'chats' => $chats
        ], 200);
    } catch (Exception $e) {
        logError("Error obteniendo lista de chats: " . $e->getMessage());
        sendJsonResponse(['error' => 'Error interno del servidor'], 500);
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
