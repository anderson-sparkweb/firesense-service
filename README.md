# Firesense Service

Instalador leve do Firesense para pfSense — sem depender do formato
`.pkg` do FreeBSD (que exigiria build via ports/poudriere). Este
instalador copia os arquivos necessários e registra a página no menu
do pfSense automaticamente, via script shell.

## Requisitos

- pfSense **2.8.0 ou superior** (versões anteriores têm um bug conhecido
  do próprio pfSense — [Redmine #15157](https://redmine.pfsense.org/issues/15157)
  — que quebra o `write_config()` usado no registro do menu).

## Instalação

No shell do pfSense (**Diagnostics > Command Prompt**, ou via SSH):

```sh
fetch -o /tmp/install_firesense.sh https://raw.githubusercontent.com/anderson-sparkweb/firesense-service/main/install.sh
sh /tmp/install_firesense.sh
```

Depois:
1. Faça **logout/login** no pfSense (o menu não atualiza sozinho na mesma sessão).
2. Acesse **Services > Firesense** e configure servidor, credenciais e agendamento.

## Desinstalação

```sh
fetch -o /tmp/uninstall_firesense.sh https://raw.githubusercontent.com/anderson-sparkweb/firesense-service/main/uninstall.sh
sh /tmp/uninstall_firesense.sh
```

O arquivo `/usr/local/etc/firesense.conf` **não é apagado** automaticamente
(contém credenciais), remova manualmente se quiser.

## Estrutura do repositório

```
firesense-service/
├── install.sh
├── uninstall.sh
├── files/
│   ├── config.php      # página de configuração (Services > Firesense)
│   └── firesense.sh    # agente que gera o backup e envia à API
└── README.md
```

## Notas técnicas importantes (ver histórico de desenvolvimento)

- **Ícones**: pfSense 2.8+ usa Font Awesome 6 — sempre usar prefixo
  de estilo completo, ex: `fa-solid fa-save` (não só `fa-save`).
- **Acentuação em `write_config()`**: evite gravar texto acentuado
  (`ç`, `õ`, etc.) em campos que vão para o `config.xml` via
  `write_config()` — foi identificado um bug onde acentos viram
  entidades HTML inválidas em XML, corrompendo o arquivo.
- **Menu de pacotes**: o pfSense só processa `installedpackages/menu`
  se também existir algo em `installedpackages/package` — por isso o
  instalador registra os dois.
- **Agendamento**: a página usa `install_cron_job()` (nativa do
  pfSense) para gerenciar o cron — não edite `crontab` manualmente,
  pois isso pode causar execução duplicada.

## ⚠️ Antes de usar

O arquivo `files/firesense.sh` neste repositório é um **placeholder**.
Substitua pelo conteúdo real do script do agente (o que já roda em
produção) antes de instalar em qualquer pfSense.
