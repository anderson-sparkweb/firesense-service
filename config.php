<?php
require_once("guiconfig.inc");
require_once("classes/Form.class.php");

$config_file  = "/usr/local/etc/firesense.conf";
$log_file     = "/var/log/firesense.log";
$agent_script = "/usr/local/bin/firesense.sh";
$default_port = "7010";

$firesense_config = array();
if (file_exists($config_file)) {
    $firesense_config = parse_ini_file($config_file);
}

// Host / Porta - com compatibilidade retroativa (conf antigo só tinha SERVER completo)
$host = $firesense_config['HOST'] ?? '';
$port = $firesense_config['PORT'] ?? $default_port;

if (empty($host) && !empty($firesense_config['SERVER'])) {
    $parsed = parse_url($firesense_config['SERVER']);
    $host = $parsed['host'] ?? '';
    if (!empty($parsed['port'])) {
        $port = (string)$parsed['port'];
    }
}

// Agendamento - valores atuais (ou padrão)
$frequencia  = $firesense_config['FREQUENCIA']  ?? 'diario';
$horario     = $firesense_config['HORARIO']     ?? '03:00';
$dia_semana  = $firesense_config['DIA_SEMANA']  ?? '0';   // 0=Domingo ... 6=Sábado
$dia_mes     = $firesense_config['DIA_MES']     ?? '1';
$todos_dias  = ($firesense_config['TODOS_DIAS'] ?? 'no') === 'yes';

$pgtitle = array("Services", "Firesense", "Configuração");
require_once("head.inc");

$test_output = "";

// -------------------------------------------------
// Extrai só o status de uma linha no formato "data | STATUS | arquivo"
// -------------------------------------------------
function firesense_extract_status($line) {
    $parts = array_map('trim', explode('|', trim($line)));
    return $parts[1] ?? trim($line);
}

if ($_POST) {

    // -------------------------------------------------
    // Botão "Testar configuração" - roda o agente na hora
    // -------------------------------------------------
    if (isset($_POST['test'])) {
        if (is_executable($agent_script)) {
            exec(escapeshellcmd($agent_script) . " 2>&1", $out, $ret);
            $last_out_line = "";
            foreach (array_reverse($out) as $line) {
                if (trim($line) !== "") {
                    $last_out_line = $line;
                    break;
                }
            }
            $test_output = $last_out_line !== ""
                ? firesense_extract_status($last_out_line)
                : "Sem retorno do script.";
        } else {
            $test_output = "Script do agente não encontrado ou sem permissão de execução.";
        }
    }

    // -------------------------------------------------
    // Botão "Salvar"
    // -------------------------------------------------
    if (isset($_POST['save'])) {
        $host       = trim($_POST['host'] ?? '');
        $port       = trim($_POST['port'] ?? '');
        $frequencia = $_POST['frequencia'] ?? 'diario';
        $horario    = trim($_POST['horario'] ?? '03:00');
        $dia_semana = $_POST['dia_semana'] ?? '0';
        $dia_mes    = trim($_POST['dia_mes'] ?? '1');
        $todos_dias = isset($_POST['todos_dias']);

        if ($port === '') {
            $port = $default_port;
        }
        if ($horario === '' || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $horario)) {
            $horario = '03:00';
        }

        $server_url = "http://{$host}:{$port}/firesense/api.php";

        // Monta minuto/hora do cron a partir do campo horário (HH:MM)
        list($cron_hour, $cron_minute) = explode(':', $horario);

        // "Todos os dias" força diário, ignorando semana/mês
        $freq_efetiva = $todos_dias ? 'diario' : $frequencia;

        switch ($freq_efetiva) {
            case 'semanal':
                $cron_mday  = '*';
                $cron_month = '*';
                $cron_wday  = $dia_semana;
                break;
            case 'mensal':
                $cron_mday  = $dia_mes;
                $cron_month = '*';
                $cron_wday  = '*';
                break;
            case 'diario':
            default:
                $cron_mday  = '*';
                $cron_month = '*';
                $cron_wday  = '*';
                break;
        }

        // Registra/atualiza o agendamento no cron nativo do pfSense
        install_cron_job(
            $agent_script,
            true,
            $cron_minute,
            $cron_hour,
            $cron_mday,
            $cron_month,
            $cron_wday,
            "root"
        );

        $conteudo  = 'HOST="' . $host . '"' . PHP_EOL;
        $conteudo .= 'PORT="' . $port . '"' . PHP_EOL;
        $conteudo .= 'SERVER="' . $server_url . '"' . PHP_EOL;
        $conteudo .= 'CLIENT_ID="' . ($_POST['client_id'] ?? '') . '"' . PHP_EOL;
        $conteudo .= 'CLIENT_SECRET="' . ($_POST['client_secret'] ?? '') . '"' . PHP_EOL;
        $conteudo .= 'FREQUENCIA="' . $frequencia . '"' . PHP_EOL;
        $conteudo .= 'HORARIO="' . $horario . '"' . PHP_EOL;
        $conteudo .= 'DIA_SEMANA="' . $dia_semana . '"' . PHP_EOL;
        $conteudo .= 'DIA_MES="' . $dia_mes . '"' . PHP_EOL;
        $conteudo .= 'TODOS_DIAS="' . ($todos_dias ? 'yes' : 'no') . '"' . PHP_EOL;

        file_put_contents($config_file, $conteudo);

        $firesense_config = parse_ini_file($config_file);
        $host = $firesense_config['HOST'];
        $port = $firesense_config['PORT'];

        print_info_box("Configuração salva e agendamento atualizado com sucesso.", "success");
    }
}

