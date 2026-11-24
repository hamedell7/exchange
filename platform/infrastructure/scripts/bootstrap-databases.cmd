@echo off
setlocal EnableExtensions
cd /d "%~dp0.."

if not exist ".env" (
  echo [ERROR] .env not found. Copy .env.example to .env and configure it first.
  exit /b 1
)

for /f "usebackq eol=# tokens=1,* delims==" %%A in (".env") do (
  if "%%A"=="IDENTITY_DB_USERNAME" set "IDENTITY_DB_USERNAME=%%B"
  if "%%A"=="IDENTITY_DB_DATABASE" set "IDENTITY_DB_DATABASE=%%B"
  if "%%A"=="IDENTITY_DB_PASSWORD" set "IDENTITY_DB_PASSWORD=%%B"
  if "%%A"=="WALLET_DB_USERNAME" set "WALLET_DB_USERNAME=%%B"
  if "%%A"=="WALLET_DB_DATABASE" set "WALLET_DB_DATABASE=%%B"
  if "%%A"=="WALLET_DB_PASSWORD" set "WALLET_DB_PASSWORD=%%B"
  if "%%A"=="TRADING_DB_USERNAME" set "TRADING_DB_USERNAME=%%B"
  if "%%A"=="TRADING_DB_DATABASE" set "TRADING_DB_DATABASE=%%B"
  if "%%A"=="TRADING_DB_PASSWORD" set "TRADING_DB_PASSWORD=%%B"
  if "%%A"=="MARKET_DATA_DB_USERNAME" set "MARKET_DATA_DB_USERNAME=%%B"
  if "%%A"=="MARKET_DATA_DB_DATABASE" set "MARKET_DATA_DB_DATABASE=%%B"
  if "%%A"=="MARKET_DATA_DB_PASSWORD" set "MARKET_DATA_DB_PASSWORD=%%B"
)

if not defined IDENTITY_DB_USERNAME goto missing_env_value
if not defined IDENTITY_DB_DATABASE goto missing_env_value
if not defined IDENTITY_DB_PASSWORD goto missing_env_value
if not defined WALLET_DB_USERNAME goto missing_env_value
if not defined WALLET_DB_DATABASE goto missing_env_value
if not defined WALLET_DB_PASSWORD goto missing_env_value
if not defined TRADING_DB_USERNAME goto missing_env_value
if not defined TRADING_DB_DATABASE goto missing_env_value
if not defined TRADING_DB_PASSWORD goto missing_env_value
if not defined MARKET_DATA_DB_USERNAME goto missing_env_value
if not defined MARKET_DATA_DB_DATABASE goto missing_env_value
if not defined MARKET_DATA_DB_PASSWORD goto missing_env_value

goto env_values_ok

:missing_env_value
echo [ERROR] One or more database variables are missing in .env.
exit /b 1

:env_values_ok
echo [INFO] Applying database roles, databases and connection permissions...
type "postgres\bootstrap.sql" | docker compose exec -T postgres psql -U exchange_admin -d postgres ^
  -v identity_username="%IDENTITY_DB_USERNAME%" ^
  -v identity_database="%IDENTITY_DB_DATABASE%" ^
  -v identity_password="%IDENTITY_DB_PASSWORD%" ^
  -v wallet_username="%WALLET_DB_USERNAME%" ^
  -v wallet_database="%WALLET_DB_DATABASE%" ^
  -v wallet_password="%WALLET_DB_PASSWORD%" ^
  -v trading_username="%TRADING_DB_USERNAME%" ^
  -v trading_database="%TRADING_DB_DATABASE%" ^
  -v trading_password="%TRADING_DB_PASSWORD%" ^
  -v market_data_username="%MARKET_DATA_DB_USERNAME%" ^
  -v market_data_database="%MARKET_DATA_DB_DATABASE%" ^
  -v market_data_password="%MARKET_DATA_DB_PASSWORD%"

if errorlevel 1 (
  echo [ERROR] Database bootstrap failed.
  exit /b 1
)

echo [SUCCESS] Service databases and roles are ready.
