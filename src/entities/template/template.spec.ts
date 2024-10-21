/* eslint-disable no-console */
import { Template } from './template'
import { mockTemplate } from './template.mock'

describe('Template Store', () => {
	it('create Template entity with full data', () => {
		const template = new Template(mockTemplate()[0])

		expect(template).toBeInstanceOf(Template)
		expect(template).toEqual(mockTemplate()[0])

		expect(template.validate().success).toBe(true)
	})

	it('create Template entity with partial data', () => {
		const template = new Template(mockTemplate()[1])

		expect(template).toBeInstanceOf(Template)
		expect(template.id).toBe(mockTemplate()[1].id)
		expect(template.name).toBe(mockTemplate()[1].name)

		expect(template.validate().success).toBe(true)
	})

	it('create Template entity with falsy data', () => {
		const template = new Template(mockTemplate()[2])

		expect(template).toBeInstanceOf(Template)
		expect(template).toEqual(mockTemplate()[2])

		expect(template.validate().success).toBe(false)
	})
})
