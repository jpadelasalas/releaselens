import api from '../../lib/api'

export type DemoSession = {
  session: {
    type: 'demo'
    id: string
    read_only: boolean
  }
  organization: {
    id: number
    name: string
    slug: string
    timezone: string
    is_demo: boolean
  }
  capabilities: {
    can_read_analytics: boolean
    can_mutate_demo: boolean
    can_connect_github: boolean
  }
}

type DemoSessionResponse = {
  data: DemoSession
}

export async function createDemoSession(): Promise<DemoSession> {
  const response = await api.post<DemoSessionResponse>('/api/v1/demo/session')

  return response.data.data
}
