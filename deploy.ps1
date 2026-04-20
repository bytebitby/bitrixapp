param(
    [string]$Target = "root@89.169.154.151",
    [string]$RemotePath = "/var/www/html/bitrixapp"
)

$files = @(
    "bootstrap.php",
    "install.php",
    "handler.php",
    "placement.php",
    "README.md",
    ".env.example"
)

Write-Host "Uploading files to $Target`:$RemotePath"
scp $files "$Target`:$RemotePath/"

Write-Host "Remember to create $RemotePath/var and configure the web server document root."
