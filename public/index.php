<?php
require __DIR__.'/../src/bootstrap.php';
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if (!hash_equals($_SESSION['csrf'],$_POST['csrf'] ?? '')) throw new RuntimeException('Formulario inválido.');
  $action=$_POST['action'] ?? '';
  if ($action==='logout') { $_SESSION=[]; session_regenerate_id(true); header('Location: /'); exit; }
  rate('ip:'.($_SERVER['REMOTE_ADDR'] ?? ''));
  if (in_array($action,['register','login','reset'],true)) {
   $email=strtolower(trim($_POST['email'] ?? ''));
   if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254) throw new RuntimeException('Correo inválido.');
   rate('email:'.$email); $u=user($email);
   if ($action==='register') {
    if (!$u) { $q=db()->prepare('INSERT INTO usuarios(email) VALUES (?)'); $q->execute([$email]); $u=user($email); }
    if (!$u['cuenta_activa']) sendCode($u,'register');
    $message='Si la cuenta permite el registro, recibirás un código.';
   } elseif ($action==='reset') {
    if ($u && $u['cuenta_activa'] && $u['password_hash']) sendCode($u,'reset');
    $message='Si existe una cuenta local activa, recibirás un código.';
   } else {
    $ok=password_verify($_POST['password'] ?? '',$u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
    if (!$u || !$u['cuenta_activa'] || !$u['password_hash'] || !$ok) throw new RuntimeException('Credenciales inválidas.');
    sendCode($u,'login'); $message='Introduce el código enviado a tu correo para completar el acceso.';
   }
  } elseif ($action==='verify') {
   $pending=$_SESSION['pending'] ?? null;
   if (!$pending) throw new RuntimeException('Solicita primero un código.');
   db()->beginTransaction();
   try {
    $q=db()->prepare('SELECT * FROM usuarios WHERE id=? FOR UPDATE'); $q->execute([$pending['id']]); $u=$q->fetch();
    if (!$u || $u['codigo_proposito']!==$pending['purpose'] || !validCode($_POST['code'] ?? '',$u['codigo_verificacion'],$u['codigo_expiracion'],(int)$u['codigo_intentos'])) {
     $q=db()->prepare('UPDATE usuarios SET codigo_intentos=codigo_intentos+1 WHERE id=?'); $q->execute([$pending['id']]); db()->commit(); throw new RuntimeException('Código inválido, agotado o caducado.');
    }
    if ($pending['purpose']!=='login') {
     $password=$_POST['password'] ?? '';
     if (!passwordValid($password)) throw new RuntimeException('La contraseña debe tener entre 12 y 72 bytes.');
     $q=db()->prepare('UPDATE usuarios SET password_hash=?,cuenta_activa=1 WHERE id=?'); $q->execute([password_hash($password,PASSWORD_BCRYPT),$u['id']]);
    }
    $q=db()->prepare('UPDATE usuarios SET codigo_verificacion=NULL,codigo_expiracion=NULL,codigo_proposito=NULL WHERE id=?'); $q->execute([$u['id']]); db()->commit();
    if ($pending['purpose']==='login') { loginSession((int)$u['id']); $message='Acceso completado con dos pasos.'; }
    else { unset($_SESSION['pending']); $message='Contraseña guardada. Inicia sesión con tu nueva contraseña.'; }
   } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
  }
 } catch (RuntimeException $e) { $message=$e instanceof PDOException ? 'No se pudo procesar la solicitud.' : $e->getMessage(); }
 catch (Throwable $e) { $message='Error de configuración. Revisa el servidor y la base de datos.'; }
}
function csrf(): void { echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
?>
<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Autenticación en dos pasos</title><link rel="stylesheet" href="/style.css"><main>
<header><span>PRÁCTICA · SEGURIDAD WEB</span><h1>Tu cuenta, en dos pasos</h1><p>Contraseña y un código temporal enviado a tu correo.</p></header>
<?php if ($message): ?><p role="status" class="notice"><?=h($message)?></p><?php endif; ?>
<?php if (isset($_SESSION['uid'])): ?><section><h2>Sesión autenticada</h2><p>Has ingresado al área protegida.</p><form method="post"><?php csrf(); ?><button name="action" value="logout">Cerrar sesión</button></form></section>
<?php else: ?>
<div class="grid"><section><h2>Iniciar sesión</h2><form method="post"><?php csrf(); ?><label>Correo<input type="email" name="email" autocomplete="username" required></label><label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label><button name="action" value="login">Continuar con correo</button></form><a class="google" href="/google.php"><img src="/google-logo.png" alt="" width="20" height="20">Iniciar sesión con Google</a><p class="small">Google usa un flujo federado; no solicita una contraseña local. El segundo factor de Google depende de tu cuenta.</p></section>
<section><h2>Crear o recuperar cuenta</h2><p>Primero verifica tu correo; después establece tu contraseña.</p><form method="post"><?php csrf(); ?><label>Correo<input type="email" name="email" autocomplete="email" required></label><button name="action" value="register">Registrarme</button><button class="secondary" name="action" value="reset">Restablecer contraseña</button></form></section></div>
<?php if (isset($_SESSION['pending'])): ?><section><h2>Verifica tu correo</h2><p>El código dura 10 minutos y admite hasta 5 intentos.</p><form method="post"><?php csrf(); ?><label>Código de 6 dígitos<input name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></label><?php if ($_SESSION['pending']['purpose']!=='login'): ?><label>Nueva contraseña (12–72 bytes)<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><?php endif; ?><button name="action" value="verify">Verificar y continuar</button></form></section><?php endif; ?>
<?php endif; ?><footer>Nunca compartas tus códigos. Revisa el dominio antes de iniciar sesión.</footer></main></html>
