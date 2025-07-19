<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        h1 { margin-bottom: 30px; }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            background: #fff;
            color: #333;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenido al Sistema de Chat</h1>
        
        <?php if (isLoggedIn()): ?>
            <p>¡Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
            
            <?php if (hasRole('cliente')): ?>
                <a href="cliente/chat.php" class="btn">Ir al Chat</a>
            <?php else: ?>
                <a href="responsable/panel.php" class="btn">Panel de Responsable</a>
            <?php endif; ?>
            
            <a href="logout.php" class="btn">Cerrar Sesión</a>
            
        <?php else: ?>
            <p>Selecciona tu tipo de acceso:</p>
            <a href="login.php?role=cliente" class="btn">Soy Cliente</a>
            <a href="login.php?role=responsable" class="btn">Soy Responsable</a>
        <?php endif; ?>
    </div>
</body>
</html>