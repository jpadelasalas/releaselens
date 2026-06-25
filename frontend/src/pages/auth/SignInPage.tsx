import { Link } from 'react-router-dom'

export function SignInPage() {
  return (
    <main className="centered-page">
      <p className="eyebrow">Connected workspaces</p>
      <h1>Sign in is not required for the demo.</h1>
      <p>
        Authentication belongs to the connected-workspace slice. The public demo
        remains available without registration or GitHub authorization.
      </p>
      <Link className="primary-action" to="/">
        Return to ReleaseLens
      </Link>
    </main>
  )
}
