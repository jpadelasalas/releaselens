import { describe, expect, it } from 'vitest'

import { getMetricDefinitionUrl, metricDefinitions } from './metricDefinitions'

describe('metric definitions', () => {
  it('provides complete, uniquely addressable definitions', () => {
    const ids = metricDefinitions.map((definition) => definition.id)

    expect(new Set(ids).size).toBe(ids.length)
    expect(metricDefinitions).toHaveLength(8)

    for (const definition of metricDefinitions) {
      expect(definition.name).not.toBe('')
      expect(definition.formula).not.toBe('')
      expect(definition.cohort).not.toBe('')
      expect(definition.dateBasis).not.toBe('')
      expect(definition.sampleSize).not.toBe('')
      expect(definition.interpretation).not.toBe('')
      expect(definition.limitations.length).toBeGreaterThan(0)
      expect(getMetricDefinitionUrl(definition.id)).toBe(
        `/demo/metrics#${definition.id}`,
      )
    }
  })
})
