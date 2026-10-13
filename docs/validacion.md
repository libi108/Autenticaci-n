# Validación en este entorno

- Imagen PHP 8.3 compilada con PDO MySQL y dependencias Composer del lockfile.
- MariaDB 11.4 y Mailpit iniciados.
- 11 comprobaciones de seguridad y 18 comprobaciones HTTP/BD/correo local completadas.
- Comprobación de sintaxis de todos los archivos PHP sin errores.
- Composer no reportó vulnerabilidades conocidas en las dependencias al resolverlas.
- Google: comprobado rechazo de callback sin estado válido. Acceso real no ejecutado: faltan credenciales del equipo.
- SMTP externo no ejecutado: se usó Mailpit; faltan credenciales del proveedor.

Para compilar en la nube con su proxy TLS, se pasó el bundle de CA del entorno como secreto de BuildKit (no se deshabilitó TLS):

```sh
DOCKER_CONFIG=/tmp/auth-docker docker build --build-arg HTTP_PROXY --build-arg HTTPS_PROXY --build-arg NO_PROXY --secret id=cloud_ca,src=/etc/ssl/certs/ca-certificates.crt -t autenticaci-n-app .
DOCKER_CONFIG=/tmp/auth-docker docker compose up -d --no-build
```

No se enviaron archivos a GitHub. El reporte PDF es un borrador; faltan nombres, capturas y evidencias externas.
