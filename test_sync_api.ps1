$uri = "http://localhost/controlepcp_sandbox/api/sync_codi.php"
$headers = @{"Content-Type" = "application/json"}
$body = @{"action" = "sync_yesterday"; "force" = $true} | ConvertTo-Json

Write-Host "Enviando POST para: $uri"
Write-Host "Headers: $($headers | ConvertTo-Json)"
Write-Host "Body: $body"
Write-Host "`n--- Resposta ---`n"

try {
    $response = Invoke-WebRequest -Uri $uri -Method POST -Headers $headers -Body $body -ErrorAction Stop
    Write-Host "Status: $($response.StatusCode)"
    Write-Host "Content:`n$($response.Content)"
    
    $json = $response.Content | ConvertFrom-Json
    Write-Host "`n✅ JSON válido"
    Write-Host "Success: $($json.success)"
    Write-Host "Message: $($json.message)"
} catch {
    Write-Host "❌ Erro: $_"
    Write-Host "Resposta: $($_.Exception.Response.StatusCode)"
}
