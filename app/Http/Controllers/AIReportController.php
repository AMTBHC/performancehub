<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReportController extends Controller
{
    public function refineText(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'project' => 'nullable|string',
            'section' => 'nullable|string'
        ]);

        try {
            $apiKey = env('GEMINI_API_KEY');

            if (!$apiKey) {
                return response()->json(['error' => 'Configuración de API faltante'], 500);
            }

            // Usamos la versión estable v1 para mayor confiabilidad
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            // Mejoramos el Prompt para que el resultado sea nivel Senior NTT DATA
            $systemPrompt = "Actúa como un Consultor Senior de Performance de NTT DATA. 
            Tu tarea es mejorar el texto adjunto para un informe técnico oficial.
            REGLAS:
            1. Usa un tono ejecutivo, profesional y técnico.
            2. Emplea terminología como: latencia, throughput, escalabilidad, cuellos de botella y SLAs.
            3. Si el texto menciona una empresa (como Terpel), asegúrate de que el contexto sea de aseguramiento de calidad.
            4. Devuelve ÚNICAMENTE el texto mejorado, sin introducciones como 'Aquí tienes el texto' ni comillas.";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nTEXTO A MEJORAR:\n" . $request->text]
                        ]
                    ]
                ],
                'generationConfig' => [
    'temperature' => 0.5,      // Más enfocado y profesional
    'maxOutputTokens' => 2048, // Suficiente espacio para un reporte completo
    'topP' => 0.8,
    'topK' => 40
]
            ];

            $response = Http::timeout(30)->post($endpoint, $payload);

            if ($response->failed()) {
                Log::error('Error Gemini API', ['body' => $response->json()]);
                return response()->json(['error' => 'La IA no pudo procesar el texto'], $response->status());
            }

            $data = $response->json();
            
            // Extracción segura del texto
            $improvedText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$improvedText) {
                return response()->json(['error' => 'Respuesta vacía de la IA'], 500);
            }

            // Limpieza de posibles etiquetas Markdown que a veces la IA agrega
            $cleanText = str_replace(['```markdown', '```'], '', $improvedText);

            return response()->json([
                'improvedText' => trim($cleanText)
            ]);

        } catch (\Exception $e) {
            Log::error('Error en AIReportController: ' . $e->getMessage());
            return response()->json(['error' => 'Error de conexión'], 500);
        }
    }

    /**
     * Genera un script de prueba de carga (k6 por defecto) a partir de
     * historias de usuario en lenguaje natural, usando Gemini.
     *
     * El script resultante lee VUs y duración desde variables de entorno
     * (__ENV.VUS / __ENV.DURATION) para que sea ejecutable tal cual en el
     * pipeline de Jenkins, igual que los scripts del generador manual.
     */
    public function generarScript(Request $request)
    {
        $request->validate([
            'historias' => 'required|string',
            'baseUrl'   => 'nullable|string',
            'tool'      => 'nullable|string',  // k6 (por ahora)
            'vus'       => 'nullable',
            'duration'  => 'nullable',
        ]);

        try {
            $apiKey = env('GEMINI_API_KEY');

            if (!$apiKey) {
                return response()->json(['error' => 'Configuración de API faltante'], 500);
            }

            $tool     = strtolower($request->input('tool', 'k6'));
            $baseUrl  = trim($request->input('baseUrl', '')) ?: 'https://api.ejemplo.com';
            $vus      = (int) ($request->input('vus', 10)) ?: 10;
            $duration = (int) ($request->input('duration', 30)) ?: 30;

            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            // Prompt especializado en k6. Pide un script válido, ejecutable y
            // compatible con Jenkins, devolviendo SOLO código (sin markdown).
            $systemPrompt = "Actúa como un Ingeniero de Performance Senior experto en k6 (JavaScript).
A partir de las HISTORIAS DE USUARIO que te paso, genera UN ÚNICO script de k6 válido y ejecutable.

REQUISITOS TÉCNICOS:
1. Usa la sintaxis moderna de k6: import http from 'k6/http'; import { check, sleep, group } from 'k6';
2. Define `export const options` leyendo los parámetros desde el entorno para que funcione en Jenkins:
   - vus: Number(__ENV.VUS) || {$vus}
   - duration: (__ENV.DURATION || '{$duration}') + 's'
   - thresholds: { http_req_duration: ['p(95)<500'], http_req_failed: ['rate<0.01'] }
3. Crea un `group('...')` por cada historia de usuario, con un nombre descriptivo.
4. Dentro de cada grupo realiza las peticiones HTTP (http.get/post/put/del según corresponda) usando como base la URL: {$baseUrl}
5. Añade `check()` validando el status esperado (200/201) y, cuando aplique, parte del cuerpo de la respuesta.
6. Usa `sleep(1)` entre pasos para simular think time realista.
7. Si una historia necesita autenticación o un body JSON, créalo de forma realista con marcadores claros (ej: // TODO: reemplazar credenciales).

REGLAS DE SALIDA (MUY IMPORTANTE):
- Devuelve ÚNICAMENTE el código JavaScript del script.
- NO incluyas explicaciones, ni introducciones, ni bloques de markdown (nada de tres comillas invertidas).
- El script debe empezar directamente con la línea de los import.";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nHISTORIAS DE USUARIO:\n" . $request->historias]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => 0.3,   // Determinista: queremos código correcto, no creativo
                    'maxOutputTokens' => 4096,
                    'topP'            => 0.9,
                    'topK'            => 40
                ]
            ];

            $response = Http::timeout(45)->post($endpoint, $payload);

            if ($response->failed()) {
                Log::error('Error Gemini (generarScript)', ['body' => $response->json()]);
                return response()->json(['error' => 'La IA no pudo generar el script'], $response->status());
            }

            $data = $response->json();

            $script = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$script) {
                return response()->json(['error' => 'Respuesta vacía de la IA'], 500);
            }

            // La IA a veces envuelve el código en ```javascript ... ```; lo quitamos.
            $script = preg_replace('/^\s*```[a-zA-Z]*\s*\n?/', '', $script);
            $script = preg_replace('/\n?```\s*$/', '', $script);

            return response()->json([
                'script' => trim($script),
                'tool'   => $tool
            ]);

        } catch (\Exception $e) {
            Log::error('Error generarScript: ' . $e->getMessage());
            return response()->json(['error' => 'Error de conexión'], 500);
        }
    }
}