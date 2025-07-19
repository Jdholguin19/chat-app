<?php
require_once 'config.php';

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    if (hasRole('cliente')) {
        redirect('cliente/chat.php');
    } else {
        redirect('responsable/panel.php');
    }
}

$error = '';
$role = $_GET['role'] ?? 'cliente';

if ($_POST) {
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];
    $ip = getUserIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Buscar usuario
            $stmt = $db->prepare("SELECT id, nombre, email, pass, role FROM users WHERE email = ? AND role = ?");
            $stmt->execute([$email, $role]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['pass'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Registrar acceso exitoso
                logAccess($user['id'], $email, 'login_exitoso', $ip, $user_agent);
                
                // Redirigir según rol
                if ($role === 'cliente') {
                    redirect('cliente/chat.php');
                } else {
                    redirect('responsable/panel.php');
                }
            } else {
                // Login fallido
                logAccess(null, $email, 'login_fallido', $ip, $user_agent);
                $error = 'Email o contraseña incorrectos';
            }
        } catch (Exception $e) {
            $error = 'Error en el sistema. Intenta nuevamente.';
            error_log("Login error: " . $e->getMessage());
        }
    } else {
        $error = 'Por favor completa todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo ucfirst($role); ?></title>
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2>Acceso - <?php echo ucfirst($role); ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
            </form>
            
            <div class="demo-credentials">
                <h4>Credenciales de Prueba:</h4>
                <?php if ($role === 'cliente'): ?>
                    <p><strong>Cliente 1:</strong> cliente1@costasol.com</p>
                    <p><strong>Cliente 2:</strong> cliente2@costasol.com</p>
                <?php else: ?>
                    <p><strong>Responsable 1:</strong> resp1@costasol.com</p>
                    <p><strong>Responsable 2:</strong> resp2@costasol.com</p>
                <?php endif; ?>
                <p><em>Contraseña para todos: password</em></p>
            </div>
            
            <div class="switch-role">
                <a href="login.php?role=<?php echo $role === 'cliente' ? 'responsable' : 'cliente'; ?>">
                    Soy <?php echo $role === 'cliente' ? 'Responsable' : 'Cliente'; ?>
                </a>
                <a href="index.php">Volver al Inicio</a>
            </div>
        </div>
    </div>
</body>
</html>
