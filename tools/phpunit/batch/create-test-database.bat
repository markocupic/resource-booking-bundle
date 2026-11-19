@echo off
:: Creates the local MySQL/MariaDB test database for the integration tests of the
:: resource-booking-bundle and stores the connection in the Windows user environment
:: variable RBB_TEST_DATABASE_URL (not in a file inside the bundle).
::
:: - Database:  rbb_test (utf8mb4). The tests EMPTY and OVERWRITE this database on every run.
:: - User:      rbb_test, only with privileges on this database, only from localhost.
:: - Admin:     the script asks for an administrator account (e.g. root) to create both;
::              its password is only entered at the prompt of the mysql client.
::
:: Run it once; run it again to change the password. Restart PhpStorm afterwards
:: (new environment variables are only visible in newly started programs).
setlocal EnableExtensions EnableDelayedExpansion

echo.
echo === Test database for the resource-booking-bundle ===
echo.

:: Find the client (mariadb.exe or mysql.exe): in the PATH, in the MariaDB folders under
:: "Program Files", or ask for the full path (e.g. C:\xampp\mysql\bin\mysql.exe)
set "MYSQL_BIN="
for %%c in (mariadb mysql) do (
    if not defined MYSQL_BIN for /f "delims=" %%i in ('where %%c 2^>nul') do if not defined MYSQL_BIN set "MYSQL_BIN=%%i"
)

if not defined MYSQL_BIN (
    for /d %%d in ("%ProgramFiles%\MariaDB*" "%ProgramFiles(x86)%\MariaDB*") do (
        if not defined MYSQL_BIN if exist "%%~d\bin\mariadb.exe" set "MYSQL_BIN=%%~d\bin\mariadb.exe"
        if not defined MYSQL_BIN if exist "%%~d\bin\mysql.exe" set "MYSQL_BIN=%%~d\bin\mysql.exe"
    )
)

if not defined MYSQL_BIN (
    echo The client mariadb.exe or mysql.exe was not found.
    echo It is in the bin folder of your MariaDB installation, see "sc qc MariaDB".
    set /p "MYSQL_BIN=Full path to mariadb.exe or mysql.exe: "
)

if not exist "!MYSQL_BIN!" (
    echo ERROR: "!MYSQL_BIN!" does not exist.
    goto :fail
)

echo mysql client: !MYSQL_BIN!
echo.

set "DB_HOST=127.0.0.1"
set /p "DB_HOST=Host [127.0.0.1]: "
set "DB_PORT=3306"
set /p "DB_PORT=Port [3306]: "
set "ADMIN_USER=root"
set /p "ADMIN_USER=Administrator account [root]: "

set "DB_NAME=rbb_test"
set "DB_USER=rbb_test"
set "DB_PASSWORD=rbb_test"
echo.
echo Password for the test user %DB_USER% (letters and digits only, it is part of a URL).
set /p "DB_PASSWORD=Password [rbb_test]: "

echo !DB_PASSWORD!| findstr /r /c:"^[A-Za-z0-9]*$" >nul
if errorlevel 1 (
    echo ERROR: Use letters and digits only.
    goto :fail
)

echo.
echo The database %DB_NAME% will be created on %DB_HOST%:%DB_PORT% (if it does not exist yet).
echo It must never be the database of a website: the tests empty it on every run.
set "CONFIRM="
set /p "CONFIRM=Continue? [y/N]: "
if /i not "!CONFIRM!"=="y" goto :cancel

set "SQL=CREATE DATABASE IF NOT EXISTS `%DB_NAME%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for %%h in (localhost 127.0.0.1) do (
    set "SQL=!SQL! CREATE USER IF NOT EXISTS '%DB_USER%'@'%%h' IDENTIFIED BY '!DB_PASSWORD!';"
    set "SQL=!SQL! ALTER USER '%DB_USER%'@'%%h' IDENTIFIED BY '!DB_PASSWORD!';"
    set "SQL=!SQL! GRANT ALL PRIVILEGES ON `%DB_NAME%`.* TO '%DB_USER%'@'%%h';"
)
set "SQL=!SQL! FLUSH PRIVILEGES;"

echo.
:: The password is passed to the client in the environment variable MYSQL_PWD (only for this
:: script): the password prompt of the client does not work in every console (e.g. PhpStorm).
set "ADMIN_PASSWORD="
set /p "ADMIN_PASSWORD=Password of %ADMIN_USER% (leave empty if there is none; the input is visible): "

echo.
echo [1/3] Creating the database and the user as %ADMIN_USER% on %DB_HOST%:%DB_PORT% ...
set "MYSQL_PWD=!ADMIN_PASSWORD!"
"!MYSQL_BIN!" --host=%DB_HOST% --port=%DB_PORT% --protocol=TCP --connect-timeout=10 --user=%ADMIN_USER% --execute="!SQL!" <nul
set "RESULT=!errorlevel!"
set "MYSQL_PWD="
set "ADMIN_PASSWORD="
if not "!RESULT!"=="0" (
    echo ERROR: The database or the user could not be created ^(see the message of the client above^).
    echo Check host, port and password, and that MariaDB is running: sc query MariaDB
    goto :fail
)

:: Check the connection with the new user
echo [2/3] Checking the connection of the test user %DB_USER% ...
"!MYSQL_BIN!" --host=%DB_HOST% --port=%DB_PORT% --protocol=TCP --connect-timeout=10 --user=%DB_USER% --password=!DB_PASSWORD! --execute="SELECT 1" %DB_NAME% <nul >nul
if errorlevel 1 (
    echo ERROR: The test user cannot connect to %DB_NAME%.
    goto :fail
)

echo [3/3] Saving the environment variable RBB_TEST_DATABASE_URL ...

set "URL=mysql://%DB_USER%:!DB_PASSWORD!@%DB_HOST%:%DB_PORT%/%DB_NAME%?charset=utf8mb4"
setx RBB_TEST_DATABASE_URL "!URL!" >nul
if errorlevel 1 (
    echo ERROR: The environment variable could not be set.
    goto :fail
)

echo.
echo Done. RBB_TEST_DATABASE_URL = !URL!
echo Restart PhpStorm and the console so that the variable is available.
goto :end

:cancel
echo Cancelled.
goto :end

:fail
echo.
pause
exit /b 1

:end
echo.
pause
exit /b 0
