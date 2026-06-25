import { Link } from 'react-router-dom'

type BrandLinkProps = {
  to?: string
  label?: string
}

export function BrandLink({
  to = '/',
  label = 'ReleaseLens',
}: BrandLinkProps) {
  return (
    <Link className="brand" to={to} aria-label={`${label} home`}>
      <span className="brand-mark">R</span>
      <span>{label}</span>
    </Link>
  )
}
