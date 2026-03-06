param(
    [string]$TaskName = "Core1-Inventory-Automation",
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\\..")).Path,
    [string]$PhpExe = "php",
    [string]$RunAt = "08:00"
)

$scriptPath = Join-Path $ProjectRoot "backend\\utils\\automate_inventory_ops.php"
if (!(Test-Path $scriptPath)) {
    throw "Automation script not found: $scriptPath"
}

$taskCommand = "`"$PhpExe`" `"$scriptPath`""

Write-Host "Registering scheduled task..."
Write-Host "Task: $TaskName"
Write-Host "Command: $taskCommand"
Write-Host "Schedule: Daily at $RunAt"

schtasks /Create /TN $TaskName /TR $taskCommand /SC DAILY /ST $RunAt /F | Out-Host

if ($LASTEXITCODE -ne 0) {
    throw "Failed to create scheduled task. Try running PowerShell as Administrator."
}

Write-Host "Scheduled task created successfully."
Write-Host "To run immediately:"
Write-Host "  schtasks /Run /TN `"$TaskName`""
