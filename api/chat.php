<?php
require_once '../config.php';
require_once '../includes/funciones.php';

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

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
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
            throw new Exception('Método no permitido');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/*Maneja requests POST*/
function handlePostRequest($action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'send':
            enviarMensaje($input);
            break;
        default:
            throw new Exception('Acción no válida');
    }
}

/*Maneja requests GET*/
function handleGetRequest($action) {
    switch ($action) {
        case 'messages':
            obtenerMensajes();
            break;
        case 'chats':
            obtenerChats();
            break;
        default:
            throw new Exception('Acción no válida');
    }
}

/*Envía un mensaje*/
function enviarMensaje($input) {
    $chat_id = $input['chat_id'] ?? null;
    $mensaje = $input['mensaje'] ?? '';
    $user_role = $_SESSION['user_role'];
    $user_id = $_SESSION['user_id'];
    
    if (empty($mensaje)) {
        throw new Exception('El mensaje no puede estar vacío');
    }
    
    // Si es cliente y no tiene chat_id, obtener o crear chat
    if ($user_role === 'cliente' && !$chat_id) {
        $chat_id = getChatParaCliente($user_id);
        if (!$chat_id) {
            throw new Exception('No se pudo crear el chat');
        }
    }
    
    // Verificar permisos del chat
    if (!verificarPermisoChat($chat_id, $user_id, $user_role)) {
        throw new Exception('Sin permisos para este chat');
    }
    
    // Guardar mensaje del usuario
    $remitente = $user_role === 'cliente' ? 'cliente' : 'resp';
    $mensaje_id = guardarMensaje($chat_id, $remitente, $mensaje);
    
    if (!$mensaje_id) {
        throw new Exception('Error al guardar el mensaje');
    }
    
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
        $respuesta_bot = generarRespuestaBot($mensaje);
        $bot_mensaje_id = guardarMensaje($chat_id, 'bot', $respuesta_bot);
        
        if ($bot_mensaje_id) {
            $response['bot_response'] = [
                'mensaje_id' => $bot_mensaje_id,
                'contenido' => $respuesta_bot,
                'remitente' => 'bot',
                'fecha' => date('Y-m-d H:i:s')
            ];
            
            // Simular envío por WhatsApp (opcional)
            // sendWhatsApp('123456789', $respuesta_bot);
        }
    }
    
    echo json_encode($response);
}

/*Obtiene mensajes de un chat*/
function obtenerMensajes() {
    $chat_id = $_GET['chat_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    
    if (!$chat_id) {
        // Si es cliente, obtener su chat
        if ($user_role === 'cliente') {
            $chat_id = getChatParaCliente($user_id);
        } else {
            throw new Exception('chat_id requerido');
        }
    }
    
    if (!$chat_id || !verificarPermisoChat($chat_id, $user_id, $user_role)) {
        throw new Exception('Sin permisos para este chat');
    }
    
    $mensajes = getMensajesChat($chat_id);
    
    // Marcar mensajes como leídos
    marcarMensajesComoLeidos($chat_id, $user_role === 'cliente' ? 'cliente' : 'resp');
    
    echo json_encode([
        'success' => true,
        'chat_id' => $chat_id,
        'mensajes' => $mensajes
    ]);
}

/*Obtiene lista de chats solo para responsables*/
function obtenerChats() {
    if ($_SESSION['user_role'] !== 'responsable') {
        throw new Exception('Solo disponible para responsables');
    }
    
    $user_id = $_SESSION['user_id'];
    
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
        $chats = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'chats' => $chats
        ]);
    } catch (Exception $e) {
        throw new Exception('Error obteniendo chats: ' . $e->getMessage());
    }
}

/*Verifica si el usuario tiene permisos para acceder al chat*/
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
        return false;
    }
}
?>