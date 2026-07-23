#!/bin/sh
#
# Firesense - Instalador
# Uso (no shell do pfSense, Diagnostics > Command Prompt):
#   fetch -o /tmp/install_firesense.sh https://raw.githubusercontent.com/anderson-sparkweb/firesense-service/main/install.sh
#   sh /tmp/install_firesense.sh
#
set -e

REPO_RAW="https://raw.githubusercontent.com/anderson-sparkweb/firesense-service/main"
WWW_DIR="/usr/local/www/firesense"
BIN_DIR="/usr/local/bin"
AGENT_SCRIPT="$BIN_DIR/firesense.sh"

echo "== Firesense Installer =="

# 1. Verifica versao minima do pfSense (>= 2.8, por causa do bug
#    Redmine #15157, corrigido na 2.8.0)
PF_VERSION=$(cat /etc/version 2>/dev/null | cut -d'-' -f1)
PF_MAJOR=$(echo "$PF_VERSION" | cut -d'.' -f1)
PF_MINOR=$(echo "$PF_VERSION" | cut -d'.' -f2)

if [ -z "$PF_MAJOR" ] || [ -z "$PF_MINOR" ]; then
    echo "AVISO: nao foi possivel detectar a versao do pfSense. Prosseguindo mesmo assim."
elif [ "$PF_MAJOR" -lt 2 ] || { [ "$PF_MAJOR" -eq 2 ] && [ "$PF_MINOR" -lt 8 ]; }; then
    echo "ERRO: Firesense requer pfSense 2.8 ou superior."
    echo "Versao detectada: $PF_VERSION"
    exit 1
else
    echo "Versao do pfSense OK: $PF_VERSION"
fi

# 2. Cria diretorios e baixa arquivos do GitHub
mkdir -p "$WWW_DIR"

echo "Baixando config.php..."
fetch -o "$WWW_DIR/config.php" "$REPO_RAW/files/config.php"

echo "Baixando firesense.sh..."
fetch -o "$AGENT_SCRIPT" "$REPO_RAW/files/firesense.sh"
chmod +x "$AGENT_SCRIPT"

echo "Arquivos instalados."

# 3. Registra menu e pacote no config.xml (sem acentuacao, ver nota no README)
#
# OBS: usamos is_array() em vez de empty() para checar
# installedpackages/package/menu. Isso evita o erro
# "Cannot access offset of type string on string", que ocorre
# quando uma instalacao anterior deixou essas chaves gravadas
# como tag XML vazia (o pfSense le tag vazia como string "" e
# nao como array vazio).
php -r '
require_once("config.inc");
global $config;

$changed = false;

if (!isset($config["installedpackages"]) || !is_array($config["installedpackages"])) {
    $config["installedpackages"] = array();
}

if (!isset($config["installedpackages"]["package"]) || !is_array($config["installedpackages"]["package"])) {
    $config["installedpackages"]["package"] = array();
}
$exists_pkg = false;
foreach ($config["installedpackages"]["package"] as $pkg) {
    if (($pkg["name"] ?? "") === "Firesense") { $exists_pkg = true; break; }
}
if (!$exists_pkg) {
    $config["installedpackages"]["package"][] = array(
        "name"          => "Firesense",
        "internal_name" => "firesense",
        "version"       => "1.0",
        "descr"         => "Backup remoto e monitoramento Firesense"
    );
    $changed = true;
}

if (!isset($config["installedpackages"]["menu"]) || !is_array($config["installedpackages"]["menu"])) {
    $config["installedpackages"]["menu"] = array();
}
$exists_menu = false;
foreach ($config["installedpackages"]["menu"] as $item) {
    if (($item["url"] ?? "") === "/firesense/config.php") { $exists_menu = true; break; }
}
if (!$exists_menu) {
    $config["installedpackages"]["menu"][] = array(
        "name"        => "Firesense",
        "tooltiptext" => "Configuracoes do Firesense",
        "section"     => "Services",
        "url"         => "/firesense/config.php"
    );
    $changed = true;
}

if ($changed) {
    write_config("Firesense: instalado via script");
    echo "Menu e pacote registrados.\n";
} else {
    echo "Menu e pacote ja estavam registrados.\n";
}
'

echo ""
echo "== Instalacao concluida =="
echo "1. Faca logout/login no pfSense para o menu Firesense aparecer em Services."
echo "2. Acesse Services > Firesense para configurar servidor, credenciais e agendamento."
