import { fileURLToPath } from 'node:url'

export const backendDirectory = fileURLToPath(
  new URL('../../backend/', import.meta.url),
)
export const databasePath = fileURLToPath(
  new URL('../../backend/database/e2e.sqlite', import.meta.url),
)

export const backendEnvironment: NodeJS.ProcessEnv = {
  ...process.env,
  APP_ENV: 'testing',
  APP_URL: 'http://localhost:8010',
  CLIENT_URL: 'http://localhost:4173',
  CACHE_STORE: 'array',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: databasePath,
  DB_URL: '',
  QUEUE_CONNECTION: 'sync',
  SESSION_DRIVER: 'file',
}
