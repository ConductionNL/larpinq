import { Event } from './event'
import { TEvent } from './event.types'

export const mockEventData = (): TEvent[] => [
	{
		id: "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
		name: "Summer Solstice Celebration",
		description: "A magical gathering to honor the longest day of the year",
		players: [],
		effects: [],
		startDate: "2023-06-21T18:00:00Z",
		endDate: "2023-06-22T06:00:00Z",
		location: "Enchanted Forest Clearing"
	},
	{
		id: "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
		name: "Dragon's Lair Expedition",
		description: "A perilous journey to retrieve a legendary artifact",
		players: [],
		effects: [],
		startDate: "2023-07-15T09:00:00Z",
		endDate: "2023-07-16T17:00:00Z",
		location: "Misty Mountains"
	},
	{
		id: "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
		name: "Royal Masquerade Ball",
		description: "An elegant evening of mystery and intrigue at the royal palace",
		players: [],
		effects: [],
		startDate: "2023-08-01T20:00:00Z",
		endDate: "2023-08-02T02:00:00Z",
		location: "Royal Palace Grand Ballroom"
	},
]

export const mockEvent = (data: TEvent[] = mockEventData()): TEvent[] => data.map(item => new Event(item))
