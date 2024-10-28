/* eslint-disable no-console */
import { setActivePinia, createPinia } from 'pinia'

import { useEventStore } from './event.js'
import { Event, mockEvent } from '../../entities/index.js'

describe(
	'Event Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets event item correctly', () => {
				const store = useEventStore()

				store.setEventItem(mockEvent()[0])

				expect(store.eventItem).toBeInstanceOf(Event)
				expect(store.eventItem).toEqual(mockEvent()[0])

				expect(store.eventItem.validate().success).toBe(true)
			},
		)

		it(
			'sets event list correctly', () => {
				const store = useEventStore()

				store.setEventList(mockEvent())

				expect(store.eventList).toHaveLength(mockEvent().length)

				// list item 1
				expect(store.eventList[0]).toBeInstanceOf(Event)
				expect(store.eventList[0]).toEqual(mockEvent()[0])

				expect(store.eventList[0].validate().success).toBe(true)

				// list item 2
				expect(store.eventList[1]).toBeInstanceOf(Event)
				expect(store.eventList[1]).toEqual(mockEvent()[1])

				expect(store.eventList[1].validate().success).toBe(true)

				// list item 3
				expect(store.eventList[2]).toBeInstanceOf(Event)
				expect(store.eventList[2]).toEqual(mockEvent()[2])

				expect(store.eventList[2].validate().success).toBe(false)
			},
		)
	},
)
