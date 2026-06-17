<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerformanceController extends Controller
{
    // MÉTODO 1: Solo guarda el script en el repositorio
  public function saveScript(Request $request)
{
    try {

        $project = preg_replace('/[^A-Za-z0-9_\-]/', '_', $request->input('project'));

        if (!$request->hasFile('file')) {
            return response()->json([
                'error' => 'File not received'
            ], 400);
        }

        $file = $request->file('file');

        $filename = $file->getClientOriginalName();
        $content = file_get_contents($file->getRealPath());

        $timestamp = now()->format('Y-m-d_H-i-s');

        $path = "history/{$project}/{$timestamp}_{$filename}";

        $githubUrl = "https://api.github.com/repos/" . env('GH_REPO') . "/contents/{$path}";

        $response = Http::withHeaders([
            'User-Agent' => 'Laravel-App'
        ])->withToken(env('GH_TOKEN'))->put($githubUrl, [
            'message' => "Save script: {$filename} for {$project}",
            'content' => base64_encode($content),
        ]);

        if ($response->failed()) {
            return response()->json([
                'error' => $response->body()
            ], 500);
        }

        return response()->json([
            'status' => 'saved',
            'path' => $path
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'error' => $e->getMessage()
        ], 500);

    }
}

    // MÉTODO 2: Lanza una ejecución de un script que YA existe en GitHub
public function runJenkins(Request $request)
{

    $projectName = $request->input('project');
    $jobPath = "Contenedor-Proyectos/job/{$projectName}";
    $token = "123456789"; 

$jenkinsUrl = env('JENKINS_URL') . "/job/{$jobPath}/buildWithParameters" . 
              "?token=" . $token . 
              "&SCRIPT_PATH=" . urlencode($request->input('path')) . 
              "&PROJECT=" . urlencode($request->input('project')) .
              "&TOOL=" . urlencode($request->input('tool')) .
              "&HILOS=" . urlencode($request->input('hilos')) .
              "&RAMPUP=" . urlencode($request->input('rampup')) .
              "&STEPS=" . urlencode($request->input('steps')) .
              "&DURATION=" . urlencode($request->input('duration')) .
              "&ESCENARIO=" . urlencode($request->input('escenario'));

  $response = Http::withBasicAuth(env('JENKINS_USER'), env('JENKINS_TOKEN'))
        ->post($jenkinsUrl);

    if ($response->failed()) {
        \Log::error("Error de Jenkins: " . $response->body());
        return response()->json([
            'status' => 'error',
            'details' => $response->body()
        ], 500);
    }

    return response()->json(['status' => 'triggered']);
}

    // MÉTODO 3: Listar historial desde GitHub (para la pestaña History)
    public function getHistory($project)
{
    $url = "https://api.github.com/repos/" . env('GH_REPO') . "/contents/history/{$project}";

    $response = Http::withHeaders([
        'User-Agent' => 'Laravel-App'
    ])->withToken(env('GH_TOKEN'))->get($url);

    return response()->json($response->json());
}

public function getBuilds(Request $request)
    {
        // 1. Validamos el nombre del proyecto
        $projectName = $request->query('project');
        
        if (!$projectName) {
            return response()->json(['error' => 'No se especificó un proyecto'], 400);
        }

        // 2. Definimos la ruta dinámica de Jenkins
        $jobPath = "Contenedor-Proyectos/job/{$projectName}";
        
        // 3. Construimos la URL de la API de Jenkins
        $jenkinsUrl = env('JENKINS_URL') . "/job/{$jobPath}/api/json?tree=builds[number,status,timestamp,id,result,url,displayName]";

        try {
            // 4. Petición HTTP con Timeout y Autenticación
            $response = Http::withBasicAuth(env('JENKINS_USER'), env('JENKINS_TOKEN'))
                ->timeout(5) // Si en 5 segundos no responde, asumimos que está caído
                ->get($jenkinsUrl);

            // Si Jenkins responde pero con un error (ej. 404 porque el proyecto no existe)
            if ($response->failed()) {
                return response()->json([
                    'error' => 'proyecto_no_encontrado',
                    'message' => 'No se encontraron ejecuciones para este proyecto.'
                ], 404);
            }

            $data = $response->json();
            
            // 5. Mapeamos los resultados si existen builds
            $builds = collect($data['builds'] ?? [])->map(function($build) use ($jobPath) {
                // Lógica de estados
                $status = 'running';
                if ($build['result'] == 'SUCCESS') $status = 'success';
                if ($build['result'] == 'FAILURE') $status = 'failure';
                if ($build['result'] == 'ABORTED') $status = 'aborted';

                // Detectamos la herramienta desde el nombre del build (lo escribe el Jenkinsfile)
                // para apuntar al reporte correcto: k6 -> Reporte_20k6, JMeter -> Reporte_20JMeter.
                $displayName = $build['displayName'] ?? "Build #{$build['number']}";
                $tool = str_contains(strtolower($displayName), 'k6') ? 'k6' : 'jmeter';
                $reportDir = $tool === 'k6' ? 'Reporte_20k6' : 'Reporte_20JMeter';

                return [
                    'id' => $build['number'],
                    'displayName' => $displayName,
                    'status' => $status,
                    'tool' => $tool,
                    'timestamp' => date('Y-m-d H:i', $build['timestamp'] / 1000),
                    'reportUrl' => env('JENKINS_URL') . "/job/{$jobPath}/" . $build['number'] . "/{$reportDir}/"
                ];
            });

            return response()->json($builds);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // ERROR CLAVE: El servidor de Docker está apagado o la URL no es alcanzable
            Log::error("Jenkins Offline: " . $e->getMessage());
            return response()->json([
                'error' => 'server_offline',
                'message' => 'El servidor de Jenkins no responde. Verifica el contenedor de Docker.'
            ], 503);

        } catch (\Exception $e) {
            // Otros errores (errores de código, JSON malformado, etc.)
            Log::error("Error en JenkinsController: " . $e->getMessage());
            return response()->json([
                'error' => 'error_general',
                'message' => 'Ocurrió un error inesperado al conectar con Jenkins.'
            ], 500);
        }
    }

public function analizarReporte(Request $request)
{
    $projectName = $request->query('project');
    $buildNumber = $request->query('build');
    $tool        = strtolower($request->query('tool', 'jmeter'));

    if (!$projectName || !$buildNumber) {
        return response()->json(['error' => 'Faltan parámetros: project o build'], 400);
    }

    // Cada herramienta publica su reporte en una carpeta y un JSON distintos.
    if ($tool === 'k6') {
        $reportDir = 'Reporte_20k6';
        $statsFile = 'summary.json';
    } else {
        $reportDir = 'Reporte_20JMeter';
        $statsFile = 'statistics.json';
    }

    // 1. Construimos la URL completa para descargar el artifact desde Jenkins
    $statsUrl = env('JENKINS_URL') . "/job/Contenedor-Proyectos/job/{$projectName}/{$buildNumber}/{$reportDir}/{$statsFile}";
Log::debug("URL exacta enviada a Jenkins: " . $statsUrl);
    try {
        // 2. Descargamos el JSON mediante petición HTTP autenticada (indispensable en Docker)
       $response = Http::withBasicAuth(env('JENKINS_USER'), env('JENKINS_TOKEN'))
    ->withoutVerifying() // <--- PRUEBA ESTO por si es un tema de certificados
    ->timeout(10)
    
    ->get($statsUrl);

        if ($response->failed()) {
    // ESTA LÍNEA ES CLAVE: guarda en el log qué te respondió Jenkins realmente
    Log::error("Fallo al contactar Jenkins. Status: " . $response->status() . " Cuerpo: " . $response->body());
    
    return response()->json([
        'error' => 'Jenkins respondió con error ' . $response->status(),
        'debug' => $response->body() // Solo para pruebas, luego quítalo
    ], 404);
}

        $statsContent = $response->body();

        // 3. Prompt estructurado para la IA (ajustado a la herramienta)
        $herramienta = $tool === 'k6' ? 'k6' : 'JMeter';
        $prompt = "Actúa como un Ingeniero de Performance Senior. Analiza los siguientes datos JSON de un reporte de {$herramienta} y proporciona un reporte ejecutivo:
        1. Identificación de cuellos de botella (basado en tiempos de respuesta).
        2. Análisis de escalabilidad (Percentil 95 vs Promedio).
        3. Resumen de tasa de errores.
        4. Evaluación de viabilidad técnica según valores de Mínimo, Promedio y Máximo.

        Datos JSON:
        " . $statsContent;

        // 4. Llamada a la API de Gemini
        // Nota: Asegúrate de usar una versión válida del modelo (ej: gemini-1.5-flash)
        $aiResponse = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . env('GEMINI_API_KEY'), [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

        if ($aiResponse->failed()) {
            return response()->json(['error' => 'Error al contactar con la API de Gemini.'], 502);
        }

        return response()->json([
            'analisis' => $aiResponse->json()['candidates'][0]['content']['parts'][0]['text']
        ]);

   } catch (\Exception $e) {
    // Esto mostrará el error real en la respuesta JSON en lugar de ocultarlo
    return response()->json([
        'error' => 'Ocurrió un error inesperado',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}
}

}