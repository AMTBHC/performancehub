# Imagen de Jenkins con k6 y JMeter preinstalados.
# Así NO necesitas Docker-in-Docker: las pruebas corren dentro de este contenedor.
FROM jenkins/jenkins:lts-jdk17

USER root

# --- Dependencias base ---
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        curl gnupg ca-certificates unzip && \
    rm -rf /var/lib/apt/lists/*

# --- k6 (binario oficial de Grafana) ---
RUN curl -fsSL https://dl.k6.io/key.gpg | gpg --dearmor -o /usr/share/keyrings/k6-archive-keyring.gpg && \
    echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
        > /etc/apt/sources.list.d/k6.list && \
    apt-get update && \
    apt-get install -y --no-install-recommends k6 && \
    rm -rf /var/lib/apt/lists/* && \
    k6 version

# --- Apache JMeter (misma versión que generan los scripts: 5.6.3) ---
ARG JMETER_VERSION=5.6.3
ENV JMETER_HOME=/opt/apache-jmeter-${JMETER_VERSION}
RUN curl -fsSL "https://archive.apache.org/dist/jmeter/binaries/apache-jmeter-${JMETER_VERSION}.tgz" -o /tmp/jmeter.tgz && \
    tar -xzf /tmp/jmeter.tgz -C /opt && \
    rm /tmp/jmeter.tgz && \
    ln -s ${JMETER_HOME}/bin/jmeter /usr/local/bin/jmeter

# --- Plugins de JMeter que usan los scripts generados ---
# jpgc-casutg     -> Custom Thread Groups (ConcurrencyThreadGroup)
# jpgc-graphs-basic -> Transactions per Second, etc.
RUN curl -fsSL "https://jmeter-plugins.org/get/" -o ${JMETER_HOME}/lib/ext/jmeter-plugins-manager.jar && \
    curl -fsSL "https://repo1.maven.org/maven2/kg/apc/cmdrunner/2.3/cmdrunner-2.3.jar" -o ${JMETER_HOME}/lib/cmdrunner-2.3.jar && \
    java -cp ${JMETER_HOME}/lib/ext/jmeter-plugins-manager.jar org.jmeterplugins.repository.PluginManagerCMDInstaller && \
    ${JMETER_HOME}/bin/PluginsManagerCMD.sh install jpgc-casutg,jpgc-graphs-basic

# --- Plugins de Jenkins ---
COPY plugins.txt /usr/share/jenkins/ref/plugins.txt
RUN jenkins-plugin-cli --plugin-file /usr/share/jenkins/ref/plugins.txt

USER jenkins
