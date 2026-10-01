@echo off
cd /d "%~dp0"
git add "admin\assets\page_plus.js" "deploy_forms_layout.bat"
git commit -m "Assets and waste forms: professional two-column layout with labels"
git push
pause
