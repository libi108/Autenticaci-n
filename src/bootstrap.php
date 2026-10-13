<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/security.php';
date_default_timezone_set('UTC');
session_set_cookie_params(['httponly'=>true,'secure'=>str_starts_with(getenv('APP_URL') ?: '', 'https://'),'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');
session_start();
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function db(): PDO {
 static $pdo;
 return $pdo ??= new PDO(getenv('DB_DSN'),getenv('DB_USER'),getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function user(string $email): array|false {
 $q=db()->prepare('SELECT * FROM usuarios WHERE email=?'); $q->execute([$email]); return $q->fetch();
}
function rate(string $key): void {
 $key=hash('sha256',$key);
 $q=db()->prepare('INSERT INTO limites(clave,intentos,ventana) VALUES (?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE intentos=IF(ventana < UTC_TIMESTAMP()-INTERVAL 15 MINUTE,1,intentos+1), ventana=IF(ventana < UTC_TIMESTAMP()-INTERVAL 15 MINUTE,UTC_TIMESTAMP(),ventana)'); $q->execute([$key]);
 $q=db()->prepare('SELECT intentos FROM limites WHERE clave=?'); $q->execute([$key]);
 if ((int)$q->fetchColumn()>20) throw new RuntimeException('Demasiados intentos. Espera 15 minutos.');
}
function sendCode(array $u,string $purpose): void {
 $code=otp(); $hash=digest($code);
 $q=db()->prepare('UPDATE usuarios SET codigo_verificacion=?, codigo_expiracion=UTC_TIMESTAMP()+INTERVAL 10 MINUTE, codigo_proposito=?, codigo_intentos=0, ultimo_envio=UTC_TIMESTAMP() WHERE id=? AND (ultimo_envio IS NULL OR ultimo_envio < UTC_TIMESTAMP()-INTERVAL 60 SECOND)');
 $q->execute([$hash,$purpose,$u['id']]);
 if (!$q->rowCount()) throw new RuntimeException('Espera un minuto antes de solicitar otro código.');
 try {
  $m=new PHPMailer\PHPMailer\PHPMailer(true); $m->isSMTP();
  $m->Host=getenv('SMTP_HOST'); $m->Port=(int)getenv('SMTP_PORT');
  $m->SMTPAuth=(getenv('SMTP_USER') ?: '')!==''; $m->Username=getenv('SMTP_USER') ?: ''; $m->Password=getenv('SMTP_PASSWORD') ?: '';
  $enc=getenv('SMTP_ENCRYPTION') ?: 'tls';
  if ($enc==='none') {
   if (!in_array($m->Host,['mail','localhost','127.0.0.1'],true) || $m->SMTPAuth) throw new RuntimeException('SMTP sin TLS solo permitido para pruebas locales sin credenciales.');
   $m->SMTPAutoTLS=false;
  } else { $m->SMTPSecure=$enc; }
  $m->Timeout=15; $m->CharSet='UTF-8'; $m->setFrom(getenv('SMTP_FROM')); $m->addAddress($u['email']);
  $m->Subject='Código de verificación'; $m->Body="Tu código es $code. Caduca en 10 minutos. No lo compartas. Si no solicitaste este acceso, ignora el mensaje."; $m->send();
 } catch (Throwable $e) {
  $q=db()->prepare('UPDATE usuarios SET codigo_verificacion=NULL,codigo_expiracion=NULL,ultimo_envio=NULL WHERE id=? AND codigo_verificacion=?'); $q->execute([$u['id'],$hash]);
  throw new RuntimeException('No se pudo enviar el correo. Revisa la configuración SMTP.');
 }
 $_SESSION['pending']=['id'=>$u['id'],'purpose'=>$purpose];
}
function google(): Google\Client {
 if (!getenv('GOOGLE_CLIENT_ID') || !getenv('GOOGLE_CLIENT_SECRET')) throw new RuntimeException('Configura las credenciales OAuth de Google.');
 $c=new Google\Client(); $c->setClientId(getenv('GOOGLE_CLIENT_ID')); $c->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
 $c->setRedirectUri(rtrim(getenv('APP_URL'),'/').'/callback.php'); $c->addScope('openid'); $c->addScope('email'); return $c;
}
function loginSession(int $id): void { session_regenerate_id(true); unset($_SESSION['pending']); $_SESSION['uid']=$id; $_SESSION['csrf']=bin2hex(random_bytes(32)); }
