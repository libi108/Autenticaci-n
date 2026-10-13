# Práctica: autenticación en dos pasos

PHP 8.3, MariaDB, PHPMailer y Google OAuth 2.0. Se implementa la opción A (correo); SMS/WhatsApp son opcionales y no se incluyen.

## Ejecutar

1. Copia `.env.example` a `.env` y sustituye ambas contraseñas de base de datos por valores aleatorios distintos. No publiques `.env`.
2. Ejecuta `docker compose up --build -d` desde el repositorio.
3. Abre `http://localhost:8000`. El buzón **local de pruebas** está en `http://localhost:8025`; no es entrega SMTP externa.
4. Registra un correo, consulta el código en el buzón y establece una contraseña de 12–72 bytes. Inicia sesión con esa contraseña y verifica el nuevo código. Espera 60 segundos entre emisiones para la misma cuenta.
5. Prueba recuperación: solicita un código, establece una contraseña nueva y comprueba que la anterior ya no funciona.
6. Ejecuta `docker compose exec app composer test`.

Si el entorno no permite escribir la configuración de Docker en el directorio del usuario, antepone `DOCKER_CONFIG=/tmp/auth-docker` a los comandos Docker.

## Correo SMTP real

En `.env`, configura `SMTP_HOST`, `SMTP_PORT` (587 para STARTTLS o 465 para SMTPS), `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM` y `SMTP_ENCRYPTION=tls` o `ssl`. Usa una contraseña de aplicación del proveedor cuando corresponda. Reinicia con `docker compose up -d --force-recreate app`. La opción `none` está restringida a servidores locales sin autenticación; no se deshabilita la verificación de certificados.

## Google

Crea una pantalla de consentimiento y un cliente OAuth de tipo aplicación web en GCP. Autoriza exactamente `http://localhost:8000/callback.php` como redirección (o la URL HTTPS de tu despliegue). Configura `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `APP_URL`; recrea el contenedor app. Añade usuarios de prueba si la pantalla está en modo de pruebas.

Se verifica el ID token y el correo confirmado, y se valida `state` con vencimiento. Las cuentas Google no necesitan contraseña local. Para evitar vinculación indebida, una cuenta local existente no se convierte automáticamente en cuenta Google por compartir correo. Usa un correo distinto para demostrar ambos flujos. La autenticación Google es federada: no garantiza por sí sola que el usuario tenga MFA habilitado en Google. El botón usa el logotipo de Google y el texto de acceso en español; el flujo se implementa mediante el cliente OAuth del backend.

## Estructura y controles

- `database/schema.sql`: tabla `usuarios` con los campos de la práctica y tabla de límites persistentes.
- `src/bootstrap.php`: PDO con consultas preparadas, PHPMailer, límite de solicitudes y cliente Google.
- `public/index.php`: registro, acceso, OTP y recuperación; contraseña elegida solo después de verificar el código en el backend.
- Códigos de seis dígitos generados con `random_int`, almacenados mediante SHA-256; duran diez minutos, máximo cinco intentos y consumo con bloqueo transaccional. Se permite una emisión por minuto.
- Sesión regenerada al autenticar; cookies HttpOnly y SameSite; Secure cuando APP_URL es HTTPS; formularios con CSRF.
- Contraseñas almacenadas con `password_hash(..., PASSWORD_BCRYPT)` y comprobadas con `password_verify`. Se rechazan contraseñas superiores a 72 bytes para evitar truncamiento de bcrypt.

Este es un proyecto de laboratorio. Para producción utiliza HTTPS, un servidor web adecuado (el servidor PHP incluido es de desarrollo), gestión de secretos, monitoreo y límites en el perímetro. Las respuestas de registro son genéricas, pero el formulario pendiente y la temporización aún pueden revelar diferencias entre cuentas. Los hashes de OTP de baja entropía requieren proteger el acceso a la base de datos. No se incluyen llaves WebAuthn, recuperación con códigos de respaldo ni vinculación explícita de cuentas.

## Reporte

`docs/reporte.md` contiene arquitectura, respuestas conceptuales y una lista de evidencias para completar. No inventa capturas de GCP ni resultados de SMTP externo o Google. Se incluye también `docs/reporte.pdf` como borrador. Exporta el documento actualizado a PDF después de incorporar tus evidencias reales (por ejemplo con la opción imprimir/exportar PDF de tu editor). No incluyas secretos ni códigos aún vigentes en capturas.

Prueba funcional local (Python 3): `python tests/functional.py`. Usa exclusivamente la instancia de laboratorio con Mailpit; genera y elimina una cuenta propia y ajusta caducidades para probar casos negativos. Los límites persistentes pueden impedir repetirla en los siguientes quince minutos.
