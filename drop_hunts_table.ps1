# PowerShell script to drop pp_hunts table
# Update the connection parameters below

$dbServer = "localhost"
$dbName = "your_wordpress_db_name"
$dbUser = "your_db_username"
$dbPassword = "your_db_password"
$tablePrefix = "wp2s_"  # Update this to your actual prefix

# MySQL command
$mysqlCommand = "DROP TABLE IF EXISTS ${tablePrefix}pp_hunts;"

# Execute the command (requires mysql command line tool)
try {
    mysql -h $dbServer -u $dbUser -p$dbPassword $dbName -e $mysqlCommand
    Write-Host "✅ pp_hunts table dropped successfully!" -ForegroundColor Green
} catch {
    Write-Host "❌ Error dropping table: $_" -ForegroundColor Red
    Write-Host "Please run this SQL command manually in your database:" -ForegroundColor Yellow
    Write-Host $mysqlCommand -ForegroundColor Cyan
}
