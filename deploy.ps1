param(
    [string]$Target = "root@91.230.94.22",
    [string]$RemotePath = "/var/www/bitrixapp/current"
)

$files = @(
    "bootstrap.php",
    "install.php",
    "handler.php",
    "placement.php",
    "index.php",
    "index.html",
    "debug_log.php",
    "README.md",
    ".env.example"
)

Write-Host "Uploading files to $Target`:$RemotePath"
scp $files "$Target`:$RemotePath/"

Write-Host "Remember to create $RemotePath/var and configure the web server document root."