// ---------------------------------------------------------
// Status - lê e interpreta a última linha do log do agente
// ---------------------------------------------------------
$status_text  = "Sem informações de status ainda.";
$status_class = "info";
$last_backup  = "";

if (file_exists($log_file)) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines && count($lines) > 0) {
        $last_line = trim(end($lines));
        $parts = array_map('trim', explode('|', $last_line));

        $log_datetime = $parts[0] ?? '';
        $log_status   = $parts[1] ?? $last_line;
        $log_file_bkp = $parts[2] ?? '';

        if (stripos($log_status, "OK") === 0) {
            $status_class = "success";
        } elseif (stripos($log_status, "inválido") !== false || stripos($log_status, "invalido") !== false
                  || stripos($log_status, "erro") !== false || stripos($log_status, "falha") !== false) {
            $status_class = "danger";
        } else {
            $status_class = "warning";
        }

        $status_text = "Status: " . htmlspecialchars($log_status) . " (Última verificação: {$log_datetime})";

        if (!empty($log_file_bkp)) {
            $last_backup = "Último backup: " . htmlspecialchars($log_file_bkp);
        }
    }
}

print_info_box($status_text, $status_class);

if (!empty($last_backup)) {
    print_info_box($last_backup, "info");
}

if ($test_output !== "") {
    print_info_box("Resultado do teste: " . htmlspecialchars($test_output), "info");
}

// ---------------------------------------------------------
// Formulário
// ---------------------------------------------------------
$form = new Form(false); // false = não adiciona o botão "Save" automático

$section = new Form_Section('Configuração do Firesense');

$section->addInput(
    (new Form_Input('host', 'Servidor (IP ou hostname)', 'text', $host))
        ->setHelp('Informe apenas o IP ou hostname do servidor. A porta é definida no campo abaixo.')
);
$section->addInput(
    (new Form_Input('port', 'Porta', 'text', $port))
        ->setHelp("Porta da API remota. Padrão: {$default_port} (deixe em branco para usar o padrão).")
);
$section->addInput(
    new Form_Input('client_id', 'Client ID', 'text', $firesense_config['CLIENT_ID'] ?? '')
);
$section->addInput(
    new Form_Input('client_secret', 'Client Secret', 'password', $firesense_config['CLIENT_SECRET'] ?? '')
);

$form->add($section);

// --- Seção de agendamento ---
$section_cron = new Form_Section('Agendamento do Backup');

$section_cron->addInput(
    (new Form_Select(
        'frequencia',
        'Frequência',
        $frequencia,
        array('diario' => 'Diário', 'semanal' => 'Semanal', 'mensal' => 'Mensal')
    ))->setHelp('Define de quanto em quanto tempo o backup será executado.')
);

$section_cron->addInput(
    (new Form_Select(
        'dia_semana',
        'Dia da semana',
        $dia_semana,
        array(
            '0' => 'Domingo', '1' => 'Segunda', '2' => 'Terça', '3' => 'Quarta',
            '4' => 'Quinta', '5' => 'Sexta', '6' => 'Sábado'
        )
    ))->setHelp('Usado apenas quando a frequência é Semanal.')
);

$section_cron->addInput(
    (new Form_Input('dia_mes', 'Dia do mês', 'number', $dia_mes))
        ->setHelp('Usado apenas quando a frequência é Mensal (1 a 31).')
        ->setAttribute('min', '1')->setAttribute('max', '31')
);

$section_cron->addInput(
    (new Form_Input('horario', 'Horário', 'time', $horario))
        ->setHelp('Horário em que o backup será executado.')
);

$section_cron->addInput(
    (new Form_Checkbox('todos_dias', 'Todos os dias', 'Ignorar frequência e rodar todo dia neste horário', $todos_dias))
        ->setHelp('Se marcado, executa diariamente no horário definido acima, independente da frequência escolhida.')
);

$form->add($section_cron);

$form->addGlobal((new Form_Button('save', 'Salvar', null, 'fa-solid fa-save'))->addClass('btn-primary'));
$form->addGlobal((new Form_Button('test', 'Testar configuração', null, 'fa-solid fa-rotate'))->addClass('btn-info'));

print $form;
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
    function firesenseToggleAgendamento() {
        var freq = $('#frequencia').val();
        $('#dia_semana').closest('.form-group').toggle(freq === 'semanal');
        $('#dia_mes').closest('.form-group').toggle(freq === 'mensal');
    }
    $('#frequencia').on('change', firesenseToggleAgendamento);
    firesenseToggleAgendamento();
});
//]]>
</script>
<?php
include("foot.inc");
