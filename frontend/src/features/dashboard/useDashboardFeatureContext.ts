import { useContext } from 'react'
import {
  DashboardFeatureContext,
  type DashboardContentState,
  type DashboardControlsState,
  type DashboardWorkspaceState,
} from './dashboardContextInstance'

function useDashboardFeatureContext() {
  const context = useContext(DashboardFeatureContext)

  if (context === null) {
    throw new Error(
      'Dashboard feature hooks must be used within DashboardFeatureProvider.',
    )
  }

  return context
}

export function useDashboardWorkspace(): DashboardWorkspaceState {
  return useDashboardFeatureContext().workspaceState
}

export function useDashboardControls(): DashboardControlsState {
  return useDashboardFeatureContext().controls
}

export function useDashboardContent(): DashboardContentState {
  return useDashboardFeatureContext().content
}
