import { rmSync } from 'node:fs'

import { databasePath } from './environment'

export default function globalTeardown() {
  rmSync(databasePath, { force: true })
}
