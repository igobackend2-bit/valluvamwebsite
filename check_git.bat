@echo off
echo === git version === > "%~dp0git_check.txt"
git --version >> "%~dp0git_check.txt" 2>&1
echo === git global config === >> "%~dp0git_check.txt"
git config --global --list >> "%~dp0git_check.txt" 2>&1
echo === repo status in htdocs\valluvam === >> "%~dp0git_check.txt"
cd /d "C:\xampp\htdocs\valluvam"
git status >> "%~dp0git_check.txt" 2>&1
echo === remotes === >> "%~dp0git_check.txt"
git remote -v >> "%~dp0git_check.txt" 2>&1
echo === repo status in this project folder === >> "%~dp0git_check.txt"
cd /d "%~dp0"
git status >> "%~dp0git_check.txt" 2>&1
