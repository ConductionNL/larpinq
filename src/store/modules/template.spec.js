/* eslint-disable no-console */
import { setActivePinia, createPinia } from 'pinia'

import { useTemplateStore } from './template.js'
import { mockTemplate, Template } from '../../entities/index.js'

describe(
	'Template Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets template item correctly', () => {
				const store = useTemplateStore()

				store.setTemplateItem(mockTemplate()[0])

				expect(store.templateItem).toBeInstanceOf(Template)
				expect(store.templateItem).toEqual(mockTemplate()[0])
				expect(store.templateItem.validate().success).toBe(true)

				store.setTemplateItem(mockTemplate()[1])

				expect(store.templateItem).toBeInstanceOf(Template)
				expect(store.templateItem).toEqual(mockTemplate()[1])
				expect(store.templateItem.validate().success).toBe(true)

				store.setTemplateItem(mockTemplate()[2])

				expect(store.templateItem).toBeInstanceOf(Template)
				expect(store.templateItem).toEqual(mockTemplate()[2])
				expect(store.templateItem.validate().success).toBe(false)
			},
		)

		it(
			'sets template list correctly', () => {
				const store = useTemplateStore()

				store.setTemplateList(mockTemplate())

				expect(store.templateList).toHaveLength(mockTemplate().length)

				expect(store.templateList[0]).toBeInstanceOf(Template)
				expect(store.templateList[0]).toEqual(mockTemplate()[0])
				expect(store.templateList[0].validate().success).toBe(true)

				expect(store.templateList[1]).toBeInstanceOf(Template)
				expect(store.templateList[1]).toEqual(mockTemplate()[1])
				expect(store.templateList[1].validate().success).toBe(true)

				expect(store.templateList[2]).toBeInstanceOf(Template)
				expect(store.templateList[2]).toEqual(mockTemplate()[2])
				expect(store.templateList[2].validate().success).toBe(false)
			},
		)
	},
)
