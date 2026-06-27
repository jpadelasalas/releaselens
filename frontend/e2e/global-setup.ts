import { execFileSync } from 'node:child_process'
import { writeFileSync } from 'node:fs'

import {
  backendDirectory,
  backendEnvironment,
  databasePath,
} from './environment'

export default function globalSetup() {
  writeFileSync(databasePath, '')
  execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
    cwd: backendDirectory,
    env: backendEnvironment,
    stdio: 'inherit',
  })
}
