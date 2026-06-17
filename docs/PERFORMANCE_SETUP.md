# PerformanceHub — Puesta en marcha (k6 + JMeter con Jenkins en Docker)

Guía para levantar el motor de pruebas en **local**. La app (Laravel + React) **no**
necesita Docker; Docker solo levanta **Jenkins**, que es quien ejecuta k6/JMeter.

## Arquitectura

```
React (front)  ──>  Laravel API  ──>  Jenkins (Docker)  ──>  k6 / JMeter
   Generadores       /performance/run     pipeline           ejecuta script
   history.jsx       /performance/logs     (Jenkinsfile)      publica reporte
                     /performance/analizar  ──>  Gemini (IA)
```

- Los **scripts** se guardan en el repo de GitHub `GH_REPO` (carpeta `history/<proyecto>/`).
- Jenkins descarga el script, lo ejecuta y publica el **reporte HTML** + un **JSON** de métricas.
- El backend lee los builds y manda el JSON a Gemini para el análisis con IA.

## Requisitos

- Docker + Docker Compose
- PHP 8.2+ y Composer (backend Laravel)
- Node 18+ (front React)
- Una cuenta/repo de GitHub para los scripts y un Personal Access Token (PAT)
- Una API key de Google Gemini

## 1) Levantar Jenkins con k6 y JMeter

Desde la raíz del repo `performancehub`:

```bash
docker compose up -d --build
```

La primera vez tarda (descarga JMeter, k6 y plugins). Jenkins queda en
`http://localhost:8080` (igual que `JENKINS_URL` del `.env`).

Contraseña inicial de administrador:

```bash
docker exec performancehub-jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```

En el asistente: instala los *plugins sugeridos* y crea el usuario administrador.
Usa el **mismo usuario** que pusiste en `JENKINS_USER` del `.env` (p. ej. `AMTBHC`).

## 2) Configurar Jenkins

### a) API Token (para `JENKINS_TOKEN`)
`Tu usuario → Security → API Token → Add new token`. Copia el valor a
`JENKINS_TOKEN` en el `.env`.

### b) Credencial de GitHub (para que el pipeline baje los scripts)
`Manage Jenkins → Credentials → System → Global → Add Credentials`
- Kind: **Secret text**
- Secret: tu **PAT de GitHub**
- ID: **`gh-token`**  ← el `Jenkinsfile` lo busca por este ID exacto

### c) Carpeta y jobs
1. Crea una **carpeta** llamada `Contenedor-Proyectos` (New Item → Folder).
   El backend arma la ruta `Contenedor-Proyectos/job/<proyecto>`.
2. Dentro, crea un **Pipeline** por cada proyecto, con el **nombre exacto del proyecto**
   (el mismo que usas en la app).
3. En cada job:
   - **Pipeline → Definition: Pipeline script** y pega el contenido del `Jenkinsfile`
     (o usa *Pipeline script from SCM* apuntando a este repo).
   - Marca **"Trigger builds remotely (e.g., from scripts)"** y pon el token
     `123456789` (es el que envía el backend en `PerformanceController@runJenkins`).
   - Revisa que la variable `GH_REPO` dentro del `Jenkinsfile` apunte a tu repo de scripts.

> Los parámetros (`TOOL`, `SCRIPT_PATH`, `HILOS`, ...) los define el propio `Jenkinsfile`;
> no hace falta crearlos a mano.

## 3) Backend (Laravel)

```bash
composer install
cp .env.example .env          # si aún no tienes .env
php artisan key:generate
php artisan migrate
php artisan serve             # http://localhost:8000
```

Rellena en el `.env`: `GH_REPO`, `GH_TOKEN`, `JENKINS_URL`, `JENKINS_USER`,
`JENKINS_TOKEN`, `GEMINI_API_KEY`.

> Si alguna vez cacheas config (`php artisan config:cache`), recuerda que el código usa
> `env(...)` directo; vuelve a `php artisan config:clear` en desarrollo.

## 4) Front (React)

```bash
cd ../performancehubfront
npm install
npm run dev
```

## 5) Flujo de uso

1. **Generadores**: elige `k6` o `JMeter`, configura la prueba y, para correrla en
   Jenkins, activa **"PARA JENKINS"** (así k6 lee `__ENV.VUS`/`__ENV.DURATION` y JMeter
   usa `__P(...)`). Descarga el script.
2. **Centro de Operaciones → Subir Script**: sube el `.js` (k6) o `.jmx` (JMeter).
3. **Lanzar Jenkins**: ajusta la carga y pulsa *Ejecutar*. La herramienta se detecta por
   la extensión y se envía a Jenkins.
4. **Histórico Logs**: ve el estado, abre el **Reporte** o pide el análisis con **IA**.

## Cómo se mapea cada herramienta

| | k6 | JMeter |
|---|---|---|
| Script | `.js` | `.jmx` |
| Parámetros de carga | `-e VUS`, `-e DURATION` | `-Jp_hilos`, `-Jp_rampup`, `-Jp_steps`, `-Jp_duration` |
| Carpeta del reporte | `Reporte_20k6` | `Reporte_20JMeter` |
| JSON para la IA | `summary.json` | `statistics.json` |

## Notas

- **k6 y umbrales**: si el `threshold` (`p(95)<500`) no se cumple, k6 sale con código ≠ 0 y
  el build sale **rojo**, aunque la prueba haya corrido. El reporte se publica igual.
- **Reportes en iframe**: el plugin *htmlpublisher* aplica una CSP que a veces recorta
  estilos del dashboard de JMeter. El reporte se ve; si quieres estilos completos, ajusta
  la propiedad `hudson.model.DirectoryBrowserSupport.CSP` en Jenkins.
- **Seguridad**: nunca subas el `.env` (ya está en `.gitignore`). Si un token se expone,
  rótalo (GitHub PAT, API token de Jenkins, key de Gemini).
