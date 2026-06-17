// Relaja la Content-Security-Policy con la que Jenkins sirve los HTML publicados,
// para que el reporte web de k6 (JavaScript, estilos e imágenes embebidos) se renderice
// en vez de salir en blanco. Recomendable solo en entornos locales/de confianza.
System.setProperty(
    "hudson.model.DirectoryBrowserSupport.CSP",
    "sandbox allow-scripts allow-same-origin; default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval';"
)
