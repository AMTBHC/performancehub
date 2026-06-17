// Pipeline de PerformanceHub.
// Lo dispara el backend (PerformanceController@runJenkins) vía buildWithParameters.
// Un mismo pipeline sirve para k6 y JMeter; decide según el parámetro TOOL.
//
// Requisitos en Jenkins:
//   - Credencial tipo "Secret text" con ID 'gh-token' = tu Personal Access Token de GitHub.
//   - Job dentro de la carpeta "Contenedor-Proyectos", con el nombre del proyecto.
//   - "Trigger builds remotely" activado con token: 123456789  (igual que en el backend).

pipeline {
    agent any

    parameters {
        string(name: 'TOOL',        defaultValue: 'jmeter',       description: 'k6 | jmeter')
        string(name: 'SCRIPT_PATH', defaultValue: '',             description: 'Ruta del script dentro del repo GH_REPO')
        string(name: 'PROJECT',     defaultValue: '',             description: 'Nombre del proyecto')
        string(name: 'HILOS',       defaultValue: '10',           description: 'Hilos (JMeter) / VUs (k6)')
        string(name: 'RAMPUP',      defaultValue: '60',           description: 'Ramp-up en segundos (JMeter)')
        string(name: 'STEPS',       defaultValue: '5',            description: 'Pasos (JMeter)')
        string(name: 'DURATION',    defaultValue: '300',          description: 'Duración en segundos')
        string(name: 'ESCENARIO',   defaultValue: 'GH_LineaBase', description: 'Escenario de carga')
    }

    environment {
        // Repo de GitHub donde el backend guarda los scripts (mismo valor que GH_REPO del .env).
        GH_REPO = 'AMTBHC/jenkins'
    }

    options {
        timestamps()
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '30'))
    }

    stages {
        stage('Descargar script') {
            steps {
                cleanWs()
                withCredentials([string(credentialsId: 'gh-token', variable: 'GH_TOKEN')]) {
                    sh '''
                        set -e
                        if [ "$TOOL" = "k6" ]; then OUT=script.js; else OUT=script.jmx; fi
                        curl -fsSL \
                            -H "Authorization: token ${GH_TOKEN}" \
                            -H "Accept: application/vnd.github.raw" \
                            "https://api.github.com/repos/${GH_REPO}/contents/${SCRIPT_PATH}" \
                            -o "$OUT"
                        echo "Script descargado: $OUT"
                    '''
                }
            }
        }

        stage('Ejecutar k6') {
            when { expression { params.TOOL == 'k6' } }
            steps {
                sh '''
                    set -e
                    mkdir -p k6-report
                    # El dashboard web genera el HTML; --summary-export genera el JSON para la IA.
                    K6_WEB_DASHBOARD=true \
                    K6_WEB_DASHBOARD_EXPORT=k6-report/index.html \
                    k6 run \
                        --summary-export=k6-report/summary.json \
                        -e VUS=${HILOS} \
                        -e DURATION=${DURATION} \
                        script.js
                '''
            }
            post {
                always {
                    publishHTML(target: [
                        reportDir: 'k6-report', reportFiles: 'index.html',
                        reportName: 'Reporte k6', keepAll: true,
                        alwaysLinkToLastBuild: true, allowMissing: true
                    ])
                    archiveArtifacts artifacts: 'k6-report/**', allowEmptyArchive: true
                }
            }
        }

        stage('Ejecutar JMeter') {
            when { expression { params.TOOL == 'jmeter' } }
            steps {
                sh '''
                    set -e
                    rm -rf "Reporte JMeter" results.jtl
                    jmeter -n -t script.jmx -l results.jtl \
                        -Jp_hilos=${HILOS} \
                        -Jp_rampup=${RAMPUP} \
                        -Jp_steps=${STEPS} \
                        -Jp_duration=${DURATION} \
                        -e -o "Reporte JMeter"
                '''
            }
            post {
                always {
                    publishHTML(target: [
                        reportDir: 'Reporte JMeter', reportFiles: 'index.html',
                        reportName: 'Reporte JMeter', keepAll: true,
                        alwaysLinkToLastBuild: true, allowMissing: true
                    ])
                    archiveArtifacts artifacts: 'Reporte JMeter/**', allowEmptyArchive: true
                }
            }
        }
    }

    post {
        always {
            script {
                // El backend lee la herramienta desde el nombre del build.
                currentBuild.displayName = "#${env.BUILD_NUMBER} · ${params.PROJECT} · ${params.TOOL}"
            }
        }
    }
}
