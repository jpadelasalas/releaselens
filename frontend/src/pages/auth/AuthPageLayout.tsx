import type { ReactNode } from 'react'

import { BrandLink } from '../../components/navigation/BrandLink'
import { ThemeToggle } from '../../components/theme/ThemeToggle'

export function AuthPageLayout({
  children,
  eyebrow,
  title,
  description,
}: {
  children: ReactNode
  eyebrow: string
  title: string
  description: string
}) {
  return (
    <main className="min-h-svh bg-[var(--color-page)] px-5 py-6">
      <nav className="mx-auto flex max-w-5xl items-center justify-between">
        <BrandLink />
        <ThemeToggle />
      </nav>
      <section className="mx-auto grid min-h-[calc(100svh-88px)] max-w-md content-center py-10">
        <header className="mb-6">
          <p className="eyebrow">{eyebrow}</p>
          <h1 className="mt-2 text-4xl text-[var(--color-heading)]">{title}</h1>
          <p className="mt-2 text-[var(--color-muted)]">{description}</p>
        </header>
        {children}
      </section>
    </main>
  )
}
