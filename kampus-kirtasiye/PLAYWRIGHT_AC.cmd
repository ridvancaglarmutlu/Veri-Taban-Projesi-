@echo off
cd /d "%~dp0tests"
if not exist node_modules (
  echo Playwright paketleri kuruluyor...
  call npm install
  call npx playwright install chrome
)
set BASE_URL=http://localhost/kampus-kirtasiye/v2/
start "" npx playwright test --ui
