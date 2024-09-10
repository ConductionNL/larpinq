import { Template } from './template'
import { TTemplate } from './template.types'

export const mockTemplateData = (): TTemplate[] => [
	{
		id: "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
		name: "Character Sheet",
		description: "A template for creating character sheets",
		template: "<h1>{{character.name}}</h1><p>Class: {{character.class}}</p><p>Level: {{character.level}}</p>"
	},
	{
		id: "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
		name: "Event Flyer",
		description: "A template for creating event flyers",
		template: "<h1>{{event.name}}</h1><p>Date: {{event.date}}</p><p>Location: {{event.location}}</p>"
	},
	{
		id: "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
		name: "Storybook",
		description: "This event's storybook",
		template: "<h1>{{story.title}}</h1><p>{{story.content}}</p>"
	},
	{
		id: "0a3a0ffb-dc03-4aae-b207-0ed1502e60da",
		name: "Item Card",
		description: "A template for creating item cards",
		template: "<h2>{{item.name}}</h2><p>{{item.description}}</p><p>Effect: {{item.effect}}</p>"
	},
]

export const mockTemplate = (data: TTemplate[] = mockTemplateData()): TTemplate[] => data.map(item => new Template(item))
