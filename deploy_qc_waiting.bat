@echo off
cd /d "D:\IGO Groups Websites\Valluvam Products"
git add admin/quality_checks.php deploy_qc_waiting.bat
git commit -m "Quality Check page: list unloaded goods waiting for quality check"
git push
pause
