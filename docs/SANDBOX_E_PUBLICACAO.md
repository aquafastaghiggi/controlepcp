# Sandbox e Publica??o (PCP)

Este documento descreve o padr?o adotado para:

- trabalhar no **sandbox** sem impactar a produ??o
- aprovar mudan?as com um **backlog vis?vel**
- publicar do sandbox ? produ??o com **backup** e **rollback**

## URLs

- Produ??o: `http://<IP-DO-SERVIDOR>/`
- Sandbox (local): `http://<IP-DO-SERVIDOR>:8081/`

## 1) Configura??o do Sandbox (Apache)

O sandbox depende de um vhost separado (porta `8081`) e de `SetEnv` para isolar ambiente e banco.

Exemplo (adaptar paths e credenciais):

```apacheconf
Listen 8081

<VirtualHost *:8081>
    ServerName controleproducao-sandbox.local
    ServerAlias <IP-DO-SERVIDOR> localhost 127.0.0.1

    DocumentRoot "C:/xampp/htdocs/controlepcp_sandbox"
    DirectoryIndex index.php index.html

    # Identifica o ambiente (usa na UI e bloqueia APIs de sandbox na produ??o)
    SetEnv APP_ENV sandbox

    # Banco do sandbox (somente local)
    SetEnv DB_HOST 127.0.0.1
    SetEnv DB_PORT 3306
    SetEnv DB_NAME controlepcp_sandbox
    SetEnv DB_USER <DB_USER_SANDBOX>
    SetEnv DB_PASS <DB_PASS_SANDBOX>

    Alias /controlepcp "C:/xampp/htdocs/controlepcp_sandbox"
    <Directory "C:/xampp/htdocs/controlepcp_sandbox">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

## 2) Central de Publica??o (UI)

No sandbox, usu?rios admin veem a se??o **"Publica??o"** no topo e no Painel Inicial.

Ela controla:

- Backlog (itens em teste/aprovados/publicados)
- Checklist de valida??o (obrigat?rio antes de publicar)
- Hist?rico de releases e rollbacks

Estado runtime (n?o versionado): `.tmp/release-center.json`

## 3) Publicar para produ??o (com backup)

No servidor (PowerShell), dentro da pasta do sandbox:

```powershell
powershell -ExecutionPolicy Bypass -File tools\publish.ps1 -AllApproved -Message "release X"
```

O script:

- faz backup completo da produ??o em `C:\xampp\backups\controlepcp\releases\...`
- copia arquivos do sandbox ? produ??o (exclui `.tmp`, `tools`, `.git`, etc.)
- registra a publica??o na Central

## 4) Rollback (restaurar backup)

Restaurar o ?ltimo backup:

```powershell
powershell -ExecutionPolicy Bypass -File tools\rollback.ps1 -Latest -Message "rollback"
```

Restaurar um backup espec?fico:

```powershell
powershell -ExecutionPolicy Bypass -File tools\rollback.ps1 -ToBackupDir "C:\xampp\backups\controlepcp\releases\YYYYMMDD_HHMMSS" -Message "rollback"
```

## Notas de seguran?a

- O MySQL deve permanecer **local apenas** (`127.0.0.1`), sem porta aberta na rede.
- Evite colocar senhas reais em arquivos do Git. Use `SetEnv` no Apache na m?quina alvo.

