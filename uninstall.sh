#!/bin/sh
#
# Firesense - Desinstalador
# Uso (no shell do pfSense):
#   fetch -o /tmp/uninstall_firesense.sh https://raw.githubusercontent.com/anderson-sparkweb/firesense-service/main/uninstall.sh
#   sh /tmp/uninstall_firesense.sh
#
set -e

WWW_DIR="/usr/local/www/firesense"
AGENT_SCRIPT="/usr/local/bin/firesense.sh"
CONF_FILE="/usr/local/etc/firesense.conf"

echo "== Firesense Uninstaller =="

# 1. Remove a entrada de cron gerenciada pelo pfSense
php -r '
require_once("config.inc");
require_once("util.inc");
install_cron_job("/usr/local/bin/firesense.sh", false);
echo "Entrada de cron removida (se existia).\n";
'

# 2. Remove menu e registro de pacote do config.xml
php -r '
require_once("config.inc");
global $config;
$changed = false;

if (!empty($config["installedpackages"]["menu"])) {
    foreach ($config["installedpackages"]["menu"] as $k => $item) {
        if (($item["url"] ?? "") === "/firesense/config.php") {
            unset($config["installedpackages"]["menu"][$k]);
            $changed = true;
        }
    }
    $config["installedpackages"]["menu"] = array_values($config["installedpackages"]["menu"]);
}

if (!empty($config["installedpackages"]["package"])) {
    foreach ($config["installedpackages"]["package"] as $k => $pkg) {
        if (($pkg["name"] ?? "") === "Firesense") {
            unset($config["installedpackages"]["package"][$k]);
            $changed = true;
        }
    }
    $config["installedpackages"]["package"] = array_values($config["installedpackages"]["package"]);
}

if ($changed) {
    write_config("Firesense: desinstalado via script");
    echo "Menu e pacote removidos do config.xml.\n";
} else {
    echo "Nenhum registro de menu/pacote encontrado.\n";
}
'

# 3. Remove arquivos da interface e do agente
rm -rf "$WWW_DIR"
rm -f "$AGENT_SCRIPT"

echo ""
echo "Arquivos removidos."
echo "O arquivo $CONF_FILE foi MANTIDO (contem credenciais)."
echo "Se quiser apagar tambem, rode: rm $CONF_FILE"
echo "Faca logout/login para o menu sumir da tela."
