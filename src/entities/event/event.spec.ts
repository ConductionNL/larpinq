/* eslint-disable no-console */
import { Event } from './event'
import { mockEvent } from './event.mock'

describe('Event entity', () => {
	it('create Event entity with full data', () => {
		const event = new Event(mockEvent()[0])

		expect(event).toBeInstanceOf(Event)
		expect(event).toEqual(mockEvent()[0])

		expect(event.validate().success).toBe(true)
	})

	it('create Event entity with partial data', () => {
		const event = new Event(mockEvent()[1])

		expect(event).toBeInstanceOf(Event)
		expect(event).toEqual(mockEvent()[1])

		expect(event.validate().success).toBe(true)
	})

	it('create Event entity with falsy data', () => {
		const event = new Event(mockEvent()[2])

		expect(event).toBeInstanceOf(Event)
		expect(event).toEqual(mockEvent()[2])

		expect(event.validate().success).toBe(false)
	})
})
