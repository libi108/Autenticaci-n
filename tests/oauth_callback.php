<?php
// Ejecuta el callback real en procesos aislados con proveedor y BD simulados.
$root=sys_get_temp_dir().'/oauth-test-'.bin2hex(random_bytes(8));
mkdir($root.'/public',0700,true); mkdir($root.'/src',0700,true);
copy(__DIR__.'/../public/callback.php',$root.'/public/callback.php');
$bootstrap= <<<'STUB'
<?php
function h(string $s): string { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
$mode=getenv('TEST_OAUTH_CASE');
$_SESSION=['oauth_state'=>'expected','oauth_time'=>time()];
$_GET=['state'=>'expected','code'=>'SECRET_AUTH_CODE'];
if ($mode==='state') $_GET['state']='bad';
if ($mode==='expired') $_SESSION['oauth_time']=time()-601;
function google() {
 if (getenv('TEST_OAUTH_CASE')==='configuration') throw new RuntimeException('SECRET_CLIENT_SECRET');
 return new class {
  function fetchAccessTokenWithAuthCode($code) {
   $m=getenv('TEST_OAUTH_CASE');
   if ($m==='network') throw new RuntimeException('SECRET_CLIENT_SECRET');
   if (in_array($m,['invalid_client','invalid_grant','redirect_uri_mismatch','unknown'],true)) return ['error'=>$m,'error_description'=>'SECRET_CLIENT_SECRET'];
   return ['id_token'=>'SECRET_ID_TOKEN'];
  }
  function verifyIdToken($token) {
   if (getenv('TEST_OAUTH_CASE')==='identity') return false;
   return ['email'=>'example@example.test','email_verified'=>true,'sub'=>'subject'];
  }
 };
}
function user($email) {
 $m=getenv('TEST_OAUTH_CASE');
 if ($m==='database') throw new RuntimeException('SECRET_DATABASE_PASSWORD');
 return ['id'=>1,'google_id'=>$m==='account_conflict' ? null : 'subject'];
}
function loginSession($id) {
 if (getenv('TEST_OAUTH_CASE')==='session') throw new RuntimeException('SECRET_SESSION');
 echo 'LOGIN_OK';
}
STUB;
file_put_contents($root.'/src/bootstrap.php',$bootstrap);
$cases=['state'=>'Etapa: state','expired'=>'Etapa: state','configuration'=>'Etapa: configuration','network'=>'Etapa: token','invalid_client'=>'Google rechazó las credenciales','invalid_grant'=>'Google rechazó el código','redirect_uri_mismatch'=>'La redirección enviada','unknown'=>'Etapa: token','identity'=>'Etapa: identity','database'=>'Etapa: database','account_conflict'=>'Etapa: account_conflict','session'=>'Etapa: session','success'=>'LOGIN_OK'];
try {
 foreach ($cases as $mode=>$expected) {
  $env=getenv(); $env['TEST_OAUTH_CASE']=$mode;
  $proc=proc_open([PHP_BINARY,$root.'/public/callback.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
  $out=stream_get_contents($pipes[1]); $log=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $status=proc_close($proc);
  if ($status!==0 || !str_contains($out,$expected) || str_contains($out.$log,'SECRET_')) throw new RuntimeException('Failed OAuth case: '.$mode);
  if ($mode!=='success' && str_contains($out,'LOGIN_OK')) throw new RuntimeException('Failure granted access');
 }
 echo count($cases)." OAuth callback checks passed\n";
} finally {
 unlink($root.'/src/bootstrap.php'); unlink($root.'/public/callback.php'); rmdir($root.'/src'); rmdir($root.'/public'); rmdir($root);
}
