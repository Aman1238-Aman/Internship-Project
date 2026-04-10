@echo off
setlocal

cd /d "%~dp0"

set "PHP_EXE=C:\Users\Lenovo\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
set "PHP_INI=C:\Users\Lenovo\OneDrive\Desktop\cursor new\Documents\Playground\php.ini"
set "COMPOSER_PHAR=..\composer.phar"
set "SQLITE_DB=C:\Users\Lenovo\AppData\Local\Temp\laravel_quiz.sqlite"
set "PHPRC=C:\Users\Lenovo\OneDrive\Desktop\cursor new\Documents\Playground"

if not exist "%PHP_EXE%" (
  echo [ERROR] PHP executable not found:
  echo %PHP_EXE%
  pause
  exit /b 1
)

if not exist "%PHP_INI%" (
  echo [ERROR] php.ini not found:
  echo %PHP_INI%
  pause
  exit /b 1
)

if not exist "%COMPOSER_PHAR%" (
  echo [ERROR] composer.phar not found:
  echo %COMPOSER_PHAR%
  pause
  exit /b 1
)

if not exist "%SQLITE_DB%" (
  type nul > "%SQLITE_DB%"
)

echo [1/8] Checking OpenSSL extension...
"%PHP_EXE%" -c "%PHP_INI%" -m | findstr /I "openssl" >nul
if errorlevel 1 (
  echo [ERROR] openssl extension not loaded. Please check php.ini.
  pause
  exit /b 1
)

echo [2/8] Checking mbstring extension...
"%PHP_EXE%" -c "%PHP_INI%" -m | findstr /I "mbstring" >nul
if errorlevel 1 (
  echo [ERROR] mbstring extension not loaded. Please check php.ini.
  pause
  exit /b 1
)

echo [3/8] Installing Composer dependencies...
"%PHP_EXE%" -c "%PHP_INI%" "%COMPOSER_PHAR%" install --no-interaction
if errorlevel 1 (
  echo [ERROR] composer install failed.
  pause
  exit /b 1
)

echo [4/8] Generating app key...
"%PHP_EXE%" -c "%PHP_INI%" artisan key:generate

echo [5/8] Clearing config cache...
"%PHP_EXE%" -c "%PHP_INI%" artisan config:clear

echo [6/8] Running migrations + seed...
"%PHP_EXE%" -c "%PHP_INI%" artisan migrate --seed --force
if errorlevel 1 (
  echo [ERROR] migrate --seed failed.
  pause
  exit /b 1
)

echo [7/8] Linking storage...
if exist "public\storage" (
  echo [INFO] storage link already exists. Skipping.
) else (
  "%PHP_EXE%" -c "%PHP_INI%" artisan storage:link
)

echo [8/8] Starting Laravel server on http://127.0.0.1:8000
"%PHP_EXE%" -c "%PHP_INI%" artisan serve

endlocal
