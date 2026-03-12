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
}