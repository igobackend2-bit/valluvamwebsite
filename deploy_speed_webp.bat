@echo off
cd /d "%~dp0"
rem SPEED FIX (2 Oct 2026): WebP copies of the images + gzip / browser-cache headers
git add ".htaccess" "Dockerfile" "deploy_speed_webp.bat"
git add -- "images/*.webp" "assets/*.webp"
git commit -m "Speed: serve resized WebP images automatically, gzip JS, enable browser caching"
git push
pause
