<?php
require __DIR__.'/../src/bootstrap.php';
try {
 $state=$_SESSION['oauth_state'] ?? ''; $created=$_SESSION['oauth_time'] ?? 0; unset($_SESSION['oauth_state'],$_SESSION['oauth_time']);
 if (!$state || !hash_equals($state,$_GET['state'] ?? '') || time()-$created>600 || empty($_GET['code'])) throw new RuntimeException();
 $c=google(); $token=$c->fetchAccessTokenWithAuthCode($_GET['code']);
 if (isset($token['error']) || empty($token['id_token'])) throw new RuntimeException();
 $claims=$c->verifyIdToken($token['id_token']);
 if (!$claims || empty($claims['email_verified']) || empty($claims['sub']) || !filter_var($claims['email'] ?? '',FILTER_VALIDATE_EMAIL)) throw new RuntimeException();
 $email=strtolower($claims['email']); $u=user($email);
 if ($u) {
  // No vincular cuentas locales automáticamente por coincidencia de correo.
  if (!$u['google_id'] || !hash_equals($u['google_id'],$claims['sub'])) throw new RuntimeException();
 } else {
  $q=db()->prepare('INSERT INTO usuarios(email,google_id,cuenta_activa) VALUES (?,?,1)'); $q->execute([$email,$claims['sub']]); $u=user($email);
 }
 loginSession((int)$u['id']); header('Location: /');
} catch (Throwable $e) { http_response_code(400); echo 'No se pudo completar el acceso con Google. Si ya existe una cuenta local con ese correo, utiliza su contraseña.'; }
