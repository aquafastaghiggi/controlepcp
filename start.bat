@echo off
:: Iniciar MySQL do XAMPP se não estiver rodando
sc query mysql | find "RUNNING" >nul 2>&1
if errorlevel 1 (
    net start mysql
    timeout /t 3 /nobreak >nul
)

:: Aguardar MySQL estabilizar
timeout /t 2 /nobreak >nul

:: Iniciar o servidor Laravel em background (sem janela)
cd /d C:\xampp\htdocs\controlepcp_v2
start /min "ControlePCP-Server" php artisan serve --port=8000 --host=127.0.0.1

:: Aguardar servidor subir
timeout /t 3 /nobreak >nul

:: Iniciar o scheduler do Laravel em background (sem janela)
start /min "ControlePCP-Scheduler" php artisan schedule:work
