# Sandbox e Publicação (PCP)

Este documento descreve o padrão adotado para:

- trabalhar no **sandbox** sem impactar a produção
- aprovar mudanças com um **backlog visível**
- publicar do sandbox → produção com **backup** e **rollback**

## URLs

- Produção: `http://<IP-DO-SERVIDOR>/`
- Sandbox (local): `http://<IP-DO-SERVIDOR>:8081/`

## 1) Configuração do Sandbox (Apache)

O sandbox depende de um vhost separado (porta `8081`) e de `SetEnv` para isolar ambiente e banco.

Exemplo (adaptar paths e credenciais):

```apacheconf
Listen 8081

<VirtualHost *:8081>
    ServerName controleproducao-sandbox.local
    ServerAlias <IP-DO-SERVIDOR> localhost 127.0.0.1

    DocumentRoot "C:/xampp/htdocs/controlepcp_sandbox"
    DirectoryIndex index.php index.html

    # Identifica o ambiente (usa na UI e bloqueia APIs de sandbox na produção)
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

## 2) Central de Publicação (UI)

No sandbox, usuários admin veem a seção **“Publicação”** no topo e no Painel Inicial.

Ela controla:

- Backlog (itens em teste/aprovados/publicados)
- Checklist de validação (obrigatório antes de publicar)
- Histórico de releases e rollbacks

Estado runtime (não versionado): `.tmp/release-center.json`

## 3) Publicar para produção (com backup)

No servidor (PowerShell), dentro da pasta do sandbox:

```powershell
powershell -ExecutionPolicy Bypass -File tools\publish.ps1 -AllApproved -Message "release X"
```

O script:

- faz backup completo da produção em `C:\xampp\backups\controlepcp\releases\...`
- copia arquivos do sandbox → produção (exclui `.tmp`, `tools`, `.git`, etc.)
- registra a publicação na Central

## 4) Rollback (restaurar backup)

Restaurar o último backup:

```powershell
powershell -ExecutionPolicy Bypass -File tools\rollback.ps1 -Latest -Message "rollback"
```

Restaurar um backup específico:

```powershell
powershell -ExecutionPolicy Bypass -File tools\rollback.ps1 -ToBackupDir "C:\xampp\backups\controlepcp\releases\YYYYMMDD_HHMMSS" -Message "rollback"
```

## Notas de segurança

- O MySQL deve permanecer **local apenas** (`127.0.0.1`), sem porta aberta na rede.
- Evite colocar senhas reais em arquivos do Git. Use `SetEnv` no Apache na máquina alvo.

