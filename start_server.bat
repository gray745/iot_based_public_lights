@echo off
title Streetlight Server
echo ========================================
echo  IoT Street Light Monitoring System
echo  PHP Built-in Server on port 8080
echo ========================================
echo.
echo Server starting...
echo Open in browser: http://localhost:8080/login.php
echo.
echo Press Ctrl+C to stop the server.
echo.
cd /d "%~dp0"
C:\php\php.exe -S 0.0.0.0:8080 router.php
pause
