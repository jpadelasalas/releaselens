# Portfolio Screenshots

The PNG files in this directory are generated from the deterministic local demo with Playwright:

```powershell
cd frontend
$env:CAPTURE_PORTFOLIO = '1'
npx playwright test e2e/portfolio-screenshots.spec.ts
```

Capture again after intentional UI changes and before a public release. Do not add connected-workspace screenshots containing private repository names, account data, or credentials.

