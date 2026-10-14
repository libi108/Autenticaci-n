<?php
require __DIR__.'/../src/bootstrap.php';
$stage = 'state';
$reason = '';
try {
 $state=$_SESSION['oauth_state'] ?? ''; $created=$_SESSION['oauth_time'] ?? 0;
 unset($_SESSION['oauth_state'],$_SESSION['oauth_time']);
 if (!$state || !hash_equals($state,$_GET['state'] ?? '') || time()-$created>600 || empty($_GET['code'])) throw new RuntimeException();
 $stage = 'configuration';
 $c=google();
 $stage = 'token';
 $token=$c->fetchAccessTokenWithAuthCode($_GET['code']);
 if (isset($token['error']) || empty($token['id_token'])) {
  // Solo códigos conocidos: nunca registrar el token, código o descripción externa.
  if (in_array($token['error'] ?? '', ['invalid_client','invalid_grant','redirect_uri_mismatch'], true)) $reason=$token['error'];
  throw new RuntimeException();
 }
 $stage = 'identity';
 $claims=$c->verifyIdToken($token['id_token']);
 if (!$claims || empty($claims['email_verified']) || empty($claims['sub']) || !filter_var($claims['email'] ?? '',FILTER_VALIDATE_EMAIL)) throw new RuntimeException();
 $stage = 'database';
 $email=strtolower($claims['email']); $u=user($email);
 if ($u) {
  // No vincular cuentas locales automáticamente por coincidencia de correo.
  if (!$u['google_id'] || !hash_equals($u['google_id'],$claims['sub'])) {
   $stage = 'account_conflict';
   throw new RuntimeException();
  }
 } else {
  $q=db()->prepare('INSERT INTO usuarios(email,google_id,cuenta_activa) VALUES (?,?,1)'); $q->execute([$email,$claims['sub']]); $u=user($email);
 }
 $stage = 'session';
 loginSession((int)$u['id']); header('Location: /');
} catch (Throwable $e) {
 $reference = bin2hex(random_bytes(4));
 // La excepción puede contener datos sensibles: registrar solo su clase y etapa.
 error_log('Google OAuth failure reference='.$reference.' stage='.$stage.' reason='.($reason ?: 'unspecified').' exception='.get_class($e));
 $messages = [
  'state'=>'La solicitud caducó o no coincide con la sesión. Inicia otra vez desde la página principal, en la misma pestaña y con el mismo dominio. No recargues el callback.',
  'configuration'=>'Faltan las credenciales OAuth en el proceso de la aplicación. Revisa .env y recrea el contenedor app.',
  'token'=>'No se pudo canjear el código con Google. Comprueba que el ID y el secreto pertenecen al mismo cliente OAuth, y revisa la conectividad HTTPS del contenedor.',
  'identity'=>'No se pudo verificar la identidad de Google. Revisa la conectividad HTTPS para descargar sus certificados y que el correo esté verificado.',
  'database'=>'No se pudo consultar o guardar la cuenta en MariaDB. Revisa la conexión y el esquema de la tabla usuarios.',
  'account_conflict'=>'Ya existe una cuenta local o vinculada a otra identidad con ese correo. Utiliza su contraseña; no se vinculan cuentas automáticamente.',
  'session'=>'No se pudo establecer la sesión. Revisa la configuración de sesiones de PHP.',
 ];
 if ($reason==='invalid_client') $messages['token']='Google rechazó las credenciales del cliente OAuth. Comprueba el ID y el secreto actual del mismo cliente y recrea el contenedor app.';
 if ($reason==='invalid_grant') $messages['token']='Google rechazó el código de autorización: puede haber caducado o haberse usado. Inicia un nuevo acceso desde la página principal y comprueba la redirección configurada.';
 if ($reason==='redirect_uri_mismatch') $messages['token']='La redirección enviada al canjear el código no coincide con la autorizada en Google Cloud. Revisa APP_URL y la ruta /callback.php.';
 http_response_code($stage==='account_conflict' ? 409 : (in_array($stage,['state','token','identity'],true) ? 400 : 503));
 header('Content-Type: text/html; charset=utf-8');
 echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso con Google</title><link rel="stylesheet" href="/style.css"><main><section><h1>No se pudo completar el acceso con Google</h1><p>'.h($messages[$stage]).'</p><p>Referencia: '.h($reference).' · Etapa: '.h($stage).'</p><a class="google" href="/">Volver a iniciar sesión</a></section></main></html>';
}
