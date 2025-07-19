<?php
require_once 'config.php';

echo "<h2>Verificación y Configuración de Tabla mensajes_pred</h2>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar si la tabla existe
    $stmt = $db->prepare("SHOW TABLES LIKE 'mensajes_pred'");
    $stmt->execute();
    $tabla_existe = $stmt->fetch();
    
    if (!$tabla_existe) {
        echo "<p style='color: orange;'>⚠️ Tabla mensajes_pred no encontrada. Creando...</p>";
        
        // Crear la tabla
        $sql = "
            CREATE TABLE `mensajes_pred` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tipo` enum('bot','auto') NOT NULL DEFAULT 'bot',
                `palabras_clave` text NOT NULL,
                `texto` text NOT NULL,
                `orden` int(11) NOT NULL DEFAULT 0,
                `activo` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tipo_orden` (`tipo`, `orden`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $db->exec($sql);
        echo "<p style='color: green;'>✅ Tabla mensajes_pred creada exitosamente</p>";
        
        // Insertar mensajes de ejemplo
        $mensajes_ejemplo = [
            ['bot', 'hola,hi,buenos días,buenas tardes,buenas noches,saludos', '¡Hola! ¿En qué puedo ayudarte hoy?', 1],
            ['bot', 'precio,costo,valor,cuanto cuesta,tarifas,presupuesto', 'Te ayudo con información sobre precios. ¿Qué producto o servicio te interesa?', 2],
            ['bot', 'horario,hora,cuando abren,horarios,atención', 'Nuestros horarios de atención son de lunes a viernes de 9:00 AM a 6:00 PM.', 3],
            ['bot', 'contacto,teléfono,email,dirección,ubicación', 'Puedes contactarnos al teléfono 123-456-7890 o al email info@empresa.com', 4],
            ['bot', 'ayuda,help,soporte,asistencia', 'Estoy aquí para ayudarte. ¿Qué necesitas saber?', 5],
            ['bot', 'gracias,thank you,muchas gracias,agradezco', '¡De nada! ¿Hay algo más en lo que pueda ayudarte?', 6],
            ['bot', 'adios,bye,hasta luego,chao,nos vemos', '¡Hasta luego! Que tengas un excelente día.', 7],
            ['bot', 'problema,error,fallo,no funciona', 'Lamento escuchar que tienes un problema. ¿Podrías describirme qué está sucediendo?', 8],
            ['bot', 'producto,servicio,oferta,promoción', '¿Te interesa conocer más sobre nuestros productos y servicios? Te puedo ayudar con eso.', 9],
            ['bot', 'información,info,detalles,más datos', '¿Sobre qué te gustaría obtener más información? Estoy aquí para ayudarte.', 10]
        ];
        
        $stmt = $db->prepare("INSERT INTO mensajes_pred (tipo, palabras_clave, texto, orden) VALUES (?, ?, ?, ?)");
        foreach ($mensajes_ejemplo as $mensaje) {
            $stmt->execute($mensaje);
        }
        
        echo "<p style='color: green;'>✅ " . count($mensajes_ejemplo) . " mensajes de ejemplo insertados</p>";
        
    } else {
        echo "<p style='color: green;'>✅ Tabla mensajes_pred ya existe</p>";
    }
    
    // Mostrar contenido actual de la tabla
    $stmt = $db->prepare("SELECT * FROM mensajes_pred ORDER BY orden ASC");
    $stmt->execute();
    $mensajes = $stmt->fetchAll();
    
    echo "<h3>Contenido actual de la tabla (". count($mensajes) ." registros):</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Tipo</th><th>Palabras Clave</th><th>Texto Respuesta</th><th>Orden</th><th>Activo</th></tr>";
    
    foreach ($mensajes as $mensaje) {
        echo "<tr>";
        echo "<td>{$mensaje['id']}</td>";
        echo "<td>{$mensaje['tipo']}</td>";
        echo "<td style='max-width: 200px;'>" . htmlspecialchars($mensaje['palabras_clave']) . "</td>";
        echo "<td style='max-width: 300px;'>" . htmlspecialchars($mensaje['texto']) . "</td>";
        echo "<td>{$mensaje['orden']}</td>";
        echo "<td>" . ($mensaje['activo'] ? 'Sí' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar conexión a la base de datos
    echo "<h3>Estado de la Base de Datos:</h3>";
    echo "<p style='color: green;'>✅ Conexión exitosa a la base de datos</p>";
    echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
    echo "<p><strong>Base de datos:</strong> " . DB_NAME . "</p>";
    
    // Probar la función generarRespuestaBot
    echo "<h3>Prueba de la función Bot:</h3>";
    require_once 'includes/funciones.php';
    
    $mensajes_prueba = ['hola', 'precio', 'horario', 'ayuda', 'gracias', 'mensaje sin coincidencia'];
    
    foreach ($mensajes_prueba as $msg) {
        $respuesta = generarRespuestaBot($msg);
        echo "<p><strong>Mensaje:</strong> '$msg' <br><strong>Respuesta:</strong> " . htmlspecialchars($respuesta) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Volver al inicio</a></p>";
?>