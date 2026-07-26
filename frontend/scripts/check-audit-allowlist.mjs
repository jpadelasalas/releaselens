// Fails CI on any high/critical npm advisory except ones explicitly allowlisted below.
// Reads `npm audit --json` from stdin so a real fix (or a newly-appeared advisory)
// still breaks the build; only known, evaluated risks are suppressed.
//
// Allowlisted:
// - GHSA-qwww-vcr4-c8h2 (react-router RSC Mode CSRF Bypass): this app is a plain
//   client-side SPA using createBrowserRouter; no RSC/framework-mode APIs are used
//   anywhere, so the vulnerable code path is unreachable. No non-breaking upstream
//   fix exists yet (registry has no react-router-dom release above 7.18.1); the only
//   "fix" is downgrading to 7.11.0, which reintroduces 14 previously-patched
//   advisories (RCE, XSS, open redirect) in code paths this app does use. Revisit
//   when react-router ships a patched version above 7.18.1.

const allowed = new Set(['GHSA-qwww-vcr4-c8h2'])

let input = ''
process.stdin.setEncoding('utf8')
for await (const chunk of process.stdin) input += chunk

const report = JSON.parse(input)
const bad = []

for (const vuln of Object.values(report.vulnerabilities ?? {})) {
  if (vuln.severity !== 'high' && vuln.severity !== 'critical') continue

  for (const via of vuln.via) {
    if (typeof via !== 'object' || !via.url) continue

    const id = via.url.split('/').pop()
    if (!allowed.has(id)) bad.push(`${vuln.name}: ${id} (${via.title})`)
  }
}

if (bad.length > 0) {
  console.error('Unallowlisted high/critical advisories found:\n' + bad.join('\n'))
  process.exit(1)
}

console.log('No unallowlisted high/critical advisories.')
