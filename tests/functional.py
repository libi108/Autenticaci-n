"""Prueba local con Docker y Mailpit. Crea una cuenta única y la elimina al terminar."""
import http.cookiejar, json, os, re, subprocess, time, urllib.parse, urllib.request, uuid
from pathlib import Path
os.environ.setdefault('DOCKER_CONFIG','/tmp/auth-docker')
root=Path(__file__).resolve().parents[1]
base='http://127.0.0.1:8000'
cookies=http.cookiejar.CookieJar()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
email='test-'+uuid.uuid4().hex+'@example.test'
password='First-secure-password-123'
newpassword='Second-secure-password-456'
checks=0

def check(value,label):
 global checks
 assert value,label
 checks+=1

def get(): return client.open(base).read().decode()
def post(action,**values):
 token=re.search(r'name="csrf" value="([a-f0-9]+)"',get()).group(1)
 values.update(action=action,csrf=token)
 return client.open(base,urllib.parse.urlencode(values).encode()).read().decode()
def sql(statement):
 code="require '/app/src/bootstrap.php'; $q=db()->prepare("+json.dumps(statement)+"); $q->execute(["+json.dumps(email)+"]); echo json_encode($q->fetchAll());"
 p=subprocess.run(['docker','compose','exec','-T','app','php','-r',code],cwd=root,capture_output=True,text=True,check=True)
 return json.loads(p.stdout)
def unlock(): sql('UPDATE usuarios SET ultimo_envio=NULL WHERE email=?')
def code():
 for _ in range(20):
  messages=json.load(urllib.request.urlopen('http://127.0.0.1:8025/api/v1/messages'))['messages']
  for message in messages:
   if any(x['Address']==email for x in message['To']):
    detail=json.load(urllib.request.urlopen('http://127.0.0.1:8025/api/v1/message/'+message['ID']))
    found=re.search(r'Tu código es (\d{6})',detail['Text'])
    if found: return found.group(1)
  time.sleep(.25)
 raise AssertionError('Correo no recibido')
try:
 check('Tu cuenta, en dos pasos' in get(),'page loads')
 check('recibirás un código' in post('register',email=email),'registration sent')
 otp=code()
 check('Sesión autenticada' not in get(),'registration does not authenticate')
 check('Código inválido' in post('verify',code='000000',password=password),'incorrect OTP rejected')
 check('Contraseña guardada' in post('verify',code=otp,password=password),'registration verifies')
 row=sql('SELECT password_hash,cuenta_activa,codigo_verificacion FROM usuarios WHERE email=?')[0]
 check(row['password_hash'].startswith('$2y$') and row['cuenta_activa']==1 and row['codigo_verificacion'] is None,'bcrypt active account and consumed OTP')
 check('Credenciales inválidas' in post('login',email=email,password='wrong-password'),'incorrect password rejected')
 unlock()
 check('completar el acceso' in post('login',email=email,password=password),'login challenges')
 otp=code()
 check('Sesión autenticada' not in get(),'first factor grants no session')
 check('Sesión autenticada' in post('verify',code=otp),'second factor grants session')
 post('logout')
 check('Sesión autenticada' not in get(),'logout')
 unlock()
 post('reset',email=email)
 otp=code()
 check('Contraseña guardada' in post('verify',code=otp,password=newpassword),'reset completes')
 check('Credenciales inválidas' in post('login',email=email,password=password),'old password rejected')
 unlock()
 post('login',email=email,password=newpassword)
 otp=code()
 sql('UPDATE usuarios SET codigo_expiracion=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE email=?')
 check('Código inválido' in post('verify',code=otp),'expired code rejected')
 unlock()
 post('login',email=email,password=newpassword)
 otp=code()
 for _ in range(5): post('verify',code='000000')
 check('Código inválido' in post('verify',code=otp),'five failures block correct OTP')
 unlock()
 post('login',email=email,password=newpassword)
 otp=code()
 check('Sesión autenticada' in post('verify',code=otp),'new password and factor work')
 # Callback sin state válido no autentica.
 post('logout')
 try: client.open(base+'/callback.php?state=fake&code=fake')
 except urllib.error.HTTPError as error: check(error.code==400,'OAuth forged callback rejected')
 check('Sesión autenticada' not in get(),'OAuth failure grants no session')
 print(f'{checks} functional checks passed')
finally:
 sql('DELETE FROM usuarios WHERE email=?')
