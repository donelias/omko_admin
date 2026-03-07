# Guía de Despliegue en Digital Ocean App Platform

## Pasos para desplegar Omko Admin:

### 1. **Conectar GitHub a Digital Ocean**
- Ve a https://cloud.digitalocean.com/apps
- Haz clic en "Create App"
- Conecta tu repositorio GitHub: `donelias/omko_admin`
- Selecciona la rama: `develop`
- Digital Ocean detectará `app.yaml` automáticamente

### 2. **Configurar Variables de Entorno**
En Digital Ocean, establece estas variables:

#### Base de Datos (usar DB Managed por Digital Ocean)
```
DB_HOST=db-mysql-xxx.ondigitalocean.com
DB_DATABASE=omko_admin
DB_USERNAME=root
DB_PASSWORD=tu-contraseña-generada
```

#### Aplicación
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:CLAVE-GENERADA
APP_URL=https://tu-dominio.com
```

#### Cache y Sesiones
```
CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 3. **Generar APP_KEY**
Ejecuta localmente:
```bash
php artisan key:generate --show
```
Copia el valor y pégalo en `APP_KEY` en Digital Ocean

### 4. **Crear Base de Datos**
- Digital Ocean creará automáticamente una BD MySQL
- Los valores de conexión aparecerán en las variables de entorno

### 5. **Ejecutar Migraciones**
El archivo `Procfile` ejecutará automáticamente:
```
php artisan migrate --force
```
en el evento "release" antes de cada despliegue

### 6. **Configurar Dominio**
- Ve a la App en Digital Ocean
- Agregar dominio personalizado (o usar el subdominio de DO)
- Configurar certificado SSL (automático)

### 7. **Monitorear el Despliegue**
- En Digital Ocean, ve a la pestaña "Deployments"
- Verifica los logs en "Runtime Logs"
- Si hay errores, revisa los logs

## Archivos Importantes

- `app.yaml` - Configuración de App Platform
- `Procfile` - Comandos de ejecución
- `.env.production` - Variables de ejemplo

## Notas

⚠️ **IMPORTANTE**: 
- Nunca commits credenciales reales en el repositorio
- Usa variables de entorno de Digital Ocean para secretos
- Revisa los logs regularmente en el dashboard

✅ **Después del primer deploy:**
- Los futuros pushes a `develop` desplegarán automáticamente
- Las migraciones se ejecutarán automáticamente

