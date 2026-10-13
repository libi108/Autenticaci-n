<?php
require __DIR__.'/../src/bootstrap.php';
try { $c=google(); $_SESSION['oauth_state']=bin2hex(random_bytes(32)); $_SESSION['oauth_time']=time(); $c->setState($_SESSION['oauth_state']); header('Location: '.$c->createAuthUrl()); }
catch (Throwable $e) { http_response_code(503); echo 'Google no está disponible. Revisa las credenciales OAuth en la configuración.'; }
